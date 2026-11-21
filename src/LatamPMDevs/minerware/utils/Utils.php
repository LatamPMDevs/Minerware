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
use LatamPMDevs\minerware\libs\_189c4e77bf695c21\IvanCraft623\languages\Translator;
use pocketmine\block\utils\DyeColor;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;
use function abs;
use function array_reverse;
use function asort;
use function ceil;
use function floor;
use function implode;
use function max;
use function min;
use function morton2d_encode;
use function strlen;
use function substr;

final class Utils {

	public static function calculateSize(Vector3 $firstPoint, Vector3 $secondPoint) : string {
		return (abs($firstPoint->x - $secondPoint->x) + 1) . "x" . (abs($firstPoint->z - $secondPoint->z) + 1);
	}

	public static function setZip(string $targetPath, string $zipPath) : bool {
		$zip = new ZipArchive;
		if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			return false;
		}
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($targetPath),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ($files as $file) {
			if ($file instanceof SplFileInfo && $file->isFile()) {
				$filePath = $file->getRealPath();
				$relativePath = substr($filePath, strlen($targetPath) + 1);
				if (!$zip->addFile($filePath, $relativePath)) {
					$zip->close();
					return false;
				}
			}
		}
		return $zip->close();
	}

	/**
	 * @return array{0: Vector3, 1: Vector3}
	 */
	public static function calculateMinAndMaxPos(Vector3 $pos1, Vector3 $pos2) : array {
		$minX = min($pos1->x, $pos2->x);
		$minY = min($pos1->y, $pos2->y);
		$minZ = min($pos1->z, $pos2->z);

		$maxX = max($pos1->x, $pos2->x);
		$maxY = max($pos1->y, $pos2->y);
		$maxZ = max($pos1->z, $pos2->z);

		return [new Vector3($minX, $minY, $minZ), new Vector3($maxX, $maxY, $maxZ)];
	}

	/**
	 * @param Player[] $players
	 * @return string[]
	 */
	public static function getPlayersNames(array $players) : array {
		$names = [];
		foreach ($players as $player) {
			$names[] = $player->getName();
		}
		return $names;
	}

	public static function initPlayer(Player $player) : void {
		$player->extinguish();
		$player->setFlying(false);
		$player->setAllowFlight(false);
		$player->setMaxHealth(20);
		$player->setHealth(20);
		$player->getHungerManager()->setFood(20);;
		$player->getEffects()->clear();
		$player->getInventory()->clearAll();
		$player->getArmorInventory()->clearAll();
		$player->getCursorInventory()->clearAll();
		$player->getOffHandInventory()->clearAll();
	}

	/**
	 * @param array<int, int> $scores
	 * @return array<int, int>
	 */
	public static function sortScoresDescending(array $scores) : array {
		if (asort($scores) === false) {
			throw new AssumptionFailedError("Failed to sort scores");
		}
		return array_reverse($scores, true);
	}

	/**
	 * @param array<int, int> $array
	 * @return array<int, array<int, int>>
	 */
	public static function chunkScores(array $array) : array {
		$result = [];
		foreach ($array as $key => $score) {
			$result[$score][] = $key;
		}
		return $result;
	}

	/**
	 * @return array<int, Vector2[]>
	 */
	public static function chunkVector2(Vector2 $pos1, Vector2 $pos2, int $chunkSize) : array {
		if ($chunkSize < 2) {
			throw new InvalidArgumentException("Chunk size should be at least 2");
		}

		$minX = min($pos1->x, $pos2->x);
		$minY = min($pos1->y, $pos2->y);

		$maxX = max($pos1->x, $pos2->x);
		$maxY = max($pos1->y, $pos2->y);

		$chunks = [];
		for ($x = $minX; $x <= $maxX; ++$x) {
			$xDiff = $x - $minX;
			$chunkX = (int) floor($xDiff / $chunkSize);
			for ($y = $minY; $y <= $maxY; ++$y) {
				$yDiff = $y - $minY;
				$chunkY = (int) floor($yDiff / $chunkSize);
				$chunks[morton2d_encode($chunkX, $chunkY)][] = new Vector2($xDiff, $yDiff);
			}
		}
		return $chunks;
	}

	/**
	 * Some colors of DyeColor do not exist in TextFormat
	 * in these cases return TextFormat::WHITE
	 */
	public static function DyeColor2TextFormat(DyeColor $dyeColor) : string {
		return match ($dyeColor) {
			DyeColor::ORANGE => TextFormat::GOLD,
			DyeColor::MAGENTA => TextFormat::DARK_PURPLE,
			DyeColor::LIGHT_BLUE => TextFormat::AQUA,
			DyeColor::LIME => TextFormat::GREEN,
			DyeColor::PINK => TextFormat::LIGHT_PURPLE,
			DyeColor::GRAY => TextFormat::DARK_GRAY,
			DyeColor::LIGHT_GRAY => TextFormat::GRAY,
			DyeColor::CYAN => TextFormat::DARK_AQUA,
			DyeColor::BLUE => TextFormat::BLUE,
			DyeColor::GREEN => TextFormat::DARK_GREEN,
			DyeColor::RED => TextFormat::DARK_RED,
			DyeColor::BLACK => TextFormat::BLACK,
			DyeColor::YELLOW => TextFormat::YELLOW,
			default => TextFormat::WHITE
		};
	}

	/**
	 * @return array<string, int>
	 */
	public static function getTime(int $seconds) : array {
		if ($seconds < 0) {
			throw new InvalidArgumentException("Seconds is lower than 0");
		}
		$year = floor($seconds / 31540000);
		$monthSec = $seconds % 31540000;
		$month = floor($monthSec / 2628000);
		$daySec = $monthSec % 2628000;
		$day = floor($daySec / 86400);
		$hourSec = $daySec % 86400;
		$hour = floor($hourSec / 3600);
		$minuteSec = $hourSec % 3600;
		$minute = floor($minuteSec / 60);
		$remainingSec = $minuteSec % 60;
		$second = ceil($remainingSec);
		return [
			"years" => (int) $year,
			"months" => (int) $month,
			"days" => (int) $day,
			"hours" => (int) $hour,
			"minutes" => (int) $minute,
			"seconds" => (int) $second
		];
	}

	public static function getTimeTranslated(int $seconds, ?Translator $translator = null, ?CommandSender $sender = null) : string {
		$time = [];
		foreach (self::getTime($seconds) as $key => $value) {
			if ($value !== 0 || $key === "seconds") {
				if ($translator !== null) {
					$time[] = $value . " " . $translator->translate($sender, "text.time." . $key);
				} else {
					$time[] = $value . " " . $key;
				}
			}
		}
		return implode(", ", $time);
	}
}