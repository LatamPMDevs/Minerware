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
 * Single entry point for applying Selections without blocking the main thread.
 */
final class AsyncBlockManager {

	use SingletonTrait;

	/** @var array<int, list<PendingBlockOperation>> world id → operations waiting for the in-flight one */
	private array $queued = [];

	/** @var array<int, bool> world id → an operation is currently in flight */
	private array $running = [];

	public function __construct() {
	}

	/**
	 * Applies a selection asynchronously.
	 *
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
		$this->queued[$worldId][] = new PendingBlockOperation($selection, $onComplete);
		if (!isset($this->running[$worldId])) {
			$this->submitNext($worldId);
		}
	}

	private function submitNext(int $worldId) : void {
		/** @var list<Closure(?Selection $undo) : void> $dropped */
		$dropped = [];
		while (isset($this->queued[$worldId]) && $this->queued[$worldId] !== []) {
			$pending = array_shift($this->queued[$worldId]);
			$selection = $pending->selection;
			$onComplete = $pending->onComplete;
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