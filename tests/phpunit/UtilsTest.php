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

namespace LatamPMDevs\minerware\utils;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use pocketmine\math\Vector3;

final class UtilsTest extends TestCase {

	public function testMinAndMaxPosAreOrdered() : void {
		[$min, $max] = Utils::calculateMinAndMaxPos(new Vector3(5, 70, -2), new Vector3(1, 64, 9));
		self::assertEquals(new Vector3(1, 64, -2), $min);
		self::assertEquals(new Vector3(5, 70, 9), $max);
	}

	public function testScoresAreSortedDescendingKeepingKeys() : void {
		self::assertSame([3 => 9, 1 => 5, 2 => 1], Utils::sortScoresDescending([1 => 5, 2 => 1, 3 => 9]));
	}

	public function testScoresAreChunkedByPoints() : void {
		self::assertSame([5 => [1, 3], 2 => [2]], Utils::chunkScores([1 => 5, 2 => 2, 3 => 5]));
	}

	public function testTimeIsSplit() : void {
		self::assertSame(
			["years" => 0, "months" => 0, "days" => 1, "hours" => 1, "minutes" => 1, "seconds" => 1],
			Utils::getTime(86400 + 3600 + 60 + 1)
		);
	}

	public function testNegativeTimeIsRejected() : void {
		$this->expectException(InvalidArgumentException::class);
		Utils::getTime(-1);
	}

	public function testPlatformSizeIsCounted() : void {
		self::assertSame("24x24", Utils::calculateSize(new Vector3(0, 0, 0), new Vector3(23, 0, 23)));
	}
}
