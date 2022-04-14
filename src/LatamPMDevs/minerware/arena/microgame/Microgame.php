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
use LatamPMDevs\minerware\Minerware;

use pocketmine\player\Player;
use function microtime;

abstract class Microgame {

	protected Minerware $plugin;

	protected bool $hasStarted = false;

	protected bool $hasEnded = false;

	protected float $startTime;

	/** @var Player[] */
	protected array $winners = [];

	/** @var Player[] */
	protected array $losers = [];

	public function __construct(protected arena $arena) {
		$this->plugin = $this->arena->getPlugin();
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
	abstract public function start() : void;
	abstract public function tick() : void;
	abstract public function end() : void;
}
