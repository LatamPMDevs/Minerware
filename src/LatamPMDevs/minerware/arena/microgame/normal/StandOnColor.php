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
use LatamPMDevs\minerware\utils\Utils;

use pocketmine\block\Block;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Wool;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\world\format\Chunk;
use function array_key_first;
use function array_rand;
use function strtolower;

class StandOnColor extends Microgame implements Listener {

	/**
	 * @return DyeColor[]
	 */
	public static function getColors() : array {
		return [DyeColor::LIGHT_BLUE, DyeColor::LIME, DyeColor::MAGENTA, DyeColor::ORANGE, DyeColor::PINK, DyeColor::YELLOW];
	}

	public const KNOCKBACK_LEVEL = 2;

	/** @var Block[] */
	protected array $changedBlocks = [];

	protected DyeColor $color;

	/** @var array<int, int> */
	protected array $hitsCount = [];

	public function getName() : string {
		return "Stand on Color";
	}

	public function getLevel() : Level {
		return Level::NORMAL;
	}

	public function getGameDuration() : float {
		return 8.9;
	}

	public function getRecompensePoints() : int {
		return self::DEFAULT_RECOMPENSE_POINTS;
	}

	public function start() : void {
		$colors = self::getColors();
		$this->color = $colors[array_rand($colors)];

		$map = $this->arena->getMap();
		$minPos = $map->getPlatformMinPos();
		$maxPos = $map->getPlatformMaxPos();
		$world = $this->arena->getWorld();
		$this->setMiniPlatforms(VanillaBlocks::AIR(), true);
		for ($x = $minPos->x; $x <= $maxPos->x; ++$x) {
			for ($z = $minPos->z; $z <= $maxPos->z; ++$z) {
				$world->loadChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE);
				for ($y = $minPos->y; $y <= $maxPos->y; ++$y) {
					$this->changedBlocks[] = $world->getBlockAt((int) $x, (int) $y, (int) $z);
					$world->setBlockAt((int) $x, (int) $y, (int) $z, VanillaBlocks::WOOL()->setColor($colors[array_rand($colors)]), false);
				}
			}
		}

		$woolItem = VanillaBlocks::WOOL()->setColor($this->color)->asItem();
		$knockback = VanillaEnchantments::KNOCKBACK();
		foreach ($this->arena->getPlayers() as $player) {
			Utils::initPlayer($player);
			$stick = VanillaItems::STICK();
			$stick->setCustomName($this->plugin->getTranslator()->translate($player, "microgame.item.powerstick"));
			$stick->addEnchantment(new EnchantmentInstance($knockback, self::KNOCKBACK_LEVEL));
			$player->setGamemode(GameMode::ADVENTURE);
			$player->getInventory()->setItem(0, $stick);
			$player->getInventory()->setItem(8, $woolItem);
			$player->getInventory()->setHeldItemIndex(0);
		}
		$this->arena->getLosersCage()->set();
		parent::start();
	}

	public function tick() : void {
		$timeLeft = $this->getTimeLeft();
		if ($timeLeft <= 0) {
			$world = $this->arena->getWorld();
			foreach ($this->arena->getPlayers() as $player) {
				$block = $world->getBlock($player->getLocation()->down());
				if ($block instanceof Wool && $block->getColor() === $this->color) {
					$this->addWinner($player);
				} else {
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
		$hits = $this->getPlayersHitsOrderedByHigherScore();
		$hitter = null;
		if ($hits !== []) {
			$hitter = $players[array_key_first($hits)] ?? null;
		}
		$textformatColor = Utils::DyeColor2TextFormat($this->color);
		foreach ($players as $player) {
			if ($hitter !== null) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.standoncolor.hitscount", [
						"{%player}" => $hitter->getName(),
						"{%hits_count}" => $this->getHits($player)
					]
				));
			}
			if ($this->isWinner($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.standoncolor.won", [
						"{%color}" => $textformatColor . $this->plugin->getTranslator()->translate($player, "text.color." . strtolower($this->color->name()))
					]
				));
			} elseif ($this->isLoser($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "microgame.standoncolor.lose", [
						"{%color}" => $textformatColor . $this->plugin->getTranslator()->translate($player, "text.color." . strtolower($this->color->name()))
					]
				));
			}
		}
		parent::end();
	}

	public function getColor() : DyeColor {
		return $this->color;
	}

	public function getHits(Player $player) : int {
		return $this->hitsCount[$player->getId()] ?? 0;
	}

	/**
	 * @return array<int, int>
	 */
	public function getPlayersHitsOrderedByHigherScore() : array {
		return Utils::sortScoresDescending($this->hitsCount);
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
			$damager = $event->getDamager();
			if ($damager instanceof Player && $this->arena->inGame($damager)) {
				$this->hitsCount[$damager->getId()] = $this->getHits($damager) + 1;
			}
			$event->setBaseDamage(0);
			return;
		}
		$event->cancel();
		if ($event->getCause() === EntityDamageEvent::CAUSE_VOID && !$this->isWinner($player)) {
			$this->addLoser($player);
			$this->arena->getLosersCage()->addPlayer($player);
			$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.felloffplatform"));
		}
	}
}