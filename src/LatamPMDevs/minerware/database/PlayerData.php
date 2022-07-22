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
		protected int $wins,
		protected int $bossgamesWon,
		protected int $microgamesWon,
		protected int $gamesPlayed,
		protected int $microgamesPlayed,
		protected int $timePlayed
	) {
	}

	public function getName() : string {
		return $this->name;
	}

	public function getGenerationTime() : int {
		return $this->generationTime;
	}

	public function getWins() : int {
		return $this->wins;
	}

	public function getBossgamesWon() : int {
		return $this->bossgamesWon;
	}

	public function getMicrogamesWon() : int {
		return $this->microgamesWon;
	}

	public function getGamesPlayed() : int {
		return $this->gamesPlayed;
	}

	public function getMicrogamesPlayed() : int {
		return $this->microgamesPlayed;
	}

	public function getTimePlayed() : int {
		return $this->timePlayed;
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
			"wins" => $this->wins,
			"bossgamesWon" => $this->bossgamesWon,
			"microgamesWon" => $this->microgamesWon,
			"gamesPlayed" => $this->gamesPlayed,
			"microgamesPlayed" => $this->microgamesPlayed,
			"timePlayed" => $this->timePlayed
		];
	}

	/**
	 * Returns a PlayerData from properties created in an array by {@link PlayerData#jsonSerialize}
	 * @param mixed[] $data
	 * @phpstan-param array{
	 * 	name: string,
	 * 	generationTime?: int,
	 * 	wins?: int,
	 * 	bossgamesWon?: int,
	 * 	microgamesWon?: int,
	 * 	gamesPlayed?: int,
	 * 	microgamesPlayed?: int,
	 * 	timePlayed?: int
	 * } $data
	 */
	public static function jsonDeserialize(array $data) : PlayerData {
		return new PlayerData(
			(string) $data["name"],
			(int) ($data["generationTime"] ?? time()),
			(int) ($data["wins"] ?? 0),
			(int) ($data["bossgamesWon"] ?? 0),
			(int) ($data["microgamesWon"] ?? 0),
			(int) ($data["gamesPlayed"] ?? 0),
			(int) ($data["microgamesPlayed"] ?? 0),
			(int) ($data["timePlayed"] ?? 0)
		);
	}
}