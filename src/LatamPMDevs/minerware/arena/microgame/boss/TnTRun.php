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

namespace LatamPMDevs\minerware\arena\microgame\boss;

use LatamPMDevs\minerware\arena\microgame\Level;
use LatamPMDevs\minerware\arena\microgame\Microgame;
use LatamPMDevs\minerware\entity\object\FallingBlock;
use LatamPMDevs\minerware\utils\Utils;

use pocketmine\block\Block;
use pocketmine\block\Sand;
use pocketmine\block\TNT;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Location;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\Position;
use function ceil;
use function count;

class TnTRun extends Microgame implements Listener {

	public const LAYERS = 4;

	public const LAYERS_DIAMETER = 29;

	public const SPLACING_BETWEEN_LAYERS = 4;

	public const EXTRA_CAGE_Y_POS = 16;

	public const START_TIME = 11;

	protected bool $hasActuallyStarted = false;

	public function getName() : string {
		return "TnT Run";
	}

	public function getLevel() : Level {
		return Level::BOSS;
	}

	public function getGameDuration() : float {
		return 100.9;
	}

	public function getRecompensePoints() : int {
		return self::BOSS_RECOMPENSE_POINTS;
	}

	public function start() : void {
		$map = $this->arena->getMap();
		$world = $this->arena->getWorld();
		$minPos = Position::fromObject($map->getPlatformMinPos(), $world);
		$selection = $this->getStageSelection();
		foreach ($map->getMiniPlatforms() as $key => $values) {
			foreach ($values as $blockPos) {
				$x = (int) ($minPos->x + $blockPos[0]);
				$y = (int) ($minPos->y + $blockPos[1]);
				$z = (int) ($minPos->z + $blockPos[2]);
				$selection->addCell($x, $y, $z, VanillaBlocks::AIR());
			}
		}
		$selection->addFill($minPos, $map->getPlatformMaxPos(), VanillaBlocks::AIR());

		$layerBlock = VanillaBlocks::TNT();
		foreach (self::fillCircleCells($map->getCenter(), self::LAYERS_DIAMETER / 2) as $pos) {
			$selection->addBlock($pos, $layerBlock);
			$bPos = $pos->add(0, 1, 0);
			$selection->addBlock($bPos, VanillaBlocks::SAND());

			$y = (int) ($pos->y + self::SPLACING_BETWEEN_LAYERS + 2);
			for ($layer = 1; $layer < self::LAYERS; $layer++) {
				$selection->addCell((int) $pos->x, $y, (int) $pos->z, VanillaBlocks::TNT());
				$selection->addCell((int) $pos->x, $y + 1, (int) $pos->z, VanillaBlocks::SAND());
				$y += self::SPLACING_BETWEEN_LAYERS + 2;
			}
		}

		$highestPlatformY = $minPos->y + ((self::SPLACING_BETWEEN_LAYERS + 2) * self::LAYERS);
		$players = $this->arena->getPlayers();
		foreach ($players as $player) {
			Utils::initPlayer($player);
			$player->setGamemode(GameMode::ADVENTURE);
			$player->getInventory()->setHeldItemIndex(0);
		}
		$losersCage = $this->arena->getLosersCage();
		$losersCagePos = $losersCage->getPosition();
		$losersCagePos->y = $highestPlatformY + self::EXTRA_CAGE_Y_POS;
		$losersCage->setPosition($losersCagePos);

		# Teleport players onto the towers only once they are written, so
		# getSafePosition() does not resolve against the stale platform.
		$this->commitStage(function () use ($players, $highestPlatformY) : void {
			foreach ($players as $player) {
				$safePos = $this->arena->getSafePosition($player);
				$safePos->y = $highestPlatformY;
				$player->teleport($safePos);
			}
		});
		$losersCage->set();
		parent::start();
	}

