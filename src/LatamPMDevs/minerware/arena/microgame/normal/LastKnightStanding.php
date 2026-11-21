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

namespace LatamPMDevs\minerware\arena\microgame\normal;

use LatamPMDevs\minerware\arena\microgame\CancelsBuildEvents;
use LatamPMDevs\minerware\arena\microgame\Level;
use LatamPMDevs\minerware\arena\microgame\Microgame;
use LatamPMDevs\minerware\item\SpecialItems;
use LatamPMDevs\minerware\utils\Utils;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use function array_key_first;

class LastKnightStanding extends Microgame implements Listener {

	use CancelsBuildEvents;

	public const SHARPNESS_LEVEL = 4;

	/** @var array<int, int> */
	protected array $kills = [];

	public function getName() : string {
		return "Last Knight Standing";
	}

	public function getLevel() : Level {
		return Level::NORMAL;
	}

	public function getGameDuration() : float {
		return 22.9;
	}

	public function start() : void {
		$helmet = VanillaItems::IRON_HELMET();
		$chestplate = VanillaItems::IRON_CHESTPLATE();
		$leggings = VanillaItems::LEATHER_PANTS();
		$boots = VanillaItems::LEATHER_BOOTS();
		foreach ($this->arena->getPlayers() as $player) {
			Utils::initPlayer($player);
			$sword = SpecialItems::ETERNITY_SWORD($this->plugin->getTranslator()->getLanguageOf($player), self::SHARPNESS_LEVEL);
			$player->setGamemode(GameMode::ADVENTURE);
			$player->getInventory()->setItem(0, $sword);
			$player->getArmorInventory()->setItem($helmet->getArmorSlot(), $helmet);
			$player->getArmorInventory()->setItem($chestplate->getArmorSlot(), $chestplate);
			$player->getArmorInventory()->setItem($leggings->getArmorSlot(), $leggings);
			$player->getArmorInventory()->setItem($boots->getArmorSlot(), $boots);
			$player->getInventory()->setHeldItemIndex(0);
		}
		$this->arena->getLosersCage()->set();
		parent::start();
	}

	public function tick() : void {
		$timeLeft = $this->getTimeLeft();
		if ($timeLeft <= 0) {
			$this->endRound(true);
			return;
		}
		$this->updateTimeBar($timeLeft);
	}

	public function end() : void {
		$players = $this->arena->getPlayers();
		$killer = null;
		$kills = Utils::sortScoresDescending($this->kills);
		if ($kills !== []) {
			$killer = $players[array_key_first($kills)] ?? null;
		}
		foreach ($players as $player) {
			if ($killer !== null) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.topkiller", [
						"{%player}" => $killer->getName(),
						"{%kills_count}" => $this->getKills($killer)
					]
				));
			}
			$this->sendOutcome($player, "microgame.lastknightstanding.won", "microgame.failedtosurvive");
		}
		parent::end();
	}

	public function getKills(Player $player) : int {
		return $this->kills[$player->getId()] ?? 0;
	}

	# Listener

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
						$player, "microgame.youwereslain", [
							"{%player}" => $damager->getName()
						]
					));
				}
			}
			$this->lose($player);
			$event->cancel();
		}
	}
}