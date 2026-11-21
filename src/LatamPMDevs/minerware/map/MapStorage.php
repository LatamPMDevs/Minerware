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

use InvalidArgumentException;
use LatamPMDevs\minerware\map\world\MapTemplates;
use LatamPMDevs\minerware\map\world\MapWorldException;
use LatamPMDevs\minerware\Minerware;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use function basename;
use function copy;
use function file_exists;
use function glob;
use function mkdir;
use function str_ends_with;
use function str_starts_with;

/**
 * The maps in the data folder: a JSON file with the data and a zip with the world.
 */
final class MapStorage {
	use SingletonTrait;

	private Minerware $plugin;

	private string $mapsPath;

	private string $backupsPath;

	public function __construct() {
		$this->plugin = Minerware::getInstance();
		$database = $this->plugin->getDataFolder() . "database" . DIRECTORY_SEPARATOR;
		$this->mapsPath = $database . "maps" . DIRECTORY_SEPARATOR;
		$this->backupsPath = $database . "backups" . DIRECTORY_SEPARATOR;
		@mkdir($this->mapsPath, 0777, true);
		@mkdir($this->backupsPath, 0777, true);
	}

	/**
	 * Copies the maps that come with the plugin (resources/maps) on the first run,
	 * leaving alone any a server owner already has.
	 */
	public function install() : void {
		$resources = $this->plugin->getResources();
		foreach ($resources as $path => $file) {
			if (!str_starts_with($path, "maps/") || !str_ends_with($path, ".json")) {
				continue;
			}
			$name = basename($path, ".json");
			$zip = $resources["maps/" . $name . ".zip"] ?? null;
			$json = $this->getPath($name);
			$backup = $this->getBackupPath($name);
			if ($zip === null || file_exists($json) || file_exists($backup)) {
				continue;
			}
			copy($file->getPathname(), $json);
			copy($zip->getPathname(), $backup);
		}
	}

	public function loadAll() : void {
		$logger = $this->plugin->getLogger();
		foreach (glob($this->mapsPath . "*.json") ?: [] as $file) {
			try {
				$map = Map::fromArray((new Config($file, Config::JSON))->getAll());
				MapTemplates::getInstance()->load($map);
				MapManager::getInstance()->add($map);
			} catch (InvalidArgumentException | MapWorldException $e) {
				$logger->warning("Skipping map '" . basename($file) . "': " . $e->getMessage());
			}
		}
	}

	/**
	 * @throws MapWorldException
	 */
	public function save(Map $map) : void {
		MapTemplates::getInstance()->load($map);
		(new Config($this->getPath($map->getName()), Config::JSON, $map->toArray()))->save();
		MapManager::getInstance()->add($map);
	}

	public function getPath(string $map) : string {
		return $this->mapsPath . $map . ".json";
	}

	public function getBackupPath(string $map) : string {
		return $this->backupsPath . $map . ".zip";
	}
}
