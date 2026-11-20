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

namespace LatamPMDevs\minerware\map;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MapTest extends TestCase {

	/**
	 * @return array<string, mixed>
	 */
	private static function validData() : array {
		return [
			"name" => "Test",
			"platform" => [
				"pos1" => ["X" => 10, "Y" => 64, "Z" => 10],
				"pos2" => ["X" => 33, "Y" => 64, "Z" => 33]
			],
			"spawns" => [["X" => 20.5, "Y" => 66, "Z" => 20.5]],
			"cages" => [
				"winners" => ["X" => 0, "Y" => 90, "Z" => 0],
				"losers" => ["X" => 50, "Y" => 90, "Z" => 50]
			]
		];
	}

	public function testRoundTrip() : void {
		$map = Map::fromArray(self::validData());
		self::assertSame("Test", $map->getName());
		self::assertCount(1, $map->getSpawns());
		self::assertEquals(Map::fromArray($map->toArray())->toArray(), $map->toArray());
	}

	public function testMiniPlatforms() : void {
		$platforms = Map::fromArray(self::validData())->getMiniPlatforms();
		self::assertCount(Map::MINI_PLATFORMS_X * Map::MINI_PLATFORMS_Z, $platforms);
		foreach ($platforms as $blocks) {
			self::assertCount(Map::MINI_PLATFORM_SIZE ** 2, $blocks);
		}
	}

	/**
	 * @return iterable<string, array{string, mixed}>
	 */
	public static function invalidKeys() : iterable {
		yield "no name" => ["name", null];
		yield "no platform" => ["platform", null];
		yield "no spawns" => ["spawns", []];
		yield "bad spawn" => ["spawns", [["X" => "a", "Y" => 0, "Z" => 0]]];
		yield "no cages" => ["cages", null];
	}

	/**
	 * @dataProvider invalidKeys
	 */
	public function testInvalidData(string $key, mixed $value) : void {
		$data = self::validData();
		if ($value === null) {
			unset($data[$key]);
		} else {
			$data[$key] = $value;
		}
		$this->expectException(InvalidArgumentException::class);
		Map::fromArray($data);
	}
}
