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

use pocketmine\block\Block;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\HandlerListManager;
use pocketmine\event\Listener;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use function array_key_first;
use function array_reverse;
use function asort;
use function count;
use function shuffle;

class StackBlocks extends Microgame implements Listener {

	public const STACK_SIZE = 10;

	/** @var Block[] */
	protected array $changedBlocks = [];

	/** @var array<int, Block> */
	protected array $assignedBlock = [];

	/** @var array<int, int> */
	protected array $stackedBlocks = [];

	public function getName() : string {
		return "Stack Blocks";
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
		$dyeColors = DyeColor::getAll();
		shuffle($dyeColors);
		$i = 0;
		foreach ($this->arena->getPlayers() as $player) {
			Utils::initPlayer($player);
			$player->setGamemode(GameMode::SURVIVAL());
			if ($i < count($dyeColors)) {
				$block = VanillaBlocks::WOOL()->setColor($dyeColors[$i]);
				$this->setAssignedBlock($player, $block);
				$player->getInventory()->setItem(0, $block->asItem()->setCount(self::STACK_SIZE * 2));
				$player->getInventory()->setHeldItemIndex(0);
			}
			$i++;
		}
		if (!$this->arena->areInvisibleBlocksSet()) {
			$this->arena->buildInvisibleBlocks();
		}
		parent::start();
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
		$stacker = null;
		$stackedBlocks = $this->getStackedBlocksOrderedByHigherScore();
		if ($stackedBlocks !== []) {
			$stacker = $players[array_key_first($stackedBlocks)] ?? null;
		}
		foreach ($players as $player) {
			if ($stacker !== null) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.stackblocks.stacker", [
						"{%player}" => $stacker->getName(),
						"{%stack_count}" => $this->getStackedBlocks($stacker)
					]
				));
			}
			if ($this->isWinner($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.stackblocks.won", [
						"{%stack_count}" => $this->getStackedBlocks($player)
					]
				));
			} elseif ($this->isLoser($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.stackblocks.lose", [
						"{%stack_count}" => self::STACK_SIZE
					]
				));
			}
		}
		foreach ($this->changedBlocks as $block) {
			$this->arena->getWorld()->setBlock($block->getPosition(), $block, false);
		}
		parent::end();
	}

	public function getAssignedBlock(Player $player) : ?Block {
		return $this->assignedBlock[$player->getId()] ?? null;
	}

	public function setAssignedBlock(Player $player, Block $block) : void {
		$this->assignedBlock[$player->getId()] = $block;
	}

	public function getStackedBlocks(Player $player) : int {
		return $this->stackedBlocks[$player->getId()] ?? 0;
	}

	/**
	 * @return array<int, int>
	 */
	public function getStackedBlocksOrderedByHigherScore() : array {
		$array = $this->stackedBlocks;
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

	/**
	 * @ignoreCancelled
	 * @priority MONITOR
	 */
	public function onBlockPlace(BlockPlaceEvent $event) : void {
		$player = $event->getPlayer();
		if (!$this->arena->inGame($player)) return;
		$this->changedBlocks[] = $event->getBlockAgainst();
		$assignedBlock = $this->getAssignedBlock($player);
		if ($assignedBlock !== null && $event->getBlockAgainst()->isSameState($assignedBlock)) {
			$size = 1;
			$lastBlockPos = $event->getBlockAgainst()->getPosition();
			$world = $this->arena->getWorld();
			while ($world->getBlock($lastBlockPos->subtract(0, 1, 0))->isSameState($assignedBlock)) {
				$size++;
				$lastBlockPos = $lastBlockPos->subtract(0, 1, 0);
			}
			if ($size > $this->getStackedBlocks($player)) {
				$this->stackedBlocks[$player->getId()] = $size;
			}
			if ($size < self::STACK_SIZE) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.stackblocks.stack", [
						"{%stacked}" => $size,
						"{%stack_size}" => self::STACK_SIZE
					]
				));
			} elseif ($size === self::STACK_SIZE) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.stackblocks.success"));
				if (!$this->isWinner($player)) {
					$this->addWinner($player);
				}
			} elseif ($size === self::STACK_SIZE + 1) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.stackblocks.enough"));
			} elseif ($size === self::STACK_SIZE + 2) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.stackblocks.nomore"));
			} elseif ($size === self::STACK_SIZE + 3) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.stackblocks.nomoremessages"));
			} elseif ($size === self::STACK_SIZE + 5) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.stackblocks.pleasestop"));
			} elseif ($size === self::STACK_SIZE + 6) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.stackblocks.alert"));
			} elseif ($size === ((self::STACK_SIZE * 2) - 1)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.stackblocks.brokesystem"));
			}
		}
	}

	public function onDamage(EntityDamageEvent $event) : void {
		$player = $event->getEntity();
		if (!$player instanceof Player) return;
		if (!$this->arena->inGame($player)) return;
		$event->cancel();
	}
}