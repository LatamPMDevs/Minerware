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
 * Copyright 2021 © Minerware
 */

declare(strict_types=1);

namespace minerware\utils;

use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;

final class Utils {
    
    public static function calculateParameter(Vector3 $firstPoint, Vector3 $secondPoint): string {
        //TODO:: Calculate parameter between $firstPoint and $secondPoint.
        return "";
    }
    
    public static function calculateSize(Vector3 $firstPoint, Vector3 $secondPoint): string {
        return (abs($firstPoint->x - $secondPoint->x) + 1)."x".(abs($firstPoint->z - $secondPoint->z) + 1);
    }
    
    public static function setZip(string $targetPath, string $zipPath): bool {
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($targetPath),
            \RecursiveIteratorIterator::LEAVES_ONLY
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
        return str_repeat("▌", $colorSticks)."§7".str_repeat("▌", $leftover);
    }
}