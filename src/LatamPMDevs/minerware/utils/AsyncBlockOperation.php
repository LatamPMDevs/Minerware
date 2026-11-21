<?php

/*
 *  ███╗   ███╗██╗███╗   ██╗███████╗██████╗ ██╗    ██╗ █████╗ ██████╗ ███████╗
 *  ████╗ ████║██║████╗  ██║██╔════╝██╔══██╗██║    ██║██╔══██╗██╔══██╗██╔════╝
 *  ██╔████╔██║██║██╔██╗ ██║█████╗  ██████╔╝██║ █╗ ██║███████║██████╔╝█████╗
 *  ██║╚██╔╝██║██║██║╚██╗██║██╔══╝  ██╔══██╗██║███╗██║██╔══██║██╔══██╗██╔══╝
 *  ██║ ╚═╝ ██║██║██║ ╚████║███████╗██║  ██║╚███╔███╔╝██║  ██║██║  ██║███████╗
 *  ╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝ ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝
 *
 * A game written in PHP for PocketMine-MP software.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author LatamPMDevs
 */

declare(strict_types=1);

namespace LatamPMDevs\minerware\utils;

use Closure;
use InvalidArgumentException;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\tile\Tile;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\BinaryDataException;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\Position;
use pocketmine\world\SimpleChunkManager;
use pocketmine\world\World;
use function igbinary_serialize;
use function igbinary_unserialize;
use function is_array;
use function is_callable;
use function is_int;
use function is_string;

/**
 * Applies a Selection to a World on a worker thread.
 *
 * @phpstan-import-type BlockPosHash from World
 * @phpstan-import-type ChunkPosHash from World
 */
final class AsyncBlockOperation extends AsyncTask {

	/** @var string igbinary-serialized chunkHash → [blockHash → newStateId, ...] */
	private string $statesByChunk;

	/** @var string igbinary-serialized chunkHash → serialized original terrain */
	private string $serializedChunks;

	/** @var string igbinary-serialized blockHash → pre-change state id (the inverse change set) */
	private string $undoStates;

	/** @var string igbinary-serialized list of blockHash values whose new state expects a tile entity */
	private string $tileCells;

	private int $worldId;

	private int $minY;

	private int $maxY;

	/**
	 * @phpstan-param ?Closure(?Selection $undo) : void $onComplete
	 */
	public function __construct(Selection $selection, World $world, ?Closure $onComplete = null) {
		if ($onComplete !== null) {
			$this->storeLocal('closure', $onComplete);
		}

		$this->setWorld($world);
		$this->setSelection($world, $selection);
	}

	/**
	 * Groups the selection by chunk and serializes the original terrain of every affected chunk.
	 */
	private function setSelection(World $world, Selection $selection) : void {
		$statesByChunk = $selection->getStatesByChunk();
		$serializedChunks = [];
		$skipped = 0;

		foreach ($statesByChunk as $chunkHash => $states) {
			World::getXZ($chunkHash, $chunkX, $chunkZ);
			# A chunk locked by a population task is about to be (or is being)
			# mutated concurrently — don't snapshot or later stomp that terrain.
			if ($world->isChunkLocked($chunkX, $chunkZ)) {
				unset($statesByChunk[$chunkHash]);
				$skipped++;
				continue;
			}
			$chunk = $world->loadChunk($chunkX, $chunkZ);
			if ($chunk === null) {
				unset($statesByChunk[$chunkHash]);
				$skipped++;
			} else {
				$serializedChunks[$chunkHash] = FastChunkSerializer::serializeTerrain($chunk);
			}
		}
		if ($skipped > 0) {
			Server::getInstance()->getLogger()->warning("AsyncBlockOperation: skipped $skipped chunk(s) (locked or unavailable) in world " . $world->getFolderName());
		}

		$this->statesByChunk = self::pack($statesByChunk);
		$this->serializedChunks = self::pack($serializedChunks);
	}

	private function setWorld(World $world) : void {
		$this->worldId = $world->getId();
		$this->maxY = $world->getMaxY();
		$this->minY = $world->getMinY();
	}

