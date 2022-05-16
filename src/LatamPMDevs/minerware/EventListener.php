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

namespace LatamPMDevs\minerware;

use LatamPMDevs\minerware\database\DataManager;
use LatamPMDevs\minerware\event\arena\ArenaEndEvent;
use LatamPMDevs\minerware\event\arena\microgame\MicrogameEndEvent;
use LatamPMDevs\minerware\event\arena\microgame\PlayerLoseMicrogameEvent;
use LatamPMDevs\minerware\event\arena\microgame\PlayerWinMicrogameEvent;
use pocketmine\event\Listener;

final class EventListener implements Listener {

	public function __construct(private Minerware $plugin, private DataManager $dataManager) {
	}

	/**
	 * @priority MONITOR
	 */
	public function onArenaEnd(ArenaEndEvent $event) : void {
		$arena = $event->getArena();

		foreach ($arena->getPlayers() as $player) {
			$this->dataManager->addGamesPlayed($player->getName(), 1);
			if ($arena->isWinner($player)) {
				$this->dataManager->addGamesWon($player->getName(), 1);
			} elseif ($arena->isLoser($player)) {
				$this->dataManager->addLostGames($player->getName(), 1);
			}
		}
	}

	/**
	 * @priority MONITOR
	 */
	public function onMicrogameEnd(MicrogameEndEvent $event) : void {
		foreach ($event->getArena()->getPlayers() as $player) {
			$this->dataManager->addMicrogamesPlayed($player->getName(), 1);
		}
	}

	/**
	 * @priority MONITOR
	 */
	public function onPlayerWinMicrogame(PlayerWinMicrogameEvent $event) : void {
		$this->dataManager->addMicrogamesWon($event->getPlayer()->getName(), 1);
	}

	/**
	 * @priority MONITOR
	 */
	public function onPlayerLoseMicrogame(PlayerLoseMicrogameEvent $event) : void {
		$this->dataManager->addLostMicrogames($event->getPlayer()->getName(), 1);
	}
}