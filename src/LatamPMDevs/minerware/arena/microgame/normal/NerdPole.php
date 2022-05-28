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
use LatamPMDevs\minerware\entity\object\TextEntity;
use LatamPMDevs\minerware\utils\Utils;

use pocketmine\block\Block;
use pocketmine\block\Chest;
use pocketmine\block\StainedHardenedClay;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Snowball;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\HandlerListManager;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use function array_rand;

class NerdPole extends Microgame implements Listener {

	/**
	 * @return StainedHardenedClay[]
	 */
	public static function getStainedClays() : array {
		$stainedClays = [];
		foreach (DyeColor::getAll() as $color) {
			$stainedClays[] = VanillaBlocks::STAINED_CLAY()->setColor($color);
		}
		return $stainedClays;
	}

	public const PLATFORM_HEIGHT = 12;
	public const SNOWBALL_COUNT = 5;

	protected AxisAlignedBB $platformBoundingBox;

	/** @var array<int, Player> */
	protected array $claimed = [];

	/** @var Block[] */
	protected array $changedBlocks = [];

	public function getName() : string {
		return "Nerd Pole";
	}

	public function getLevel() : Level {
		return Level::NORMAL();
	}

	public function getGameDuration() : float {
		return 30.9;
	}

	public function getRecompensePoints() : int {
		return self::DEFAULT_RECOMPENSE_POINTS;
	}

	public function start() : void {
		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);

		$map = $this->arena->getMap();
		$minPos = $map->getPlatformMinPos();
		$maxPos = $map->getPlatformMaxPos();
		$world = $this->arena->getWorld();
		$stainedClays = self::getStainedClays();