	/**
	 * @return array<int, array<int, int>>
	 * @phpstan-return array<ChunkPosHash, array<BlockPosHash, int>>
	 */
	public function getStatesByChunk() : array {
		$states = [];
		foreach (self::unpack($this->statesByChunk) as $chunkHash => $cells) {
			if (!is_int($chunkHash) || !is_array($cells)) {
				throw new AssumptionFailedError("Corrupted block operation data");
			}
			$states[$chunkHash] = self::ints($cells);
		}
		return $states;
	}

	/**
	 * @return array<int, string>
	 * @phpstan-return array<ChunkPosHash, string>
	 */
	public function getSerializedChunks() : array {
		$chunks = [];
		foreach (self::unpack($this->serializedChunks) as $chunkHash => $serialized) {
			if (!is_int($chunkHash) || !is_string($serialized)) {
				throw new AssumptionFailedError("Corrupted block operation data");
			}
			$chunks[$chunkHash] = $serialized;
		}
		return $chunks;
	}

	/**
	 * Everything the task keeps is packed into strings so it can cross threads.
	 *
	 * @param array<mixed> $data
	 */
	private static function pack(array $data) : string {
		return igbinary_serialize($data) ?? throw new AssumptionFailedError("Could not serialize the block operation");
	}

	/**
	 * @param array<mixed> $values
	 * @return array<int, int>
	 */
	private static function ints(array $values) : array {
		$ints = [];
		foreach ($values as $key => $value) {
			if (!is_int($key) || !is_int($value)) {
				throw new AssumptionFailedError("Corrupted block operation data");
			}
			$ints[$key] = $value;
		}
		return $ints;
	}

	/**
	 * @return array<mixed>
	 */
	private static function unpack(string $blob) : array {
		$data = igbinary_unserialize($blob);
		return is_array($data) ? $data : throw new AssumptionFailedError("Corrupted block operation data");
	}

	/**
	 * Runs on a worker thread.
	 */
	public function onRun() : void {
		$serialized = $this->getSerializedChunks();
		$manager = $this->makeChunkManager($serialized);
		$undoStates = [];
		$tileCells = [];
		$tileClassByState = [];

		foreach ($this->getStatesByChunk() as $chunkHash => $states) {
			World::getXZ($chunkHash, $chunkX, $chunkZ);
			$chunk = $manager->getChunk($chunkX, $chunkZ);
			if ($chunk === null) {
				continue;
			}
			foreach ($states as $blockHash => $stateId) {
				World::getBlockXYZ($blockHash, $x, $y, $z);
				if ($y < $this->minY || $y >= $this->maxY) {
					continue;
				}
				# Capture the pre-change state before overwriting, so the inverse
				# Selection needs no main-thread world reads at all.
				$undoStates[$blockHash] = $chunk->getBlockStateId($x & 0xf, $y, $z & 0xf);
				$chunk->setBlockStateId($x & 0xf, $y, $z & 0xf, $stateId);
				if (!isset($tileClassByState[$stateId])) {
					$tileClassByState[$stateId] = RuntimeBlockStateRegistry::getInstance()->fromStateId($stateId)->getIdInfo()->getTileClass();
				}
				if ($tileClassByState[$stateId] !== null) {
					$tileCells[] = $blockHash;
				}
			}
		}
		$this->undoStates = self::pack($undoStates);
		$this->tileCells = self::pack($tileCells);

		$this->saveChunkManager($manager, $serialized);
	}

