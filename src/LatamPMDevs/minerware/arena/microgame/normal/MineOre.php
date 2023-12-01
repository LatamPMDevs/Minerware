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
use pocketmine\block\VanillaBlocks;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\HandlerListManager;
use pocketmine\event\Listener;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\world\format\Chunk;
use function array_rand;
use function mt_rand;
use function str_replace;
use function strtolower;

class MineOre extends Microgame implements Listener {

	/**
	 * @return Block[]
	 */
	public static function getOres() : array {
		return [VanillaBlocks::COAL_ORE(), VanillaBlocks::DIAMOND_ORE(), VanillaBlocks::EMERALD_ORE(), VanillaBlocks::GOLD_ORE(), VanillaBlocks::IRON_ORE(), VanillaBlocks::LAPIS_LAZULI_ORE(), VanillaBlocks::REDSTONE_ORE()];
	}

	public const EFFICIENCY_LEVEL = 3;

	public const COBBLESTONE_LAYERS = 1;

	public const ORES_LAYERS = 5;

	protected Block $ore;

	/** @var Block[] */
	protected array $changedBlocks = [];

	/** @var array<int, int> */
	protected array $minedBlocks = [];

	public function getName() : string {
		return "Mine Ore";
	}

	public function getLevel() : Level {
		return Level::NORMAL();
	}

	public function getGameDuration() : float {
		return 18.9;
	}

	public function getRecompensePoints() : int {
		return self::DEFAULT_RECOMPENSE_POINTS;
	}

	public function start() : void {
		$ores = self::getOres();
		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
		$oreKey = array_rand($ores);
		$this->ore = $ores[$oreKey];
		unset($ores[$oreKey]);

		$map = $this->arena->getMap();
		$minPos = $map->getPlatformMinPos();
		$maxPos = $map->getPlatformMaxPos();
		$world = $this->arena->getWorld();

		$minY = $minPos->y + 1;
		$maxY = $maxPos->y + self::ORES_LAYERS + self::COBBLESTONE_LAYERS;
		for ($x = $minPos->x; $x <= $maxPos->x; ++$x) {
			for ($z = $minPos->z; $z <= $maxPos->z; ++$z) {
				$world->loadChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE);
				for ($y = $minY; $y <= $maxY; ++$y) {
					$this->changedBlocks[] = $world->getBlockAt((int) $x, (int) $y, (int) $z);
					if ($y <= ($maxY - self::COBBLESTONE_LAYERS)) {
						if (mt_rand(1, 20) === 1) {
							$block = $this->ore;
						} else {
							$block = $ores[array_rand($ores)];
						}
					} else {
						$block = VanillaBlocks::COBBLESTONE();
					}
					$world->setBlockAt((int) $x, (int) $y, (int) $z, $block, false);
				}
			}
		}

		$oreItem = $this->ore->asItem();
		$efficiency = VanillaEnchantments::EFFICIENCY();
		foreach ($this->arena->getPlayers() as $player) {
			Utils::initPlayer($player);
			$pickaxe = VanillaItems::DIAMOND_PICKAXE();
			$pickaxe->setCustomName($this->plugin->getTranslator()->translate($player, "microgame.item.pickaxe"));
			$pickaxe->addEnchantment(new EnchantmentInstance($efficiency, self::EFFICIENCY_LEVEL));
			$player->setGamemode(GameMode::SURVIVAL());
			$player->getInventory()->setItem(0, $pickaxe);
			$player->getInventory()->setItem(8, $oreItem);
			$player->getInventory()->setHeldItemIndex(0);
			$this->arena->tpSafePosition($player);
		}
		$this->arena->getWinnersCage()->set();
		$this->arena->getLosersCage()->set();
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

		$miner = null;
		$minedBlocks = 0;
		foreach ($this->losers as $loser) {
			$mB = $this->getMinedBlocks($loser);
			if ($miner === null || $mB > $minedBlocks) {
				$miner = $loser;
				$minedBlocks = $mB;
			}
		}
		$blockName = str_replace(" ", "_", strtolower($this->ore->getName()));
		$players = $this->arena->getPlayers();
		foreach ($players as $player) {
			if ($miner !== null) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.mineore.miner", [
						"{%player}" => $miner->getName(),
						"{%mined_blocks}" => $minedBlocks
					]
				));
			}
			if ($this->isWinner($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.mineore.won", [
						"{%ore}" => $this->plugin->getTranslator()->translate($player, "text.block." . $blockName)
					]
				));
			} elseif ($this->isLoser($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.mineore.lose", [
						"{%ore}" => $this->plugin->getTranslator()->translate($player, "text.block." . $blockName)
					]
				));
			}
		}
		foreach ($this->changedBlocks as $block) {
			$this->arena->getWorld()->setBlock($block->getPosition(), $block, false);
		}
		parent::end();
	}

	public function getMinedBlocks(Player $player) : int {
		return $this->minedBlocks[$player->getId()] ?? 0;
	}

	# Listener

	/**
	 * @ignoreCancelled
	 * @priority HIGH
	 */
	public function onBlockBreak(BlockBreakEvent $event) : void {
		$player = $event->getPlayer();
		if (!$this->arena->inGame($player)) return;
		if ($this->isWinner($player) || $this->isLoser($player)) {
			$event->cancel();
			return;
		}
		$block = $event->getBlock();
		$y = $this->arena->getMap()->getPlatformMinPos()->y;
		$this->minedBlocks[$player->getId()] = $this->getMinedBlocks($player) + 1;
		if ((int) $block->getPosition()->y === $y) {
			$this->changedBlocks[] = $block;
		}
		if ($block->hasSameTypeId($this->ore)) {
			$this->addWinner($player);
			$this->arena->getWinnersCage()->addPlayer($player);
			$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.mineore.oremined"));
		}
		$event->setDrops([]);
		$event->setXpDropAmount(0);
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
}