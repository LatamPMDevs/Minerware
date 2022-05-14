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

use Closure;
use InvalidArgumentException;
use IvanCraft623\languages\Language;
use LatamPMDevs\minerware\arena\Map;
use LatamPMDevs\minerware\Minerware;
use LatamPMDevs\minerware\utils\Utils;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;
use function array_map;
use function basename;
use function file_exists;
use function glob;
use function max;
use function mkdir;
use function opendir;
use function parse_ini_file;
use function readdir;
use function str_replace;
use function time;

final class DataManager {
	use SingletonTrait;

	private Minerware $plugin;

	private string $pluginPath;

	private Config $config;

	private DataConnector $database;

	private Config $jsonPlayersData;

	public Closure $onError;

	public function __construct() {
		$this->plugin = Minerware::getInstance();
		$this->pluginPath = $this->plugin->getDataFolder();
		$this->config = $this->plugin->getConfig();
		$this->createContext();

		@mkdir($this->pluginPath . "database" . DIRECTORY_SEPARATOR);
		@mkdir($this->pluginPath . "database" . DIRECTORY_SEPARATOR . "players" . DIRECTORY_SEPARATOR);
		@mkdir($this->pluginPath . "database" . DIRECTORY_SEPARATOR . "maps" . DIRECTORY_SEPARATOR);
		@mkdir($this->pluginPath . "database" . DIRECTORY_SEPARATOR . "backups" . DIRECTORY_SEPARATOR);

		$this->plugin->saveResource("languages/en_US.ini", true);
		$this->plugin->saveResource("languages/es_MX.ini", true);

		$this->onError = function (SqlError $result) : void {
			$this->plugin->getLogger()->emergency($result->getQuery() . ' - ' . $result->getErrorMessage());
		};
	}

	public function closeDatabase() : void {
		if (isset($this->database)) {
			$this->database->close();
		} elseif (isset($this->jsonPlayersData)) {
			$this->jsonPlayersData->save();
		}
	}

	public function createContext() : void {
		$configData = $this->config->get("database");
		switch ((string) $configData["type"]) {
			case 'json':
			case 'js':
				$this->isJsonStorageType = true;
				$this->jsonPlayersData = new Config(Utils::resolvePath($this->pluginPath, (string) $configData["json"]["file"]), Config::JSON);
				break;

			default:
				$this->database = libasynql::create($this->plugin, $configData, [
					"sqlite" => "database/sqlite.sql",
					"mysql"  => "database/mysql.sql",
				]);

				$this->database->executeGeneric('table.players');
				break;
		}
	}

	public function getPlayerData(string $name, callable $onSuccess, ?callable $onError = null, bool $nonNull = false) : void {
		if (isset($this->database)) {
			$this->database->executeSelect('data.players.get', [
				"name" => $name
			], function (array $rows) use ($name, $onSuccess, $nonNull) {
				$playerdata = null;
				if (isset($rows[0])) {
					$playerdata = PlayerData::jsonDeserialize($rows[0]);
				} elseif ($nonNull) {
					$playerdata = new PlayerData($name, time(), 0, 0, 0, 0, 0, 0);
				}
				$onSuccess($playerdata);
			}, $onError ?? $this->onError);
		} elseif (isset($this->jsonPlayersData)) {
			$playerdata = null;
			if ($this->jsonPlayersData->exists($name)) {
				$playerdata = PlayerData::jsonDeserialize($this->jsonPlayersData->get($name));
			} elseif ($nonNull) {
				$playerdata = new PlayerData($name, time(), 0, 0, 0, 0, 0, 0);
			}
			$onSuccess($playerdata);
		}
	}

	public function addPlayer(
		string $name,
		int $gamesPlayed = 0,
		int $gamesWon = 0,
		int $lostGames = 0,
		int $microgamesPlayed = 0,
		int $microgamesWon = 0,
		int $lostMicrogames = 0,
		?callable $onSuccess = null,
		?callable $onError = null
	) : void {
		$values = [
			"name" => $name,
			"gamesPlayed" => $gamesPlayed,
			"gamesWon" => $gamesWon,
			"lostGames" => $lostGames,
			"microgamesPlayed" => $microgamesPlayed,
			"microgamesWon" => $microgamesWon,
			"lostMicrogames" => $lostMicrogames
		];
		if (isset($this->database)) {
			$this->database->executeGeneric('data.players.add', $values, $onSuccess, $onError ?? $this->onError);
		} elseif (isset($this->jsonPlayersData)) {
			if (!$this->jsonPlayersData->exists($name)) {
				$this->jsonPlayersData->set($name, $values);
			}
			if ($onSuccess !== null) {
				$onSuccess();
			}
		}
	}

	public function addGamesPlayed(string $name, int $count, ?callable $onSuccess = null, ?callable $onError = null) : void {
		if (isset($this->database)) {
			$this->database->executeGeneric('data.players.addGamesPlayed', [
				"name" => $name,
				"count" => $count
			], $onSuccess, $onError ?? $this->onError);
		} elseif (isset($this->jsonPlayersData)) {
			$data = $this->jsonPlayersData->get($name, null);
			if ($data !== null) {
				$data["gamesPlayed"] = ($data["gamesPlayed"] ?? 0) + $count;
				$this->jsonPlayersData->set($name, $data);
			} else {
				$this->addPlayer($name, $count);
			}
			if ($onSuccess !== null) {
				$onSuccess();
			}
		}
	}

