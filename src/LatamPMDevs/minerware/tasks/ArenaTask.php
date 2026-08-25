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

namespace LatamPMDevs\minerware\tasks;

use LatamPMDevs\minerware\arena\Arena;
use LatamPMDevs\minerware\arena\ArenaManager;
use LatamPMDevs\minerware\arena\microgame\Level;
use LatamPMDevs\minerware\arena\Status;
use LatamPMDevs\minerware\Minerware;
use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\Task;
use function count;
use function max;

final class ArenaTask extends Task {

	private Minerware $plugin;

	public function __construct(private Arena $arena) {
		$this->plugin = Minerware::getInstance();
	}

	public function onRun() : void {
		$arena = $this->arena;
		$status = $arena->getStatus();
		$players = $arena->getPlayers();
		switch ($status) {
			case Status::WAITING:
				if (count($players) < $arena->getMinPlayers()) {
					foreach ($players as $player) {
						$player->sendTip($this->plugin->getTranslator()->translate($player, "game.arena.needMorePlayers"));
					}
				} else {
					$arena->setStatus(Status::STARTING);
				}
				break;

			case Status::STARTING:
				if (count($players) < $arena->getMinPlayers()) {
					foreach ($players as $player) {
						$player->sendMessage($this->plugin->getTranslator()->translate($player, "game.arena.countCancelled"));
					}
					$arena->setStatus(Status::WAITING);
				} else {
					if ($arena->getCountdown() > 15 && count($players) >= Arena::MAX_PLAYERS) {
						foreach ($players as $player) {
							$player->sendMessage($this->plugin->getTranslator()->translate(
								$player, "game.arena.startingByReachCapacity", [
									"{%time}" => 15 . " " . $this->plugin->getTranslator()->translate($player, "text.seconds")
								]
							));
						}
						$arena->setCountdown(15);
					}
					foreach ($players as $player) {
						$player->getXpManager()->setXpAndProgress($arena->getCountdown(), 0);
					}
					if ($arena->getCountdown() <= 0) {
						$arena->start();
					}
				}
				if ($arena->getStatus() === Status::STARTING) {
					$arena->decrementCountdown();
				}
				break;

			case Status::INBETWEEN:
				$total = max(1, $arena->getCountdownTotal());
				$countdown = $arena->getCountdown();
				if ($countdown === max(1, $total - 2)) {
					if ($arena->getNextMicrogame() === null) {
						$arena->end();
						return;
					}
				}
				if ($countdown > 0) {
					$pct = $countdown / $total;
					if ($pct >= 0.7) {
						foreach ($players as $player) {
							$player->sendTitle("§6MinerWare", $this->plugin->getTranslator()->translate($player, "game.arena.inbetween.credits"), 10, 10, 10);
						}
					} elseif ($pct >= 0.4) {
						foreach ($players as $player) {
							$player->sendTitle("§1§2", $this->plugin->getTranslator()->translate($player, "game.arena.inbetween.winthemost"), 10, 10, 10);
						}
					} else {
						$isBoss = $arena->getNextMicrogameNonNull()->getLevel() === Level::BOSS;
						foreach ($players as $player) {
							if ($isBoss) {
								$player->sendTitle("§6BOSS GAME", "§c" . $arena->getNextMicrogameNonNull()->getName(), 10, 10, 10);
							} else {
								$player->sendTitle("§k§4|||§r§6" . $countdown . "§k§4|||", "§5" . $arena->getNextMicrogameNonNull()->getName(), 10, 10, 10);
							}
						}
					}
				}
				if ($countdown <= 0) {
					foreach ($players as $player) {
						$player->sendTitle("§6GO", "", 10, 10, 10);
					}
					$arena->setStatus(Status::INGAME);
					$arena->startNextMicrogame();
				}
				if ($arena->getStatus() === Status::INBETWEEN) {
					$arena->decrementCountdown();
				}
				break;

			case Status::INGAME:
				if ($arena->getCurrentMicrogame() === null) {
					$this->arena->setStatus(Status::INBETWEEN);
				}
				break;

			case Status::ENDING:
				if ($arena->getCountdown() <= 0) {
					foreach ($players as $player) {
						ArenaManager::getInstance()->join($player);
					}
					$arena->deleteMap();
					ArenaManager::getInstance()->deleteArena($arena);
					throw new CancelTaskException("Arena is no more running");
				}
				$arena->decrementCountdown();
				break;
		}
		$arena->updateScoreboard();
	}
}