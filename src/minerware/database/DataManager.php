<?php

/**
 *  ███╗   ███╗██╗███╗   ██╗███████╗██████╗ ██╗    ██╗ █████╗ ██████╗ ███████╗
 *  ████╗ ████║██║████╗  ██║██╔════╝██╔══██╗██║    ██║██╔══██╗██╔══██╗██╔════╝
 *  ██╔████╔██║██║██╔██╗ ██║█████╗  ██████╔╝██║ █╗ ██║███████║██████╔╝█████╗
 *  ██║╚██╔╝██║██║██║╚██╗██║██╔══╝  ██╔══██╗██║███╗██║██╔══██║██╔══██╗██╔══╝
 *  ██║ ╚═╝ ██║██║██║ ╚████║███████╗██║  ██║╚███╔███╔╝██║  ██║██║  ██║███████╗
 *  ╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝ ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝
 *
 * This is a private project, your not allow to redistribute nor resell it.
 * The only ones with that power are this project's contributors.
 *
 * Copyright 2021 © Minerware
 */

declare(strict_types=1);

namespace minerware\database;

use minerware\arena\Map;
use minerware\Minerware;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use function file_exists;
use function mkdir;
use function opendir;
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
	}

	/**
	 * TODO:: Add multi storage type support.
	 */

	public function getPlayerData(Player|string $player): ?DataHolder {
		$filePath = "players" . DIRECTORY_SEPARATOR . (($player instanceof Player) ? $player->getName() : $player) . "." . $this->playerStorageType;
		$path = $this->pluginPath . "database" . DIRECTORY_SEPARATOR . $filePath;
		if (file_exists($path)) {
			return new DataHolder((new Config($path, $this->formats[$this->playerStorageType]))->getAll());
		}

		return null;
	}

	public function loadMaps(): bool {
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

	public function getMapData(string $map): ?DataHolder {
		$filePath = "maps" . DIRECTORY_SEPARATOR . $map . ".json";
		$path = $this->pluginPath . "database" . DIRECTORY_SEPARATOR . $filePath;
		if (file_exists($path)) {
			return new DataHolder((new Config($path, Config::JSON))->getAll());
		}

		return null;
	}

	public function saveMapData(DataHolder $dataHolder): void {
		$filePath = "maps" . DIRECTORY_SEPARATOR . $dataHolder->getString("name") . ".json";
		$path = $this->pluginPath . "database" . DIRECTORY_SEPARATOR . $filePath;
		(new Config($path, Config::JSON, $dataHolder->getAll()))->save();
		Map::$maps[] = new Map($dataHolder);
	}

	//TODO:: #2 Fix $lobby no being null.
	public function getLobby(): ?World {
		$path = $this->pluginPath . "lobby.yml";
		$name = (new Config($path, Config::YAML))->get("lobby");
		if ($name !== null && $name !== false) {
			if ($this->plugin->getServer()->getWorldManager()->loadWorld($name, true)) {
				return $this->plugin->getServer()->getWorldManager()->getWorldByName($name);
			}
		}

		return null;
	}

	public function setLobby(string $name): void {
		$path = $this->pluginPath . "lobby.yml";
		$config = new Config($path, Config::YAML);
		$config->set("lobby", $name);
		$config->save();
	}
}
