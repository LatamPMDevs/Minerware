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

use InvalidArgumentException;
use LatamPMDevs\minerware\arena\Map;
use LatamPMDevs\minerware\arena\microgame\Level;
use LatamPMDevs\minerware\arena\microgame\Microgame;
use LatamPMDevs\minerware\entity\object\FallingBlock;
use LatamPMDevs\minerware\utils\Utils;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\HandlerListManager;
use pocketmine\event\Listener;
use pocketmine\math\Vector2;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use function array_rand;
use function count;
use function is_array;
use funcion morton2d_encode;

class PlatformPlummet extends Microgame implements Listener {

	public function platformHash(int $x, int $z) : int {
		return morton2d_encode($x, $z);
	}

	public function getCorrespondingBlock(int $phase) : Block {
		return match ($phase) {
			self::QUARTZ_PHASE => VanillaBlocks::QUARTZ(),
			self::POLISHED_DIORITE_PHASE => VanillaBlocks::POLISHED_DIORITE(),
			self::DIORITE_PHASE => VanillaBlocks::DIORITE(),
			self::POLISHED_ANDESITE_PHASE => VanillaBlocks::POLISHED_ANDESITE(),
			self::ANDESITE_PHASE => VanillaBlocks::ANDESITE(),
			self::COBBLESTONE_PHASE => VanillaBlocks::COBBLESTONE(),
			default => VanillaBlocks::AIR()
		};
	}

	public function getNextPhase(int $phase) : int {
		return match ($phase) {
			self::QUARTZ_PHASE => self::POLISHED_DIORITE_PHASE,
			self::POLISHED_DIORITE_PHASE => self::DIORITE_PHASE,
			self::POLISHED_ANDESITE_PHASE => self::ANDESITE_PHASE,
			self::ANDESITE_PHASE => self::COBBLESTONE_PHASE,
			default => self::AIR_PHASE
		};
	}

	public const NORMAL_PLATFORM_SIZE = 2;
	public const UNBREAKABLE_PLATFORMS = 8;

	public const QUARTZ_PHASE = 0;
	public const POLISHED_DIORITE_PHASE = 1;
	public const DIORITE_PHASE = 2;

	public const POLISHED_ANDESITE_PHASE = 3;
	public const ANDESITE_PHASE = 4;
	public const COBBLESTONE_PHASE = 5;

	public const AIR_PHASE = 6;

	public const INITIAL_PHASES = [self::QUARTZ_PHASE, self::POLISHED_ANDESITE_PHASE];

	/** @var Block[] */
	protected array $changedBlocks = [];

	/** @var array<int, Vector2[]> */
	protected array $platforms = [];

	/** @var array<int, Vector2[]> */
	protected array $unbreakablePlatforms = [];

	/** @var array<int, Vector2[]> */
	protected array $breakablePlatforms = [];

	/** @var array<int, int> */
	protected array $platformsPhase = [];

	public function getName() : string {
		return "Platform Plummet";
	}

	public function getLevel() : Level {
		return Level::NORMAL();
	}

	public function getGameDuration() : float {
		return 20.9;
	}

	public function getRecompensePoints() : int {
		return self::DEFAULT_RECOMPENSE_POINTS;
	}

	public function getPlatformSize() : int {
		return self::NORMAL_PLATFORM_SIZE;
	}

	public function start() : void {
		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);

		$map = $this->arena->getMap();
		$minPos = $map->getPlatformMinPos();
		$maxPos = $map->getPlatformMaxPos();
		$world = $this->arena->getWorld();
		$this->platforms = Utils::chunkVector2(
			new Vector2($minPos->x, $minPos->z),
			new Vector2($maxPos->x, $maxPos->z),
			self::NORMAL_PLATFORM_SIZE
		);
		$this->breakablePlatforms = $this->platforms;

