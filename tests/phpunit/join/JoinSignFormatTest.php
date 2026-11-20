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

use PHPUnit\Framework\TestCase;
use pocketmine\block\utils\SignText;
use function array_keys;
use function parse_ini_file;
use const INI_SCANNER_RAW;

final class JoinSignFormatTest extends TestCase {

	public function testMarkerIgnoresCaseAndColors() : void {
		self::assertTrue(JoinSignFormat::isJoinSign(new SignText(["[Minerware]", "", "", ""])));
		self::assertTrue(JoinSignFormat::isJoinSign(new SignText(["§l§2[MINERWARE]§r ", "", "", ""])));
		self::assertFalse(JoinSignFormat::isJoinSign(new SignText(["Minerware", "", "", ""])));
		self::assertFalse(JoinSignFormat::isJoinSign(new SignText(["", "[Minerware]", "", ""])));
	}

	public function testMapFilter() : void {
		self::assertNull(JoinSignFormat::getMapFilter(new SignText(["[Minerware]", "  ", "", ""])));
		self::assertSame("City", JoinSignFormat::getMapFilter(new SignText(["[Minerware]", "§aCity ", "", ""])));
	}

	public function testStateKeepsTheWrittenLines() : void {
		$text = new SignText(["[Minerware]", "City", "old", "old"]);
		$new = JoinSignFormat::withState($text, "status", "players");
		self::assertSame(["[Minerware]", "City", "status", "players"], $new->getLines());
	}

	public function testEveryResultMessageExists() : void {
		$keys = array_keys(parse_ini_file(__DIR__ . "/../../../resources/languages/en_US.ini", false, INI_SCANNER_RAW) ?: []);
		foreach (JoinResult::cases() as $result) {
			$key = $result->getMessageKey();
			if ($key !== null) {
				self::assertContains($key, $keys);
			}
		}
	}
}
