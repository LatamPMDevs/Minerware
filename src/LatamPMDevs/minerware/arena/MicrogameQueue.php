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

use LatamPMDevs\minerware\arena\microgame\Microgame;
use LatamPMDevs\minerware\arena\microgame\MicrogameManager;
use pocketmine\utils\AssumptionFailedError;
use function array_rand;
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
	 * How many normal microgames the queue holds (the boss game is not counted).
	 */
	public function getNormalCount() : int {
		return count($this->games) - 1;
	}
}