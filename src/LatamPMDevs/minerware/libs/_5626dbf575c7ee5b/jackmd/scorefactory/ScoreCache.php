<?php
declare(strict_types = 1);

namespace LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\jackmd\scorefactory;

use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;
use function array_keys;
use function array_values;
use function count;
use function ksort;

/**
 * @internal
 */
class ScoreCache {

	public const MIN_ENTRIY_INDEX = 1;
	public const MAX_ENTRIY_INDEX = 15;

	/**
	 * The lines the player should show, by line.
	 *
	 * @var ScorePacketEntry[]
	 */
	private array $entries = [];

	/**
	 * The lines the client is showing right now, by line, with their text.
	 *
	 * @var array<int, string>
	 */
	private array $sent = [];

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
		$entries = $this->entries;
		ksort($entries);

		return array_values($entries);
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

	public function getEntry(int $line): ?ScorePacketEntry {
		return $this->entries[$line] ?? null;
	}

	public function isSent(int $line): bool {
		return isset($this->sent[$line]);
	}

	/**
	 * @return list<int>
	 */
	public function getSentLines(): array {
		return array_keys($this->sent);
	}

	/**
	 * Whether the client already shows this line with this exact text.
	 */
	public function isShowing(int $line, string $text): bool {
		return ($this->sent[$line] ?? null) === $text;
	}

	public function markSent(int $line, string $text): void {
		$this->sent[$line] = $text;
	}

	public function forgetSent(int $line): void {
		unset($this->sent[$line]);
	}

	public function clearSent(): void {
		$this->sent = [];
	}

	public function __destruct() {
		unset($this->entries);
	}
}