	/**
	 * Returns the world positions inside the disc of the given radius around
	 * $center, used to stage the TnT towers without a nested fillCircle write.
	 *
	 * @return Vector3[]
	 */
	private static function fillCircleCells(Vector3 $center, float $radius) : array {
		$cells = [];
		$rSqrt = $radius ** 2;
		$rInt = (int) ceil($radius);
		for ($x = -$rInt; $x <= $rInt; $x++) {
			for ($z = -$rInt; $z <= $rInt; $z++) {
				if (($x ** 2) + ($z ** 2) <= $rSqrt) {
					$cells[] = $center->add($x, 0, $z);
				}
			}
		}
		return $cells;
	}

	public function tick() : void {
		$timeLeft = $this->getTimeLeft();
		$players = $this->arena->getPlayers();
		if ($timeLeft <= 0 || (count($players) - count($this->getLosers()) <= 1)) {
			foreach ($players as $player) {
				if (!$this->isLoser($player) && !$this->isWinner($player)) {
					$this->addWinner($player);
				}
			}
			$this->arena->endCurrentMicrogame();
			return;
		}
		$gameDuration = $this->getGameDuration();
		$time2start = (int) ($timeLeft - ($gameDuration - self::START_TIME));
		$starting = $time2start >= 0 && $time2start <= self::START_TIME && !$this->hasActuallyStarted;
		$started = $time2start  <= 0 && !$this->hasActuallyStarted;
		foreach ($players as $player) {
			$player->getXpManager()->setXpAndProgress((int) $timeLeft, $timeLeft / $gameDuration);
			if ($starting) {
				$player->sendTip($this->plugin->getTranslator()->translate($player, "microgame.tntrun.starting", [
					"{%seconds}" => $time2start
				]));
			}
			if ($started) {
				$player->sendTitle(
					$this->plugin->getTranslator()->translate($player, "microgame.tntrun.started.title"),
					$this->plugin->getTranslator()->translate($player, "microgame.tntrun.started.subtitle"),
					10, 10, 10
				);
				$player->getEffects()->add(new EffectInstance(VanillaEffects::SPEED(), 32000));
			}
			if ($this->hasActuallyStarted) {
				if (!$this->isLoser($player) && !$this->isWinner($player)) {
					$position = $player->getPosition();
					$world = $position->getWorld();
					foreach ($world->getCollisionBlocks(new AxisAlignedBB($position->x - 0.1, $position->y - 0.25, $position->z - 0.1, $position->x + 0.1, $position->y, $position->z + 0.1)) as $b1) {
						$b2 = $world->getBlock($b1->getPosition()->down());
						if ($b1 instanceof Sand && $b2 instanceof TNT) {
							foreach ([$b1, $b2] as $block) {
								$this->delayedBlockDestruction($block, 5);
							}
						}
					}
				}
			}
		}
		if ($started) {
			$this->hasActuallyStarted = true;
		}
	}

	public function end() : void {
		foreach ($this->arena->getPlayers() as $player) {
			if ($this->isWinner($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.tntrun.won"));
			} elseif ($this->isLoser($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.threwoffstage"
				));
			}
		}
		parent::end();
	}

	public function hasActuallyStarted() : bool {
		return $this->hasActuallyStarted;
	}

	public function delayedBlockDestruction(Block $block, int $ticks) : void {
		$task = new ClosureTask(function () use ($block) : void {
			$pos = $block->getPosition();
			$pos->getWorld()->setBlockAt($pos->x, $pos->y, $pos->z, VanillaBlocks::AIR(), false);
			$fallingBlock = new FallingBlock(
				new Location($pos->x + 0.5, $pos->y, $pos->z + 0.5, $pos->getWorld(), 0.0, 0.0),
				$block
			);
			$fallingBlock->setMaxTicksOfLife(5);
			$fallingBlock->spawnToAll();
		});

		$this->plugin->getScheduler()->scheduleDelayedTask($task, $ticks);
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
		if ($this->arena->isWinner($player) || $this->arena->isLoser($player)) return;
		$event->cancel();
		$player->extinguish();
		if ($event->getCause() === EntityDamageEvent::CAUSE_VOID && !$this->isWinner($player)) {
			$this->addLoser($player);
			$this->arena->getLosersCage()->addPlayer($player);
		}
	}
}