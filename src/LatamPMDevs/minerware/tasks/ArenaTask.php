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
use LatamPMDevs\minerware\arena\Status;
use LatamPMDevs\minerware\Minerware;
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
				if (count($players) < Arena::MIN_PLAYERS) {
					foreach ($players as $player) {
						$player->sendTip($this->plugin->getTranslator()->translate($player, "game.arena.needMorePlayers"));
					}
				} else {
					$arena->setStatus(Status::STARTING());
				}
				break;

			case ($status->equals(Status::STARTING())):
				$arena->startingtime--;
				if (count($players) < Arena::MIN_PLAYERS) {
					$arena->startingtime = Arena::STARTING_TIME;
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
						$player->getXpManager()->setXpLevel($arena->startingtime);
					}
					if ($arena->startingtime <= 0) {
						$arena->setStatus(Status::INBETWEEN());
						$arena->isInFirstInBetween = true;
						$arena->getPointHolder()->clear();
						foreach ($players as $player) {
							$arena->getPointHolder()->addPlayer($player);
						}
					}
				}
				break;

			case ($status->equals(Status::INBETWEEN())):
				$arena->inbetweentime--;
				if ($arena->inbetweentime === 6) {
					if ($arena->initNextMicrogame() === null) {
						# TODO: Finish game!
					}
				}
				if ($arena->isInFirstInBetween) {
					if ($arena->inbetweentime === 6) {
						foreach ($players as $player) {
							$player->sendTitle("§6MinerWare", "§5By LatamPMDevs", 10, 10, 10);
						}
					} elseif ($arena->inbetweentime === 4) {
						foreach ($players as $player) {
							$player->sendTitle("§1§2", "§5Win the most microgames", 10, 10, 10);
						}
					}
				}
				if ($arena->inbetweentime <= 3 && $arena->inbetweentime >= 1) {
					foreach ($players as $player) {
						$player->sendTitle("§k§4|||§6" . $arena->inbetweentime . "§k§4|||", "§5" . $arena->getCurrentMicrogame()->getName(), 1, 1, 1);
					}
				}
				if ($arena->inbetweentime <= 0) {
					$arena->setStatus(Status::INGAME());
					$arena->inbetweentime = Arena::INBETWEEN_TIME;
				}
				break;

			case ($status->equals(Status::INGAME())):
				// TODO!
				break;

			case ($status->equals(Status::ENDING())):
				$arena->endingtime--;
				if ($arena->endingtime == 0) {
					foreach ($players as $player) {
						ArenaManager::getInstance()->join($player);
					}
					$arena->deleteMap();
					ArenaManager::getInstance()->deleteArena($arena);
				}
				break;
		}
		$arena->updateScoreboard();
	}
}
