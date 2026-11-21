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

use function is_numeric;
use function is_string;

final class LeaderboardEntry {

	public function __construct(
		public readonly string $uuid,
		public readonly string $name,
		public readonly int $wins
	) { }

	/**
	 * @param array<string, mixed> $row
	 */
	public static function fromRow(array $row) : ?self {
		$uuid = $row["uuid"] ?? null;
		$name = $row["name"] ?? null;
		$wins = $row["wins"] ?? null;
		if (!is_string($uuid) || !is_string($name) || !is_numeric($wins)) {
			return null;
		}
		return new self($uuid, $name, (int) $wins);
	}

	public function equals(self $other) : bool {
		return $this->uuid === $other->uuid && $this->name === $other->name && $this->wins === $other->wins;
	}
}
