<?php

/*
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
 * @author LatamPMDevs
 */

declare(strict_types=1);

namespace LatamPMDevs\minerware\arena\microgame\normal;

use LatamPMDevs\minerware\arena\microgame\Level;
use LatamPMDevs\minerware\arena\microgame\Microgame;
use LatamPMDevs\minerware\entity\object\TextEntity;
use LatamPMDevs\minerware\utils\Utils;

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
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
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

	public function getName() : string {
		return "Nerd Pole";
	}

	public function getLevel() : Level {
		return Level::NORMAL;
	}

	public function getGameDuration() : float {
		return 30.9;
	}

	public function getRecompensePoints() : int {
		return self::DEFAULT_RECOMPENSE_POINTS;
	}

	public function start() : void {
		$map = $this->arena->getMap();
		$minPos = $map->getPlatformMinPos();
		$maxPos = $map->getPlatformMaxPos();
		$world = $this->arena->getWorld();
		$stainedClays = self::getStainedClays();
		$selection = $this->getStageSelection();

		#Fill the platform with Stained Clay
		$miniPlatforms = $map->getMiniPlatforms();
		foreach ($miniPlatforms as $key => $value) {
			foreach ($miniPlatforms[$key] as $blockPos) {
				$selection->addCell((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]), $stainedClays[array_rand($stainedClays)]);
			}
		}
		for ($x = $minPos->x; $x <= $maxPos->x; ++$x) {
			for ($z = $minPos->z; $z <= $maxPos->z; ++$z) {
				for ($y = $minPos->y; $y <= $maxPos->y; ++$y) {
					$selection->addCell((int) $x, (int) $y, (int) $z, $stainedClays[array_rand($stainedClays)]);
				}
			}
		}

		$defaultChesttext = $this->plugin->getTranslator()->translate(null, "microgame.nerdpole.chesttext");
		$textEntities = [];

		#Place Chests (a fresh block per corner so the facing is stable in the selection)
		$chestPositions = [
			[$minPos->add(0, 1, 0), Facing::SOUTH],
			[$minPos->add(0, 1, 0), Facing::NORTH],
			[$maxPos->add(0, 1, 0), Facing::NORTH],
			[$maxPos->add(0, 1, 0), Facing::SOUTH]
		];
		foreach ($chestPositions as $index => $chestPosData) {
			$pos = $chestPosData[0];
			if ($index === 1) {
				$pos->z = $maxPos->z;
			} elseif ($index === 3) {
				$pos->z = $minPos->z;
			}
			$chest = VanillaBlocks::CHEST();
			$chest->setFacing($chestPosData[1]);
			$selection->addBlock($pos, $chest);
			$textEntity = new TextEntity(Location::fromObject($pos->add(0.5, 1.2, 0.5), $world));
			$textEntity->setNameTag($defaultChesttext);
			$textEntity->spawnToAll();
			$textEntities[] = $textEntity;
		}

		#Set the gold platform
		$diff = $maxPos->subtractVector($minPos);
		$platformMinPos = Position::fromObject($minPos->addVector($diff->divide(2)->add(0, self::PLATFORM_HEIGHT, 0))->floor(), $world);
		$platformMaxPos = Position::fromObject($platformMinPos->add(1, 0, 1), $world);
		$selection->addFill($platformMinPos, $platformMaxPos, VanillaBlocks::GOLD());
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
			$player->setGamemode(GameMode::SURVIVAL);
			$player->getInventory()->setHeldItemIndex(0);

			$chesttext = $this->plugin->getTranslator()->translate($player, "microgame.nerdpole.chesttext");
			foreach ($textEntities as $textEntity) {
				$textEntity->setNameTagToPlayer($player, $chesttext);
			}
		}
		$this->commitStage();
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
		$this->updateTimeBar($timeLeft);
	}

	public function end() : void {
		$players = $this->arena->getPlayers();
		foreach ($players as $player) {
			if ($this->isWinner($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.nerdpole.won"));
			} elseif ($this->isLoser($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.nerdpole.lose"));
			}
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
		if ($this->arena->isBuildingStage()) {
			$event->cancel();
			return;
		}

		$replacedBlocks = [];
		foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
			if ($this->platformBoundingBox->isVectorInside($block->getPosition()->add(0.5, -0.1, 0.5))) {
				$event->cancel();
				return;
			}
			$this->recordOriginalBlock($this->arena->getWorld()->getBlockAt($x, $y, $z));
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
				$this->arena->getLosersCage()->addPlayer($player);
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
			$this->arena->getWinnersCage()->addPlayer($player);
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