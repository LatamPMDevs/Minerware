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
use LatamPMDevs\minerware\Minerware;
use pocketmine\utils\SingletonTrait;
use function array_shift;

/**
 * Single entry point for applying {@see Selection}s without blocking the main
 * thread. Submits an {@see AsyncBlockOperation} to PocketMine's async task pool:
 * the per-block mutation runs on a worker thread and the chunks are swapped back
 * into the world on the main thread in the operation's completion pass.
 *
 * Callers that read or mutate the stage afterwards must coordinate through their
 * own build accounting (e.g. {@see LatamPMDevs\minerware\arena\Arena
 * ::isBuildingStage}); this manager is intentionally stateless and fire-and-forget.
 */
final class AsyncBlockManager {

	use SingletonTrait;

	/**
	 * @var array<int, list<array{0: Selection, 1: ?Closure}>> world id →
	 * operations waiting for the in-flight one
	 * @phpstan-var array<int, list<array{0: Selection, 1: ?Closure(?Selection $undo) : void}>>
	 */
	private array $queued = [];

	/** @var array<int, bool> world id → an operation is currently in flight */
	private array $running = [];

	public function __construct() {
	}

	/**
	 * Applies a selection asynchronously. Operations on the same world are
	 * serialized (one in flight at a time): each worker mutates a full copy of
	 * the affected chunks, so two concurrent operations touching the same chunk
	 * would overwrite each other wholesale when swapped back. Different worlds
	 * (arenas) still run in parallel.
	 *
	 * @param Selection $selection the change set to apply. An empty selection
	 * is a no-op that settles immediately.
	 * @param ?Closure $onComplete invoked on the main thread once the modified
	 * chunks have been swapped into the world, receiving the inverse selection
	 * (every applied cell's pre-change state) — or null when nothing was
	 * applied (empty selection, world unloaded mid-queue, or swap failure);
	 * submit it through this same method to undo the operation. May run
	 * synchronously (empty selection) or later (one async step).
	 * @phpstan-param ?Closure(?Selection $undo) : void $onComplete
	 */
	public function executeSet(Selection $selection, ?Closure $onComplete = null) : void {
		if ($selection->isEmpty()) {
			# Nothing to apply; settle the caller's accounting right away.
			if ($onComplete !== null) {
				$onComplete(null);
			}
			return;
		}
		$worldId = $selection->getWorld()->getId();
		$this->queued[$worldId][] = [$selection, $onComplete];
		if (!isset($this->running[$worldId])) {
			$this->submitNext($worldId);
		}
	}

	private function submitNext(int $worldId) : void {
		/** @var list<Closure(?Selection $undo) : void> $dropped */
		$dropped = [];
		while (isset($this->queued[$worldId]) && $this->queued[$worldId] !== []) {
			[$selection, $onComplete] = array_shift($this->queued[$worldId]);
			$world = $selection->getWorld();
			if (!$world->isLoaded()) {
				# The world was unloaded while this operation waited (e.g. the
				# arena teardown raced the queue): snapshotting its terrain
				# would hit the closed provider and throw inside a previous
				# operation's completion pass. Drop it; the null undo tells the
				# caller nothing was applied.
				if ($onComplete !== null) {
					$dropped[] = $onComplete;
				}
				continue;
			}
			$this->running[$worldId] = true;
			$operation = new AsyncBlockOperation($selection, $world, function (?Selection $undo) use ($worldId, $onComplete) : void {
				unset($this->running[$worldId]);
				# Drain before the caller's callback so the next operation's terrain
				# snapshot already includes this one's landed changes, and a throwing
				# callback can never stall the queue.
				$this->submitNext($worldId);
				if ($onComplete !== null) {
					$onComplete($undo);
				}
			});
			Minerware::getInstance()->getServer()->getAsyncPool()->submitTask($operation);
			# Dropped callbacks run last, after the next operation is already
			# in flight, so a throwing one cannot stall the queue either.
			foreach ($dropped as $callback) {
				$callback(null);
			}
			return;
		}
		unset($this->queued[$worldId], $this->running[$worldId]);
		foreach ($dropped as $callback) {
			$callback(null);
		}
	}
}