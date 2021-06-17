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

use minerware\Minerware;
use minerware\database\DataHolder;
use minerware\database\DataManager;
use minerware\language\Translator;
use minerware\utils\Utils;
use pocketmine\lang\TranslationContainer;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;
use pocketmine\utils\SingletonTrait;

# Events
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;

final class MapRegisterer implements Listener {

    use SingletonTrait;
    
    /** @var array<string, self> */
    private static $mapRegisterer;

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
        Minerware::getInstance()->getServer()->getPluginManager()->registerEvents($this, Minerware::getInstance());
    }
    

    public static function createRegisterer(Player $player, World $world): self {
        return (self::$mapRegisterer[strtolower($player->getName())] = new self($player, $world));
    }
    
    public static function getRegister(Player $player): ?self {
        return self::$mapRegisterer[strtolower($player->getName())] ?? null;
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

    #  _      _     _                       
    # | |    (_)   | |                      
    # | |     _ ___| |_ ___ _ __   ___ _ __ 
    # | |    | / __| __/ _ \ '_ \ / _ \ '__|
    # | |____| \__ \ ||  __/ | | |  __/ |   
    # |______|_|___/\__\___|_| |_|\___|_|

    public function onChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        $args = explode(' ',$event->getMessage());
        if ($player === $this->player) {
            switch ($args[0]) {
                case 'help':
                    // code...
                break;

                default:
                    // code...
                break;
            }
        }
    }
}