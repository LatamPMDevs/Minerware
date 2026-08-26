<?php
declare(strict_types = 1);

namespace LatamPMDevs\minerware\libs\_f657fb06cbb97d69\jackmd\scorefactory;

use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntryAction;
use pocketmine\player\Player;
use function array_map;
use function array_values;

class ScoreFactory {

	private const OBJECTIVE_NAME = "objective";
	private const CRITERIA_NAME  = "dummy";

	private const MIN_LINES = 0;
	private const MAX_LINES = 15;

	/** @var ScoreCache[] */
	private static array $cache = [];

	/**
	 * Adds a scoreboard to the player if he doesn't have one.
	 * This should be the very first call to adding a scoreboard to the player.
	 *
	 * @param Player $player
	 * @param string $displayName   Name that will appear on the title of the scoreboard
	 * @param int    $slotOrder     Either SetDisplayObjectivePacket::SORT_ORDER_ASCENDING or SetDisplayObjectivePacket::SORT_ORDER_DESCENDING
	 * @param string $displaySlot   Choose one from SetDisplayObjectivePacket::DISPLAY_SLOT_xxx
	 * @param string $objectiveName default: objective
	 * @param string $criteriaName  default: dummy
	 * @return void
	 */
	public static function setObjective(
		Player $player,
		string $displayName,
		int $slotOrder = SetDisplayObjectivePacket::SORT_ORDER_ASCENDING,
		string $displaySlot = SetDisplayObjectivePacket::DISPLAY_SLOT_SIDEBAR,
		string $objectiveName = self::OBJECTIVE_NAME,
		string $criteriaName = self::CRITERIA_NAME
	): void {
		if (!$player->isConnected()) throw new ScoreFactoryException("Player is not connected.");

		$pk = new SetDisplayObjectivePacket();
		$pk->displaySlot = $displaySlot;
		$pk->objectiveName = $objectiveName;
		$pk->displayName = $displayName;
		$pk->criteriaName = $criteriaName;
		$pk->sortOrder = $slotOrder;

		if (self::hasCache($player)) self::removeCache($player);

		self::$cache[$player->getUniqueId()->getBytes()] = ScoreCache::init($player, $objectiveName, $pk);
	}

	/**
	 * Sends only the objective that includes the bare minimum scoreboard with the title and no lines.
	 * This should be the second call for sending a scoreboard to the player.
	 * After this, set lines and send the lines.
	 */
	public static function sendObjective(Player $player): void {
		if (!$player->isConnected()) throw new ScoreFactoryException("Player is not connected.");
		if (!self::hasCache($player)) throw new ScoreFactoryException("Cannot send score objective to a player without a scoreboard. Please call ScoreFactory::setObjective() beforehand.");

		// remove the previous scoreboard (if any) for update to take place
		self::removeObjective($player, false);

		$player->getNetworkSession()->sendDataPacket(self::getCache($player)->getObjectivePacket());
	}

	/**
	 * Removes the scoreboard from the player specified.
	 */
	public static function removeObjective(Player $player, bool $removeCache = false): void {
		if (!$player->isConnected()) throw new ScoreFactoryException("Player is not connected.");

		$objectiveName = self::hasCache($player) ? self::getCache($player)->getObjective() : self::OBJECTIVE_NAME;

		$pk = new RemoveObjectivePacket();
		$pk->objectiveName = $objectiveName;
		$player->getNetworkSession()->sendDataPacket($pk);

		if ($removeCache) self::removeCache($player);
	}

	/**
	 * Same as ScoreFactory::hasCache()
	 */
	public static function hasObjective(Player $player): bool {
		return self::hasCache($player);
	}

	/**
	 * Returns a list of online players having scoreboard.
	 *
	 * @return Player[]
	 */
	public static function getActivePlayers(): array {
		return array_values(array_map(static function(ScoreCache $cache) {
			return $cache->getPlayer();
		}, self::$cache));
	}

	/**
	 * Set a message at the line specified to the players' scoreboard.
	 */
	public static function setScoreLine(Player $player, int $line, string $message, ScorePacketEntryAction $type = ScorePacketEntryAction::CHANGE_FAKE_PLAYER): ScorePacketEntry {
		if (!$player->isConnected()) throw new ScoreFactoryException("Player is not connected.");
		if (!self::hasCache($player)) throw new ScoreFactoryException("Cannot set a score line to a player without a scoreboard. Please call ScoreFactory::setObjective() beforehand.");
		if ($line < self::MIN_LINES || $line > self::MAX_LINES) throw new ScoreFactoryException("Line: $line is out of range, expected value between " . self::MIN_LINES . " and " . self::MAX_LINES);

		$cache = self::getCache($player);

		$entry = new ScorePacketEntry();
		$entry->actorUniqueId = $player->getId();
		$entry->action = $type;
		$entry->objectiveName = $cache->getObjective();
		$entry->customName = $message;
		$entry->score = $line;
		$entry->scoreboardId = $line;

		$cache->setEntry($line, $entry);

		return $entry;
	}

