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
use LatamPMDevs\minerware\Settings;
use pocketmine\player\Player;
use function count;

/**
 * Waiting for players, or counting down to start.
 */
abstract class LobbyPhase extends ArenaPhase {

	public function isLobby() : bool {
		return true;
	}

	public function createScoreboard(Arena $arena) : ?Closure {
		$translator = $arena->getPlugin()->getTranslator();
		$map = $arena->getMap()->getName();
		$players = count($arena->getPlayers());
		$serverIp = Settings::getInstance()->getServerIp();
		return static fn (Player $player) : array => [
			"§5" . $translator->translate($player, "text.map") . ":",
			"§f" . $map,
			"§1",
			"§5" . $translator->translate($player, "text.players") . ":",
			"§f" . $players . "/" . Arena::MAX_PLAYERS,
			"§2",
			"§6" . $serverIp
		];
	}
}