	public function addGamesWon(string $name, int $count, ?callable $onSuccess = null, ?callable $onError = null) : void {
		if (isset($this->database)) {
			$this->database->executeGeneric('data.players.addGamesWon', [
				"name" => $name,
				"count" => $count
			], $onSuccess, $onError ?? $this->onError);
		} elseif (isset($this->jsonPlayersData)) {
			$data = $this->jsonPlayersData->get($name, null);
			if ($data !== null) {
				$data["gamesWon"] = ($data["gamesWon"] ?? 0) + $count;
				$this->jsonPlayersData->set($name, $data);
			} else {
				$this->addPlayer($name, 0, $count);
			}
			if ($onSuccess !== null) {
				$onSuccess();
			}
		}
	}

	public function addLostGames(string $name, int $count, ?callable $onSuccess = null, ?callable $onError = null) : void {
		if (isset($this->database)) {
			$this->database->executeGeneric('data.players.addLostGames', [
				"name" => $name,
				"count" => $count
			], $onSuccess, $onError ?? $this->onError);
		} elseif (isset($this->jsonPlayersData)) {
			$data = $this->jsonPlayersData->get($name, null);
			if ($data !== null) {
				$data["lostGames"] = ($data["lostGames"] ?? 0) + $count;
				$this->jsonPlayersData->set($name, $data);
			} else {
				$this->addPlayer($name, 0, 0, $count);
			}
			if ($onSuccess !== null) {
				$onSuccess();
			}
		}
	}

	public function addMicrogamesPlayed(string $name, int $count, ?callable $onSuccess = null, ?callable $onError = null) : void {
		if (isset($this->database)) {
			$this->database->executeGeneric('data.players.addMicrogamesPlayed', [
				"name" => $name,
				"count" => $count
			], $onSuccess, $onError ?? $this->onError);
		} elseif (isset($this->jsonPlayersData)) {
			$data = $this->jsonPlayersData->get($name, null);
			if ($data !== null) {
				$data["microgamesPlayed"] = ($data["microgamesPlayed"] ?? 0) + $count;
				$this->jsonPlayersData->set($name, $data);
			} else {
				$this->addPlayer($name, 0, 0, 0, $count);
			}
			if ($onSuccess !== null) {
				$onSuccess();
			}
		}
	}

	public function addMicrogamesWon(string $name, int $count, ?callable $onSuccess = null, ?callable $onError = null) : void {
		if (isset($this->database)) {
			$this->database->executeGeneric('data.players.addMicrogamesWon', [
				"name" => $name,
				"count" => $count
			], $onSuccess, $onError ?? $this->onError);
		} elseif (isset($this->jsonPlayersData)) {
			$data = $this->jsonPlayersData->get($name, null);
			if ($data !== null) {
				$data["microgamesWon"] = ($data["microgamesWon"] ?? 0) + $count;
				$this->jsonPlayersData->set($name, $data);
			} else {
				$this->addPlayer($name, 0, 0, 0, 0, $count);
			}
			if ($onSuccess !== null) {
				$onSuccess();
			}
		}
	}

	public function addLostMicrogames(string $name, int $count, ?callable $onSuccess = null, ?callable $onError = null) : void {
		if (isset($this->database)) {
			$this->database->executeGeneric('data.players.addLostMicrogames', [
				"name" => $name,
				"count" => $count
			], $onSuccess, $onError ?? $this->onError);
		} elseif (isset($this->jsonPlayersData)) {
			$data = $this->jsonPlayersData->get($name, null);
			if ($data !== null) {
				$data["lostMicrogames"] = ($data["lostMicrogames"] ?? 0) + $count;
				$this->jsonPlayersData->set($name, $data);
			} else {
				$this->addPlayer($name, 0, 0, 0, 0, 0, $count);
			}
			if ($onSuccess !== null) {
				$onSuccess();
			}
		}
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
			$content = parse_ini_file($file, false, INI_SCANNER_RAW);
			if ($content === false) {
				throw new AssumptionFailedError("Missing or inaccessible required resource files");
			}
			$data = array_map('\stripcslashes', $content);
			$translator->registerLanguage(new Language($locale, $data));
		}
		$l = $this->config->get("default-language", "en_US");
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

	public function getServerIp() : string {
		return $this->config->get("server-ip", "yourserverip.net");
	}

	public function getMaxRuntimeArenas() : int {
		return max((int) $this->config->get("max-runtime-arenas", 15), 1);
	}

	public function getArenaStartingTime() : int {
		return max((int) $this->config->get("arena-starting-time", 120), 5);
	}

	public function getMinimumStartingPlayers() : int {
		return max((int) $this->config->get("minimum-starting-players", 4), 2);
	}
}
