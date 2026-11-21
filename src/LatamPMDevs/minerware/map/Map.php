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
use LatamPMDevs\minerware\map\data\MapData;
use LatamPMDevs\minerware\map\data\MapDataParser;
use LatamPMDevs\minerware\map\data\PositionData;
use LatamPMDevs\minerware\utils\Utils;
use pocketmine\math\Vector3;
use function array_map;
use function count;

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

	private function __construct() { }

	/**
	 * @param array<string, mixed> $data
	 * @throws InvalidArgumentException
	 */
	public static function fromArray(array $data) : self {
		return self::fromData(MapDataParser::parse($data));
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public static function fromData(MapData $data) : self {
		$map = new self();
		if ($data->name === "") {
			throw new InvalidArgumentException("The map has no name");
		}
		$map->name = $data->name;

		[$min, $max] = Utils::calculateMinAndMaxPos(self::vector($data->platform->pos1), self::vector($data->platform->pos2));
		$size = (int) ($max->x - $min->x) + 1;
		$depth = (int) ($max->z - $min->z) + 1;
		if ($size !== self::PLATFORM_X_SIZE || $depth !== self::PLATFORM_Z_SIZE) {
			throw new InvalidArgumentException("The platform must be " . self::PLATFORM_X_SIZE . "x" . self::PLATFORM_Z_SIZE . ", got {$size}x{$depth}");
		}
		$map->platformMinPos = $min;
		$map->platformMaxPos = $max;
		$map->center = $min->add(self::PLATFORM_X_SIZE / 2, 0, self::PLATFORM_Z_SIZE / 2);

		foreach ($data->spawns as $spawn) {
			$map->spawns[] = self::vector($spawn);
		}
		if (count($map->spawns) === 0) {
			throw new InvalidArgumentException("The map has no spawns");
		}

		$map->winnersCage = self::vector($data->cages->winners);
		$map->losersCage = self::vector($data->cages->losers);
		return $map;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray() : array {
		return [
			"name" => $this->name,
			"platform" => [
				"pos1" => self::fromVector($this->platformMinPos),
				"pos2" => self::fromVector($this->platformMaxPos)
			],
			"spawns" => array_map(self::fromVector(...), $this->spawns),
			"cages" => [
				"winners" => self::fromVector($this->winnersCage),
				"losers" => self::fromVector($this->losersCage)
			]
		];
	}

	private static function vector(PositionData $position) : Vector3 {
		return new Vector3($position->X, $position->Y, $position->Z);
	}

	/**
	 * @return array{X: float, Y: float, Z: float}
	 */
	private static function fromVector(Vector3 $v) : array {
		return ["X" => $v->x, "Y" => $v->y, "Z" => $v->z];
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