		#Fill the platform with Stayned Clay
		foreach (Map::MINI_PLATFORMS as $key => $value) {
			foreach (Map::MINI_PLATFORMS[$key] as $blockPos) {
				$this->changedBlocks[] = $world->getBlockAt((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]));
				$world->setBlockAt((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]), $stainedClays[array_rand($stainedClays)]);
			}
		}
		for ($x = $minPos->x; $x <= $maxPos->x; ++$x) {
			for ($z = $minPos->z; $z <= $maxPos->z; ++$z) {
				$world->loadChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE);
				for ($y = $minPos->y; $y <= $maxPos->y; ++$y) {
					$this->changedBlocks[] = $world->getBlockAt((int) $x, (int) $y, (int) $z);
					$world->setBlockAt((int) $x, (int) $y, (int) $z, $stainedClays[array_rand($stainedClays)]);
				}
			}
		}

		$defaultChesttext = $this->plugin->getTranslator()->translate(null, "microgame.nerdpole.chesttext");
		$textEntities = [];

		#Place Chests
		$chest = VanillaBlocks::CHEST();

		$pos = $minPos->add(0, 1, 0);
		$this->changedBlocks[] = $world->getBlock($pos);
		$chest->setFacing(Facing::SOUTH);
		$world->setBlock($pos, $chest);
		$textEntity = new TextEntity(Location::fromObject($pos->add(0.5, 1.2, 0.5), $world));
		$textEntity->setNameTag($defaultChesttext);
		$textEntity->spawnToAll();
		$textEntities[] = $textEntity;

		$pos = $minPos->add(0, 1, 0);
		$pos->z = $maxPos->z;
		$this->changedBlocks[] = $world->getBlock($pos);
		$chest->setFacing(Facing::NORTH);
		$world->setBlock($pos, $chest);
		$textEntity = new TextEntity(Location::fromObject($pos->add(0.5, 1.2, 0.5), $world));
		$textEntity->setNameTag($defaultChesttext);
		$textEntity->spawnToAll();
		$textEntities[] = $textEntity;

		$pos = $maxPos->add(0, 1, 0);
		$this->changedBlocks[] = $world->getBlock($pos);
		$chest->setFacing(Facing::NORTH);
		$world->setBlock($pos, $chest);
		$textEntity = new TextEntity(Location::fromObject($pos->add(0.5, 1.2, 0.5), $world));
		$textEntity->setNameTag($defaultChesttext);
		$textEntity->spawnToAll();
		$textEntities[] = $textEntity;

		$pos = $maxPos->add(0, 1, 0);
		$pos->z = $minPos->z;
		$this->changedBlocks[] = $world->getBlock($pos);
		$chest->setFacing(Facing::SOUTH);
		$world->setBlock($pos, $chest);
		$textEntity = new TextEntity(Location::fromObject($pos->add(0.5, 1.2, 0.5), $world));
		$textEntity->setNameTag($defaultChesttext);
		$textEntity->spawnToAll();
		$textEntities[] = $textEntity;

		#Set the gold platform
		$diff = $maxPos->subtractVector($minPos);
		$platformMinPos = Position::fromObject($minPos->addVector($diff->divide(2)->add(0, self::PLATFORM_HEIGHT, 0))->floor(), $world);
		$platformMaxPos = Position::fromObject($platformMinPos->add(1, 0, 1), $world);
		foreach (Utils::fill($platformMinPos, $platformMaxPos, VanillaBlocks::GOLD(), true) as $changedBlock) {
			$this->changedBlocks[] = $changedBlock;
		}
		$this->platformBoundingBox = new AxisAlignedBB(
			$platformMinPos->x,
			$platformMinPos->y,
			$platformMinPos->z,
			$platformMaxPos->x + 1,
			$platformMaxPos->y + 1,
			$platformMaxPos->z + 1
		);

		foreach ($this->arena->getPlayers() as $player) {
			Utils::initPlayer($player);
			$player->setGamemode(GameMode::SURVIVAL());
			$player->getInventory()->setHeldItemIndex(0);

			$chesttext = $this->plugin->getTranslator()->translate($player, "microgame.nerdpole.chesttext");
			foreach ($textEntities as $textEntity) {
				$textEntity->setNameTagToPlayer($player, $chesttext);
			}
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
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.nerdpole.won"));
			} elseif ($this->isLoser($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.sneaking.lose"));
			}
		}
		foreach ($this->changedBlocks as $block) {
			$this->arena->getWorld()->setBlock($block->getPosition(), $block, false);
		}
		parent::end();
	}

	public function getPlatformBoundingBox() : AxisAlignedBB {
		return $this->platformBoundingBox;
	}

	public function hasClaimed(Player $player) : bool {
		return isset($this->claimed[$player->getId()]);
	}

	public function claim(Player $player) : void {
		$colors = DyeColor::getAll();
		$wool = VanillaBlocks::WOOL()->setColor($colors[array_rand($colors)])->asItem();
		$wool->setCount($wool->getMaxStackSize());
		$player->getInventory()->setItem(0, $wool);
		$player->getInventory()->setItem(1, VanillaItems::SNOWBALL()->setCount(self::SNOWBALL_COUNT));
		$this->claimed[$player->getId()] = $player;
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
		$blockReplaced = $event->getBlockReplaced();
		if ($this->platformBoundingBox->isVectorInside($blockReplaced->getPosition()->add(0.5, -0.1, 0.5))) {
			$event->cancel();
		} else {
			$this->changedBlocks[] = $blockReplaced;
		}
	}

	public function onDamage(EntityDamageEvent $event) : void {
		$player = $event->getEntity();
		if (!$player instanceof Player) return;
		if (!$this->arena->inGame($player)) return;
		if (!($event instanceof EntityDamageByChildEntityEvent &&
			$event->getChild() instanceof Snowball)) {
			if ($event->getCause() === EntityDamageEvent::CAUSE_VOID && !$this->isWinner($player)) {
				$this->addLoser($player);
				$this->arena->sendToLosersCage($player);
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.felloffplatform"));
			}
			$event->cancel();
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

		if ($this->platformBoundingBox->isVectorInside($player->getLocation()->subtract(0, 0.001, 0))) {
			$this->addWinner($player);
			$this->arena->sendToWinnersCage($player);
		}
	}

	/**
	 * @ignoreCancelled
	 * @priority HIGH
	 */
	public function onInteract(PlayerInteractEvent $event) : void {
		$player = $event->getPlayer();
		if (!$this->arena->inGame($player)) return;
		if ($event->getBlock() instanceof Chest) {
			if (!$this->hasClaimed($player)) {
				$this->claim($player);
			}
			$event->cancel();
		}
	}
}