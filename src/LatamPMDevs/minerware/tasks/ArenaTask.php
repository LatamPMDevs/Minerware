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
use LatamPMDevs\minerware\database\DataManager;
use LatamPMDevs\minerware\Minerware;
use LatamPMDevs\minerware\utils\Utils;
use pocketmine\scheduler\Task;
use function count;

final class ArenaTask extends Task {

	private Minerware $plugin;

	public function __construct(private Arena $arena) {
		$this->plugin = Minerware::getInstance();
	}

	public function onRun() : void {
		$arena = $this->arena;
		$players = $arena->getPlayers();
		$world = $arena->getWorld();
		switch ($arena->getStatus()) {
			case "waiting":
				if (count($players) < Arena::MIN_PLAYERS) {
				   $arena->waitingtime = 40;
					foreach ($players as $player) {
						$player->sendTip($this->plugin->getTranslator()->translate($player, "game.arena.needMorePlayers"));
					}
				} else {
					$arena->waitingtime--;
					if (count($players) == Arena::MAX_PLAYERS) {
						foreach ($players as $player) {
							$player->sendMessage($this->plugin->getTranslator()->translate($player, "game.arena.startingByReachCapacity"));
						}
						$arena->setStatus("starting");
					}
					foreach ($players as $player) {
						if ($arena->waitingtime >= 6 && $arena->waitingtime <= 40) {
							$player->sendMessage($this->plugin->getTranslator()->translate(
								$player, "game.arena.starting", [
									"{%time}" => $arena->waitingtime
								]
							));
						} elseif ($arena->waitingtime >= 1 && $arena->waitingtime <= 5) {
							$player->sendMessage($this->plugin->getTranslator()->translate(
								$player, "game.arena.starting", [
									"{%time}" => "§c" . $arena->waitingtime
								]
							));
							Utils::playSound($player, "random.click");
						}
					}
					if ($arena->waitingtime == 0) {
						$arena->setStatus("starting");
					}
				}
			break;

			case "starting":
				$arena->startingtime--;
				if ($arena->startingtime == 11) {
					$map = $arena->getVoteCounter()->getWinner();
					$world = $map->generateWorld($arena->getId());
					$arena->setMap($map);
					$arena->setWorld($world);
					foreach ($players as $player) {
						$player->getInventory()->clearAll();
						$player->getArmorInventory()->clearAll();
						$player->getCursorInventory()->clearAll();
						$arena->tpSpawn($player);
					}
				}
				if (count($players) < Arena::MIN_PLAYERS) {
					$arena->setStatus("waiting");
					$lobby = DataManager::getInstance()->getLobby();
					foreach ($players as $player) {
						$player->sendMessage($this->plugin->getTranslator()->translate($player, "game.arena.countCancelled"));
						$lobby->loadChunk($lobby->getSafeSpawn()->getFloorX(), $lobby->getSafeSpawn()->getFloorZ());
						$player->teleport($lobby->getSafeSpawn(), 0, 0);
						$player->getInventory()->clearAll();
						$player->getArmorInventory()->clearAll();
						$player->getCursorInventory()->clearAll();
						$arena->deleteMap();
					}
				}
				if ($arena->startingtime >= 4 && $arena->startingtime <= 10) {
					foreach ($players as $player) {
						$player->sendTip($this->plugin->getTranslator()->translate(
							$player, "game.arena.start", [
								"{%time}" => "§e" . Utils::getStartingBar($arena->startingtime, 10) . "§f " . $arena->startingtime
							]
						));
					}
				}
				if ($arena->startingtime >= 1 && $arena->startingtime <= 3) {
					foreach ($players as $player) {
						$player->sendTip($this->plugin->getTranslator()->translate(
							$player, "game.arena.start", [
								"{%time}" => "§c" . Utils::getStartingBar($arena->startingtime, 10) . "§f " . $arena->startingtime
							]
						));
						Utils::playSound($player, "random.toast", 1, 1.5);
					}
				}
				if ($arena->startingtime == 0) {
					$arena->setStatus("ingame");
				}
			break;

			case "ingame":
				$arena->gametime++;
				$microgame = $arena->getCurrentMicrogame();
				if ($arena->gametime == 1) {
					$microgame = $arena->startNextMicrogame();
				}
				$microgame->tick();
			break;

			case "ending":
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
	}
}
