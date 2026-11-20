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

use LatamPMDevs\minerware\item\SpecialItems;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Item;
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

	public function onItemUse(PlayerItemUseEvent $event) : void {
		$this->useSpecialItem($event->getPlayer(), $event->getItem(), $event);
	}

	public function onInteract(PlayerInteractEvent $event) : void {
		$this->useSpecialItem($event->getPlayer(), $event->getItem(), $event);
	}

	/**
	 * The players of an arena can't move items around while they wait.
	 */
	public function onTransaction(InventoryTransactionEvent $event) : void {
		$arena = ArenaManager::getInstance()->getArenaOf($event->getTransaction()->getSource());
		if ($arena !== null && $arena->getPhase()->isLobby()) {
			$event->cancel();
		}
	}

	private function useSpecialItem(Player $player, Item $item, PlayerItemUseEvent|PlayerInteractEvent $event) : void {
		if (ArenaManager::getInstance()->getArenaOf($player) === null) {
			return;
		}
		if ($item->getNamedTag()->getTag(SpecialItems::LEAVE_GAME) !== null) {
			$event->cancel();
			$spawn = $player->getServer()->getWorldManager()->getDefaultWorld()?->getSafeSpawn();
			if ($spawn !== null) {
				# Leaving the arena's world is what removes the player from it.
				$player->teleport($spawn);
			}
		}
	}

	public function onDamage(EntityDamageEvent $event) : void {
		$player = $event->getEntity();
		if ($player instanceof Player) {
			ArenaManager::getInstance()->getArenaOf($player)?->handleDamage($event, $player);
		}
	}
}
