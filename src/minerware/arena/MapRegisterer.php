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

namespace minerware\arena;

use minerware\database\DataHolder;
use minerware\database\DataManager;
use minerware\language\Translator;
use minerware\utils\Utils;
use pocketmine\lang\TranslationContainer;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;

final class MapRegisterer {
    
    /** @var array<string, self> */
    private static $mapRegisterer;
    
    public static function createRegisterer(Player $player, World $world): self {
        return (self::$mapRegisterer[strtolower($player->getName())] = new self($player, $world));
    }
    
    public static function getRegister(Player $player): ?self {
        return self::$mapRegisterer[strtolower($player->getName())] ?? null;
    }
    
    /** @var array<string, mixed> */
    private $data = [];
    
    /** @var world */
    private $world;
    
    /** @var Player */
    private $player;
    
    private function __construct(Player $player, World $world) {
        $this->data["name"] = $world->getDisplayName();
        $this->world = $world;
        $this->setConfiguratorMode($player);
    }
    
    private function setConfiguratorMode(Player $player): void {
        $this->world->loadChunk($this->world->getSafeSpawn()->getFloorX(), $this->world->getSafeSpawn()->getFloorZ());
        $player->teleport($this->world->getSafeSpawn(), 0, 0);
        $player->sendMessage(Translator::getInstance()->translate(new TranslationContainer("configurator.mode.enter")));
        $this->player = $player;
    }
    
    public function getWorld(): World {
        return $this->world;
    }
    
    public function setPlatform(Vector3 $firstPoint, Vector3 $secondPoint): void {
        $this->data["platform"]["firstPoint"] = "{$firstPoint->getX()}, {$firstPoint->getY()}, {$firstPoint->getZ()}";
        $this->data["platform"]["secondPoint"] = "{$secondPoint->getX()}, {$secondPoint->getY()}, {$secondPoint->getZ()}";
        $this->data["platform"]["parameter"] = Utils::calculateParameter($firstPoint, $secondPoint);
    }
    
    public function setVoid(Vector3 $void): void {
        $this->data["void"]["limit"] = "{$void->getY()}";
    }
    
    public function save(): void {
        DataManager::getInstance()->saveArenaData(new DataHolder($this->data));
        unset(self::$mapRegisterer[$this->player->getName()]);
    }
}