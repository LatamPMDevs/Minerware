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

use Closure;
use LatamPMDevs\minerware\Minerware;
use LatamPMDevs\minerware\utils\Utils;
use NetherGames\libasyncio\compression\CompressionFormat;
use NetherGames\libasyncio\FileOrDirectoryUncompressTask;
use NetherGames\libasyncio\RecursiveCompressor;
use pocketmine\world\World;
use RuntimeException;
use ZipArchive;
use function basename;
use function file_exists;
use function glob;
use function mkdir;
use function strlen;
use function substr;
use function unlink;

final class MapWorldGenerator {

	public static function getZip(Map $map) : string {
		return Minerware::getInstance()->getDataFolder() . "database" . DIRECTORY_SEPARATOR . "backups" . DIRECTORY_SEPARATOR . $map->getName() . "." . CompressionFormat::GZIP->getFileExtension();
	}

	/**
	 * Asynchronously extracts a map world backup and hands the ready world to $onComplete.
	 *
	 * @param Closure(?World $world): void $onComplete
	 */
	public static function generateAsync(Map $map, string $uniqueId, Closure $onComplete) : void {
		$plugin = Minerware::getInstance();
		$worldPath = $plugin->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . $map->getName() . "-" . $uniqueId . DIRECTORY_SEPARATOR;
		$backup = self::getZip($map);

		if (!file_exists($backup)) {
			$onComplete(null);
			return;
		}

		$plugin->getServer()->getAsyncPool()->submitTask(new FileOrDirectoryUncompressTask(
			$backup,
			$worldPath,
			function () use ($plugin, $map, $uniqueId, $onComplete) : void {
				$name = $map->getName() . "-" . $uniqueId;
				if ($plugin->getServer()->getWorldManager()->loadWorld($name, true)) {
					$onComplete($plugin->getServer()->getWorldManager()->getWorldByName($name));
					return;
				}
				$onComplete(null);
			},
			CompressionFormat::GZIP
		));
	}

	/**
	 * Converts legacy .zip backups to the .nggzip format used by libasyncio.
	 * Should be called once at plugin enable.
	 */
	public static function migrateLegacyBackups() : void {
		$backupsDir = Minerware::getInstance()->getDataFolder() . "database" . DIRECTORY_SEPARATOR . "backups" . DIRECTORY_SEPARATOR;
		foreach (glob($backupsDir . "*.zip") as $zipPath) {
			$name = substr(basename($zipPath), 0, -strlen(".zip"));
			$tmpDir = $backupsDir . ".migrate_" . $name . DIRECTORY_SEPARATOR;

			$zip = new ZipArchive();
			if ($zip->open($zipPath) !== true) {
				continue;
			}
			@mkdir($tmpDir);
			$zip->extractTo($tmpDir);
			$zip->close();

			try {
				RecursiveCompressor::compress($tmpDir, $backupsDir . $name, null, CompressionFormat::GZIP);
			} catch (RuntimeException) {
				# Failed to produce the new backup, leave the original in place.
				Utils::removeDir($tmpDir);
				continue;
			}

			Utils::removeDir($tmpDir);
			unlink($zipPath);
		}
	}
}