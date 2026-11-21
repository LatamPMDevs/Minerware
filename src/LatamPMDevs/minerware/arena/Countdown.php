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

/**
 * The seconds a phase has left, and whether an admin is holding it.
 */
final class Countdown {

	private int $remaining = 0; //seconds

	private int $total = 0; //seconds

	private bool $paused = false;

	/**
	 * Starts over with the given duration.
	 */
	public function reset(int $seconds) : void {
		$this->remaining = $this->total = $seconds;
	}

	public function getRemaining() : int {
		return $this->remaining;
	}

	public function getTotal() : int {
		return $this->total;
	}

	public function set(int $seconds) : void {
		$this->remaining = $seconds;
	}

	public function decrement() : void {
		$this->remaining--;
	}

	public function isPaused() : bool {
		return $this->paused;
	}

	public function setPaused(bool $paused) : void {
		$this->paused = $paused;
	}
}