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

use Countable;
use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function count;
use function floor;
use function max;
use function min;

/**
 * A forward batch of block changes, keyed by PocketMine's native
 * {@see World::blockHash} and carrying block **state ids** (ints) rather than
 * Block objects, so the whole change set is serializable and can travel to an
 * {@see AsyncBlockOperation} worker thread.
 *
 * Each entry maps a {@see World::blockHash} to the **new** state id (last write
 * wins). Coordinates are never stored — {@see World::getBlockXYZ} recovers them
 * losslessly from the hash — and building a Selection performs **no world
 * reads**: the inverse change set needed for rollback is computed by the
 * {@see AsyncBlockOperation} worker from the terrain copy it mutates and handed
 * back to the main thread as another Selection. Entries from the same chunk are
 * grouped by {@see getStatesByChunk} so the worker can mutate one
 * SimpleChunkManager copy per chunk.
 *
 * @phpstan-import-type BlockPosHash from World
 * @phpstan-import-type ChunkPosHash from World
 */
final class Selection implements Countable {

	/**
	 * Overwrites an existing entry for the same cell (default — the last write
	 * to a cell wins in a forward change set).
	 */
	public const REPLACE_EXISTING = 0;

	/**
	 * Keeps the existing entry when the same cell is added again (used when
	 * merging **inverse** selections: the earliest pre-change state is the
	 * only correct rollback target).
	 */
	public const IGNORE_IF_EXISTS = 1;

	/**
	 * @var int[] key = blockHash → new state id, insertion-ordered
	 * @phpstan-var array<BlockPosHash, int>
	 */
	private array $newStates = [];

	public function __construct(private World $world) {
	}

	/**
	 * Wraps a raw blockHash → state id map (e.g. the inverse change set returned
	 * by an {@see AsyncBlockOperation}) into a Selection.
	 *
	 * @param array<int, int> $states key = blockHash → state id
	 * @phpstan-param array<BlockPosHash, int> $states
	 */
	public static function fromRawStates(World $world, array $states) : Selection {
		$selection = new Selection($world);
		$selection->newStates = $states;
		return $selection;
	}

	public function getWorld() : World {
		return $this->world;
	}

	/**
	 * Records a single cell change, replacing any prior change to the same cell.
	 */
	public function addCell(int $x, int $y, int $z, Block $block) : void {
		$this->setCell($x, $y, $z, $block->getStateId());
	}

	/**
	 * Records a cell change for a position; fractional coordinates are
	 * floored (the block containing the point is selected).
	 */
	public function addBlock(Vector3 $position, Block $block) : void {
		$this->addCell($position->getFloorX(), $position->getFloorY(), $position->getFloorZ(), $block);
	}

	/**
	 * Adds every cell within the axis-aligned box [pos1, pos2]. Fractional
	 * coordinates are floored; the Y range is clamped to the world's bounds.
	 */
	public function addFill(Vector3 $pos1, Vector3 $pos2, Block $block) : void {
		$stateId = $block->getStateId();
		$minX = (int) floor(min($pos1->x, $pos2->x));
		$maxX = (int) floor(max($pos1->x, $pos2->x));
		$minY = max($this->world->getMinY(), (int) floor(min($pos1->y, $pos2->y)));
		# World::getMaxY() is exclusive — the highest real cell is maxY - 1.
		$maxY = min($this->world->getMaxY() - 1, (int) floor(max($pos1->y, $pos2->y)));
		$minZ = (int) floor(min($pos1->z, $pos2->z));
		$maxZ = (int) floor(max($pos1->z, $pos2->z));

		for ($x = $minX; $x <= $maxX; ++$x) {
			for ($z = $minZ; $z <= $maxZ; ++$z) {
				for ($y = $minY; $y <= $maxY; ++$y) {
					$this->setCell($x, $y, $z, $stateId);
				}
			}
		}
	}

	/**
	 * Returns the new state ids grouped by native chunk hash, as
	 * chunkHash → [blockHash → newStateId].
	 *
	 * @return array<int, array<int, int>>
	 * @phpstan-return array<ChunkPosHash, array<BlockPosHash, int>>
	 */
	public function getStatesByChunk() : array {
		$byChunk = [];
		foreach ($this->newStates as $blockHash => $stateId) {
			World::getBlockXYZ($blockHash, $x, $y, $z);
			$byChunk[World::chunkHash($x >> 4, $z >> 4)][$blockHash] = $stateId;
		}
		return $byChunk;
	}

	/**
	 * @return int the number of pending cell changes.
	 */
	public function count() : int {
		return count($this->newStates);
	}

	/**
	 * @return bool whether this selection holds no pending cell change.
	 */
	public function isEmpty() : bool {
		return $this->newStates === [];
	}

	/**
	 * Merges every entry of $other into this selection. Forward merges use the
	 * default {@see REPLACE_EXISTING}; merging inverse selections must pass
	 * {@see IGNORE_IF_EXISTS} so the earliest pre-change state — the only
	 * correct rollback target — survives.
	 */
	public function merge(Selection $other, int $mode = self::REPLACE_EXISTING) : void {
		if ($other->world !== $this->world) {
			throw new InvalidArgumentException("Cannot merge selections of different worlds");
		}
		foreach ($other->newStates as $blockHash => $stateId) {
			if ($mode === self::IGNORE_IF_EXISTS && isset($this->newStates[$blockHash])) {
				continue;
			}
			$this->newStates[$blockHash] = $stateId;
		}
	}

	/**
	 * Records a cell change from a raw state id. Cells outside the world's
	 * bounds are dropped: the async worker would skip them anyway, and keeping
	 * them out avoids snapshotting and swapping chunks that hold no real
	 * change.
	 */
	private function setCell(int $x, int $y, int $z, int $newStateId) : void {
		if (!$this->world->isInWorld($x, $y, $z)) {
			return;
		}
		$this->newStates[World::blockHash($x, $y, $z)] = $newStateId;
	}
}