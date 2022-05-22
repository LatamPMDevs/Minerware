<?php

/**
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
 * Copyright 2022 © LatamPMDevs
 */

declare(strict_types=1);

namespace LatamPMDevs\minerware\utils;

use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\block\utils\DyeColor;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils as PMUtils;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;
use function abs;
use function basename;
use function file_exists;
use function is_array;
use function is_dir;
use function is_file;
use function max;
use function min;
use function morton2d_encode;
use function rmdir;
use function scandir;
use function str_repeat;
use function strlen;
use function substr;
use function unlink;

final class Utils {

	public static function calculateParameter(Vector3 $firstPoint, Vector3 $secondPoint) : string {
		//TODO:: Calculate parameter between $firstPoint and $secondPoint.
		return "";
	}

	public static function calculateSize(Vector3 $firstPoint, Vector3 $secondPoint) : string {
		return (abs($firstPoint->x - $secondPoint->x) + 1) . "x" . (abs($firstPoint->z - $secondPoint->z) + 1);
	}

	public static function setZip(string $targetPath, string $zipPath) : bool {
		$zip = new ZipArchive;
		$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($targetPath),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ($files as $file) {
			if ($file->isFile()) {
				$filePath = $file->getRealPath();
				$relativePath = substr($filePath, strlen($targetPath) + 1);
				$zip->addFile($filePath, $relativePath);
			}
		}
		$zip->close();
		return true;
	}

	public static function playSound(Player $player, string $sound, float $volume = 1, float $pitch = 1) : void {
		$pk = new PlaySoundPacket();
		$pk->x = $player->getPosition()->getX();
		$pk->y = $player->getPosition()->getY();
		$pk->z = $player->getPosition()->getZ();
		$pk->soundName = $sound;
		$pk->volume = $volume;
		$pk->pitch = $pitch;
		$player->getNetworkSession()->sendDataPacket($pk);
	}

	public static function removeDir(string $path) : void {
		if (!file_exists($path) || basename($path) == "." || basename($path) == "..") {
			return;
		}
		$scandir = scandir($path);
		if (is_array($scandir)) {
			foreach ($scandir as $item) {
				if ($item != "." || $item != "..") {
					if (is_dir($path . DIRECTORY_SEPARATOR . $item)) {
						self::removeDir($path . DIRECTORY_SEPARATOR . $item);
					}
					if (is_file($path . DIRECTORY_SEPARATOR . $item)) {
						self::removeFile($path . DIRECTORY_SEPARATOR . $item);
					}
				}
			}
		}
		rmdir($path);
	}

	public static function removeFile(string $path) : void {
		unlink($path);
	}

	public static function getStartingBar(int $colorSticks, int $totalSticks) : string {
		$leftover = $totalSticks - $colorSticks;
		return str_repeat("▌", $colorSticks) . "§7" . str_repeat("▌", $leftover);
	}

	public static function calculateMinAndMaxPos(Vector3 $pos1, Vector3 $pos2) : array {
		$minX = min($pos1->x, $pos2->x);
		$minY = min($pos1->y, $pos2->y);
		$minZ = min($pos1->z, $pos2->z);

		$maxX = max($pos1->x, $pos2->x);
		$maxY = max($pos1->y, $pos2->y);
		$maxZ = max($pos1->z, $pos2->z);

		return [new Vector3($minX, $minY, $minZ), new Vector3($maxX, $maxY, $maxZ)];
	}

	public static function getPlayersNames(array $players) : array {
		$names = [];
		foreach ($players as $player) {
			$names[] = $player->getName();
		}
		return $names;
	}

	/**
	 * @return Block[]
	 */
	public static function fill(Position $pos1, Position $pos2, Block $block, bool $update = false) : array {
		$changedBlocks = [];
		$world = $pos1->getWorld();

		if ($world !== $pos2->getWorld()) {
			throw new InvalidArgumentException("First and second position must be in the same world!");
		}

		$minX = min($pos1->x, $pos2->x);
		$minY = max($world->getMinY(), min($pos1->y, $pos2->y));
		$minZ = min($pos1->z, $pos2->z);

		$maxX = max($pos1->x, $pos2->x);
		$maxY = min($world->getMaxY(), max($pos1->y, $pos2->y));
		$maxZ = max($pos1->z, $pos2->z);

		for ($x = $minX; $x <= $maxX; ++$x) {
			for ($z = $minZ; $z <= $maxZ; ++$z) {
				$world->loadChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE);
				for ($y = $minY; $y <= $maxY; ++$y) {
					$changedBlocks[] = $world->getBlockAt((int) $x, (int) $y, (int) $z);
					$world->setBlockAt((int) $x, (int) $y, (int) $z, $block, $update);
				}
			}
		}
		return $changedBlocks;
	}

	public static function buildCage(Position $center, Block $block) : void {
		$world = $center->getWorld();
		$pos1 = new Position($center->x + 3, $center->y, $center->z + 3, $world);
		$pos2 = new Position($center->x - 3, $center->y, $center->z - 3, $world);
		self::fill($pos1, $pos2, $block, false);
		$pos3 = new Position($center->x + 3, $center->y + 4, $center->z - 3, $world);
		self::fill($pos1, $pos3, $block, false);
		self::fill($pos3, $pos2, $block, false);
		$pos4 = new Position($center->x - 3, $center->y + 4, $center->z + 3, $world);
		self::fill($pos2, $pos4, $block, false);
		self::fill($pos4, $pos1, $block, false);
	}

	public static function initPlayer(Player $player) : void {
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
		return match (true) {
			$dyeColor->equals(DyeColor::ORANGE()) => TextFormat::GOLD,
			$dyeColor->equals(DyeColor::MAGENTA()) => TextFormat::DARK_PURPLE,
			$dyeColor->equals(DyeColor::LIGHT_BLUE()) => TextFormat::AQUA,
			$dyeColor->equals(DyeColor::LIME()) => TextFormat::GREEN,
			$dyeColor->equals(DyeColor::PINK()) => TextFormat::LIGHT_PURPLE,
			$dyeColor->equals(DyeColor::GRAY()) => TextFormat::DARK_GRAY,
			$dyeColor->equals(DyeColor::LIGHT_GRAY()) => TextFormat::GRAY,
			$dyeColor->equals(DyeColor::CYAN()) => TextFormat::DARK_AQUA,
			$dyeColor->equals(DyeColor::BLUE()) => TextFormat::BLUE,
			$dyeColor->equals(DyeColor::GREEN()) => TextFormat::DARK_GREEN,
			$dyeColor->equals(DyeColor::RED()) => TextFormat::DARK_RED,
			$dyeColor->equals(DyeColor::BLACK()) => TextFormat::BLACK,
			$dyeColor->equals(DyeColor::YELLOW()) => TextFormat::YELLOW,
			default => TextFormat::WHITE
		};
	}

	public static function resolvePath(string $folder, string $path) : string {
		if ($path[0] === "/") {
			return $path;
		}
		if (PMUtils::getOS() === "win") {
			if ($path[0] === "\\" || $path[1] === ":") {
				return $path;
			}
		}
		return $folder . $path;
	}
}