	/**
	 * Runs on the main thread.
	 */
	public function onCompletion() : void {
		$undo = null;
		try {
			$world = Server::getInstance()->getWorldManager()->getWorld($this->worldId);
			if ($world !== null) {
				# Wrapped before the swap: it reflects what the worker applied and
				# stays valid whatever happens to the swap below.
				$undo = Selection::fromRawStates($world, self::ints(self::unpack($this->undoStates)));
				$this->swapIntoWorld($world);
			}
		} catch (BlockSwapException $exception) {
			Server::getInstance()->getLogger()->critical("AsyncBlockOperation: " . $exception->getMessage());
		} finally {
			# The completion callback drives the caller's build accounting
			# ({@see LatamPMDevs\minerware\arena\StageGate::settled}):
			# it must run even when the swap above throws, or the arena's
			# stage gate would never release.
			try {
				$action = $this->fetchLocal('closure');
			} catch (InvalidArgumentException $exception) {
				$action = null;
			}
			if (is_callable($action)) {
				$action($undo);
			}
		}
	}

	/**
	 * Swaps the worker's modified chunks into the live world and recreates the tiles for placed tile-blocks.
	 *
	 * @throws BlockSwapException
	 */
	private function swapIntoWorld(World $world) : void {
		foreach ($this->getSerializedChunks() as $chunkHash => $serialized) {
			World::getXZ($chunkHash, $chunkX, $chunkZ);
			try {
				$world->setChunk($chunkX, $chunkZ, FastChunkSerializer::deserializeTerrain($serialized));
			} catch (InvalidArgumentException | BinaryDataException $exception) {
				throw new BlockSwapException("Could not swap the chunk $chunkX $chunkZ of world " . $this->worldId . ": " . $exception->getMessage(), 0, $exception);
			}
		}
		try {
			$this->recreateTiles($world, $this->getStatesByChunk());
		} catch (InvalidArgumentException $exception) {
			throw new BlockSwapException("Could not recreate the tiles of world " . $this->worldId . ": " . $exception->getMessage(), 0, $exception);
		}
	}

	/**
	 * Recreates tile entities for cells whose new block state expects one.
	 *
	 * @param array<int, array<int, int>> $statesByChunk
	 * @phpstan-param array<ChunkPosHash, array<BlockPosHash, int>> $statesByChunk
	 */
	private function recreateTiles(World $world, array $statesByChunk) : void {
		foreach (self::ints(self::unpack($this->tileCells)) as $blockHash) {
			World::getBlockXYZ($blockHash, $x, $y, $z);
			$position = new Position($x, $y, $z, $world);
			if ($world->getTile($position) !== null) {
				continue;
			}
			$stateId = $statesByChunk[World::chunkHash($x >> 4, $z >> 4)][$blockHash] ?? null;
			if ($stateId === null) {
				continue;
			}
			$tileClass = RuntimeBlockStateRegistry::getInstance()->fromStateId($stateId)->getIdInfo()->getTileClass();
			if ($tileClass !== null) {
				/** @var Tile $tile */
				$tile = new $tileClass($world, $position->asVector3());
				$world->addTile($tile);
			}
		}
	}

	/**
	 * @param array<int, string> $serializedChunks
	 * @phpstan-param array<ChunkPosHash, string> $serializedChunks
	 */
	protected function makeChunkManager(array $serializedChunks) : SimpleChunkManager {
		$manager = new SimpleChunkManager($this->minY, $this->maxY);
		foreach ($serializedChunks as $chunkHash => $serializedChunk) {
			World::getXZ($chunkHash, $chunkX, $chunkZ);
			$manager->setChunk($chunkX, $chunkZ, FastChunkSerializer::deserializeTerrain($serializedChunk));
		}
		return $manager;
	}

	/**
	 * @param array<int, string> $original
	 * @phpstan-param array<ChunkPosHash, string> $original
	 */
	protected function saveChunkManager(SimpleChunkManager $manager, array $original) : void {
		$serializedChunks = [];
		foreach ($original as $chunkHash => $unused) {
			World::getXZ($chunkHash, $chunkX, $chunkZ);
			/** @var ?Chunk $chunk */
			$chunk = $manager->getChunk($chunkX, $chunkZ);
			if ($chunk !== null) {
				$serializedChunks[$chunkHash] = FastChunkSerializer::serializeTerrain($chunk);
			}
		}
		$this->serializedChunks = self::pack($serializedChunks);
	}
}