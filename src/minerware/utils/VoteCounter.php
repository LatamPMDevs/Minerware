<?php

/**
 *  ███╗   ███╗██╗███╗   ██╗███████╗██████╗ ██╗    ██╗ █████╗ ██████╗ ███████╗
 *  ████╗ ████║██║████╗  ██║██╔════╝██╔══██╗██║    ██║██╔══██╗██╔══██╗██╔════╝
 *  ██╔████╔██║██║██╔██╗ ██║█████╗  ██████╔╝██║ █╗ ██║███████║██████╔╝█████╗
 *  ██║╚██╔╝██║██║██║╚██╗██║██╔══╝  ██╔══██╗██║███╗██║██╔══██║██╔══██╗██╔══╝
 *  ██║ ╚═╝ ██║██║██║ ╚████║███████╗██║  ██║╚███╔███╔╝██║  ██║██║  ██║███████╗
 *  ╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝ ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝
 *
 * This is a private project, your not allow to redistribute nor resell it.
 * The only ones with that power are this project's contributors.
 *
 * Copyright 2021 © Minerware
 */

declare(strict_types=1);

namespace minerware\utils;

use minerware\arena\Map;
use pocketmine\player\Player;
use function array_rand;
use function count;

final class VoteCounter {

	/** @var array */
	private $votes = [];

	public function __construct() {
		// code...
	}

	/**
	 * @param Map|string $map
	 */
	public function vote(Player $player, $map): void {
		$this->votes[($map instanceof Map) ? $map->getName() : $player][] = $player->getName();
	}

	public function getVote(Player $player): ?string {
		foreach ($this->votes as $map => $voters) {
			foreach ($voters as $voter) {
				if ($voter === $player->getName()) {
					return $map;
				}
			}
		}
		return null;
	}

	public function getWinner(): Map {
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
