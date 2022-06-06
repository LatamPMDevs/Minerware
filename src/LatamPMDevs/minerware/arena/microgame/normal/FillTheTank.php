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


use IvanCraft623\fakeblocks\FakeBlock;
use IvanCraft623\fakeblocks\FakeBlockManager;

use pocketmine\block\Block;
use pocketmine\block\Water;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\HandlerListManager;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\LiquidBucket;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\world\Position;
use function morton3d_encode;
use function mt_rand;

class FillTheTank extends Microgame implements Listener {

	/** Should this be configurable? */
	public const TANK_WATER_Y_POS = 3;
	public const TANK_DEPT = 2;

	public const WATER_PLATFORM_SIZE = 3;

	/** @var Block[] */
	protected array $changedBlocks = [];

	/** @var array<int, Vector3> */
	protected array $waterPlatform = [];

	protected Vector3 $tankPosition;

	protected FakeBlockManager $fakeblockManeger;

	/** @var FakeBlock[] */
	protected array $tankFakeblocks;

	/** @var array<int, int> */
	protected array $filled = [];

	public function getName() : string {
		return "Fill the Tank";
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

	public function addWinner(Player $player) : void {
		parent::addWinner($player);
		foreach ($this->arena->getPlayers() as $pl) {
			if (!$player->canSee($pl)) {
				$player->showPlayer($pl);
			}
		}
	}

	public function addLoser(Player $player) : void {
		parent::addLoser($player);
		foreach ($this->arena->getPlayers() as $pl) {
			if (!$player->canSee($pl)) {
				$player->showPlayer($pl);
			}
		}
	}

	public function start() : void {
		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
		$this->fakeblockManeger = FakeBlockManager::getInstance();

		$map = $this->arena->getMap();
		$minPos = $map->getPlatformMinPos();
		$maxPos = $map->getPlatformMaxPos();
		$world = $this->arena->getWorld();

		foreach (Map::MINI_PLATFORMS as $key => $value) {
			foreach (Map::MINI_PLATFORMS[$key] as $blockPos) {
				$this->changedBlocks[] = $world->getBlockAt((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]));
				$world->setBlockAt((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]), VanillaBlocks::AIR(), true);
			}
		}
		foreach (Utils::fill(Position::fromObject($minPos->down(), $world), $maxPos->down(), VanillaBlocks::STONE()) as $changedBlock) {
			$this->changedBlocks[] = $changedBlock;
		}

		#Water platform
		$waterPos = new Position(
			mt_rand($minPos->x + 1, $maxPos->x - (self::WATER_PLATFORM_SIZE + 1)),
			(int) $maxPos->y,
			mt_rand($minPos->z + 1, $maxPos->z - (self::WATER_PLATFORM_SIZE + 1)),
			$world
		);
		$waterSize = self::WATER_PLATFORM_SIZE - 1;
		foreach (Utils::fill($waterPos, $waterPos->add($waterSize, 0, $waterSize), VanillaBlocks::WATER()) as $changedBlock) {
			$this->changedBlocks[] = $changedBlock;
			$pos = $changedBlock->getPosition();
			$this->waterPlatform[morton3d_encode($pos->x, $pos->y, $pos->z)] = $pos;
		}

		#Tank
		do {
			$this->tankPosition = new Position(
				mt_rand($minPos->x + 1, $maxPos->x - 1),
				(int) ($maxPos->y + 1),
				mt_rand($minPos->z + 1, $maxPos->z - 1),
				$world
			);
		} while (isset($this->waterPlatform[morton3d_encode($this->tankPosition->x, $this->tankPosition->y - 1, $this->tankPosition->z)]));
		foreach ($this->buildTank() as $changedBlock) {
			$this->changedBlocks[] = $changedBlock;
		}

		#FakeBlocks
		for ($i=0; $i < self::TANK_DEPT; $i++) { 
			$this->tankFakeblocks[$i] = $this->fakeblockManeger->create(
				VanillaBlocks::WATER(),
				new Position(
					$this->tankPosition->x,
					$this->tankPosition->y + self::TANK_WATER_Y_POS + $i,
					$this->tankPosition->z,
					$world
				)
			);
		}

