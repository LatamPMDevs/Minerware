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
use PHPUnit\Framework\TestCase;

final class LeaderboardTest extends TestCase {

	/** @var list<array{Closure(list<LeaderboardEntry>) : void, Closure() : void}> */
	private array $requests = [];

	private function leaderboard() : Leaderboard {
		return new Leaderboard(function (Closure $onSuccess, Closure $onError) : void {
			$this->requests[] = [$onSuccess, $onError];
		});
	}

	public function testNothingIsLoadedBeforeTheFirstAnswer() : void {
		$leaderboard = $this->leaderboard();
		$leaderboard->refresh();
		self::assertFalse($leaderboard->isLoaded());
		self::assertNull($leaderboard->getEntry(1));
	}

	public function testRanksStartAtOne() : void {
		$leaderboard = $this->leaderboard();
		$leaderboard->refresh();
		$this->requests[0][0]([new LeaderboardEntry("a", "Ann", 5), new LeaderboardEntry("b", "Bob", 3)]);
		self::assertTrue($leaderboard->isLoaded());
		self::assertSame("Ann", $leaderboard->getEntry(1)?->name);
		self::assertSame("Bob", $leaderboard->getEntry(2)?->name);
		self::assertNull($leaderboard->getEntry(3));
		self::assertNull($leaderboard->getEntry(0));
	}

	public function testVersionOnlyChangesWithTheEntries() : void {
		$leaderboard = $this->leaderboard();
		$leaderboard->refresh();
		$this->requests[0][0]([new LeaderboardEntry("a", "Ann", 5)]);
		$version = $leaderboard->getVersion();

		$leaderboard->refresh();
		$this->requests[1][0]([new LeaderboardEntry("a", "Ann", 5)]);
		self::assertSame($version, $leaderboard->getVersion());

		$leaderboard->refresh();
		$this->requests[2][0]([new LeaderboardEntry("a", "Ann", 6)]);
		self::assertSame($version + 1, $leaderboard->getVersion());
	}

	public function testAnEmptyFirstAnswerStillLoads() : void {
		$leaderboard = $this->leaderboard();
		$leaderboard->refresh();
		$this->requests[0][0]([]);
		self::assertTrue($leaderboard->isLoaded());
		self::assertSame([], $leaderboard->getEntries());
	}

	public function testARefreshWhileFetchingRunsRightAfter() : void {
		$leaderboard = $this->leaderboard();
		$leaderboard->refresh();
		$leaderboard->refresh();
		$leaderboard->refresh();
		self::assertCount(1, $this->requests);
		$this->requests[0][0]([]);
		self::assertCount(2, $this->requests);
		$this->requests[1][0]([]);
		self::assertCount(2, $this->requests);
	}

	public function testAFailureAllowsTheNextRefresh() : void {
		$leaderboard = $this->leaderboard();
		$leaderboard->refresh();
		$this->requests[0][1]();
		self::assertFalse($leaderboard->isLoaded());
		$leaderboard->refresh();
		self::assertCount(2, $this->requests);
	}

	public function testRowsAreReadFromTheDatabase() : void {
		$entry = LeaderboardEntry::fromRow(["uuid" => "u", "name" => "Ann", "wins" => "7"]);
		self::assertSame(7, $entry?->wins);
		self::assertNull(LeaderboardEntry::fromRow(["uuid" => "u", "name" => "Ann"]));
		self::assertNull(LeaderboardEntry::fromRow(["uuid" => 1, "name" => "Ann", "wins" => 2]));
	}
}
