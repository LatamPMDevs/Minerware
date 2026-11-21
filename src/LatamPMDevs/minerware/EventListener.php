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

namespace LatamPMDevs\minerware;

use LatamPMDevs\minerware\arena\microgame\Level;
use LatamPMDevs\minerware\database\DataManager;
use LatamPMDevs\minerware\event\arena\ArenaEndEvent;
use LatamPMDevs\minerware\event\arena\microgame\MicrogameEndEvent;
use LatamPMDevs\minerware\event\arena\microgame\PlayerWinMicrogameEvent;
use LatamPMDevs\minerware\event\arena\PlayerQuitArenaEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use function time;

final class EventListener implements Listener {

	public function __construct(private DataManager $dataManager) {
	}

	/**
	 * Restores the language the player chose in an earlier session.
	 */
	public function onPlayerJoin(PlayerJoinEvent $event) : void {
		$player = $event->getPlayer();
		Minerware::getInstance()->getLogger()->debug($player->getName() . " is " . $player->getUniqueId()->toString() . " (xuid '" . $player->getXuid() . "')");
		$this->dataManager->getPlayerLanguage($player, function (?string $locale) use ($player) : void {
			if ($locale === null || !$player->isConnected()) {
				return;
			}
			$translator = Minerware::getInstance()->getTranslator();
			$language = $translator->getLanguage($locale);
			if ($language !== null) {
				$translator->setPlayerLanguage($player, $language);
			}
		});
	}

	/**
	 * @priority MONITOR
	 */
	public function onArenaEnd(ArenaEndEvent $event) : void {
		if (!$event->isCompleted()) {
			return;
		}
		$arena = $event->getArena();

		foreach ($arena->getPlayers() as $player) {
			$this->dataManager->addGamesPlayed($player, 1);
			if ($arena->getPodium()->isWinner($player)) {
				$this->dataManager->addWins($player, 1, Minerware::getInstance()->getLeaderboard()->refresh(...));
			}
		}
	}

	/**
	 * @priority MONITOR
	 */
	public function onMicrogameEnd(MicrogameEndEvent $event) : void {
		foreach ($event->getArena()->getPlayers() as $player) {
			$this->dataManager->addMicrogamesPlayed($player, 1);
		}
	}

	/**
	 * @priority MONITOR
	 */
	public function onPlayerWinMicrogame(PlayerWinMicrogameEvent $event) : void {
		if ($event->getMicrogame()->getLevel() === Level::BOSS) {
			$this->dataManager->addBossgamesWon($event->getPlayer(), 1);
		} else {
			$this->dataManager->addMicrogamesWon($event->getPlayer(), 1);
		}
	}

	/**
	 * @priority MONITOR
	 */
	public function onPlayerQuitArena(PlayerQuitArenaEvent $event) : void {
		$startTime = $event->getArena()->getStartTime();
		if ($startTime !== null) {
			$this->dataManager->addTimePlayed($event->getPlayer(), time() - $startTime);
		}
	}
}