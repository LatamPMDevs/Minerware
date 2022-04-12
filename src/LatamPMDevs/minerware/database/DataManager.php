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

use InvalidArgumentException;
use IvanCraft623\languages\Language;
use minerware\arena\Map;
use minerware\Minerware;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use pocketmine\world\World;
use function array_map;
use function basename;
use function file_exists;
use function glob;
use function mkdir;
use function opendir;
use function parse_ini_file;
use function readdir;
use function str_replace;

final class DataManager {
	use SingletonTrait;

	private Minerware $plugin;

	private string $pluginPath;

	private COnfig $config;

	private string $playerStorageType;

	/** @var array<string, int> */
	private array $formats;

	private ?World $lobby = null;

	public function __construct() {
		$this->plugin = Minerware::getInstance();
		$this->pluginPath = $this->plugin->getDataFolder();
		$this->config = $this->plugin->getConfig();

		$formats = Config::$formats;
		$formats["nbt"] = 6;
		$formats["namedtag"] = $formats["nbt"];
		$this->formats = $formats;

		$this->playerStorageType = $this->config->getNested("storage-format.player-data");

		@mkdir($this->pluginPath . "database" . DIRECTORY_SEPARATOR);
		@mkdir($this->pluginPath . "database" . DIRECTORY_SEPARATOR . "players" . DIRECTORY_SEPARATOR);
		@mkdir($this->pluginPath . "database" . DIRECTORY_SEPARATOR . "maps" . DIRECTORY_SEPARATOR);
		@mkdir($this->pluginPath . "database" . DIRECTORY_SEPARATOR . "backups" . DIRECTORY_SEPARATOR);

		$this->plugin->saveResource("languages/en_US.ini", true);
		$this->plugin->saveResource("languages/es_MX.ini", true);

		$name = $this->plugin->getConfig()->get("lobby", null);
		if ($name !== null) {
			if ($this->plugin->getServer()->getWorldManager()->loadWorld($name, true)) {
				$this->lobby = $this->plugin->getServer()->getWorldManager()->getWorldByName($name);
			}
		}
	}

	/**
	 * TODO:: Add multi storage type support.
	 */

	public function getPlayerData(Player|string $player) : ?DataHolder {
		$filePath = "players" . DIRECTORY_SEPARATOR . (($player instanceof Player) ? $player->getName() : $player) . "." . $this->playerStorageType;
		$path = $this->pluginPath . "database" . DIRECTORY_SEPARATOR . $filePath;
		if (file_exists($path)) {
			return new DataHolder((new Config($path, $this->formats[$this->playerStorageType]))->getAll());
		}

		return null;
	}

	public function loadMaps() : bool {
		if ($handle = opendir($this->pluginPath . "database" . DIRECTORY_SEPARATOR . 'maps' . DIRECTORY_SEPARATOR)) {
			while (false !== ($entry = readdir($handle))) {
				if ($entry !== '.' && $entry !== '..') {
					$map = str_replace('.json', '', $entry);
					new Map($this->getMapData($map));
				}
			}

			return true;
		}

		return false;
	}

	public function loadLanguages() : void {
		$translator = $this->plugin->getTranslator();
		foreach (glob($this->pluginPath . "languages" . DIRECTORY_SEPARATOR . "*.ini") as $file) {
			$locale = basename($file, ".ini");
			$data = array_map('\stripcslashes', Utils::assumeNotFalse(parse_ini_file($file, false, INI_SCANNER_RAW), "Missing or inaccessible required resource files"));
			$translator->registerLanguage(new Language($locale, $data));
		}
		$l = $this->plugin->getConfig()->get("default-language", "en_US");
		$lang = $translator->getLanguage($l) ?? throw new InvalidArgumentException("Language $l not found");
		$translator->setDefaultLanguage($lang);
	}

	public function getMapData(string $map) : ?DataHolder {
		$filePath = "maps" . DIRECTORY_SEPARATOR . $map . ".json";
		$path = $this->pluginPath . "database" . DIRECTORY_SEPARATOR . $filePath;
		if (file_exists($path)) {
			return new DataHolder((new Config($path, Config::JSON))->getAll());
		}

		return null;
	}

	public function saveMapData(DataHolder $dataHolder) : void {
		$filePath = "maps" . DIRECTORY_SEPARATOR . $dataHolder->getString("name") . ".json";
		$path = $this->pluginPath . "database" . DIRECTORY_SEPARATOR . $filePath;
		(new Config($path, Config::JSON, $dataHolder->getAll()))->save();
		Map::$maps[] = new Map($dataHolder);
	}

	public function getLobby() : ?World {
		return $this->lobby;
	}

	public function setLobby(World $lobby) : void {
		$this->lobby = $lobby;
		$config = $this->plugin->getConfig();
		$config->set("lobby", $lobby->getDisplayName());
		$config->save();
	}
}
