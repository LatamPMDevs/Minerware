<?php

/**
 *  ███╗   ███╗██╗███╗   ██╗███████╗██████╗ ██╗    ██╗ █████╗ ██████╗ ███████╗
 *  ████╗ ████║██║████╗  ██║██╔════╝██╔══██╗██║    ██║██╔══██╗██╔══██╗██╔════╝
 *  ██╔████╔██║██║██╔██╗ ██║█████╗  ██████╔╝██║ █╗ ██║███████║██████╔╝█████╗
 *  ██║╚██╔╝██║██║██║╚██╗██║██╔══╝  ██╔══██╗██║███╗██║██╔══██║██╔══██╗██╔══╝
 *  ██║ ╚═╝ ██║██║██║ ╚████║███████╗██║  ██║╚███╔███╔╝██║  ██║██║  ██║███████╗
 *  ╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝ ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝
 *
 * This is a private project, your not allow to redistribute nor resell it.
 * The only ones with that power are this project's contributors.
 *
 * Copyright 2022 © Minerware
 */

declare(strict_types=1);

namespace minerware\utils;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\player\Player;
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
use function rmdir;
use function scandir;
use function str_repeat;
use function strlen;
use function substr;
use function unlink;

final class Utils {

	public static function calculateParameter(Vector3 $firstPoint, Vector3 $secondPoint): string {
		//TODO:: Calculate parameter between $firstPoint and $secondPoint.
		return "";
	}

	public static function calculateSize(Vector3 $firstPoint, Vector3 $secondPoint): string {
		return (abs($firstPoint->x - $secondPoint->x) + 1) . "x" . (abs($firstPoint->z - $secondPoint->z) + 1);
	}

	public static function setZip(string $targetPath, string $zipPath): bool {
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

	public static function playSound(Player $player, string $sound, float $volume = 1, float $pitch = 1): void {
		$pk = new PlaySoundPacket();
		$pk->x = $player->getPosition()->getX();
		$pk->y = $player->getPosition()->getY();
		$pk->z = $player->getPosition()->getZ();
		$pk->soundName = $sound;
		$pk->volume = $volume;
		$pk->pitch = $pitch;
		$player->getNetworkSession()->sendDataPacket($pk);
	}

	public static function removeDir(string $path): void {
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

	public static function removeFile(string $path): void {
		unlink($path);
	}

	public static function getStartingBar(int $colorSticks, int $totalSticks): string {
		$leftover = $totalSticks - $colorSticks;
		return str_repeat("▌", $colorSticks) . "§7" . str_repeat("▌", $leftover);
	}

	public static function calculateMinAndMaxPos(Vector3 $pos1, Vector3 $pos2): array {
		$minX = min($pos1->x, $pos2->x);
		$minY = min($pos1->y, $pos2->y);
		$minZ = min($pos1->z, $pos2->z);

		$maxX = max($pos1->x, $pos2->x);
		$maxY = max($pos1->y, $pos2->y);
		$maxZ = max($pos1->z, $pos2->z);

		return ["Min" => new Vector3($minX, $minY, $minZ), "Max" => new Vector3($maxX, $maxY, $maxZ)];
	}
}
