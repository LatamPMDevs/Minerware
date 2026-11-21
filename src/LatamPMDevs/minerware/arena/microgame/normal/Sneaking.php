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

use pocketmine\block\VanillaBlocks;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
use pocketmine\player\GameMode;

class Sneaking extends Microgame implements Listener {

	use CancelsBuildEvents;
	use LosesInVoid;

	protected float $totalSneakedDistance = 0;

	public function getName() : string {
		return "Sneaking";
	}

	public function getLevel() : Level {
		return Level::NORMAL;
	}

	public function getGameDuration() : float {
		return 12.9;
	}

	public function start() : void {
		$map = $this->arena->getMap();
		$minPos = $map->getPlatformMinPos();
		$world = $this->arena->getWorld();
		$this->setMiniPlatformsAsync($this->getStageSelection(), VanillaBlocks::AIR());
		$this->commitStage();

		foreach ($this->arena->getPlayers() as $player) {
			Utils::initPlayer($player);
			$player->setGamemode(GameMode::ADVENTURE);
			$player->getInventory()->setHeldItemIndex(0);
			$player->sendTitle("§1§2", $this->plugin->getTranslator()->translate($player, "microgame.sneaking.start"), 10, 20, 10);
		}
		$this->arena->getLosersCage()->set();
		parent::start();
	}

	public function tick() : void {
		$timeLeft = $this->getTimeLeft();
		if ($timeLeft <= 0) {
			$this->endRound(true);
			return;
		}
		$this->updateTimeBar($timeLeft);
		foreach ($this->arena->getPlayers() as $player) {
			if (!$this->isLoser($player) &&
				!$player->isSneaking() &&
				($timeLeft <= ($this->getGameDuration() - 1)
			)) {
				$this->lose($player);
			}
		}
	}

	public function end() : void {
		$players = $this->arena->getPlayers();
		foreach ($players as $player) {
			$player->sendMessage($this->plugin->getTranslator()->translate(
				$player, "microgame.sneaking.total", [
					"{%distance}" => (int) $this->totalSneakedDistance
				]
			));
			$this->sendOutcome($player, "microgame.sneaking.won", "microgame.sneaking.lose");
		}
		parent::end();
	}

	public function getTotalSneakedDistance() : float {
		return $this->totalSneakedDistance;
	}

	# Listener

	/**
	 * @ignoreCancelled
	 * @priority HIGH
	 */
	public function onToggleSneak(PlayerToggleSneakEvent $event) : void {
		$player = $event->getPlayer();
		if (!$this->arena->inGame($player)) return;
		if ($this->isLoser($player) || $this->isWinner($player)) return;
		if (!$event->isSneaking()) {
			$this->lose($player);
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
		$this->totalSneakedDistance += $event->getFrom()->distance($event->getTo());
	}
}