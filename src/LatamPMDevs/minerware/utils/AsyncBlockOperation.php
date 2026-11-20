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
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\Position;
use pocketmine\world\SimpleChunkManager;
use pocketmine\world\World;
use Throwable;
use function igbinary_serialize;
use function igbinary_unserialize;
use function is_callable;

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

		foreach ($statesByChunk as $chunkHash => $states) {
			World::getXZ($chunkHash, $chunkX, $chunkZ);
			# A chunk locked by a population task is about to be (or is being)
			# mutated concurrently — don't snapshot or later stomp that terrain.
			if ($world->isChunkLocked($chunkX, $chunkZ)) {
				unset($statesByChunk[$chunkHash]);
				continue;
			}
			$chunk = $world->loadChunk($chunkX, $chunkZ);
			if ($chunk === null) {
				unset($statesByChunk[$chunkHash]);
			} else {
				$serializedChunks[$chunkHash] = FastChunkSerializer::serializeTerrain($chunk);
			}
		}

		$this->statesByChunk = igbinary_serialize($statesByChunk);
		$this->serializedChunks = igbinary_serialize($serializedChunks);
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
		return igbinary_unserialize($this->statesByChunk);
	}

	/**
	 * @return array<int, string>
	 * @phpstan-return array<ChunkPosHash, string>
	 */
	public function getSerializedChunks() : array {
		return igbinary_unserialize($this->serializedChunks);
	}

	/**
	 * Runs on a worker thread.
	 */
	public function onRun() : void {
		$manager = $this->makeChunkManager();
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
		$this->undoStates = igbinary_serialize($undoStates);
		$this->tileCells = igbinary_serialize($tileCells);

		$this->saveChunkManager($manager);
	}

	/**
	 * Runs on the main thread.
	 */
	public function onCompletion() : void {
		$undo = null;
		try {
			$undo = $this->swapIntoWorld();
		} finally {
			# The completion callback drives the caller's build accounting
			# ({@see LatamPMDevs\minerware\arena\Arena::onStageBuildSettled}):
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
	 */
	private function swapIntoWorld() : ?Selection {
		$world = Server::getInstance()->getWorldManager()->getWorld($this->worldId);
		if ($world === null) {
			return null;
		}

		# Wrapped before the swap: it reflects what the worker applied and
		# stays valid whatever happens to the swap below.
		$undo = Selection::fromRawStates($world, igbinary_unserialize($this->undoStates));

		try {
			foreach ($this->getSerializedChunks() as $chunkHash => $serialized) {
				World::getXZ($chunkHash, $chunkX, $chunkZ);
				$world->setChunk($chunkX, $chunkZ, FastChunkSerializer::deserializeTerrain($serialized));
			}
			$this->recreateTiles($world, $this->getStatesByChunk());
		} catch (Throwable $exception) {
			Server::getInstance()->getLogger()->critical(
				"AsyncBlockOperation: chunk swap failed for world " . $this->worldId . ": " . $exception::class . ": " . $exception->getMessage()
			);
		}

		return $undo;
	}

	/**
	 * Recreates tile entities for cells whose new block state expects one.
	 *
	 * @param array<int, array<int, int>> $statesByChunk
	 * @phpstan-param array<ChunkPosHash, array<BlockPosHash, int>> $statesByChunk
	 */
	private function recreateTiles(World $world, array $statesByChunk) : void {
		foreach (igbinary_unserialize($this->tileCells) as $blockHash) {
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

	protected function makeChunkManager() : SimpleChunkManager {
		$manager = new SimpleChunkManager($this->minY, $this->maxY);
		foreach ($this->getSerializedChunks() as $chunkHash => $serializedChunk) {
			World::getXZ($chunkHash, $chunkX, $chunkZ);
			$manager->setChunk($chunkX, $chunkZ, FastChunkSerializer::deserializeTerrain($serializedChunk));
		}
		return $manager;
	}

	protected function saveChunkManager(SimpleChunkManager $manager) : void {
		$serializedChunks = [];
		foreach ($this->getSerializedChunks() as $chunkHash => $unused) {
			World::getXZ($chunkHash, $chunkX, $chunkZ);
			/** @var ?Chunk $chunk */
			$chunk = $manager->getChunk($chunkX, $chunkZ);
			if ($chunk !== null) {
				$serializedChunks[$chunkHash] = FastChunkSerializer::serializeTerrain($chunk);
			}
		}
		$this->serializedChunks = igbinary_serialize($serializedChunks);
	}
}