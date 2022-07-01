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

use LatamPMDevs\minerware\arena\Map;
use LatamPMDevs\minerware\arena\microgame\Level;
use LatamPMDevs\minerware\arena\microgame\Microgame;
use LatamPMDevs\minerware\utils\Utils;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\HandlerListManager;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
use pocketmine\player\GameMode;
use pocketmine\player\Player;

class Sneaking extends Microgame implements Listener {

	/** @var Block[] */
	protected array $changedBlocks = [];

	protected float $totalSneakedDistance = 0;

	public function getName() : string {
		return "Sneaking";
	}

	public function getLevel() : Level {
		return Level::NORMAL();
	}

	public function getGameDuration() : float {
		return 12.9;
	}

	public function getRecompensePoints() : int {
		return self::DEFAULT_RECOMPENSE_POINTS;
	}

	public function start() : void {
		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);

		$map = $this->arena->getMap();
		$minPos = $map->getPlatformMinPos();
		$world = $this->arena->getWorld();
		foreach (Map::MINI_PLATFORMS as $key => $value) {
			foreach (Map::MINI_PLATFORMS[$key] as $blockPos) {
				$this->changedBlocks[] = $world->getBlockAt((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]));
				$world->setBlockAt((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]), VanillaBlocks::AIR(), true);
			}
		}

		foreach ($this->arena->getPlayers() as $player) {
			Utils::initPlayer($player);
			$player->setGamemode(GameMode::ADVENTURE());
			$player->getInventory()->setHeldItemIndex(0);
			$player->sendTitle("§1§2", $this->plugin->getTranslator()->translate($player, "microgame.sneaking.start"), 10, 20, 10);
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
		foreach ($this->arena->getPlayers() as $player) {
			$player->getXpManager()->setXpAndProgress((int) $timeLeft, $timeLeft / $this->getGameDuration());
			if (!$this->isLoser($player) &&
				!$player->isSneaking() &&
				($timeLeft <= ($this->getGameDuration() - 1)
			)) {
				$this->addLoser($player);
				$this->arena->getLosersCage()->addPlayer($player);
			}
		}
	}

	public function end() : void {
		HandlerListManager::global()->unregisterAll($this);

		$players = $this->arena->getPlayers();
		foreach ($players as $player) {
			$player->sendMessage($this->plugin->getTranslator()->translate(
				$player, "microgame.sneaking.total", [
					"{%distance}" => (int) $this->totalSneakedDistance
				]
			));
			if ($this->isWinner($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.sneaking.won"));
			} elseif ($this->isLoser($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.sneaking.lose"));
			}
		}
		foreach ($this->changedBlocks as $block) {
			$this->arena->getWorld()->setBlock($block->getPosition(), $block, false);
		}
		parent::end();
	}

	public function getTotalSneakedDistance() : float {
		return $this->totalSneakedDistance;
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
		$event->cancel();
		if ($event->getCause() === EntityDamageEvent::CAUSE_VOID && !$this->isWinner($player)) {
			$this->addLoser($player);
			$this->arena->getLosersCage()->addPlayer($player);
			$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.felloffplatform"));
		}
	}

	/**
	 * @ignoreCancelled
	 * @priority HIGH
	 */
	public function onToggleSneak(PlayerToggleSneakEvent $event) : void {
		$player = $event->getPlayer();
		if (!$this->arena->inGame($player)) return;
		if ($this->isLoser($player) || $this->isWinner($player)) return;
		if (!$event->isSneaking()) {
			$this->addLoser($player);
			$this->arena->getLosersCage()->addPlayer($player);
		}
	}

	/**
	 * @ignoreCancelled
	 * @priority HIGH
	 */
	public function onMove(PlayerMoveEvent $event) : void {
		$player = $event->getPlayer();
		if (!$this->arena->inGame($player)) return;
		if ($this->isLoser($player) || $this->isWinner($player)) return;
		$this->totalSneakedDistance += $event->getFrom()->distance($event->getTo());
	}
}