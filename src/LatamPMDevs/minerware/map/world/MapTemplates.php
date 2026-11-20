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

namespace LatamPMDevs\minerware\map\world;

use LatamPMDevs\minerware\database\DataManager;
use LatamPMDevs\minerware\map\Map;
use LatamPMDevs\minerware\Minerware;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\format\io\exception\CorruptedWorldException;
use pocketmine\world\format\io\exception\UnsupportedWorldFormatException;
use pocketmine\world\World;
use RuntimeException;
use ZipArchive;
use function array_values;
use function basename;
use function count;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function filesize;
use function glob;
use function is_dir;
use function is_file;
use function mkdir;
use function rmdir;
use function str_contains;
use function str_starts_with;
use function unlink;
use const GLOB_ONLYDIR;

/**
 * Opens every map once and creates arena worlds from it without copying any file.
 */
final class MapTemplates {
	use SingletonTrait;

	public const INSTANCE_MARKER = "minerware.instance";

	/** Holds the size and modification time of the zip a template folder was extracted from. */
	private const STAMP_FILE = "minerware.template";

	/** @var array<string, MapTemplate> */
	private array $templates = [];

	/**
	 * Extracts the map backup (only when it changed) and opens it as the template of the map.
	 *
	 * @throws RuntimeException
	 */
	public function load(Map $map) : void {
		$plugin = Minerware::getInstance();
		$zip = DataManager::getInstance()->getMapBackupPath($map->getName());
		$dir = $plugin->getDataFolder() . "database" . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . $map->getName();
		$this->extractIfStale($zip, $dir);
		$root = self::findWorldRoot($dir);

		$worldManager = $plugin->getServer()->getWorldManager();
		$providers = $worldManager->getProviderManager()->getMatchingProviders($root);
		if (count($providers) !== 1) {
			throw new RuntimeException("Unknown or ambiguous world format");
		}
		try {
			$provider = array_values($providers)[0]->fromPath($root, $plugin->getLogger());
		} catch (CorruptedWorldException | UnsupportedWorldFormatException $e) {
			throw new RuntimeException($e->getMessage(), 0, $e);
		}

		$previous = $this->templates[$map->getName()] ?? null;
		$this->templates[$map->getName()] = new MapTemplate($provider);
		$previous?->retire();
	}

	public function get(string $map) : ?MapTemplate {
		return $this->templates[$map] ?? null;
	}

	public function closeAll() : void {
		foreach ($this->templates as $template) {
			$template->retire();
		}
		$this->templates = [];
	}

	/**
	 * @throws RuntimeException
	 */
	public function createInstance(Map $map, string $uniqueId) : World {
		$plugin = Minerware::getInstance();
		$worldManager = $plugin->getServer()->getWorldManager();
		$folder = $map->getName() . "-" . $uniqueId;
		$path = $this->getWorldsPath() . $folder;

		if (!is_dir($path) && !mkdir($path)) {
			throw new RuntimeException("Could not create the folder of world '$folder'");
		}
		file_put_contents($path . DIRECTORY_SEPARATOR . self::INSTANCE_MARKER, $map->getName());

		$world = null;
		if ($worldManager->loadWorld($folder, true)) {
			$world = $worldManager->getWorldByName($folder);
		}
		if ($world === null) {
			self::deleteInstanceFolder($path);
			throw new RuntimeException("Could not load world '$folder'");
		}
		$world->setAutoSave(false);
		return $world;
	}

	public function removeInstance(World $world) : bool {
		$plugin = Minerware::getInstance();
		$path = $this->getWorldsPath() . $world->getFolderName();
		if (!$plugin->getServer()->getWorldManager()->unloadWorld($world, true)) {
			$plugin->getLogger()->warning("Could not unload world '" . $world->getFolderName() . "', it was not removed");
			return false;
		}
		self::deleteInstanceFolder($path);
		return true;
	}

	/**
	 * Removes the folders left by instances of a previous run.
	 */
	public function cleanupStaleInstances() : void {
		$worldManager = Minerware::getInstance()->getServer()->getWorldManager();
		foreach (glob($this->getWorldsPath() . "*" . DIRECTORY_SEPARATOR . self::INSTANCE_MARKER) ?: [] as $marker) {
			$dir = dirname($marker);
			if (!$worldManager->isWorldLoaded(basename($dir))) {
				self::deleteInstanceFolder($dir);
			}
		}
	}

	private function getWorldsPath() : string {
		return Minerware::getInstance()->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR;
	}

	/**
	 * Backups made by the map editor hold the world files at the root of the zip.
	 */
	public static function findWorldRoot(string $dir) : string {
		if (is_file($dir . DIRECTORY_SEPARATOR . "level.dat") || is_dir($dir . DIRECTORY_SEPARATOR . "db")) {
			return $dir;
		}
		$children = glob($dir . DIRECTORY_SEPARATOR . "*", GLOB_ONLYDIR) ?: [];
		if (count($children) === 1) {
			return $children[0];
		}
		return $dir;
	}

	private static function deleteInstanceFolder(string $path) : void {
		@unlink($path . DIRECTORY_SEPARATOR . self::INSTANCE_MARKER);
		@rmdir($path);
	}

	/**
	 * @throws RuntimeException
	 */
	private function extractIfStale(string $zipPath, string $dir) : void {
		if (!is_file($zipPath)) {
			throw new RuntimeException("Missing world backup");
		}
		$stamp = filesize($zipPath) . ":" . filemtime($zipPath);
		$stampFile = $dir . DIRECTORY_SEPARATOR . self::STAMP_FILE;
		if (is_file($stampFile) && file_get_contents($stampFile) === $stamp) {
			return;
		}

		$zip = new ZipArchive();
		if ($zip->open($zipPath) !== true) {
			throw new RuntimeException("Could not open the world backup");
		}
		$names = [];
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$name = (string) $zip->getNameIndex($i);
			if (str_starts_with($name, "/") || str_contains($name, "..") || str_contains($name, ":")) {
				$zip->close();
				throw new RuntimeException("The world backup has an unsafe entry '$name'");
			}
			$names[] = $name;
		}
		if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
			$zip->close();
			throw new RuntimeException("Could not create the template folder");
		}
		$extracted = $zip->extractTo($dir, $names);
		$zip->close();
		if (!$extracted) {
			throw new RuntimeException("Could not extract the world backup");
		}
		file_put_contents($stampFile, $stamp);
	}
}
