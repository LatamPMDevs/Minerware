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

namespace LatamPMDevs\minerware\database;

use JsonSerializable;
use function time;

class PlayerData implements JsonSerializable {

	public function __construct(
		protected string $name,
		protected int $generationTime, //Time at which the data was obtained
		protected int $gamesPlayed,
		protected int $gamesWon,
		protected int $lostGames,
		protected int $microgamesPlayed,
		protected int $microgamesWon,
		protected int $lostMicrogames
	) {
	}

	public function getName() : string {
		return $this->name;
	}

	public function getGenerationTime() : int {
		return $this->generationTime;
	}

	public function getGamesPlayed() : int {
		return $this->gamesPlayed;
	}

	public function getGamesWon() : int {
		return $this->gamesWon;
	}

	public function getLostGames() : int {
		return $this->lostGames;
	}

	public function getMicrogamesPlayed() : int {
		return $this->microgamesPlayed;
	}

	public function getMicrogamesWon() : int {
		return $this->microgamesWon;
	}

	public function getLostMicrogames() : int {
		return $this->lostMicrogames;
	}

	/**
	 * Returns an array of player data properties that can be serialized to json.
	 *
	 * @return mixed[]
	 */
	public function jsonSerialize() : array {
		return [
			"name" => $this->name,
			"generationTime" => $this->generationTime,
			"gamesPlayed" => $this->gamesPlayed,
			"gamesWon" => $this->gamesWon,
			"lostGames" => $this->lostGames,
			"microgamesPlayed" => $this->microgamesPlayed,
			"microgamesWon" => $this->microgamesWon,
			"lostMicrogames" => $this->lostMicrogames
		];
	}

	/**
	 * Returns a PlayerData from properties created in an array by {@link PlayerData#jsonSerialize}
	 * @param mixed[] $data
	 * @phpstan-param array{
	 * 	name: string,
	 * 	generationTime?: int,
	 * 	gamesPlayed?: int,
	 * 	gamesWon?: int,
	 * 	lostGames?: int,
	 * 	microgamesPlayed?: int,
	 * 	microgamesWon?: int,
	 * 	lostMicrogames?: int
	 * } $data
	 *
	 * @throws InvalidArgumentException
	 */
	public static function jsonDeserialize(array $data) : PlayerData {
		return new PlayerData(
			(string) $data["name"],
			(int) ($data["generationTime"] ?? time()),
			(int) ($data["gamesPlayed"] ?? 0),
			(int) ($data["gamesWon"] ?? 0),
			(int) ($data["lostGames"] ?? 0),
			(int) ($data["microgamesPlayed"] ?? 0),
			(int) ($data["microgamesWon"] ?? 0),
			(int) ($data["lostMicrogames"] ?? 0)
		);
	}
}