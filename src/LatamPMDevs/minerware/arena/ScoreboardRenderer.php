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

use jackmd\scorefactory\ScoreFactory;
use pocketmine\player\Player;

/**
 * Draws the scoreboard of an arena's players, sending a player's lines only when they changed.
 */
final class ScoreboardRenderer {

	private const MAX_LINES = 15;

	/** @var array<int, list<string>> player id => the lines last sent to them */
	private array $sent = [];

	/**
	 * Forgets what was sent (the lines were cleared on the clients).
	 */
	public function reset() : void {
		$this->sent = [];
	}

	public function forget(Player $player) : void {
		unset($this->sent[$player->getId()]);
	}

	public function update(Arena $arena) : void {
		$linesOf = $arena->getPhase()->createScoreboard($arena);
		foreach ($arena->getPlayers() as $player) {
			if ($linesOf === null) {
				unset($this->sent[$player->getId()]);
				ScoreFactory::removeObjective($player, true);
				continue;
			}
			$lines = $linesOf($player);
			if (($this->sent[$player->getId()] ?? null) === $lines && ScoreFactory::hasObjective($player)) {
				continue;
			}
			$this->sent[$player->getId()] = $lines;
			if (!ScoreFactory::hasObjective($player)) {
				ScoreFactory::setObjective($player, '§l§eMinerWare');
				ScoreFactory::sendObjective($player);
			}
			$from = 0;
			foreach ($lines as $line) {
				if ($from < self::MAX_LINES) {
					$from++;
					ScoreFactory::setScoreLine($player, $from, $line);
				}
			}
			ScoreFactory::sendLines($player);
		}
	}
}
