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

use pocketmine\block\utils\SignText;
use pocketmine\utils\TextFormat;
use function strtolower;
use function trim;

/**
 * What a join sign looks like.
 */
final class JoinSignFormat {

	public const MARKER = "[minerware]";

	public static function isJoinSign(SignText $text) : bool {
		return strtolower(trim(TextFormat::clean($text->getLine(0)))) === self::MARKER;
	}

	public static function getMapFilter(SignText $text) : ?string {
		$name = trim(TextFormat::clean($text->getLine(1)));
		return $name === "" ? null : $name;
	}

	/**
	 * The sign with its state lines replaced; the marker and map lines are kept as written.
	 */
	public static function withState(SignText $text, string $status, string $players) : SignText {
		return new SignText([$text->getLine(0), $text->getLine(1), $status, $players]);
	}
}
