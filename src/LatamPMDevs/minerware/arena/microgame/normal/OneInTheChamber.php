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

use LatamPMDevs\minerware\arena\microgame\Level;
use LatamPMDevs\minerware\arena\microgame\Microgame;
use LatamPMDevs\minerware\utils\Utils;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\Listener;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use function array_key_first;

class OneInTheChamber extends Microgame implements Listener {

	/** @var array<int, int> */
	protected array $kills = [];

	public function getName() : string {
		return "One In The Chamber";
	}

	public function getLevel() : Level {
		return Level::NORMAL;
	}

	public function getGameDuration() : float {
		return 20.9;
	}

	public function getRecompensePoints() : int {
		return self::DEFAULT_RECOMPENSE_POINTS;
	}

	public function start() : void {
		$map = $this->arena->getMap();
		$minPos = $map->getPlatformMinPos();
		$world = $this->arena->getWorld();
		$this->setMiniPlatforms(VanillaBlocks::AIR(), true);

		foreach ($this->arena->getPlayers() as $player) {
			Utils::initPlayer($player);
			$player->setGamemode(GameMode::ADVENTURE);
			$player->getInventory()->setItem(0, VanillaItems::BOW());
			$player->getInventory()->setItem(1, VanillaItems::WOODEN_SWORD());
			$player->getInventory()->setItem(8, VanillaItems::ARROW());
			$player->getInventory()->setHeldItemIndex(0);
		}
		if (!$this->arena->areInvisibleBlocksSet()) {
			$this->arena->buildInvisibleBlocks();
		}
		$this->arena->getLosersCage()->set();
		parent::start();
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
		$this->updateTimeBar($timeLeft);
	}

	public function end() : void {
		$players = $this->arena->getPlayers();
		$killer = null;
		$kills = $this->getKillsOrderedByHigherScore();
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
			if ($this->isWinner($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.oneinthechamber.won"));
			} elseif ($this->isLoser($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.failedtosurvive"));
			}
		}
		parent::end();
	}

	public function getKills(Player $player) : int {
		return $this->kills[$player->getId()] ?? 0;
	}

	/**
	 * @return array<int, int>
	 */
	public function getKillsOrderedByHigherScore() : array {
		return Utils::sortScoresDescending($this->kills);
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
		if (!$player instanceof Player) return;
		if (!$this->arena->inGame($player)) return;
		if (!$this->isWinner($player) && !$this->isLoser($player)) {
			$fatal = $player->getHealth() <= $event->getFinalDamage();
			if ($event->getCause() === EntityDamageEvent::CAUSE_VOID && $fatal) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.felloffplatform"));
			} elseif ($event instanceof EntityDamageByEntityEvent) {
				$damager = $event->getDamager();
				if ($damager instanceof Player) {
					if ($event instanceof EntityDamageByChildEntityEvent &&
						$event->getChild() instanceof Arrow
					) {
						$fatal = true;
					}
					if ($fatal) {
						$this->kills[$damager->getId()] = $this->getKills($damager) + 1;
						$damager->sendMessage($this->plugin->getTranslator()->translate(
							$damager, "microgame.oneinthechamber.kill", [
								"{%player}" => $player->getName()
							]
						));
						$player->sendMessage($this->plugin->getTranslator()->translate(
							$player, "microgame.youwereslain", [
								"{%player}" => $damager->getName()
							]
						));
						$arrow = VanillaItems::ARROW();
						if (!$damager->getInventory()->contains($arrow)) {
							$damager->getInventory()->setItem(8, $arrow);
						} else {
							$damager->getInventory()->addItem($arrow);
						}
					}
				}
			} elseif ($fatal) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.oneinthechamber.diedincombat"));
			}
			if ($fatal) {
				$this->addLoser($player);
				$this->arena->getLosersCage()->addPlayer($player);
				$event->cancel();
			}
		}
	}

	public function onShootBow(EntityShootBowEvent $event) : void {
		$player = $event->getEntity();
		if (!$player instanceof Player) return;
		if (!$this->arena->inGame($player)) return;
		if (!$this->isWinner($player) && !$this->isLoser($player)) {
			$projectile = $event->getProjectile();
			if ($projectile instanceof Arrow) {
				$projectile->setPickupMode(Arrow::PICKUP_NONE);
			}
		}
	}
}