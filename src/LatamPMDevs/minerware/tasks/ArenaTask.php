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

namespace LatamPMDevs\minerware\tasks;

use LatamPMDevs\minerware\arena\Arena;
use LatamPMDevs\minerware\arena\ArenaManager;
use LatamPMDevs\minerware\arena\microgame\Level;
use LatamPMDevs\minerware\arena\Status;
use LatamPMDevs\minerware\Minerware;
use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\Task;
use function count;

final class ArenaTask extends Task {

	private Minerware $plugin;

	public function __construct(private Arena $arena) {
		$this->plugin = Minerware::getInstance();
	}

	public function onRun() : void {
		$arena = $this->arena;
		$status = $arena->getStatus();
		$players = $arena->getPlayers();
		$world = $arena->getWorld();
		switch (true) {
			case ($status->equals(Status::WAITING())):
				if (count($players) < $arena->getMinPlayers()) {
					foreach ($players as $player) {
						$player->sendTip($this->plugin->getTranslator()->translate($player, "game.arena.needMorePlayers"));
					}
				} else {
					$arena->setStatus(Status::STARTING());
				}
				break;

			case ($status->equals(Status::STARTING())):
				if (count($players) < $arena->getMinPlayers()) {
					foreach ($players as $player) {
						$player->sendMessage($this->plugin->getTranslator()->translate($player, "game.arena.countCancelled"));
					}
					$arena->startingtime = $arena->defaultStartingtime;
					$arena->setStatus(Status::WAITING());
				} else {
					if ($arena->startingtime > 15 && count($players) >= Arena::MAX_PLAYERS) {
						foreach ($players as $player) {
							$player->sendMessage($this->plugin->getTranslator()->translate(
								$player, "game.arena.startingByReachCapacity", [
									"{%time}" => 15 . " " . $this->plugin->getTranslator()->translate($player, "text.seconds")
								]
							));
						}
						$arena->startingtime = 15;
					}
					foreach ($players as $player) {
						$player->getXpManager()->setXpAndProgress($arena->startingtime, 0);
					}
					if ($arena->startingtime <= 0) {
						$arena->setStatus(Status::INBETWEEN());
						$arena->getPointHolder()->clear();
						foreach ($players as $player) {
							$arena->getPointHolder()->addPlayer($player);
						}
						if ($arena->areInvisibleBlocksSet()) {
							$arena->unsetInvisibleBlocks();
						}
					}
				}
				$arena->startingtime--;
				break;

			case ($status->equals(Status::INBETWEEN())):
				if ($arena->inbetweentime === 3) {
					if ($arena->getNextMicrogame() === null) {
						$arena->end();
						return;
					}
				}
				if ($arena->inbetweentime === 10) {
					foreach ($players as $player) {
						$player->sendTitle("§6MinerWare", $this->plugin->getTranslator()->translate($player, "game.arena.inbetween.credits"), 10, 10, 10);
					}
				} elseif ($arena->inbetweentime === 6) {
					foreach ($players as $player) {
						$player->sendTitle("§1§2", $this->plugin->getTranslator()->translate($player, "game.arena.inbetween.winthemost"), 10, 10, 10);
					}
				}
				if ($arena->inbetweentime <= 3 && $arena->inbetweentime >= 1) {
					$isBoss = $arena->getNextMicrogameNonNull()->getLevel()->equals(Level::BOSS());
					foreach ($players as $player) {
						if ($isBoss) {
							$player->sendTitle("§6BOSS GAME", "§c" . $arena->getNextMicrogameNonNull()->getName(), 10, 10, 10);
						} else {
							$player->sendTitle("§k§4|||§r§6" . $arena->inbetweentime . "§k§4|||", "§5" . $arena->getNextMicrogameNonNull()->getName(), 10, 10, 10);
						}
					}
				}
				if ($arena->inbetweentime <= 0) {
					foreach ($players as $player) {
						$player->sendTitle("§6GO", "", 10, 10, 10);
					}
					$arena->setStatus(Status::INGAME());
					$arena->inbetweentime = Arena::INBETWEEN_TIME;
					$arena->startNextMicrogame();
				}
				$arena->inbetweentime--;
				break;

			case ($status->equals(Status::INGAME())):
				if ($arena->getCurrentMicrogame() === null) {
					$this->arena->setStatus(Status::INBETWEEN());
				}
				break;

			case ($status->equals(Status::ENDING())):
				if ($arena->endingtime <= 0) {
					foreach ($players as $player) {
						ArenaManager::getInstance()->join($player);
					}
					$arena->deleteMap();
					ArenaManager::getInstance()->deleteArena($arena);
					throw new CancelTaskException("Arena is no more running");
				}
				$arena->endingtime--;
				break;
		}
		$arena->updateScoreboard();
	}
}
