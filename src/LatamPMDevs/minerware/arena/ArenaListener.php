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

namespace LatamPMDevs\minerware\arena;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;

/**
 * The only listener for what happens to the players of arenas.
 */
final class ArenaListener implements Listener {

	public function onQuit(PlayerQuitEvent $event) : void {
		$player = $event->getPlayer();
		ArenaManager::getInstance()->getArenaOf($player)?->quit($player);
	}

	/**
	 * @ignoreCancelled
	 * @priority MONITOR
	 */
	public function onWorldChange(EntityTeleportEvent $event) : void {
		$player = $event->getEntity();
		if (!$player instanceof Player) {
			return;
		}
		$arena = ArenaManager::getInstance()->getArenaOf($player);
		if ($arena === null || $event->getTo()->getWorld() === $arena->getWorld()) {
			return;
		}
		if ($event->getFrom()->world === $event->getTo()->getWorld()) {
			return;
		}
		$arena->quit($player);
	}

	public function onDropItem(PlayerDropItemEvent $event) : void {
		if (ArenaManager::getInstance()->getArenaOf($event->getPlayer()) !== null) {
			$event->cancel();
		}
	}

	public function onExhaust(PlayerExhaustEvent $event) : void {
		$player = $event->getPlayer();
		if ($player instanceof Player && ArenaManager::getInstance()->getArenaOf($player) !== null) {
			$event->cancel();
		}
	}

	public function onDamage(EntityDamageEvent $event) : void {
		$player = $event->getEntity();
		if ($player instanceof Player) {
			ArenaManager::getInstance()->getArenaOf($player)?->handleDamage($event, $player);
		}
	}
}
