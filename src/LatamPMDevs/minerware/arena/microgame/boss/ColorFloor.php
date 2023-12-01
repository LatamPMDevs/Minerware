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
use LatamPMDevs\minerware\entity\projectile\ColorMissile;
use LatamPMDevs\minerware\utils\Utils;

use pocketmine\block\Block;
use pocketmine\block\StainedHardenedClay;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\projectile\SplashPotion;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\HandlerListManager;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Hoe;
//use pocketmine\item\ItemFactory;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\Position;
use pocketmine\world\sound\ThrowSound;
use function array_reverse;
use function array_slice;
use function asort;
use function count;
use function shuffle;

class ColorFloor extends Microgame implements Listener {

	public const COLOR_MISSILE_COUNT = 2;

	/** @var Block[] */
	protected array $changedBlocks = [];

	/** @var array<int, DyeColor> */
	protected array $assignedColor = [];

	/** @var array<int, Player> */
	protected array $assignedPlayer = [];

	/** @var array<int, int> */
	protected array $coloredBlocksCount = [];

	protected int $totalColoredBlocks = 0;

	protected int $blocksCount = 0;

	public function getName() : string {
		return "Color Floor";
	}

	public function getLevel() : Level {
		return Level::BOSS();
	}

	public function getGameDuration() : float {
		return 60.9;
	}

	public function getRecompensePoints() : int {
		return self::BOSS_RECOMPENSE_POINTS;
	}

	public function start() : void {
		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);

