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

use LatamPMDevs\minerware\arena\Map;
use pocketmine\player\Player;
use function array_rand;
use function count;

final class VoteCounter {

	/** @var array<Map, Player[]> */
	private array $votes = [];

	public function __construct() {
		// code...
	}

	public function vote(Player $player, Map|string $map) : void {
		$this->votes[($map instanceof Map) ? $map->getName() : $map][] = $player;
	}

	public function getVote(Player $player) : ?string {
		foreach ($this->votes as $map => $voters) {
			foreach ($voters as $voter) {
				if ($voter->getName() === $player->getName()) {
					return $map;
				}
			}
		}

		return null;
	}

	public function getWinner() : Map {
		$winner = null;
		$lastCount = -1;
		foreach ($this->votes as $map => $voters) {
			if ($lastCount === -1 || $lastCount < count($voters)) {
				$winner = $map;
				$lastCount = count($voters);
			}
		}

		if ($winner === null) {
			$maps = Map::$maps;
			$randMap = $maps[array_rand($maps)];
			$winner = $randMap->getName();
		}

		return Map::getByName($winner);
	}
}
