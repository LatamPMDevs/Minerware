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

use pocketmine\block\VanillaBlocks;
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
use pocketmine\world\Position;
use function array_key_first;
use function array_rand;
use function array_reverse;
use function asort;
use function in_array;
use function microtime;

class StandOnDiamond extends Microgame implements Listener {

	public const DIAMOND_PLATFORMS = 4;

	public const KNOCKBACK_LEVEL = 2;

	public const FLOOR_BREAK_AT = 3;

	/** @var Block[] */
	protected array $changedBlocks = [];

	/** @var array<int, int> */
	protected array $hitsCount = [];

	/** @var Int[] */
	protected array $diamondPlatforms = [];

	protected bool $isFloorBroken = false;

	public function getName() : string {
		return "Stand on Diamond";
	}

	public function getLevel() : Level {
		return Level::NORMAL();
	}

	public function getGameDuration() : float {
		return 16.0;
	}

	public function getRecompensePoints() : int {
		return self::DEFAULT_RECOMPENSE_POINTS;
	}

	public function start() : void {
		$this->startTime = microtime(true);
		$this->hasStarted = true;
		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);

		$map = $this->arena->getMap();
		$minPos = $map->getPlatformMinPos();
		$world = $this->arena->getWorld();
		foreach (array_rand(Map::MINI_PLATFORMS, self::DIAMOND_PLATFORMS) as $key) {
			$this->diamondPlatforms[] = $key;
			foreach (Map::MINI_PLATFORMS[$key] as $blockPos) {
				$this->changedBlocks[] = $world->getBlockAt((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]));
				$world->setBlockAt((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]), VanillaBlocks::DIAMOND(), false);
			}
		}

		$knockback = VanillaEnchantments::KNOCKBACK();
		foreach ($this->arena->getPlayers() as $player) {
			Utils::initPlayer($player);
			$stick = VanillaItems::STICK();
			$stick->setCustomName($this->plugin->getTranslator()->translate($player, "microgame.item.powerstick"));
			$stick->addEnchantment(new EnchantmentInstance($knockback, self::KNOCKBACK_LEVEL));
			$player->setGamemode(GameMode::ADVENTURE());
			$player->getInventory()->setItem(0, $stick);
			$player->getInventory()->setHeldItemIndex(0);
		}
		$this->arena->buildWinnersCage();
		$this->arena->buildLosersCage();
	}

	public function tick() : void {
		$timeLeft = $this->getTimeLeft();
		if ($timeLeft <= 0) {
			foreach ($this->arena->getPlayers() as $player) {
				if (!$this->isLoser($player)) {
					$this->addWinner($player);
				}
			}
			$this->arena->endCurrentMicrogame();
			return;
		}
		if ($timeLeft <= self::FLOOR_BREAK_AT && !$this->isFloorBroken) {
			$this->breakFloor();
		}
		foreach ($this->arena->getPlayers() as $player) {
			$player->getXpManager()->setXpAndProgress((int) $timeLeft, $timeLeft / $this->getGameDuration());
		}
	}

	public function end() : void {
		$this->hasEnded = true;
		HandlerListManager::global()->unregisterAll($this);

		$players = $this->arena->getPlayers();
		$hits = $this->getPlayersHitsOrderedByHigherScore();
		$hitter = null;
		if ($hits !== []) {
			$id = $stackedBlocks[array_key_first($stackedBlocks)];
			$stacker = $players[$id] ?? null;
		}
		foreach ($players as $player) {
			if ($hitter !== null) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.standondiamond.hitscount", [
						"{%player}" => $hitter->getName(),
						"{%hits_count}" => $this->getHits($player)
					]
				));
			}
			if ($this->isWinner($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.standondiamond.won"));
			} elseif ($this->isLoser($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.standondiamond.lose"));
			}
		}
		foreach ($this->changedBlocks as $block) {
			$this->arena->getWorld()->setBlock($block->getPosition(), $block, false);
		}
	}

	public function isFloorBroken() : bool {
		return $this->isFloorBroken;
	}

	public function breakFloor() : bool {
		if (!$this->isFloorBroken) {
			$map = $this->arena->getMap();
			$world = $this->arena->getWorld();
			$minPos = Position::fromObject($map->getPlatformMinPos(), $world);
			$maxPos = Position::fromObject($map->getPlatformMaxPos(), $world);
			foreach (Map::MINI_PLATFORMS as $key => $value) {
				if (!in_array($key, $this->diamondPlatforms, true)) {
					foreach (Map::MINI_PLATFORMS[$key] as $blockPos) {
						$this->changedBlocks[] = $world->getBlockAt((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]));
						$world->setBlockAt((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]), VanillaBlocks::AIR(), true);
					}
				}
			}
			foreach (Utils::fill($minPos, $maxPos, VanillaBlocks::AIR(), true) as $changedBlock) {
				$this->changedBlocks[] = $changedBlock;
			}
			$this->isFloorBroken = true;
			return true;
		}
		return false;
	}

	public function getHits(Player $player) : int {
		return $this->hitsCount[$player->getId()] ?? 0;
	}

	/**
	 * @return array<int, int>
	 */
	public function getPlayersHitsOrderedByHigherScore() : array {
		$array = $this->hitsCount;
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

	public function onDamage(EntityDamageEvent $event) : void {
		$player = $event->getEntity();
		if (!$player instanceof Player) return;
		if (!$this->arena->inGame($player)) return;
		if ($event instanceof EntityDamageByEntityEvent) {
			$event->setBaseDamage(0);
			return;
		}
		$event->cancel();
		if ($event->getCause() === EntityDamageEvent::CAUSE_VOID && !$this->isWinner($player)) {
			$this->addLoser($player);
			$this->arena->sendToLosersCage($player);
			$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.felloffplatform"));
		}
	}
}