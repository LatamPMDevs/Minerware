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

namespace LatamPMDevs\minerware\leaderboard;

use Closure;
use function count;

/**
 * The players with most wins, as of the last refresh. Reading it never queries the database.
 */
final class Leaderboard {

	public const SIZE = 10;

	/** @var list<LeaderboardEntry> */
	private array $entries = [];

	private int $version = 0;

	private bool $loaded = false;

	private bool $fetching = false;

	private bool $refreshAgain = false;

	/**
	 * @param Closure(Closure(list<LeaderboardEntry>) : void, Closure() : void) : void $fetch asks for the top, then calls one of the two closures
	 */
	public function __construct(private readonly Closure $fetch) { }

	/**
	 * Asks for the top in the background. A request made while another is running runs right after it.
	 */
	public function refresh() : void {
		if ($this->fetching) {
			$this->refreshAgain = true;
			return;
		}
		$this->fetching = true;
		($this->fetch)($this->onFetched(...), $this->onFailed(...));
	}

	/**
	 * @param list<LeaderboardEntry> $entries
	 */
	private function onFetched(array $entries) : void {
		if (!$this->loaded || !self::sameEntries($this->entries, $entries)) {
			$this->entries = $entries;
			$this->version++;
		}
		$this->loaded = true;
		$this->onSettled();
	}

	private function onFailed() : void {
		$this->onSettled();
	}

	private function onSettled() : void {
		$this->fetching = false;
		if ($this->refreshAgain) {
			$this->refreshAgain = false;
			$this->refresh();
		}
	}

	/**
	 * @param list<LeaderboardEntry> $a
	 * @param list<LeaderboardEntry> $b
	 */
	private static function sameEntries(array $a, array $b) : bool {
		if (count($a) !== count($b)) {
			return false;
		}
		foreach ($a as $i => $entry) {
			if (!$entry->equals($b[$i])) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return list<LeaderboardEntry> best first
	 */
	public function getEntries() : array {
		return $this->entries;
	}

	/**
	 * @param int $rank starting at 1
	 */
	public function getEntry(int $rank) : ?LeaderboardEntry {
		return $this->entries[$rank - 1] ?? null;
	}

	/**
	 * Changes every time the entries do.
	 */
	public function getVersion() : int {
		return $this->version;
	}

	/**
	 * Whether the first refresh has landed.
	 */
	public function isLoaded() : bool {
		return $this->loaded;
	}
}
