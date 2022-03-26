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
 * Copyright 2022 © Minerware
 */

declare(strict_types=1);

namespace minerware\tasks;

use minerware\arena\Arena;
use minerware\arena\ArenaManager;
use minerware\database\DataManager;
use minerware\language\Translator;
use minerware\Minerware;
use minerware\utils\Utils;
use pocketmine\lang\Translatable;
use pocketmine\scheduler\Task;
use pocketmine\utils\SingletonTrait;
use function count;

/**
 * Class ArenaTask
 */
class ArenaTask extends Task {
	use SingletonTrait;

	private Minerware $plugin;

	public function __construct(private Arena $arena) {
		$this->plugin = Minerware::getInstance();
	}

	public function onRun(): void {
		$arena = $this->arena;
		$players = $arena->getPlayers();
		$world = $arena->getWorld();
		switch ($arena->getStatus()) {
			case "waiting":
				if(count($players) < Arena::MIN_PLAYERS) {
				   $arena->waitingtime = 40;
					foreach ($players as $player) {
						$player->sendTip(Translator::getInstance()->translate(new Translatable("game.arena.needMorePlayers")));
					}
				} else {
					$arena->waitingtime--;
					if (count($players) == Arena::MAX_PLAYERS) {
						$arena->sendMessage(Translator::getInstance()->translate(new Translatable("game.arena.startingByReachCapacity")));
						$arena->setStatus("starting");
					}
					foreach ($players as $player) {
						if ($arena->waitingtime >= 6 && $arena->waitingtime <= 40) {
							$player->sendTip(Translator::getInstance()->translate(new Translatable("game.arena.starting", [$arena->waitingtime])));
						} elseif ($arena->waitingtime >= 1 && $arena->waitingtime <= 5) {
							$player->sendTip(Translator::getInstance()->translate(new Translatable("game.arena.starting", ["§c" . $arena->waitingtime])));
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
					$arena->sendMessage(Translator::getInstance()->translate(new Translatable("game.arena.countCancelled")));
					$arena->setStatus("waiting");
					$lobby = DataManager::getInstance()->getLobby();
					foreach ($players as $player) {
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
						$player->sendTip(Translator::getInstance()->translate(new Translatable("game.arena.start", ["§e" . Utils::getStartingBar($arena->startingtime, 10) . "§f " . $arena->startingtime])));
					}
				}
				if ($arena->startingtime >= 1 && $arena->startingtime <= 3) {
					foreach ($players as $player) {
						$player->sendTip(Translator::getInstance()->translate(new Translatable("game.arena.start", ["§c" . Utils::getStartingBar($arena->startingtime, 10) . "§f " . $arena->startingtime])));
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
