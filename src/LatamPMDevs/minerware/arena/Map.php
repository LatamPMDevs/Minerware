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

namespace LatamPMDevs\minerware\arena;

use LatamPMDevs\minerware\database\DataHolder;
use LatamPMDevs\minerware\Minerware;
use LatamPMDevs\minerware\utils\Utils;
use pocketmine\math\Vector3;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\World;
use ZipArchive;
use function mkdir;

final class Map {

	public static array $maps = [];

	private string $name;

	private Vector3 $platformMinPos;

	private Vector3 $platformMaxPos;

	/** @var Vector3[] */
	private array $spawns = [];

	private Vector3 $winnersCage;

	private Vector3 $lossersCage;

	public static function getByName(string $name) : ?self {
		foreach (self::$maps as $map) {
			if ($map->getName() === $name) {
				return $map;
			}
		}

		return null;
	}

	public function __construct(private DataHolder $data) {
		$this->name = $data->getString("name");

		$platform = $data->getArray("platform");
		$minMax = Utils::calculateMinAndMaxPos(
			new Vector3($platform["pos1"]["X"], $platform["pos1"]["Y"], $platform["pos1"]["Z"]),
			new Vector3($platform["pos2"]["X"], $platform["pos2"]["Y"], $platform["pos2"]["Z"])
		);
		$this->platformMinPos = $minMax[0];
		$this->platformMaxPos = $minMax[1];

		foreach ($data->getArray("spawns") as $spawnData) {
			$this->spawns[] = new Vector3($spawnData["X"], $spawnData["Y"], $spawnData["Z"]);
		}
		$cages = $data->getArray("cages");
		$this->winnersCage = new Vector3($cages["winners"]["X"], $cages["winners"]["Y"], $cages["winners"]["Z"]);
		$this->lossersCage = new Vector3($cages["lossers"]["X"], $cages["lossers"]["Y"], $cages["lossers"]["Z"]);

		self::$maps[] = $this;
	}

	public function getName() : string {
		return $this->name;
	}

	public function getPlatformMinPos() : Vector3 {
		return $this->platformMinPos;
	}

	public function getPlatformMaxPos() : Vector3 {
		return $this->platformMaxPos;
	}

	public function getSpawns() : array {
		return $this->spawns;
	}

	public function getWinnersCage() : Vector3 {
		return $this->winnersCage;
	}

	public function getLossersCage() : Vector3 {
		return $this->lossersCage;
	}

	public function getData() : DataHolder {
		return $this->data;
	}

	public function getZip() : string {
		return Minerware::getInstance()->getDataFolder() . "database" . DIRECTORY_SEPARATOR . "backups" . DIRECTORY_SEPARATOR . $this->name . ".zip";
	}

	public function generateWorld(string $uniqueId) : World {
		$worldPath = Minerware::getInstance()->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . $this->name . "-" . $uniqueId . DIRECTORY_SEPARATOR;

		# Create files
		@mkdir($worldPath);
		$backup = $this->getZip();
		$zip = new ZipArchive();
		$zip->open($backup);
		$zip->extractTo($worldPath);
		$zip->close();

		#Get World
		if (Minerware::getInstance()->getServer()->getWorldManager()->loadWorld($this->name . "-" . $uniqueId)) {
			return Minerware::getInstance()->getServer()->getWorldManager()->getWorldByName($this->name . "-" . $uniqueId);
		}

		throw new AssumptionFailedError("Error Generating world");
	}
}
