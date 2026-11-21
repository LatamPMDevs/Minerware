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

enum JoinResult {

	case JOINED;
	case ALREADY_IN_ARENA;
	case NO_MAPS;
	case MAP_NOT_FOUND;
	case FULL;
	case COOLDOWN;
	case DENIED;
	case ERROR;

	/**
	 * The language key explaining the result to the player, null when there is nothing to say.
	 */
	public function getMessageKey() : ?string {
		return match ($this) {
			self::JOINED => null,
			self::ALREADY_IN_ARENA => "join.alreadyInArena",
			self::NO_MAPS => "join.noMaps",
			self::MAP_NOT_FOUND => "join.mapNotFound",
			self::FULL => "join.full",
			self::COOLDOWN => "join.cooldown",
			self::DENIED => "join.denied",
			self::ERROR => "join.error"
		};
	}
}
