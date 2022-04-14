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

namespace LatamPMDevs\minerware\arena\microgame;

use LatamPMDevs\minerware\utils\Utils;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\HandlerListManager;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;

class StackBlocks extends Microgame implements Listener {

	public const STACK_SIZE = 10;

	/** @var array<int Block> */
	protected array $assignedBlock = [];

	/** @var array<int int> */
	protected array $stackedBlocks = [];

	public function getName() : string {
		return "Stack Blocks";
	}

	public function getLevel() : Level {
		return Level::NORMAL();
	}

	public function getGameDuration() : float {
		return 12.0;
	}

	public function getRecompensePoints() : int {
		return 1;
	}

	public function start() : void {
		$this->startTime = microtime(true);
		$this->hasStarted = true;
		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
		$dyeColors = Utils::getDyeColors(); // 12 Colors
		shuffle($dyeColors);
		$i = 0;
		foreach ($this->arena->getPlayers() as $player) {
			$player->getInventory()->clearAll();
			$player->getArmorInventory()->clearAll();
			$player->getCursorInventory()->clearAll();
			$player->getOffHandInventory()->clearAll();
			$player->setGamemode(GameMode::SURVIVAL());
			if ($i < count($dyeColors)) {
				$block = VanillaBlocks::WOOL()->setColor($dyeColors[$i]);
				$this->setAssignedBlock($player, $block);
				$player->getInventory()->setItem(0, $block->asItem()->setCount(20));
			}
			$i++;
		}
	}

	public function tick() : void {
		$timeLeft = $this->getTimeLeft();
		if ($timeLeft <= 0) {
			$this->end();
			return;
		}
		foreach ($this->arena->getPlayers() as $player) {
			$player->getXpManager()->setXpAndProgress((int) $timeLeft, $timeLeft / $this->getGameDuration());
		}
	}

	public function end() : void {
		$this->hasEnded = true;
		HandlerListManager::global()->unregisterAll($this);
		$this->arena->setCurrentMicrogame(null);

		$players = $this->arena->getPlayers();
		$winners = $this->getWinners();
		$winnersCount = count($winners);

		$stacker = null;
		$stackedBlocks = $this->getStackedBlocksOrderedByHigherScore();
		if ($stackedBlocks !== []) {
			$id = $stackedBlocks[array_key_first($stackedBlocks)];
			$stacker = $players[$id] ?? null;
		}
		foreach ($players as $player) {
			$player->sendMessage("\n§l§e" . $this->getName());
			if ($winnersCount <= 0) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.nowinners", [
						"{%count}" => count($players)
					]
				));
			} elseif ($winnersCount <= 3) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.winners", [
						"{%players}" => implode(", ", Utils::getPlayersNames($winners))
					]
				));
			} else {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.winners2", [
						"{%winners_count}" => $winnersCount,
						"{%players_count}" => count($players)
					]
				));
			}
			if ($stacker !== null) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.stackblocks.stacker", [
						"{%player}" => $stacker->getName(),
						"{%stack_count}" => $this->getStackedBlocks($stacker)
					]
				));
			}
			if ($this->isWinner($player)) {
				$this->arena->getPointHolder()->addPlayerPoint($player, $this->getRecompensePoints());
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.stackblocks.won", [
						"{%stack_count}" => $this->getStackedBlocks($player)
					]
				));
			} else {
				$player->sendTitle("§1§2", $this->plugin->getTranslator()->translate($player, "microgame.failed"), 0, 20, 0);
				# TODO: Loser message
			}
		}
	}

	public function getAssignedBlock(Player $player) : ?Block{
		return $this->assignedBlock[$player->getId()] ?? null;
	}

	public function setAssignedBlock(Player $player, Block $block) : void {
		$this->assignedBlock[$player->getId()] = $block;
	}

	public function getStackedBlocks(Player $player) : int {
		return $this->stackedBlocks[$player->getId()] ?? 0;
	}

	/**
	 * @return array<string, int>
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

	public function onBlockPlace(BlockPlaceEvent $event) : void {
		$player = $event->getPlayer();
		if (!$this->arena->inGame($player)) return;
		$assignedBlock = $this->getAssignedBlock($player);
		if ($assignedBlock !== null && $event->getBlock()->isSameState($assignedBlock)) {
			$size = 1;
			$lastBlockPos = $event->getBlock()->getPosition();
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