		$players = $this->arena->getPlayers();
		foreach ($players as $player) {
			Utils::initPlayer($player);
			$player->setGamemode(GameMode::SURVIVAL());
			$player->getInventory()->setItem(0, VanillaItems::BUCKET());
			$player->getInventory()->setHeldItemIndex(0);
			foreach ($players as $pl) {
				$player->hidePlayer($pl);
			}
			$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.fillthetank.start"));
		}
		$this->arena->buildWinnersCage();
		$this->arena->buildLosersCage();
		parent::start();
	}

	/* This is a pretty horrible hack,
	 * in the future we plan to implement a building system
	 */
	private function buildTank() : array {
		$changedBlocks = [];

		$blocks = [
			[VanillaBlocks::FURNACE(), 0, 0, 0],
			[VanillaBlocks::COBBLESTONE_WALL(), 0, 1, 0],
			[VanillaBlocks::COBBLESTONE_WALL(), 0, 2, 0],
			[VanillaBlocks::GLASS(), 1, 3, 0],
			[VanillaBlocks::GLASS(), 0, 3, 1],
			[VanillaBlocks::GLASS(), -1, 3, 0],
			[VanillaBlocks::GLASS(), 0, 3, -1],
			[VanillaBlocks::GLASS(), 1, 4, 0],
			[VanillaBlocks::GLASS(), 0, 4, 1],
			[VanillaBlocks::GLASS(), -1, 4, 0],
			[VanillaBlocks::GLASS(), 0, 4, -1]
		];
		$world = $this->arena->getWorld();
		foreach ($blocks as $blockData) {
			$pos = $this->tankPosition->add($blockData[1], $blockData[2], $blockData[3]);
			$changedBlocks[] = $world->getBlock($pos);
			$world->setBlock($pos, $blockData[0]);
		}
		return $changedBlocks;
	}

	public function tick() : void {
		$timeLeft = $this->getTimeLeft();
		if ($timeLeft <= 0) {
			foreach ($this->arena->getPlayers() as $player) {
				if (!$this->isWinner($player) && !$this->isLoser($player)) {
					$this->addLoser($player);
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
		HandlerListManager::global()->unregisterAll($this);

		$players = $this->arena->getPlayers();
		foreach ($players as $player) {
			if ($this->isWinner($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.fillthetank.won"));
			} elseif ($this->isLoser($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.fillthetank.lose"));
			}
		}
		foreach ($this->changedBlocks as $block) {
			$this->arena->getWorld()->setBlock($block->getPosition(), $block, false);
		}
		foreach ($this->tankFakeblocks as $fakeblock) {
			$this->fakeblockManeger->destroy($fakeblock);
		}
		parent::end();
	}

	public function getWaterPlatform() : array {
		return $this->waterPlatform;
	}

	public function getTank() : Vector3 {
		return $this->tankPosition;
	}

	public function getFilled(Player $player) : int {
		return $this->filled[$player->getId()] ?? 0;
	}

	private function fill(Player $player) : void {
		$filled = $this->getFilled($player);
		if (isset($this->tankFakeblocks[$filled])) {
			$this->tankFakeblocks[$filled]->addViewer($player);
			$this->filled[$player->getId()] = $filled + 1;
		}
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
			$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.felloffplatform"));
		}
	}

	/**
	 * @ignoreCancelled
	 * @priority HIGH
	 */
	public function onInteract(PlayerInteractEvent $event) : void {
		$player = $event->getPlayer();
		if (!$this->arena->inGame($player)) return;
		if ($this->isWinner($player) || $this->isLoser($player)) return;
		$item = $event->getItem();
		$isTank = $event->getBlock()->getPosition()->equals($this->tankPosition);
		if ($item instanceof LiquidBucket && $item->getLiquid() instanceof Water) {
			if ($isTank) {
				$this->fill($player);
				$filled = $this->getFilled($player);
				$player->getInventory()->setItemInHand(VanillaItems::BUCKET());
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.fillthetank.filled", [
						"{%filled}" => $filled,
						"{%tank_dept}" => self::TANK_DEPT
					]
				));
				if ($filled >= self::TANK_DEPT) {
					$this->addWinner($player);
					$this->arena->sendToWinnersCage($player);
				}
			}
			$event->cancel();
		} elseif ($isTank) {
			$event->cancel();
		}
	}
}