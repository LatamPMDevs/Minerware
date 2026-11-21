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

use LatamPMDevs\minerware\arena\Arena;
use LatamPMDevs\minerware\arena\microgame\Level;
use pocketmine\utils\SingletonTrait;
use function max;

final class InbetweenPhase extends PlayingPhase {
	use SingletonTrait;

	private const DURATION = 5; //seconds

	public function getName() : string {
		return "inbetween";
	}

	public function getDuration() : int {
		return self::DURATION;
	}

	protected function getMicrogameLine(Arena $arena) : string {
		return "§e§fIn-between";
	}

	public function tick(Arena $arena) : void {
		if ($arena->isBuildingStage()) {
			# Async stage teardown/build still draining; hold the countdown.
			return;
		}
		$players = $arena->getPlayers();
		$translator = $arena->getPlugin()->getTranslator();
		$total = max(1, $arena->getCountdown()->getTotal());
		$countdown = $arena->getCountdown()->getRemaining();
		if ($countdown <= max(1, $total - 2) && $arena->getQueue()->peek() === null) {
			$arena->end();
			return;
		}
		if ($countdown > 0) {
			if ($countdown === $total) {
				foreach ($players as $player) {
					$player->sendTitle("§6MinerWare", $translator->translate($player, "game.arena.inbetween.credits"), 10, 10, 10);
				}
			} elseif ($countdown === $total - 1) {
				foreach ($players as $player) {
					$player->sendTitle("§1§2", $translator->translate($player, "game.arena.inbetween.winthemost"), 10, 10, 10);
				}
			} else {
				$next = $arena->getNextMicrogameNonNull();
				$isBoss = $next->getLevel() === Level::BOSS;
				foreach ($players as $player) {
					if ($isBoss) {
						$player->sendTitle("§6BOSS GAME", "§c" . $next->getName(), 10, 10, 10);
					} else {
						$player->sendTitle("§k§4|||§r§6" . $countdown . "§k§4|||", "§5" . $next->getName(), 10, 10, 10);
					}
				}
			}
		}
		if ($countdown <= 0) {
			foreach ($players as $player) {
				$player->sendTitle("§6GO", "", 10, 10, 10);
			}
			$arena->setPhase(IngamePhase::getInstance());
			$arena->startNextMicrogame();
		}
		if ($arena->getPhase() === $this) {
			$arena->getCountdown()->decrement();
		}
	}
}