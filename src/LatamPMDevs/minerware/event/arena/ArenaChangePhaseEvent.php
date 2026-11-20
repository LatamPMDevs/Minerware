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

namespace LatamPMDevs\minerware\event\arena;

use LatamPMDevs\minerware\arena\Arena;
use LatamPMDevs\minerware\arena\phase\ArenaPhase;

/**
 * Called when an arena changes its phase
 */
class ArenaChangePhaseEvent extends ArenaEvent {

	public function __construct(
		protected ArenaPhase $oldPhase,
		protected ArenaPhase $newPhase,
		protected int $countdown,
		Arena $arena
	) {
		parent::__construct($arena);
	}

	public function getOldPhase() : ArenaPhase {
		return $this->oldPhase;
	}

	public function getNewPhase() : ArenaPhase {
		return $this->newPhase;
	}

	public function setNewPhase(ArenaPhase $phase) : void {
		$this->newPhase = $phase;
	}

	/**
	 * How long (in seconds) the new phase is supposed to last before moving on.
	 */
	public function getCountdown() : int {
		return $this->countdown;
	}

	public function setCountdown(int $countdown) : void {
		$this->countdown = $countdown;
	}
}