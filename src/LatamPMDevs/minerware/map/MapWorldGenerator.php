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

namespace LatamPMDevs\minerware\map;

use LatamPMDevs\minerware\Minerware;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\World;
use ZipArchive;
use function mkdir;

final class MapWorldGenerator {

	public static function getZip(Map $map) : string {
		return Minerware::getInstance()->getDataFolder() . "database" . DIRECTORY_SEPARATOR . "backups" . DIRECTORY_SEPARATOR . $map->getName() . ".zip";
	}

	public static function generate(Map $map, string $uniqueId) : World {
		$worldPath = Minerware::getInstance()->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . $map->getName() . "-" . $uniqueId . DIRECTORY_SEPARATOR;

		# Create files
		@mkdir($worldPath);
		$backup = self::getZip($map);
		$zip = new ZipArchive();
		$zip->open($backup);
		$zip->extractTo($worldPath);
		$zip->close();

		# Get World
		if (Minerware::getInstance()->getServer()->getWorldManager()->loadWorld($map->getName() . "-" . $uniqueId, true)) {
			return Minerware::getInstance()->getServer()->getWorldManager()->getWorldByName($map->getName() . "-" . $uniqueId);
		}

		throw new AssumptionFailedError("Error Generating world");
	}
}