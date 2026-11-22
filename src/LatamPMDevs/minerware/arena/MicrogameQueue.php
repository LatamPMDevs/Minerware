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

namespace LatamPMDevs\minerware\arena;

use InvalidArgumentException;
use LatamPMDevs\minerware\arena\microgame\Microgame;
use LatamPMDevs\minerware\arena\microgame\MicrogameManager;
use LogicException;
use pocketmine\utils\AssumptionFailedError;
use function array_rand;
use function array_slice;
use function count;
use function shuffle;

/**
 * The microgames of one arena, in play order.
 */
final class MicrogameQueue {

	/** @var list<Microgame> */
	private array $games = [];

	private int $position = 0;

	/**
	 * @throws AssumptionFailedError
	 */
	public static function create(Arena $arena) : self {
		$queue = new self();
		$manager = MicrogameManager::getInstance();
		$normal = $manager->getMicrogames();
		shuffle($normal);
		foreach ($normal as $class) {
			$queue->games[] = new $class($arena);
		}
		$boss = $manager->getBossgames();
		if (count($boss) === 0) {
			throw new AssumptionFailedError("No boss microgames are registered");
		}
		$queue->games[] = new $boss[array_rand($boss)]($arena);
		return $queue;
	}

	/**
	 * The microgame that comes next, null when all were played.
	 */
	public function peek() : ?Microgame {
		return $this->games[$this->position] ?? null;
	}

	public function advance() : void {
		$this->position++;
	}

	/**
	 * How many microgames were started.
	 */
	public function getPosition() : int {
		return $this->position;
	}

	/**
	 * Keeps only this many normal microgames before the boss game. Only before the first one starts.
	 */
	public function limit(int $normal) : void {
		if ($this->position > 0) {
			throw new LogicException("The queue has already started");
		}
		if ($normal < 1 || $normal > $this->getNormalCount()) {
			throw new InvalidArgumentException("The queue holds from 1 to " . $this->getNormalCount() . " microgames, $normal given.");
		}
		$boss = $this->games[count($this->games) - 1];
		$this->games = [...array_slice($this->games, 0, $normal), $boss];
	}

	/**
	 * Plays this microgame next: it takes the place of the next normal one, or comes before the boss game.
	 */
	public function setNext(Microgame $microgame) : void {
		if ($this->peek() === null) {
			throw new LogicException("There is no microgame left");
		}
		# Before the boss game nothing is replaced: the boss game stays last.
		$replaced = $this->position === $this->getNormalCount() ? 0 : 1;
		$this->games = [...array_slice($this->games, 0, $this->position), $microgame, ...array_slice($this->games, $this->position + $replaced)];
	}

	/**
	 * How many normal microgames the queue holds (the boss game is not counted).
	 */
	public function getNormalCount() : int {
		return count($this->games) - 1;
	}
}
