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

use LatamPMDevs\minerware\utils\PointHolder;
use LatamPMDevs\minerware\utils\Utils;
use pocketmine\player\Player;
use function array_slice;

/**
 * The final ranking of an arena: the top three places (ties share one) and everyone else.
 */
final class Podium {

	public const PLACES = 3;

	/** @var array<int, array<int, Player>> place (1-based) => players by id */
	private array $winners = [];

	/** @var array<int, Player> */
	private array $losers = [];

	/**
	 * Ranks the players by points.
	 *
	 * @param array<int, Player> $players
	 */
	public function rank(array $players, PointHolder $points) : void {
		$place = 1;
		foreach (array_slice(Utils::chunkScores($points->getOrderedByHigherScore()), 0, self::PLACES) as $ids) {
			foreach ($ids as $id) {
				if (isset($players[$id])) {
					$this->winners[$place][$id] = $players[$id];
				}
			}
			$place++;
		}
		foreach ($players as $player) {
			if (!$this->isWinner($player)) {
				$this->losers[$player->getId()] = $player;
			}
		}
	}

	/**
	 * @return array<int, array<int, Player>>
	 */
	public function getWinners() : array {
		return $this->winners;
	}

	/**
	 * @return array<int, Player>
	 */
	public function getLosers() : array {
		return $this->losers;
	}

	public function isWinner(Player $player) : bool {
		foreach ($this->winners as $players) {
			if (isset($players[$player->getId()])) {
				return true;
			}
		}
		return false;
	}

	public function isLoser(Player $player) : bool {
		return isset($this->losers[$player->getId()]);
	}
}