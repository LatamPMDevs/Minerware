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

use LatamPMDevs\minerware\arena\microgame\CancelsBuildEvents;
use LatamPMDevs\minerware\arena\microgame\Level;
use LatamPMDevs\minerware\arena\microgame\LosesInVoid;
use LatamPMDevs\minerware\arena\microgame\Microgame;
use LatamPMDevs\minerware\utils\Utils;

use pocketmine\block\TNT;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\FlintSteel;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use function array_rand;

class IgniteTNT extends Microgame implements Listener {

	use CancelsBuildEvents;
	use LosesInVoid;

	/** @var array<int, int> */
	protected array $ignitedTNTs = [];

	protected int $totalIgnitedTNTs = 0;

	public function getName() : string {
		return "Ignite The TNT";
	}

	public function getLevel() : Level {
		return Level::NORMAL;
	}

	public function getGameDuration() : float {
		return 15.9;
	}

	public function start() : void {

		$selection = $this->getStageSelection();
		$minPos = $this->arena->getMap()->getPlatformMinPos();
		foreach ($this->arena->getMap()->getMiniPlatforms() as $platformBlocks) {
			$blockPos = $platformBlocks[array_rand($platformBlocks)];
			$selection->addCell(
				(int) ($minPos->x + $blockPos[0]),
				(int) ($minPos->y + $blockPos[1]),
				(int) ($minPos->z + $blockPos[2]),
				VanillaBlocks::TNT()
			);
		}
		$this->commitStage();

		foreach ($this->arena->getPlayers() as $player) {
			Utils::initPlayer($player);
			$player->setGamemode(GameMode::SURVIVAL);
			$player->getInventory()->setItem(0, VanillaItems::FLINT_AND_STEEL());
			$player->getInventory()->setHeldItemIndex(0);

			$this->ignitedTNTs[$player->getId()] = 0;
		}
		$this->arena->getLosersCage()->set();
		parent::start();
	}

public function tick() : void {
		$timeLeft = $this->getTimeLeft();
		if ($timeLeft <= 0) {
			$this->endRound();
			return;
		}
		$this->updateTimeBar($timeLeft);
	}

	public function end() : void {
		$players = $this->arena->getPlayers();
		foreach ($players as $player) {
			$player->sendMessage($this->plugin->getTranslator()->translate(
				$player, "microgame.ignitetnt.total", [
					"{%count}" => $this->totalIgnitedTNTs
				]
			));
			$this->sendOutcome($player, "microgame.ignitetnt.won", "microgame.ignitetnt.lose");
		}
		parent::end();
	}

	public function getIgnitedTNTs(Player $player) : int {
		return $this->ignitedTNTs[$player->getId()] ?? 0;
	}

	public function getTotalIgnitedTNTs() : int {
		return $this->totalIgnitedTNTs;
	}

	# Listener

	/**
	 * @ignoreCancelled
	 * @priority HIGH
	 */
	public function onInteract(PlayerInteractEvent $event) : void {
		$player = $event->getPlayer();
		if (!$this->arena->inGame($player)) return;
		if ($event->getItem() instanceof FlintSteel) {
			if (!$player->isSneaking() && $event->getBlock() instanceof TNT) {
				$this->ignitedTNTs[$player->getId()] = $this->getIgnitedTNTs($player) + 1;
				$this->totalIgnitedTNTs++;
				if (!$this->isWinner($player) && !$this->isLoser($player)) {
					$this->addWinner($player);
				}
			} else {
				$event->cancel();
			}
		}
	}

	public function onExplosion(EntityPreExplodeEvent $event) : void {
		$entity = $event->getEntity();
		if ($entity->getWorld() !== $this->arena->getWorld()) return;
		if ($entity instanceof PrimedTNT) {
			$event->setBlockBreaking(false);
		}
	}
}