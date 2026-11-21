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

namespace LatamPMDevs\minerware\arena\phase;

use Closure;
use LatamPMDevs\minerware\arena\Arena;
use LatamPMDevs\minerware\arena\microgame\Level;
use LatamPMDevs\minerware\Settings;
use pocketmine\player\Player;

/**
 * Between two microgames, or in the middle of one: the scoreboard shows the scores.
 */
abstract class PlayingPhase extends ArenaPhase {

	private const TOP_PLAYERS = 5;

	/**
	 * What the scoreboard says the current microgame is.
	 */
	abstract protected function getMicrogameLine(Arena $arena) : string;

	public function createScoreboard(Arena $arena) : ?Closure {
		$translator = $arena->getPlugin()->getTranslator();
		$pointHolder = $arena->getPointHolder();
		$scores = $pointHolder->getOrderedByHigherScore();
		$players = $arena->getPlayers();
		$serverIp = Settings::getInstance()->getServerIp();
		$queue = $arena->getQueue();
		$isBoss = $arena->getCurrentMicrogame()?->getLevel() === Level::BOSS;
		$microgameLine = $this->getMicrogameLine($arena);
		return function (Player $player) use ($translator, $pointHolder, $scores, $players, $serverIp, $queue, $isBoss, $microgameLine) : array {
			$isOnTop = false;
			$i = 0;
			$lines = ["§5" . $translator->translate($player, "text.scores") . ":"];
			foreach ($scores as $playerId => $point) {
				$i++;
				$user = $players[$playerId]->getName();
				if ($user === $player->getName()) {
					$lines[] = "§f" . "§a" . $point . " §8" . $user;
					$isOnTop = true;
				} elseif ($i === self::TOP_PLAYERS && !$isOnTop) {
					$lines[] = "§f" . "§a" . $pointHolder->getPlayerPoints($player) . " §8" . $player->getName();
				} else {
					$lines[] = "§f" . "§e" . $point . " §8" . $user;
				}
				if ($i === self::TOP_PLAYERS) {
					break;
				}
			}
			$lines[] = "§1";
			$lines[] = "§5" . $translator->translate($player, "text.microgame") . ":";
			$lines[] = $microgameLine;
			$lines[] = "§5(§f" . ($isBoss ? "Bossgame" : $queue->getPosition() . "§5/§f" . $queue->getNormalCount()) . "§5)";
			$lines[] = "§2";
			$lines[] = "§6" . $serverIp;
			return $lines;
		};
	}
}
