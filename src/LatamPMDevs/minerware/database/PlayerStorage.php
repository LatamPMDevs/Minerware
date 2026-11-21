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
use LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\IvanCraft623\languages\Language;
use LatamPMDevs\minerware\leaderboard\LeaderboardEntry;
use LatamPMDevs\minerware\Minerware;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\poggit\libasynql\DataConnector;
use LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\poggit\libasynql\libasynql;
use LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\poggit\libasynql\SqlError;
use function is_string;
use function mkdir;
use function time;

/**
 * The statistics and language choices of the players, in the database.
 */
final class PlayerStorage {
	use SingletonTrait;

	private Minerware $plugin;

	private DataConnector $database;

	public Closure $onError;

	public function __construct() {
		$this->plugin = Minerware::getInstance();
		@mkdir($this->plugin->getDataFolder() . "database" . DIRECTORY_SEPARATOR, 0777, true);
		$this->onError = function (SqlError $result) : void {
			$this->plugin->getLogger()->emergency($result->getQuery() . ' - ' . $result->getErrorMessage());
		};
		$this->connect();
	}

	public function close() : void {
		$this->database->close();
	}

	private function connect() : void {
		$configData = $this->plugin->getConfig()->get("database");
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
}