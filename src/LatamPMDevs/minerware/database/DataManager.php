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
use LatamPMDevs\minerware\leaderboard\LeaderboardEntry;
use LatamPMDevs\minerware\map\Map;
use LatamPMDevs\minerware\map\MapManager;
use LatamPMDevs\minerware\map\world\MapTemplates;
use LatamPMDevs\minerware\Minerware;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;
use RuntimeException;
use function array_map;
use function basename;
use function copy;
use function file_exists;
use function glob;
use function is_float;
use function is_int;
use function is_string;
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

		$this->onError = function (SqlError $result) : void {
			$this->plugin->getLogger()->emergency($result->getQuery() . ' - ' . $result->getErrorMessage());
		};
		$this->createContext();
		$this->installBundledMaps();
		foreach ($this->plugin->getResources() as $path => $_) {
			if (str_starts_with($path, "languages/") && str_ends_with($path, ".ini")) {
				$this->plugin->saveResource($path, true);
			}
		}

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
		$this->database->executeGeneric('table.languages');
		$this->database->executeSelect('table.check', [], static function () : void {
		}, function (SqlError $error) : void {
			$this->plugin->getLogger()->emergency("The database was made by an older version, delete it and restart: " . $error->getErrorMessage());
		});
	}

	/**
	 * Statistics of the player, found by their UUID.
	 *
	 * @param callable(?PlayerData) : void $onSuccess
	 * @param bool $nonNull give zeros instead of null to a player with no record
	 */
	public function getPlayerData(Player $player, callable $onSuccess, ?callable $onError = null, bool $nonNull = false) : void {
		$name = $player->getName();
		$this->database->executeSelect('data.players.get', [
			"uuid" => $player->getUniqueId()->toString()
		], function (array $rows) use ($name, $onSuccess, $nonNull) : void {
			$playerdata = null;
			if (isset($rows[0])) {
				$playerdata = PlayerData::jsonDeserialize($rows[0]);
			} elseif ($nonNull) {
				$playerdata = new PlayerData($name, time(), 0, 0, 0, 0, 0, 0);
			}
			$onSuccess($playerdata);
		}, $onError ?? $this->onError);
	}

	/**
	 * Statistics of the player last seen with this name (case-insensitive).
	 *
	 * @param callable(?PlayerData) : void $onSuccess
	 */
	public function getPlayerDataByName(string $name, callable $onSuccess, ?callable $onError = null) : void {
		$this->database->executeSelect('data.players.getByName', [
			"name" => $name
		], function (array $rows) use ($onSuccess) : void {
			$onSuccess(isset($rows[0]) ? PlayerData::jsonDeserialize($rows[0]) : null);
		}, $onError ?? $this->onError);
	}

	/**
	 * The players with most wins, best first.
	 *
	 * @param callable(list<LeaderboardEntry>) : void $onSuccess
	 */
	public function getTopWins(int $limit, callable $onSuccess, ?callable $onError = null) : void {
		$this->database->executeSelect('data.players.getTop', [
			"limit" => $limit
		], static function (array $rows) use ($onSuccess) : void {
			$entries = [];
			foreach ($rows as $row) {
				$entry = LeaderboardEntry::fromRow($row);
				if ($entry !== null) {
					$entries[] = $entry;
				}
			}
			$onSuccess($entries);
		}, $onError ?? $this->onError);
	}

	public function addWins(Player $player, int $count, ?callable $onSuccess = null, ?callable $onError = null) : void {
		$this->increment('addWins', $player, "count", $count, $onSuccess, $onError);
	}

	public function addBossgamesWon(Player $player, int $count, ?callable $onSuccess = null, ?callable $onError = null) : void {
		$this->increment('addBossgamesWon', $player, "count", $count, $onSuccess, $onError);
	}

	public function addMicrogamesWon(Player $player, int $count, ?callable $onSuccess = null, ?callable $onError = null) : void {
		$this->increment('addMicrogamesWon', $player, "count", $count, $onSuccess, $onError);
	}

	public function addGamesPlayed(Player $player, int $count, ?callable $onSuccess = null, ?callable $onError = null) : void {
		$this->increment('addGamesPlayed', $player, "count", $count, $onSuccess, $onError);
	}

	public function addMicrogamesPlayed(Player $player, int $count, ?callable $onSuccess = null, ?callable $onError = null) : void {
		$this->increment('addMicrogamesPlayed', $player, "count", $count, $onSuccess, $onError);
	}

	public function addTimePlayed(Player $player, int $seconds, ?callable $onSuccess = null, ?callable $onError = null) : void {
		$this->increment('addTimePlayed', $player, "seconds", $seconds, $onSuccess, $onError);
	}

	private function increment(string $query, Player $player, string $variable, int $amount, ?callable $onSuccess, ?callable $onError) : void {
		$this->database->executeGeneric('data.players.' . $query, [
			"uuid" => $player->getUniqueId()->toString(),
			"name" => $player->getName(),
			$variable => $amount
		], $onSuccess, $onError ?? $this->onError);
	}

	/**
	 * @param callable(?string) : void $onSuccess
	 */
	public function getPlayerLanguage(Player $player, callable $onSuccess, ?callable $onError = null) : void {
		$this->database->executeSelect('data.languages.get', [
			"uuid" => $player->getUniqueId()->toString()
		], function (array $rows) use ($onSuccess) : void {
			$locale = $rows[0]["locale"] ?? null;
			$onSuccess(is_string($locale) ? $locale : null);
		}, $onError ?? $this->onError);
	}

	public function setPlayerLanguage(Player $player, string $locale, ?callable $onSuccess = null, ?callable $onError = null) : void {
		$this->database->executeGeneric('data.languages.set', [
			"uuid" => $player->getUniqueId()->toString(),
			"locale" => $locale
		], $onSuccess, $onError ?? $this->onError);
	}

	/**
	 * Copies the maps that come with the plugin (resources/maps) on the first run,
	 * leaving alone any a server owner already has.
	 */
	private function installBundledMaps() : void {
		$resources = $this->plugin->getResources();
		foreach ($resources as $path => $file) {
			if (!str_starts_with($path, "maps/") || !str_ends_with($path, ".json")) {
				continue;
			}
			$name = basename($path, ".json");
			$zip = $resources["maps/" . $name . ".zip"] ?? null;
			$json = $this->getMapPath($name);
			$backup = $this->getMapBackupPath($name);
			if ($zip === null || file_exists($json) || file_exists($backup)) {
				continue;
			}
			copy($file->getPathname(), $json);
			copy($zip->getPathname(), $backup);
		}
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

	public function getMapPath(string $map) : string {
		return $this->pluginPath . "database" . DIRECTORY_SEPARATOR . "maps" . DIRECTORY_SEPARATOR . $map . ".json";
	}

	public function getMapBackupPath(string $map) : string {
		return $this->pluginPath . "database" . DIRECTORY_SEPARATOR . "backups" . DIRECTORY_SEPARATOR . $map . ".zip";
	}

	public function loadLanguages() : void {
		$translator = $this->plugin->getTranslator();
		foreach (glob($this->pluginPath . "languages" . DIRECTORY_SEPARATOR . "*.ini") ?: [] as $file) {
			$locale = basename($file, ".ini");
			$content = parse_ini_file($file, false, INI_SCANNER_RAW);
			if ($content === false) {
				throw new AssumptionFailedError("Missing or inaccessible required resource files");
			}
			$data = array_map(stripcslashes(...), $content);
			$translator->registerLanguage(new Language($locale, $data));
		}
		$l = $this->configString("default-language", "en_US");
		$lang = $translator->getLanguage($l);
		if ($lang === null) {
			$this->plugin->getLogger()->warning("Language $l not found, using en_US");
			$lang = $translator->getLanguage("en_US") ?? throw new AssumptionFailedError("The en_US language is missing");
		}
		$translator->setDefaultLanguage($lang);
	}

	/**
	 * @throws RuntimeException
	 */
	public function saveMap(Map $map) : void {
		MapTemplates::getInstance()->load($map);
		(new Config($this->getMapPath($map->getName()), Config::JSON, $map->toArray()))->save();
		MapManager::getInstance()->add($map);
	}

	public function getServerIp() : string {
		return $this->configString("server-ip", "yourserverip.net");
	}

	public function getJoinCooldown() : float {
		return max($this->configNumber("join-cooldown", 2), 0.0);
	}

	public function getMaxRuntimeArenas() : int {
		return max((int) $this->configNumber("max-runtime-arenas", 15), 1);
	}

	public function getArenaStartingTime() : int {
		return max((int) $this->configNumber("arena-starting-time", 120), 5);
	}

	public function getMinimumStartingPlayers() : int {
		return max((int) $this->configNumber("minimum-starting-players", 2), 2);
	}

	private function configString(string $key, string $default) : string {
		$value = $this->config->get($key, $default);
		return is_string($value) ? $value : $default;
	}

	private function configNumber(string $key, int|float $default) : int|float {
		$value = $this->config->get($key, $default);
		return is_int($value) || is_float($value) ? $value : $default;
	}
}
