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
use minerware\Minerware;
use minerware\utils\Utils;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\lang\TranslationContainer;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;

final class MapRegisterer implements Listener {
    use SingletonTrait;
    
    /** @var array<string, self> */
    private static $mapRegisterer;
    
    public static function createRegisterer(Player $player, World $world): self {
        return (self::$mapRegisterer[strtolower($player->getName())] = new self($player, $world));
    }
    
    /** @var array<string, mixed> */
    private $data = [];
    
    /** @var Player */
    private $player;
    
    /** @var world */
    private $world;
    
    private function __construct(Player $player, World $world) {
        $this->data["name"] = $world->getDisplayName();
        $this->player = $player;
        $this->world = $world;
        $this->setConfiguratorMode($player);
        Minerware::getInstance()->getServer()->getPluginManager()->registerEvents($this, Minerware::getInstance());
    }
    
    private function setConfiguratorMode(Player $player): void {
        $this->world->loadChunk($this->world->getSafeSpawn()->getFloorX(), $this->world->getSafeSpawn()->getFloorZ());
        $player->teleport($this->world->getSafeSpawn(), 0, 0);
        $player->sendMessage(Translator::getInstance()->translate(new TranslationContainer("configurator.mode.enter")));
    }
    
    private function setPlatform(Vector3 $firstPoint, Vector3 $secondPoint): void {
        $this->data["platform"]["firstPoint"] = "{$firstPoint->getX()}, {$firstPoint->getY()}, {$firstPoint->getZ()}";
        $this->data["platform"]["secondPoint"] = "{$secondPoint->getX()}, {$secondPoint->getY()}, {$secondPoint->getZ()}";
        $this->data["platform"]["parameter"] = Utils::calculateParameter($firstPoint, $secondPoint);
    }
    
    private function setVoid(Vector3 $void): void {
        $this->data["void"]["limit"] = "{$void->getY()}";
    }
    
    private function save(): void {
        DataManager::getInstance()->saveArenaData(new DataHolder($this->data));
        unset(self::$mapRegisterer[strtolower($this->player->getName())]);
    }
    
    public function chatCommand(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        $args = explode(" ", $event->getMessage());
        if (strtolower($player->getName()) == strtolower($this->player->getName())) {
            switch (strtolower($args[0])) {
                case "help":
                    // code...
                break;
                
                case "setvoid":
                    $this->setVoid($player->getPosition());
                break;
                
                case "setplatform":
                    // code...
                break;
                
                default:
                    // code...
                break;
            }
        }
    }
}