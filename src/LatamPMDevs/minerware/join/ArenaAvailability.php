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

namespace LatamPMDevs\minerware\join;

use LatamPMDevs\minerware\arena\ArenaManager;
use LatamPMDevs\minerware\map\MapManager;
use LatamPMDevs\minerware\Settings;
use function count;
use function strtolower;

/**
 * How many are playing and whether a player could get in, as shown by a join sign or NPC.
 */
final class ArenaAvailability {

	public function __construct(
		public readonly int $players,
		public readonly bool $joinable
	) { }

	public static function forMap(?string $mapName) : self {
		$arenaManager = ArenaManager::getInstance();
		$mapManager = MapManager::getInstance();
		if ($mapName === null ? $mapManager->getCount() === 0 : $mapManager->getByName($mapName) === null) {
			return new self(0, false);
		}

		$players = 0;
		$room = false;
		foreach ($arenaManager->getArenas() as $arena) {
			if ($mapName !== null && strtolower($arena->getMap()->getName()) !== strtolower($mapName)) {
				continue;
			}
			$count = count($arena->getPlayers());
			$players += $count;
			if ($arena->canJoin()) {
				$room = true;
			}
		}
		$canCreate = count($arenaManager->getArenas()) < Settings::getInstance()->getMaxRuntimeArenas();
		return new self($players, $room || $canCreate);
	}
}