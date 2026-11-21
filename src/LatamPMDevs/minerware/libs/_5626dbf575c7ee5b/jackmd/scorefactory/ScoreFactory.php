<?php
declare(strict_types = 1);

namespace LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\jackmd\scorefactory;

use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntryAction;
use pocketmine\player\Player;
use function array_map;
use function array_values;
use function count;
use function dechex;

class ScoreFactory {

	private const MIN_LINES = 0;
	private const MAX_LINES = 15;

	private const OBJECTIVE_NAME = "objective";
	private const CRITERIA_NAME  = "dummy";

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

		// Removing the objective removes its lines from the client.
		if (self::hasCache($player)) self::getCache($player)->clearSent();

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
	 * Set a message at the line specified (1 to 15, the first one is the top) to the players' scoreboard.
	 * It is only shown after ScoreFactory::sendLines(). The type has to be one of the CHANGE actions.
	 */
	public static function setScoreLine(Player $player, int $line, string $message, ScorePacketEntryAction $type = ScorePacketEntryAction::CHANGE_FAKE_PLAYER): ScorePacketEntry {
		self::assertCanUse($player);
		self::assertLine($line);

		$cache = self::getCache($player);

		$entry = new ScorePacketEntry();
		$entry->actorUniqueId = $player->getId();
		$entry->action = $type;
		$entry->objectiveName = $cache->getObjective();
		// The client does not show a line with an empty name: use an invisible, unique one.
		$entry->customName = $message === "" ? "§" . dechex($line) : $message;
		$entry->score = $line;
		$entry->scoreboardId = $line;

		$cache->setEntry($line, $entry);

		return $entry;
	}

	/**
	 * Replaces every line of the players' scoreboard, in order, starting at the first one.
	 * Lines past the last message are dropped. It is only shown after ScoreFactory::sendLines().
	 *
	 * @param list<string> $messages
	 */
	public static function setScoreLines(Player $player, array $messages): void {
		self::assertCanUse($player);
		if (count($messages) > self::MAX_LINES) throw new ScoreFactoryException("Cannot set " . count($messages) . " lines, the maximum is " . self::MAX_LINES);

		self::getCache($player)->setEntries([]);
		foreach ($messages as $index => $message) {
			self::setScoreLine($player, $index + 1, $message);
		}
	}

	/**
	 * Send a single score line to the player, replacing the one shown at that line.
	 * This should be called after setting and sending the objective
	 * and after setting the score line.
	 */
	public static function sendLine(Player $player, int $line, ScorePacketEntry $entry): void {
		self::assertCanUse($player);
		self::assertLine($line);

		$cache = self::getCache($player);
		$text = $entry->customName ?? "";
		if ($cache->isShowing($line, $text)) return;

		// A line cannot be replaced, it has to be removed first.
		if ($cache->isSent($line)) self::sendRemoval($player, $cache, [$line]);

		$player->getNetworkSession()->sendDataPacket(SetScorePacket::create([$entry]));
		$cache->markSent($line, $text);
	}

	/**
	 * Makes the player show exactly the lines that were set.
	 * Only the lines that changed or are gone are touched, the others stay as they are.
	 * This should be called after setting and sending the objective.
	 */
	public static function sendLines(Player $player): void {
		self::assertCanUse($player);

		$cache = self::getCache($player);

		$stale = [];
		foreach ($cache->getSentLines() as $line) {
			$entry = $cache->getEntry($line);
			if ($entry === null || !$cache->isShowing($line, $entry->customName ?? "")) $stale[] = $line;
		}
		self::sendRemoval($player, $cache, $stale);

		$changed = [];
		foreach ($cache->getEntriesList() as $entry) {
			if (!$cache->isShowing($entry->scoreboardId, $entry->customName ?? "")) $changed[] = $entry;
		}
		if (count($changed) === 0) return;

		$player->getNetworkSession()->sendDataPacket(SetScorePacket::create($changed));
		foreach ($changed as $entry) $cache->markSent($entry->scoreboardId, $entry->customName ?? "");
	}

	/**
	 * Remove a single line from the scoreboard while keeping the board intact.
	 */
	public static function removeScoreLine(Player $player, int $line, bool $removeFromCache = true): void {
		self::assertCanUse($player);
		self::assertLine($line);

		$cache = self::getCache($player);
		if ($removeFromCache) $cache->removeEntry($line);

		self::sendRemoval($player, $cache, [$line]);
	}

	/**
	 * Remove all the lines the player is showing without actually removing the scoreboard.
	 * With $removeFromCache the lines are forgotten too, so the next sendLines() only has the new ones.
	 */
	public static function removeScoreLines(Player $player, bool $removeFromCache = false): void {
		self::assertCanUse($player);

		$cache = self::getCache($player);
		if ($removeFromCache) $cache->setEntries([]);

		self::sendRemoval($player, $cache, $cache->getSentLines());
	}

	/**
	 * Removes the lines the client is showing, skipping the ones it is not.
	 *
	 * @param list<int> $lines
	 */
	private static function sendRemoval(Player $player, ScoreCache $cache, array $lines): void {
		$removals = [];
		foreach ($lines as $line) {
			if (!$cache->isSent($line)) continue;

			$entry = new ScorePacketEntry();
			$entry->action = ScorePacketEntryAction::REMOVE;
			$entry->objectiveName = $cache->getObjective();
			$entry->score = $line;
			$entry->scoreboardId = $line;
			$removals[] = $entry;
			$cache->forgetSent($line);
		}
		if (count($removals) === 0) return;

		$player->getNetworkSession()->sendDataPacket(SetScorePacket::create($removals));
	}

	private static function assertCanUse(Player $player): void {
		if (!$player->isConnected()) throw new ScoreFactoryException("Player is not connected.");
		if (!self::hasCache($player)) throw new ScoreFactoryException("The player has no scoreboard. Please call ScoreFactory::setObjective() beforehand.");
	}

	private static function assertLine(int $line): void {
		if ($line < self::MIN_LINES || $line > self::MAX_LINES) throw new ScoreFactoryException("Line: $line is out of range, expected value between " . self::MIN_LINES . " and " . self::MAX_LINES);
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