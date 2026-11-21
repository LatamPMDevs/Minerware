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

namespace LatamPMDevs\minerware\leaderboard;

use LatamPMDevs\minerware\entity\object\TopPlayerNpc;
use LatamPMDevs\minerware\Minerware;
use pocketmine\entity\Skin;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChangeSkinEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;

/**
 * Keeps the NPCs wearing the current skin of the players they show.
 */
final class LeaderboardListener implements Listener {

	public function __construct(private readonly Minerware $plugin) { }

	/**
	 * @priority MONITOR
	 */
	public function onJoin(PlayerJoinEvent $event) : void {
		$player = $event->getPlayer();
		$this->wear($player, $player->getSkin());
	}

	/**
	 * @priority MONITOR
	 * @ignoreCancelled
	 */
	public function onChangeSkin(PlayerChangeSkinEvent $event) : void {
		$this->wear($event->getPlayer(), $event->getNewSkin());
	}

	private function wear(Player $player, Skin $skin) : void {
		$uuid = $player->getUniqueId()->toString();
		$leaderboard = $this->plugin->getLeaderboard();
		$onTop = false;
		for ($rank = 1; $rank <= TopPlayerNpc::RANKS; $rank++) {
			if ($leaderboard->getEntry($rank)?->uuid === $uuid) {
				$onTop = true;
			}
		}
		if (!$onTop) {
			return;
		}
		foreach ($this->plugin->getServer()->getWorldManager()->getWorlds() as $world) {
			foreach ($world->getEntities() as $entity) {
				if ($entity instanceof TopPlayerNpc && $entity->getHolder() === $uuid) {
					$entity->applySkin($skin);
				}
			}
		}
	}
}
