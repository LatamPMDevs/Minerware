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

namespace LatamPMDevs\minerware\arena\microgame\normal;

use LatamPMDevs\minerware\arena\microgame\Level;
use LatamPMDevs\minerware\arena\microgame\Microgame;
use LatamPMDevs\minerware\utils\Utils;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\HandlerListManager;
use pocketmine\event\Listener;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use function array_key_first;
use function array_reverse;
use function asort;
use function microtime;

class LastKnightStanding extends Microgame implements Listener {

	public const SHARPNESS_LEVEL = 4;

	/** @var array<int, int> */
	protected array $kills = [];

	public function getName() : string {
		return "Last Knight Standing";
	}

	public function getLevel() : Level {
		return Level::NORMAL();
	}

	public function getGameDuration() : float {
		return 22.9;
	}

	public function getRecompensePoints() : int {
		return self::DEFAULT_RECOMPENSE_POINTS;
	}

	public function start() : void {
		$this->startTime = microtime(true);
		$this->hasStarted = true;
		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);

		$helmet = VanillaItems::IRON_HELMET();
		$chestplate = VanillaItems::IRON_CHESTPLATE();
		$leggings = VanillaItems::LEATHER_PANTS();
		$boots = VanillaItems::LEATHER_BOOTS();
		$sharpness = VanillaEnchantments::SHARPNESS();
		foreach ($this->arena->getPlayers() as $player) {
			Utils::initPlayer($player);
			$sword = VanillaItems::DIAMOND_SWORD();
			$sword->setCustomName($this->plugin->getTranslator()->translate($player, "microgame.item.sword"));
			$sword->addEnchantment(new EnchantmentInstance($sharpness, self::SHARPNESS_LEVEL));
			$player->setGamemode(GameMode::ADVENTURE());
			$player->getInventory()->setItem(0, $sword);
			$player->getArmorInventory()->setItem($helmet->getArmorSlot(), $helmet);
			$player->getArmorInventory()->setItem($chestplate->getArmorSlot(), $chestplate);
			$player->getArmorInventory()->setItem($leggings->getArmorSlot(), $leggings);
			$player->getArmorInventory()->setItem($boots->getArmorSlot(), $boots);
			$player->getInventory()->setHeldItemIndex(0);
		}
		$this->arena->buildWinnersCage();
		$this->arena->buildLosersCage();
	}

	public function tick() : void {
		$timeLeft = $this->getTimeLeft();
		if ($timeLeft <= 0) {
			foreach ($this->arena->getPlayers() as $player) {
				if (!$this->isWinner($player) && !$this->isLoser($player)) {
					$this->addWinner($player);
				}
			}
			$this->arena->endCurrentMicrogame();
			return;
		}
		foreach ($this->arena->getPlayers() as $player) {
			$player->getXpManager()->setXpAndProgress((int) $timeLeft, $timeLeft / $this->getGameDuration());
		}
	}

	public function end() : void {
		$this->hasEnded = true;
		HandlerListManager::global()->unregisterAll($this);

		$players = $this->arena->getPlayers();
		$killer = null;
		$kills = $this->getKillsOrderedByHigherScore();
		if ($kills !== []) {
			$killer = $players[array_key_first($kills)] ?? null;
		}
		foreach ($players as $player) {
			if ($killer !== null) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.lastknightstanding.killer", [
						"{%player}" => $killer->getName(),
						"{%kills_count}" => $this->getKills($killer)
					]
				));
			}
			if ($this->isWinner($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.lastknightstanding.won"));
			} elseif ($this->isLoser($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.lastknightstanding.lose"));
			}
		}
	}

	public function getKills(Player $player) : int {
		return $this->kills[$player->getId()] ?? 0;
	}

	/**
	 * @return array<int, int>
	 */
	public function getKillsOrderedByHigherScore() : array {
		$array = $this->kills;
		if (asort($array) === false) {
			throw new AssumptionFailedError("Failed to sort score");
		}
		return array_reverse($array, true);
	}

	# Listener

	public function onBlockBreak(BlockBreakEvent $event) : void {
		$player = $event->getPlayer();
		if (!$this->arena->inGame($player)) return;
		$event->cancel();
	}

	public function onBlockPlace(BlockPlaceEvent $event) : void {
		$player = $event->getPlayer();
		if (!$this->arena->inGame($player)) return;
		$event->cancel();
	}

	public function onFatalDamage(EntityDamageEvent $event) : void {
		$player = $event->getEntity();
		if ($event->getFinalDamage() < $player->getHealth()) return;
		if (!$player instanceof Player) return;
		if (!$this->arena->inGame($player)) return;
		if (!$this->isWinner($player) && !$this->isLoser($player)) {
			if ($event->getCause() === EntityDamageEvent::CAUSE_VOID) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.felloffplatform"));
			} elseif ($event instanceof EntityDamageByEntityEvent) {
				$damager = $event->getDamager();
				if ($damager instanceof Player) {
					$this->kills[$damager->getId()] = $this->getKills($damager) + 1;
					$damager->sendMessage($this->plugin->getTranslator()->translate(
						$damager, "microgame.lastknightstanding.kill", [
							"{%player}" => $player->getName()
						]
					));
					$player->sendMessage($this->plugin->getTranslator()->translate(
						$player, "microgame.lastknightstanding.death", [
							"{%player}" => $damager->getName()
						]
					));
				}
			}
			$this->addLoser($player);
			$this->arena->sendToLosersCage($player);
			$event->cancel();
		}
	}
}