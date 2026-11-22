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

use InvalidArgumentException;
use LatamPMDevs\minerware\Settings;

/**
 * What an admin can change in one arena before its game starts: the config gives the defaults.
 */
final class ArenaRules {

	public const MIN_MICROGAMES = 1;
	public const MIN_STARTING_TIME = 5; //seconds
	public const MAX_STARTING_TIME = 300; //seconds

	private int $startingTime;

	public function __construct() {
		$this->startingTime = Settings::getInstance()->getArenaStartingTime();
	}

	public function getStartingTime() : int {
		return $this->startingTime;
	}

	public function setStartingTime(int $seconds) : self {
		if ($seconds < self::MIN_STARTING_TIME || $seconds > self::MAX_STARTING_TIME) {
			throw new InvalidArgumentException("Starting time must be from " . self::MIN_STARTING_TIME . " to " . self::MAX_STARTING_TIME . ", $seconds given.");
		}
		$this->startingTime = $seconds;
		return $this;
	}
}