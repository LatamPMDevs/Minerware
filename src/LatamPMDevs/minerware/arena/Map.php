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
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;
use ZipArchive;
use function mkdir;

final class Map {

	private string $name;

	private array $platform;

	private array $platformMinAndMaxPos;

	public static array $maps = [];

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

		$this->platform = [
			"pos1" => new Vector3($platform["pos1"]["X"], $platform["pos1"]["Y"], $platform["pos1"]["Z"]),
			"pos2" => new Vector3($platform["pos2"]["X"], $platform["pos2"]["Y"], $platform["pos2"]["Z"])
		];
		$this->platformMinAndMaxPos = Utils::calculateMinAndMaxPos($this->platform["pos1"], $this->platform["pos2"]);

		self::$maps[] = $this;
	}

	public function getName() : string {
		return $this->name;
	}

	public function getPlatform() : array {
		return $this->platform;
	}

	public function getPlatformMinAndMaxPos() : array {
		return $this->platformMinAndMaxPos;
	}

	public function getData() : DataHolder {
		return $this->data;
	}

	public function getZip() : string {
		return Minerware::getInstance()->getDataFolder() . "database" . DIRECTORY_SEPARATOR . "backups" . DIRECTORY_SEPARATOR . $this->name . ".zip";
	}

	public function generateWorld(string $uniqueId) : ?World {
		$worldPath = Minerware::getInstance()->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . $this->name . "-" . $uniqueId . DIRECTORY_SEPARATOR;

		# Create files
		@mkdir($worldPath);
		$backup = $this->getZip();
		$zip = new ZipArchive();
		$zip->open($backup);
		$zip->extractTo($worldPath);
		$zip->close();

		# TODO: Edit NBT
		/*$nbt = new BigEndianNBTStream();
		$compound = $nbt->readCompressed(file_get_contents($path));
		if (!$compound instanceof CompoundTag) {
			throw new RuntimeException("Invalid data found in \"" . $this->name . ".dat\", expected " . CompoundTag::class . ", got " . (is_object($compound) ? get_class($compound): gettype($compound)));
		}
		$compound->setString("LevelName", $this->name . "-" . $uniqueId);
		$nbt2 = new BigEndianNBTStream();
		file_put_contents($path, $nbt2->writeCompressed($nbt));*/

		#Get World
		if (Minerware::getInstance()->getServer()->getWorldManager()->loadWorld($this->name . "-" . $uniqueId)) {
			return Minerware::getInstance()->getServer()->getWorldManager()->getWorldByName($this->name . "-" . $uniqueId);
		}

		return null;
	}
}
