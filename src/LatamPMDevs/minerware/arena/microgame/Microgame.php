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

use LatamPMDevs\minerware\arena\Arena;
use LatamPMDevs\minerware\event\arena\microgame\MicrogameEndEvent;
use LatamPMDevs\minerware\event\arena\microgame\MicrogameStartEvent;
use LatamPMDevs\minerware\event\arena\microgame\PlayerLoseMicrogameEvent;
use LatamPMDevs\minerware\event\arena\microgame\PlayerWinMicrogameEvent;
use LatamPMDevs\minerware\Minerware;

use pocketmine\player\Player;
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
		$this->startTime = microtime(true);
		$this->hasStarted = true;
		(new MicrogameStartEvent($this))->call();
	}

	abstract public function tick() : void;

	public function end() : void {
		$this->hasEnded = true;
		(new MicrogameEndEvent($this))->call();
	}
}