		foreach (Map::MINI_PLATFORMS as $key => $value) {
			foreach (Map::MINI_PLATFORMS[$key] as $blockPos) {
				$this->changedBlocks[] = $world->getBlockAt((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]));
				$world->setBlockAt((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]), VanillaBlocks::AIR(), true);
			}
		}
		$platformsPerLine = ($maxPos->x - $minPos->x + 1) / $this->getPlatformSize();
		foreach (array_rand($this->platforms, self::UNBREAKABLE_PLATFORMS) as $platformHash) {
			unset($this->breakablePlatforms[$platformHash]);
			$this->unbreakablePlatforms[$platformHash] = $this->platforms[$platformHash];
		}
		$currentPhase = self::QUARTZ_PHASE;
		$i = 1;
		foreach ($this->platforms as $platformHash => $positions) { //phase assigment
			$this->platformsPhase[$platformHash] = $currentPhase;
			$block = self::getCorrespondingBlock($currentPhase);
			foreach ($positions as $vec2) {
				$pos = $minPos->add($vec2->x, 0, $vec2->y);
				$this->changedBlocks[] = $world->getBlock($pos);
				$world->setBlock($pos, $block);
			}
			if (($i % $platformsPerLine) === 0) {
				#The block is not changed
			} elseif ($currentPhase === self::QUARTZ_PHASE) {
				$currentPhase = self::POLISHED_ANDESITE_PHASE;
			} else {
				$currentPhase = self::QUARTZ_PHASE;
			}
			$i++;
		}

		foreach ($this->arena->getPlayers() as $player) {
			Utils::initPlayer($player);
			$player->setGamemode(GameMode::ADVENTURE());
			$player->getInventory()->setHeldItemIndex(0);
		}
		$this->arena->buildWinnersCage();
		$this->arena->buildLosersCage();
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
		$platformCount = count($this->breakablePlatforms);
		if ($platformCount !== 0) {
			$platforms = array_rand($this->breakablePlatforms, min(4, $platformCount));
			if (is_array($platforms)) {
				foreach ($platforms as $platformHash) {
					$this->tickPlatform($platformHash);
				}
			} else {
				$this->tickPlatform($platforms);
			}
		}
		foreach ($this->arena->getPlayers() as $player) {
			$player->getXpManager()->setXpAndProgress((int) $timeLeft, $timeLeft / $this->getGameDuration());
		}
	}

	public function tickPlatform(int $platformHash) : void {
		if (!isset($this->breakablePlatforms[$platformHash])) {
			throw new InvalidArgumentException("Platform not found");
		}
		$oldPhase = $this->getPhase($platformHash);
		if ($oldPhase === self::AIR_PHASE) {
			throw new InvalidArgumentException("Platform cannot be ticked");
		}
		$world = $this->arena->getWorld();
		$oldBlock = self::getCorrespondingBlock($oldPhase);
		$nextPhase = self::getNextPhase($oldPhase);
		$nextBlock = self::getCorrespondingBlock($nextPhase);
		$isLastPhase = $nextPhase === self::AIR_PHASE;
		$minPos = $this->arena->getMap()->getPlatformMinPos();
		foreach ($this->breakablePlatforms[$platformHash] as $pos) {
			$world->setBlockAt((int) ($minPos->x + $pos->x), (int) $minPos->y, (int) ($minPos->z + $pos->y), $nextBlock);
			if ($isLastPhase) {
				$fallingBlock = new FallingBlock(
					new Location($minPos->x + $pos->x, $minPos->y, $minPos->z + $pos->y, $world, 0.0, 0.0),
					$oldBlock
				);
				$fallingBlock->setMaxTicksOfLife(30);
				$fallingBlock->spawnToAll();
			}
		}
		if ($isLastPhase) {
			unset($this->breakablePlatforms[$platformHash]);
		}
		$this->platformsPhase[$platformHash] = $nextPhase;
	}

	public function end() : void {
		HandlerListManager::global()->unregisterAll($this);

		$players = $this->arena->getPlayers();
		foreach ($players as $player) {
			if ($this->isWinner($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.platformplummet.won"));
			} elseif ($this->isLoser($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.platformplummet.lose"));
			}
		}
		foreach ($this->changedBlocks as $block) {
			$this->arena->getWorld()->setBlock($block->getPosition(), $block, false);
		}
		parent::end();
	}

	public function getPlatforms() : array {
		return $this->platforms;
	}

	public function getUnbreakablePlatforms() : array {
		return $this->unbreakablePlatforms;
	}

	public function getBreakablePlatforms() : array {
		return $this->breakablePlatforms;
	}

	public function getPhase(int $platformHash) : int {
		return $this->platformsPhase[$platformHash] ?? self::AIR_PHASE;
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
			$this->arena->sendToLosersCage($player);
			$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.platformplummet.crumble"));
		}
	}
}