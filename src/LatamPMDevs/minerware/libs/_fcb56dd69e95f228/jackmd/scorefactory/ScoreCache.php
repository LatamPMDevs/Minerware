<?php
declare(strict_types = 1);

namespace LatamPMDevs\minerware\libs\_fcb56dd69e95f228\jackmd\scorefactory;

use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;

/**
 * @internal
 */
class ScoreCache {

	public const MIN_ENTRIY_INDEX = 1;
	public const MAX_ENTRIY_INDEX = 15;

	/** @var ScorePacketEntry[] */
	private array $entries = [];

	private function __construct(
		private Player $player,
		private string $objective,
		private SetDisplayObjectivePacket $objectivePacket
	) {}

	public static function init(Player $player, string $objective, SetDisplayObjectivePacket $objectivePacket): self {
		return new self($player, $objective, $objectivePacket);
	}

	public function getPlayer(): Player {
		return $this->player;
	}

	public function getObjective(): string {
		return $this->objective;
	}

	public function setObjective(string $objective): void {
		$this->objective = $objective;
	}

	public function getObjectivePacket(): SetDisplayObjectivePacket {
		return $this->objectivePacket;
	}

	public function setObjectivePacket(SetDisplayObjectivePacket $objectivePacket): void {
		$this->objectivePacket = $objectivePacket;
	}

	/**
	 * Indexed by (int) line -> ScorePacketEntry
	 *
	 * @return ScorePacketEntry[]
	 */
	public function getEntries(): array {
		return $this->entries;
	}

	/**
	 * @return ScorePacketEntry[]
	 * @phpstan-return list<ScorePacketEntry>
	 */
	public function getEntriesList(): array {
		$enttries  = $this->entries;
		ksort($enttries);

		return array_values($enttries);
	}

	/**
	 * Should be indexed by (int) line -> ScorePacketEntry
	 * No more than 15 entries allowed. #blameMojang
	 *
	 * @param ScorePacketEntry[] $entries
	 */
	public function setEntries(array $entries): void {
		if (count($entries) > self::MAX_ENTRIY_INDEX) {
			throw new \InvalidArgumentException("No more than " . self::MAX_ENTRIY_INDEX . " entries are allowed");
		}

		$this->entries = $entries;
	}

	/**
	 * Index should be in between 1 and 15
	 */
	public function setEntry(int $index, ScorePacketEntry $entry): void {
		if ($index < self::MIN_ENTRIY_INDEX || $index > self::MAX_ENTRIY_INDEX) {
			throw new \InvalidArgumentException("Entry index element " . $index . " is out of bounds of " . self::MIN_ENTRIY_INDEX . "-" . self::MAX_ENTRIY_INDEX);
		}

		$this->entries[$index] = $entry;
	}

	public function removeEntry(int $index): void {
		unset($this->entries[$index]);
	}

	public function __destruct() {
		unset($this->entries);
	}
}