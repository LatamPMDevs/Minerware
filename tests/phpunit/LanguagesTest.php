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

namespace LatamPMDevs\minerware;

use PHPUnit\Framework\TestCase;
use function array_diff;
use function array_keys;
use function basename;
use function glob;
use function parse_ini_file;
use const INI_SCANNER_RAW;

final class LanguagesTest extends TestCase {

	private const DIR = __DIR__ . "/../../resources/languages/";

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function languages() : iterable {
		foreach (glob(self::DIR . "*.ini") ?: [] as $file) {
			yield basename($file, ".ini") => [$file];
		}
	}

	/**
	 * @dataProvider languages
	 */
	public function testSameKeysAsEnglish(string $file) : void {
		$english = array_keys(parse_ini_file(self::DIR . "en_US.ini", false, INI_SCANNER_RAW) ?: []);
		$keys = array_keys(parse_ini_file($file, false, INI_SCANNER_RAW) ?: []);
		self::assertSame([], array_diff($english, $keys), "Missing keys");
		self::assertSame([], array_diff($keys, $english), "Unknown keys");
	}
}
