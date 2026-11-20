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

namespace LatamPMDevs\minerware\arena\phase;

use Closure;
use LatamPMDevs\minerware\arena\Arena;
use pocketmine\player\Player;

/**
 * One stage of an arena's life (waiting, starting, in between, in game, ending).
 */
abstract class ArenaPhase {

	/**
	 * A short stable name, for logs and the admin form.
	 */
	abstract public function getName() : string;

	/**
	 * Runs once a second while the arena is in this phase.
	 */
	abstract public function tick(Arena $arena) : void;

	public function getDuration() : int {
		return 0;
	}

	/**
	 * Whether players are waiting for a game: they can join, and can't move items around.
	 */
	public function isLobby() : bool {
		return false;
	}

	/**
	 * Prepares what every player's scoreboard shows, so what they share is computed once.
	 *
	 * @return ?Closure(Player) : list<string>
	 */
	public function createScoreboard(Arena $arena) : ?Closure {
		return null;
	}
}
