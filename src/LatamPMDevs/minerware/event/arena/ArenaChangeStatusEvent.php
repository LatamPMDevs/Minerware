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
use LatamPMDevs\minerware\arena\Status;

/**
 * Called when an arena changes its status
 */
class ArenaChangeStatusEvent extends ArenaEvent {

	public function __construct(
		protected Status $oldStatus,
		protected Status $newStatus,
		protected int $countdown,
		Arena $arena
	) {
		parent::__construct($arena);
	}

	public function getOldStatus() : Status {
		return $this->oldStatus;
	}

	public function getNewStatus() : Status {
		return $this->newStatus;
	}

	public function setNewStatus(Status $status) : void {
		$this->newStatus = $status;
	}

	/**
	 * How long (in ticks) the new status is supposed to last before
	 * transitioning again.
	 */
	public function getCountdown() : int {
		return $this->countdown;
	}

	public function setCountdown(int $countdown) : void {
		$this->countdown = $countdown;
	}
}