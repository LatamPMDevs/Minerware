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
use LatamPMDevs\minerware\database\DataManager;
use pocketmine\utils\SingletonTrait;
use function count;

final class StartingPhase extends LobbyPhase {
	use SingletonTrait;

	private const FULL_ARENA_COUNTDOWN = 15; //seconds

	public function getName() : string {
		return "starting";
	}

	public function getDuration() : int {
		return DataManager::getInstance()->getArenaStartingTime();
	}

	public function tick(Arena $arena) : void {
		$players = $arena->getPlayers();
		$translator = $arena->getPlugin()->getTranslator();
		if (count($players) < $arena->getMinPlayers()) {
			foreach ($players as $player) {
				$player->sendMessage($translator->translate($player, "game.arena.countCancelled"));
			}
			$arena->setPhase(WaitingPhase::getInstance());
			return;
		}
		if ($arena->isCountdownPaused()) {
			foreach ($players as $player) {
				$player->sendTip($translator->translate($player, "game.arena.countdownPaused"));
			}
			return;
		}
		if ($arena->getCountdown() > self::FULL_ARENA_COUNTDOWN && count($players) >= Arena::MAX_PLAYERS) {
			foreach ($players as $player) {
				$player->sendMessage($translator->translate(
					$player, "game.arena.startingByReachCapacity", [
						"{%time}" => self::FULL_ARENA_COUNTDOWN . " " . $translator->translate($player, "text.seconds")
					]
				));
			}
			$arena->setCountdown(self::FULL_ARENA_COUNTDOWN);
		}
		foreach ($players as $player) {
			$player->getXpManager()->setXpAndProgress($arena->getCountdown(), 0);
		}
		if ($arena->getCountdown() <= 0) {
			$arena->start();
		}
		if ($arena->getPhase() === $this) {
			$arena->decrementCountdown();
		}
	}
}
