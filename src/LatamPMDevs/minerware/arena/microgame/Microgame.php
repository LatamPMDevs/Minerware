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

namespace LatamPMDevs\minerware\arena\microgame;

use LatamPMDevs\minerware\arena\Arena;
use LatamPMDevs\minerware\event\arena\microgame\MicrogameEndEvent;
use LatamPMDevs\minerware\event\arena\microgame\MicrogameStartEvent;
use LatamPMDevs\minerware\event\arena\microgame\PlayerLoseMicrogameEvent;
use LatamPMDevs\minerware\event\arena\microgame\PlayerWinMicrogameEvent;
use LatamPMDevs\minerware\map\Map;
use LatamPMDevs\minerware\Minerware;

use pocketmine\block\Block;
use pocketmine\event\HandlerListManager;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use function array_keys;
use function microtime;

abstract class Microgame {

	public const DEFAULT_RECOMPENSE_POINTS = 1;
	public const BOSS_RECOMPENSE_POINTS = 3;

	protected Minerware $plugin;

	protected bool $hasStarted = false;

	protected bool $hasEnded = false;

	protected float $startTime;

	/** @var Player[] */
	protected array $winners = [];

	/** @var Player[] */
	protected array $losers = [];

	/** @var Block[] */
	protected array $changedBlocks = [];

	public function __construct(protected Arena $arena) {
		$this->plugin = $this->arena->getPlugin();
	}

	public function getArena() : Arena {
		return $this->arena;
	}

	public function getPlugin() : Minerware {
		return $this->plugin;
	}

	public function hasStarted() : bool {
		return $this->hasStarted;
	}

	public function hasEnded() : bool {
		return $this->hasEnded;
	}

	public function isRunning() : bool {
		return $this->hasStarted && !$this->hasEnded;
	}

	public function getStartTime() : float {
		return $this->startTime;
	}

	public function getTimeLeft() : float {
		return ($this->startTime + $this->getGameDuration()) - microtime(true);
	}

	public function addWinner(Player $player) : void {
		(new PlayerWinMicrogameEvent($player, $this))->call();
		$this->winners[$player->getId()] = $player;
	}

	public function isWinner(Player $player) : bool {
		return isset($this->winners[$player->getId()]);
	}

	/**
	 * @return Player[]
	 */
	public function getWinners() : array {
		return $this->winners;
	}

	public function addLoser(Player $player) : void {
		(new PlayerLoseMicrogameEvent($player, $this))->call();
		$this->losers[$player->getId()] = $player;
	}

	public function isLoser(Player $player) : bool {
		return isset($this->losers[$player->getId()]);
	}

	/**
	 * @return Player[]
	 */
	public function getLosers() : array {
		return $this->losers;
	}

	abstract public function getName() : string;
	abstract public function getLevel() : Level;
	abstract public function getGameDuration() : float; // in seconds
	abstract public function getRecompensePoints() : int;

	public function start() : void {
		if ($this instanceof Listener) {
			$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
		}
		$this->startTime = microtime(true);
		$this->hasStarted = true;
		(new MicrogameStartEvent($this))->call();
	}

	abstract public function tick() : void;

	/**
	 * Fills the player XP bar based on the time left.
	 */
	protected function updateTimeBar(float $timeLeft) : void {
		foreach ($this->arena->getPlayers() as $player) {
			$player->getXpManager()->setXpAndProgress((int) $timeLeft, $timeLeft / $this->getGameDuration());
		}
	}

	/**
	 * Places a block on every platform of Map::MINI_PLATFORMS (or the given subset of keys),
	 * recording the replaced blocks in $this->changedBlocks.
	 *
	 * @param int[] $keys
	 */
	protected function setMiniPlatforms(Block $block, bool $update, array $keys = []) : void {
		$map = $this->arena->getMap();
		$world = $this->arena->getWorld();
		$minPos = $map->getPlatformMinPos();
		$keys = $keys === [] ? array_keys(Map::MINI_PLATFORMS) : $keys;
		foreach ($keys as $key) {
			foreach (Map::MINI_PLATFORMS[$key] as $blockPos) {
				$this->changedBlocks[] = $world->getBlockAt((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]));
				$world->setBlockAt((int) ($minPos->x + $blockPos[0]), (int) ($minPos->y + $blockPos[1]), (int) ($minPos->z + $blockPos[2]), $block, $update);
			}
		}
	}

	public function end() : void {
		if ($this instanceof Listener) {
			HandlerListManager::global()->unregisterAll($this);
		}

		foreach ($this->changedBlocks as $block) {
			$this->arena->getWorld()->setBlock($block->getPosition(), $block, false);
		}

		$this->hasEnded = true;
		(new MicrogameEndEvent($this))->call();
	}
}