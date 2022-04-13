<?php

/**
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
 * Copyright 2022 © LatamPMDevs
 */

declare(strict_types=1);

namespace LatamPMDevs\minerware\utils;

use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use function array_reverse;
use function asort;

final class PointHolder {

	/** @var array<string, int> */
	private array $points = [];

	public function addPlayer(Player $player) : void {
		$this->points[$player->getName()] = 0;
	}

	public function removePlayer(Player $player) : void {
		unset($this->points[$player->getName()]);
	}

	public function getPlayerPoints(Player $player) : ?int {
		return $this->points[$player->getName()] ?? null;
	}

	public function addPlayerPoint(Player $player, int $points = 1) : void {
		$this->points[$player->getName()] += $points;
	}

	/**
	 * @return array<string, int>
	 */
	public function getPoints() : array {
		return $this->points;
	}

	public function clear() : void {
		$this->points = [];
	}

	/**
	 * @return array<string, int>
	 */
	public function getOrderedByHigherScore() : array {
		$array = $this->points;
		if (asort($array) === false) {
			throw new AssumptionFailedError("Failed to sort points");
		}
		return array_reverse($array, true);
	}
}
