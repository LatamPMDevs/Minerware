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
use LatamPMDevs\minerware\utils\Utils;
use pocketmine\math\Vector3;
use function array_map;
use function is_array;
use function is_numeric;
use function is_string;

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
		$map = new self();
		$map->name = is_string($data["name"] ?? null) && $data["name"] !== "" ? $data["name"] :
			throw new InvalidArgumentException("Missing map name");

		$platform = self::array($data, "platform");
		$minMax = Utils::calculateMinAndMaxPos(self::vector($platform, "pos1"), self::vector($platform, "pos2"));
		$map->platformMinPos = $minMax[0];
		$map->platformMaxPos = $minMax[1];
		$map->center = $map->platformMinPos->add(self::PLATFORM_X_SIZE / 2, 0, self::PLATFORM_Z_SIZE / 2);

		foreach (self::array($data, "spawns") as $spawn) {
			$map->spawns[] = self::toVector($spawn, "spawns");
		}
		if ($map->spawns === []) {
			throw new InvalidArgumentException("The map has no spawns");
		}

		$cages = self::array($data, "cages");
		$map->winnersCage = self::vector($cages, "winners");
		$map->losersCage = self::vector($cages, "losers");
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

	/**
	 * @param array<mixed> $data
	 * @return array<mixed>
	 */
	private static function array(array $data, string $key) : array {
		return is_array($data[$key] ?? null) ? $data[$key] : throw new InvalidArgumentException("Missing '$key'");
	}

	/**
	 * @param array<mixed> $data
	 */
	private static function vector(array $data, string $key) : Vector3 {
		return self::toVector($data[$key] ?? null, $key);
	}

	private static function toVector(mixed $data, string $key) : Vector3 {
		if (!is_array($data) || !is_numeric($data["X"] ?? null) || !is_numeric($data["Y"] ?? null) || !is_numeric($data["Z"] ?? null)) {
			throw new InvalidArgumentException("Invalid position '$key'");
		}
		return new Vector3((float) $data["X"], (float) $data["Y"], (float) $data["Z"]);
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