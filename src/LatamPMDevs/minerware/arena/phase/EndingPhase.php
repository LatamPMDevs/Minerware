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

use LatamPMDevs\minerware\arena\Arena;
use LatamPMDevs\minerware\arena\ArenaManager;
use pocketmine\utils\SingletonTrait;

final class EndingPhase extends ArenaPhase {
	use SingletonTrait;

	private const DURATION = 10; //seconds

	public function getName() : string {
		return "ending";
	}

	public function getDuration() : int {
		return self::DURATION;
	}

	public function tick(Arena $arena) : void {
		if ($arena->getCountdown() > 0 || $arena->isBuildingStage()) {
			$arena->decrementCountdown();
			return;
		}
		$plugin = $arena->getPlugin();
		foreach ($arena->getPlayers() as $player) {
			$plugin->getJoiner()->notify($player, ArenaManager::getInstance()->join($player));
		}
		$arena->close();
	}
}