	/**
	 * Send a single score line to the player.
	 * This should be called after setting and sending the objective
	 * and after setting the score line.
	 */
	public static function sendLine(Player $player, int $line, ScorePacketEntry $entry): void {
		if (!$player->isConnected()) throw new ScoreFactoryException("Player is not connected.");
		if (!self::hasCache($player)) throw new ScoreFactoryException("Cannot send score lines to a player without a scoreboard. Please call ScoreFactory::setObjective() beforehand.");

		self::removeScoreLine($player, $line, false);

		$entry->action = ScorePacketEntryAction::CHANGE_PLAYER;

		$player->getNetworkSession()->sendDataPacket(SetScorePacket::create([$entry]));
	}

	/**
	 * Send the score lines after setting and sending the objective.
	 * Furthermore, this should be called after setting the score lines.
	 *
	 * This will remove the previous score lines without removing them from cache
	 * and then send all the old and updated lines.
	 */
	public static function sendLines(Player $player): void {
		if (!$player->isConnected()) throw new ScoreFactoryException("Player is not connected.");
		if (!self::hasCache($player)) throw new ScoreFactoryException("Cannot send score lines to a player without a scoreboard. Please call ScoreFactory::setObjective() beforehand.");

		self::removeScoreLines($player, false);

		$cache = self::getCache($player);

		$player->getNetworkSession()->sendDataPacket(SetScorePacket::create(array_map(static function(ScorePacketEntry $entry) {
			$entry->action = ScorePacketEntryAction::CHANGE_PLAYER;
			return $entry;
		}, $cache->getEntriesList())));
	}

	/**
	 * Remove a single line from the scoreboard while keeping the board intact.
	 */
	public static function removeScoreLine(Player $player, int $line, bool $removeFromCache = true): void {
		if (!$player->isConnected()) throw new ScoreFactoryException("Player is not connected.");
		if (!self::hasCache($player)) throw new ScoreFactoryException("Cannot remove a score line from a player without a scoreboard. Please call ScoreFactory::setObjective() beforehand.");
		if ($line < self::MIN_LINES || $line > self::MAX_LINES) throw new ScoreFactoryException("Line: $line is out of range, expected value between " . self::MIN_LINES . " and " . self::MAX_LINES);

		$cache = self::getCache($player);
		if ($removeFromCache) $cache->removeEntry($line);

		$entry = new ScorePacketEntry();
		$entry->action = ScorePacketEntryAction::REMOVE;
		$entry->objectiveName = $cache->getObjective();
		$entry->score = $line;
		$entry->scoreboardId = $line;

		$player->getNetworkSession()->sendDataPacket(SetScorePacket::create([$entry]));
	}

	/**
	 * Remove all the lines from the players' scoreboard without actually removing the scoreboard.
	 * This will just remove all the lines.
	 */
	public static function removeScoreLines(Player $player, bool $removeFromCache = false): void {
		if (!$player->isConnected()) throw new ScoreFactoryException("Player is not connected.");
		if (!self::hasCache($player)) throw new ScoreFactoryException("Cannot remove a score line from a player without a scoreboard. Please call ScoreFactory::setObjective() beforehand.");

		$cache = self::getCache($player);
		if ($removeFromCache) $cache->setEntries([]);

		$entry = new ScorePacketEntry();
		$entry->action = ScorePacketEntryAction::REMOVE;
		$entry->objectiveName = $cache->getObjective();

		$player->getNetworkSession()->sendDataPacket(SetScorePacket::create($cache->getEntriesList()));
	}

	/**
	 * Returns true or false if a player has a scoreboard or not.
	 */
	private static function hasCache(Player $player): bool {
		return isset(self::$cache[$player->getUniqueId()->getBytes()]);
	}

	/**
	 * Returns an instance of ScoreCache.
	 * Note: Do check for if the player has cache via ScoreFactory::hasCache() beforehand!
	 */
	private static function getCache(Player $player): ScoreCache {
		return self::$cache[$player->getUniqueId()->getBytes()];
	}

	/**
	 * Remove the players cache.
	 */
	private static function removeCache(Player $player): void {
		unset(self::$cache[$player->getUniqueId()->getBytes()]);
	}
}