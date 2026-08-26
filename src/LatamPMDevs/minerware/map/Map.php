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

use LatamPMDevs\minerware\database\DataHolder;
use LatamPMDevs\minerware\utils\Utils;
use pocketmine\math\Vector3;

final class Map {

	public const PLATFORM_X_SIZE = 24;
	public const PLATFORM_Z_SIZE = 24;

	public const MINI_PLATFORMS_X = 3;
	public const MINI_PLATFORMS_Z = 3;

	public const MINI_PLATFORM_SIZE = 2;
	public const MINI_PLATFORM_MARGIN = 3;

	private string $name;

	private Vector3 $platformMinPos;

	private Vector3 $platformMaxPos;

	private Vector3 $center;

	/** @var array<int, list<array{0: int, 1: int, 2: int}>>|null */
	private ?array $miniPlatforms = null;

	/** @var Vector3[] */
	private array $spawns = [];

	private Vector3 $winnersCage;

	private Vector3 $losersCage;

	public function __construct(private DataHolder $data) {
		$this->name = $data->getString("name");

		$platform = $data->getArray("platform");
		$minMax = Utils::calculateMinAndMaxPos(
			new Vector3($platform["pos1"]["X"], $platform["pos1"]["Y"], $platform["pos1"]["Z"]),
			new Vector3($platform["pos2"]["X"], $platform["pos2"]["Y"], $platform["pos2"]["Z"])
		);
		$this->platformMinPos = $minMax[0];
		$this->platformMaxPos = $minMax[1];
		$this->center = $this->platformMinPos->add(self::PLATFORM_X_SIZE / 2, 0, self::PLATFORM_Z_SIZE / 2);

		foreach ($data->getArray("spawns") as $spawnData) {
			$this->spawns[] = new Vector3($spawnData["X"], $spawnData["Y"], $spawnData["Z"]);
		}
		$cages = $data->getArray("cages");
		$this->winnersCage = new Vector3($cages["winners"]["X"], $cages["winners"]["Y"], $cages["winners"]["Z"]);
		$this->losersCage = new Vector3($cages["losers"]["X"], $cages["losers"]["Y"], $cages["losers"]["Z"]);
	}

	public function getName() : string {
		return $this->name;
	}

	public function getPlatformMinPos() : Vector3 {
		return $this->platformMinPos;
	}

	public function getPlatformMaxPos() : Vector3 {
		return $this->platformMaxPos;
	}

	public function getCenter() : Vector3 {
		return $this->center;
	}

	/**
	 * Returns the mini platforms as relative offsets referenced by their min position.
	 *
	 * @return array<int, list<array{int, int, int}>>
	 */
	public function getMiniPlatforms() : array {
		if ($this->miniPlatforms === null) {
			$this->miniPlatforms = $this->generateMiniPlatforms();
		}
		return $this->miniPlatforms;
	}

	/**
	 * @return array<int, list<array{int, int, int}>>
	 */
	private function generateMiniPlatforms() : array {
		$platforms = [];
		$stepX = self::getPlatformSpacing(self::PLATFORM_X_SIZE, self::MINI_PLATFORMS_X);
		$stepZ = self::getPlatformSpacing(self::PLATFORM_Z_SIZE, self::MINI_PLATFORMS_Z);
		$size = self::MINI_PLATFORM_SIZE;
		for ($z = 0; $z < self::MINI_PLATFORMS_Z; $z++) {
			$startZ = self::MINI_PLATFORM_MARGIN + $z * $stepZ;
			for ($x = 0; $x < self::MINI_PLATFORMS_X; $x++) {
				$startX = self::MINI_PLATFORM_MARGIN + $x * $stepX;
				$platform = [];
				for ($bz = 0; $bz < $size; $bz++) {
					for ($bx = 0; $bx < $size; $bx++) {
						$platform[] = [$startX + $bx, 1, $startZ + $bz];
					}
				}
				$platforms[] = $platform;
			}
		}
		return $platforms;
	}

	private static function getPlatformSpacing(int $platformSize, int $perAxis) : int {
		return (int) (($platformSize - self::MINI_PLATFORM_MARGIN * 2 - self::MINI_PLATFORM_SIZE) / ($perAxis - 1));
	}

	/**
	 * @return Vector3[]
	 */
	public function getSpawns() : array {
		return $this->spawns;
	}

	public function getWinnersCage() : Vector3 {
		return $this->winnersCage;
	}

	public function getLosersCage() : Vector3 {
		return $this->losersCage;
	}
}