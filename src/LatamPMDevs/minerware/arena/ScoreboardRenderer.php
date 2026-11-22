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

namespace LatamPMDevs\minerware\arena;

use LatamPMDevs\minerware\libs\_eec78460fd20c565\jackmd\scorefactory\ScoreFactory;
use function array_slice;

/**
 * Draws the scoreboard of an arena's players, ScoreFactory only sends the lines that changed.
 */
final class ScoreboardRenderer {

	private const MAX_LINES = 15;

	public function update(Arena $arena) : void {
		$linesOf = $arena->getPhase()->createScoreboard($arena);
		foreach ($arena->getPlayers() as $player) {
			if ($linesOf === null) {
				ScoreFactory::removeObjective($player, true);
				continue;
			}
			if (!ScoreFactory::hasObjective($player)) {
				ScoreFactory::setObjective($player, '§l§eMinerWare');
				ScoreFactory::sendObjective($player);
			}
			ScoreFactory::setScoreLines($player, array_slice($linesOf($player), 0, self::MAX_LINES));
			ScoreFactory::sendLines($player);
		}
	}
}