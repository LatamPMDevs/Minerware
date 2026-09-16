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
 * Applies a {@see Selection} to a {@see World} on a worker thread.
 *
 * PocketMine-MP's world API is not thread-safe, so a block write cannot simply
 * run on a second thread. Instead this task:
 *
 * 1. On the main thread (constructor) it groups the requested cells by native
 *    {@see World::chunkHash} and serializes the **original terrain** of every
 *    affected chunk to igbinary strings. Only scalar + serialized-string
 *    properties cross the worker boundary — never live Block/World objects.
 *    Each cell's previous state id was already cached by the {@see Selection}
 *    itself as its cells were added.
 * 2. On a worker thread ({@see onRun}) it rebuilds each affected chunk inside a
 *    throwaway {@see SimpleChunkManager} (a detached copy), reads each cell's
 *    **pre-change** state id from that copy (the inverse change set, for free —
 *    the terrain is already in memory), applies the new block state ids, and
 *    re-serializes the modified terrain.
 * 3. Back on the main thread ({@see onCompletion}) it swaps the modified chunks
 *    back into the live world with {@see World::setChunk} (recreating tile
 *    entities for newly placed tile-blocks), wraps the inverse change set into
 *    a {@see Selection} and invokes the completion closure with it, so the
 *    handler can undo the whole operation by submitting that Selection to
 *    another {@see AsyncBlockOperation}.
 *
 * This moves the expensive per-block mutation off the main thread; the main
 * thread only serializes/introspects cheap state-id ints up front and performs a
 * cheap chunk swap per affected chunk on completion.
 *
 * @phpstan-import-type BlockPosHash from World
 * @phpstan-import-type ChunkPosHash from World
 */
final class AsyncBlockOperation extends AsyncTask {

	/**
	 * @var string igbinary-serialized chunkHash → [blockHash → newStateId, ...]
	 */
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
	 * @param Selection $selection the change set to apply.
	 * @param World $world the target world (main thread only).
	 * @param ?Closure $onComplete invoked on the main thread once the chunks
	 * have been swapped back in, receiving the inverse {@see Selection} (every
	 * applied cell's pre-change state; null only if the world was unloaded
	 * mid-flight). Submitting it through {@see AsyncBlockManager::executeSet}
	 * undoes the whole operation.
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
	 * Groups the selection by chunk and serializes the original terrain of
	 * every affected chunk — the copy the worker derives the inverse change
	 * set from.
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
	 * Runs on a worker thread: applies the changes to a detached chunk copy,
	 * capturing each cell's pre-change state id for the inverse selection.
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
	 * Runs on the main thread: swaps the modified chunks into the live world,
	 * recreates tile entities for newly placed tile-blocks (the raw state-id
	 * write skips the tile creation {@see World::setBlock} would do), then
	 * hands the inverse selection to the completion closure — which always
	 * runs exactly once, even if the world work throws.
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
	 * Swaps the worker's modified chunks into the live world and recreates the
	 * tiles for placed tile-blocks. Returns the inverse selection, or null if
	 * the world is gone. A swap failure is logged, not thrown: the operation
	 * is then (half-)unapplied, but the undo stays exact — pre-change writes
	 * against chunks that never landed are no-ops on the live terrain — and
	 * the completion callback still releases the caller's accounting.
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
	 * Runs after the chunk swap, so it resolves tiles against the new terrain;
	 * tiles that already exist at a cell (transferred in by
	 * {@see World::setChunk}) are kept untouched.
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