		$map = $this->arena->getMap();
		$world = $this->arena->getWorld();
		$minPos = Position::fromObject($map->getPlatformMinPos(), $world);
		$maxPos = Position::fromObject($map->getPlatformMaxPos(), $world);
		foreach (Map::MINI_PLATFORMS as $key => $values) {
			foreach ($values as $blockPos) {
				$this->changedBlocks[] = $world->getBlockAt((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]));
				$world->setBlockAt((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]), VanillaBlocks::AIR(), true);
			}
		}
		foreach (Utils::fill($minPos, $maxPos, VanillaBlocks::STAINED_CLAY(), false) as $changedBlock) {
			$this->blocksCount++;
			$this->changedBlocks[] = $changedBlock;
		}

		$dyeColors = DyeColor::getAll();
		shuffle($dyeColors);
		$i = 0;
		foreach ($this->arena->getPlayers() as $player) {
			Utils::initPlayer($player);
			$player->setGamemode(GameMode::SURVIVAL());
			if ($dyeColors[$i]->equals(DyeColor::WHITE())) {
				$i++; // Players must not have the color white
			}
			if ($i < count($dyeColors)) {
				$this->setAssignedColor($player, $dyeColors[$i]);
				$hoe = VanillaItems::DIAMOND_HOE();
				$hoe->setCustomName($this->plugin->getTranslator()->translate($player, "microgame.item.colorizer"));
				$missile = VanillaItems::SPLASH_POTION();
				$missile->setCustomName($this->plugin->getTranslator()->translate($player, "microgame.item.colormissile"));
				$missile->setCount(self::COLOR_MISSILE_COUNT);
				$assignedColor = VanillaBlocks::STAINED_CLAY()->setColor($dyeColors[$i])->asItem();
				$assignedColor->setCustomName($this->plugin->getTranslator()->translate($player, "microgame.colorfloor.yourcolor"));
				$player->getInventory()->setItem(0, $hoe);
				$player->getInventory()->setItem(1, $missile);
				$player->getInventory()->setItem(8, $assignedColor);
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
		if ($timeLeft <= 0 || $this->totalColoredBlocks >= $this->blocksCount) {
			foreach ($this->getColoredBlocksOrderedByHigherScore() as $colorId => $count) {
				if (count($this->winners) < 3) {
					$player = $this->assignedPlayer[$colorId] ?? null;
					if ($player !== null) {
						$this->addWinner($player);
					}
				} else {
					break;
				}
			}
			foreach ($this->arena->getPlayers() as $player) {
				if (!$this->isWinner($player)) {
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
		$winners = $this->getWinners();
		$winnersCount = count($winners);
		$slice = array_slice($winners, 0, 3, false);
		foreach ($players as $player) {
			if ($this->isWinner($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.colorfloor.won", [
						"{%colored_blocks_count}" => $this->getColoredBlocks($player)
					]
				));
			} elseif ($this->isLoser($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.colorfloor.lose", [
						"{%colored_blocks_count}" => $this->getColoredBlocks($player)
					]
				));
			}
			$player->sendMessage($this->plugin->getTranslator()->translate(
				$player, "microgame.colorfloor.top", [
					"{%winners_count}" => $winnersCount
				]
			));
			foreach ($slice as $key => $pl) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.colorfloor.top" . $key + 1, [
						"{%player}" => $pl->getName(),
						"{%count}" => $this->getColoredBlocks($pl)
					]
				));
			}
		}
		foreach ($this->changedBlocks as $block) {
			$this->arena->getWorld()->setBlock($block->getPosition(), $block, false);
		}
		parent::end();
	}

	public function setAssignedColor(Player $player, DyeColor $color) : void {
		$this->assignedColor[$player->getId()] = $color;
		$this->assignedPlayer[$color->id()] = $player;
	}

	public function getAssignedColor(Player $player) : ?DyeColor {
		return $this->assignedColor[$player->getId()] ?? null;
	}

	public function getAssignedPlayer(DyeColor $color) : ?Player {
		return $this->assignedPlayer[$color->id()] ?? null;
	}

	public function getBlocksOfColor(DyeColor $color) : int {
		return $this->coloredBlocksCount[$color->id()] ?? 0;
	}

	public function getColoredBlocks(Player $player) : int {
		$color = $this->getAssignedColor($player);
		if ($color !== null) {
			return $this->getBlocksOfColor($color);
		}
		return 0;
	}

	public function color(StainedHardenedClay $block, DyeColor $color) : void {
		$blockColor = $block->getColor();
		if ($blockColor->equals($color)) {
			return;
		}
		if (!$blockColor->equals(DyeColor::WHITE())) {
			$this->coloredBlocksCount[$blockColor->id()] = $this->getBlocksOfColor($blockColor) - 1;
		}
		$this->arena->getWorld()->setBlock($block->getPosition(), VanillaBlocks::STAINED_CLAY()->setColor($color), false);
		$this->coloredBlocksCount[$color->id()] = $this->getBlocksOfColor($color) + 1;
		$this->totalColoredBlocks++;
	}

	/**
	 * @return array<int, int>
	 */
	public function getColoredBlocksOrderedByHigherScore() : array {
		$array = $this->coloredBlocksCount;
		if (asort($array) === false) {
			throw new AssumptionFailedError("Failed to sort score");
		}
		return array_reverse($array, true);
	}

	public function getTotalColoredBlocks() : int {
		return $this->totalColoredBlocks;
	}

	public function hasColoredBlocks(Player $player) : bool {
		return $this->getColoredBlocks($player) > 0;
	}

	public function isNextToColor(Block $block, DyeColor $color) : bool {
		if ($block instanceof StainedHardenedClay && $block->getColor()->equals($color)) {
			return true;
		}
		foreach ($block->getHorizontalSides() as $side) {
			if ($side instanceof StainedHardenedClay) {
				if ($side->getColor()->equals($color)) {
					return true;
				}
			}
		}
		return false;
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
	}

	/**
	 * @ignoreCancelled
	 * @priority HIGH
	 */
	public function onInteract(PlayerInteractEvent $event) : void {
		$player = $event->getPlayer();
		if (!$this->arena->inGame($player)) return;
		if ($event->getItem() instanceof Hoe) {
			$block = $event->getBlock();
			if ($block instanceof StainedHardenedClay) {
				$assignedColor = $this->getAssignedColor($player);
				$blockColor = $block->getColor();
				if (!$blockColor->equals(DyeColor::WHITE()) && !$blockColor->equals($assignedColor)) {
					$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.colorfloor.onlymissile"));
				} elseif ($this->hasColoredBlocks($player) && !$this->isNextToColor($block, $assignedColor)) {
					$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.colorfloor.nextto"));
				} else {
					$this->color($block, $assignedColor);
				}
			}
		}
	}

	/**
	 * @ignoreCancelled
	 * @priority HIGH
	 */
	public function onLaunch(ProjectileLaunchEvent $event) : void {
		$projectile = $event->getEntity();
		$player = $projectile->getOwningEntity();
		if (!$player instanceof Player) return;
		if (!$this->arena->inGame($player)) return;
		$item = $player->getInventory()->getItemInHand();
		if (!$item->equals(VanillaItems::SPLASH_POTION(), true, false)) return;
		if (!$projectile instanceof SplashPotion) return;
		if ($this->getAssignedColor($player) !== null) {
			$event->cancel();
			$missile = new ColorMissile($projectile->getLocation(), $player, $projectile->getPotionType());
			$missile->setMotion($projectile->getMotion());
			$missile->spawnToAll();
			$location = $player->getLocation();
			$location->getWorld()->addSound($location, new ThrowSound());

			# Remove item
			$item->setCount($item->getCount() - 1);
			if ($item->getCount() <= 0) {
                $item = VanillaItems::AIR();
//				$item = ItemFactory::air();
			}
			$player->getInventory()->setItemInHand($item);
		}
	}

	/**
	 * @ignoreCancelled
	 * @priority HIGH
	 */
	public function onProjectileHit(ProjectileHitEvent $event) : void {
		$projectile = $event->getEntity();
		$player = $projectile->getOwningEntity();
		if (!$projectile instanceof ColorMissile) return;
		if (!$player instanceof Player) return;
		if (!$this->arena->inGame($player)) return;
		$world = $this->arena->getWorld();
		if ($projectile->getWorld() !== $world) return;
		$assignedColor = $this->getAssignedColor($player);
		if ($assignedColor !== null) {
			$pos = $event->getRayTraceResult()->getHitVector();
			$pos->y = $this->arena->getMap()->getPlatformMinPos()->y; // A little hack >:D
			$block = $world->getBlock($pos);
			if ($block instanceof StainedHardenedClay) {
				$this->color($block, $assignedColor);
			}
			foreach ($block->getHorizontalSides() as $side) {
				if ($side instanceof StainedHardenedClay) {
					$this->color($side, $assignedColor);
				}
			}
		}
	}
}