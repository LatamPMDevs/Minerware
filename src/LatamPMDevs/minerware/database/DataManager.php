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

namespace LatamPMDevs\minerware\database;

use Closure;
use InvalidArgumentException;
use IvanCraft623\languages\Language;
use LatamPMDevs\minerware\map\Map;
use LatamPMDevs\minerware\map\MapManager;
use LatamPMDevs\minerware\map\world\MapTemplates;
use LatamPMDevs\minerware\Minerware;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;
use RuntimeException;
use function array_map;
use function basename;
use function glob;
use function max;
use function mkdir;
use function parse_ini_file;
use function str_ends_with;
use function str_starts_with;
use function time;

final class DataManager {
	use SingletonTrait;

	private Minerware $plugin;

	private string $pluginPath;

	private Config $config;

	private DataConnector $database;

	public Closure $onError;

	public function __construct() {
		$this->plugin = Minerware::getInstance();
		$this->pluginPath = $this->plugin->getDataFolder();
		$this->config = $this->plugin->getConfig();

		@mkdir($this->pluginPath . "database" . DIRECTORY_SEPARATOR);
		@mkdir($this->pluginPath . "database" . DIRECTORY_SEPARATOR . "maps" . DIRECTORY_SEPARATOR);
		@mkdir($this->pluginPath . "database" . DIRECTORY_SEPARATOR . "backups" . DIRECTORY_SEPARATOR);

		$this->createContext();
		foreach ($this->plugin->getResources() as $path => $_) {
			if (str_starts_with($path, "languages/") && str_ends_with($path, ".ini")) {
				$this->plugin->saveResource($path, true);
			}
		}

		$this->onError = function (SqlError $result) : void {
			$this->plugin->getLogger()->emergency($result->getQuery() . ' - ' . $result->getErrorMessage());
		};
	}

	public function closeDatabase() : void {
		$this->database->close();
	}

	public function createContext() : void {
		$configData = $this->config->get("database");
		$this->database = libasynql::create($this->plugin, $configData, [
			"sqlite" => "database/sqlite.sql",
			"mysql"  => "database/mysql.sql",
		]);

		$this->database->executeGeneric('table.players');
	}

	public function getPlayerData(string $name, callable $onSuccess, ?callable $onError = null, bool $nonNull = false) : void {
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
	}

	public function addPlayer(
		string $name,
		int $wins = 0,
		int $bossgamesWon = 0,
		int $microgamesWon = 0,
		int $gamesPlayed = 0,
		int $microgamesPlayed = 0,
		int $timePlayed = 0,
		?callable $onSuccess = null,
		?callable $onError = null
	) : void {
		$values = [
			"name" => $name,
			"wins" => $wins,
			"bossgamesWon" => $bossgamesWon,
			"microgamesWon" => $microgamesWon,
			"gamesPlayed" => $gamesPlayed,
			"microgamesPlayed" => $microgamesPlayed,
			"timePlayed" => $timePlayed
		];
		$this->database->executeGeneric('data.players.add', $values, $onSuccess, $onError ?? $this->onError);
	}

	public function addWins(string $name, int $count, ?callable $onSuccess = null, ?callable $onError = null) : void {
		$this->database->executeGeneric('data.players.addWins', [
			"name" => $name,
			"count" => $count
		], $onSuccess, $onError ?? $this->onError);
	}

	public function addBossgamesWon(string $name, int $count, ?callable $onSuccess = null, ?callable $onError = null) : void {
		$this->database->executeGeneric('data.players.addBossgamesWon', [
			"name" => $name,
			"count" => $count
		], $onSuccess, $onError ?? $this->onError);
	}

	public function addMicrogamesWon(string $name, int $count, ?callable $onSuccess = null, ?callable $onError = null) : void {
		$this->database->executeGeneric('data.players.addMicrogamesWon', [
			"name" => $name,
			"count" => $count
		], $onSuccess, $onError ?? $this->onError);
	}

	public function addGamesPlayed(string $name, int $count, ?callable $onSuccess = null, ?callable $onError = null) : void {
		$this->database->executeGeneric('data.players.addGamesPlayed', [
			"name" => $name,
			"count" => $count
		], $onSuccess, $onError ?? $this->onError);
	}

	public function addMicrogamesPlayed(string $name, int $count, ?callable $onSuccess = null, ?callable $onError = null) : void {
		$this->database->executeGeneric('data.players.addMicrogamesPlayed', [
			"name" => $name,
			"count" => $count
		], $onSuccess, $onError ?? $this->onError);
	}

	public function addTimePlayed(string $name, int $seconds, ?callable $onSuccess = null, ?callable $onError = null) : void {
		$this->database->executeGeneric('data.players.addTimePlayed', [
			"name" => $name,
			"seconds" => $seconds
		], $onSuccess, $onError ?? $this->onError);
	}

	public function loadMaps() : void {
		$logger = $this->plugin->getLogger();
		$mapsPath = $this->pluginPath . "database" . DIRECTORY_SEPARATOR . "maps" . DIRECTORY_SEPARATOR;
		foreach (glob($mapsPath . "*.json") ?: [] as $file) {
			try {
				$map = Map::fromArray((new Config($file, Config::JSON))->getAll());
				MapTemplates::getInstance()->load($map);
				MapManager::getInstance()->add($map);
			} catch (InvalidArgumentException | RuntimeException $e) {
				$logger->warning("Skipping map '" . basename($file) . "': " . $e->getMessage());
			}
		}
	}

	public function getMapBackupPath(string $map) : string {
		return $this->pluginPath . "database" . DIRECTORY_SEPARATOR . "backups" . DIRECTORY_SEPARATOR . $map . ".zip";
	}

	public function loadLanguages() : void {
		$translator = $this->plugin->getTranslator();
		foreach (glob($this->pluginPath . "languages" . DIRECTORY_SEPARATOR . "*.ini") as $file) {
			$locale = basename($file, ".ini");
			$content = parse_ini_file($file, false, INI_SCANNER_RAW);
			if ($content === false) {
				throw new AssumptionFailedError("Missing or inaccessible required resource files");
			}
			$data = array_map(stripcslashes(...), $content);
			$translator->registerLanguage(new Language($locale, $data));
		}
		$l = $this->config->get("default-language", "en_US");
		$lang = $translator->getLanguage($l) ?? throw new InvalidArgumentException("Language $l not found");
		$translator->setDefaultLanguage($lang);
	}

	/**
	 * @throws RuntimeException
	 */
	public function saveMap(Map $map) : void {
		MapTemplates::getInstance()->load($map);
		$path = $this->pluginPath . "database" . DIRECTORY_SEPARATOR . "maps" . DIRECTORY_SEPARATOR . $map->getName() . ".json";
		(new Config($path, Config::JSON, $map->toArray()))->save();
		MapManager::getInstance()->add($map);
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
