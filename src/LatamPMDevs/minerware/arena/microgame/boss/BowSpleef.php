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

namespace LatamPMDevs\minerware\arena\microgame\boss;

use LatamPMDevs\minerware\arena\Map;
use LatamPMDevs\minerware\arena\microgame\Level;
use LatamPMDevs\minerware\arena\microgame\Microgame;
use LatamPMDevs\minerware\utils\Utils;

use pocketmine\block\Block;
use pocketmine\block\TNT;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitBlockEvent;
use pocketmine\event\HandlerListManager;
use pocketmine\event\Listener;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\world\Position;

class BowSpleef extends Microgame implements Listener {

	/** @var Block[] */
	protected array $changedBlocks = [];

	public function getName() : string {
		return "Bow Spleef";
	}

	public function getLevel() : Level {
		return Level::BOSS();
	}

	public function getGameDuration() : float {
		return 120.9;
	}

	public function getRecompensePoints() : int {
		return self::BOSS_RECOMPENSE_POINTS;
	}

	public function start() : void {
		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);

		$map = $this->arena->getMap();
		$world = $this->arena->getWorld();
		$minPos = Position::fromObject($map->getPlatformMinPos(), $world);
		foreach (Map::MINI_PLATFORMS as $key => $values) {
			foreach ($values as $blockPos) {
				$this->changedBlocks[] = $world->getBlockAt((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]));
				$world->setBlockAt((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]), VanillaBlocks::AIR(), true);
			}
		}
		foreach (Utils::fill($minPos, $map->getPlatformMaxPos(), VanillaBlocks::TNT(), true) as $changedBlock) {
			$this->changedBlocks[] = $changedBlock;
		}
		$bow = VanillaItems::BOW();
		$bow->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FLAME(), 1));
		$bow->addEnchantment(new EnchantmentInstance(VanillaEnchantments::INFINITY(), 1));
		$arrow = VanillaItems::ARROW();
		foreach ($this->arena->getPlayers() as $player) {
			Utils::initPlayer($player);
			$player->setGamemode(GameMode::ADVENTURE());
			$player->getInventory()->setItem(0, $bow);
			$player->getInventory()->setItem(1, $arrow);
			$player->getInventory()->setHeldItemIndex(0);
		}
		$this->arena->buildWinnersCage();
		$this->arena->buildLosersCage();
		parent::start();
	}

	public function tick() : void {
		$timeLeft = $this->getTimeLeft();
		$players = $this->arena->getPlayers();
		if ($timeLeft <= 0) {
			foreach ($players as $player) {
				if (!$this->isLoser($player) && !$this->isWinner($player)) {
					$this->addWinner($player);
				}
			}
			$this->arena->endCurrentMicrogame();
			return;
		}
		foreach ($players as $player) {
			$player->getXpManager()->setXpAndProgress((int) $timeLeft, $timeLeft / $this->getGameDuration());
		}
	}

	public function end() : void {
		HandlerListManager::global()->unregisterAll($this);

		foreach ($this->arena->getPlayers() as $player) {
			if ($this->isWinner($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.bowspleef.won"));
			} elseif ($this->isLoser($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.bowspleef.lose"
				));
			}
		}
		foreach ($this->changedBlocks as $block) {
			$this->arena->getWorld()->setBlock($block->getPosition(), $block, true);
		}
		parent::end();
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

	public function onDamage(EntityDamageEvent $event) : void {
		$player = $event->getEntity();
		if (!$player instanceof Player) return;
		if (!$this->arena->inGame($player)) return;
		if ($this->arena->isWinner($player) || $this->arena->isLoser($player)) return;
		$event->cancel();
		$player->extinguish();
		if ($event->getCause() === EntityDamageEvent::CAUSE_VOID && !$this->isWinner($player)) {
			$this->addLoser($player);
			$this->arena->sendToLosersCage($player);
		}
	}

	/**
	 * @ignoreCancelled
	 * @priority HIGH
	 */
	public function onProjectileHitBlock(ProjectileHitBlockEvent $event) : void {
		$projectile = $event->getEntity();
		if (!$projectile instanceof Arrow) return;
		$world = $this->arena->getWorld();
		if ($projectile->getWorld() !== $world) return;
		if (!$projectile->isOnFire()) return;
		foreach ($world->getCollisionBlocks($projectile->getBoundingBox()->expandedCopy(0.25, 0.25, 0.25)) as $block) {
			if ($block instanceof TNT) {
				$block->ignite();
			}
		}
	}
}