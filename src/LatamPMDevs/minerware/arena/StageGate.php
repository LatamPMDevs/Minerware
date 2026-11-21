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

namespace LatamPMDevs\minerware\arena;

use Closure;
use LatamPMDevs\minerware\Minerware;
use LatamPMDevs\minerware\utils\AsyncBlockManager;
use LatamPMDevs\minerware\utils\Selection;
use function microtime;

/**
 * Counts the async block builds of an arena (invisible walls, cages, microgame stages).
 */
final class StageGate {

	private int $pending = 0;

	/** Seconds (microtime) of the last build started or settled. */
	private float $lastActivity = 0.0;

	public function __construct(private readonly string $arenaId) { }

	public function isBuilding() : bool {
		return $this->pending > 0;
	}

	/**
	 * Writes the selection asynchronously, holding the gate until it lands.
	 *
	 * @param Closure(?Selection) : void|null $onSettled
	 */
	public function build(Selection $selection, ?Closure $onSettled = null) : void {
		$this->started();
		AsyncBlockManager::getInstance()->executeSet($selection, function (?Selection $undo) use ($onSettled) : void {
			$this->settled();
			if ($onSettled !== null) {
				$onSettled($undo);
			}
		});
	}

	public function started() : void {
		$this->pending++;
		$this->lastActivity = microtime(true);
	}

	public function settled() : void {
		$this->lastActivity = microtime(true);
		if ($this->pending > 0) {
			$this->pending--;
		} else {
			Minerware::getInstance()->getLogger()->warning("Arena " . $this->arenaId . ": a stage build settled twice");
		}
	}

	/**
	 * Releases the gate when a build never reported back (a lost worker), so the arena can't wait for it forever.
	 */
	public function settleStuck(float $timeout) : void {
		if ($this->pending > 0 && microtime(true) - $this->lastActivity > $timeout) {
			Minerware::getInstance()->getLogger()->error("Arena " . $this->arenaId . ": " . $this->pending . " stage build(s) never finished, releasing them");
			$this->pending = 0;
		}
	}
}