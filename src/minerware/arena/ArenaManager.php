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
use minerware\language\Translator;
use minerware\database\DataManager;
use pocketmine\lang\TranslationContainer;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use pocketmine\player\Player;

final class ArenaManager {
    use SingletonTrait;
    
    /** @var Minerware */
    private $plugin;
    
    /** @var ?World */
    private $lobby;
    
    /** @var array<string, Arena> */
    private $arenas = [];
    
    /** @var array<string, World> */
    private $registeredMaps = [];
    
    public function __construct() {
        $this->plugin = Minerware::getInstance();
        $this->lobby = DataManager::getInstance()->getLobby();
    }

    public function getArenas(): array  {
        return $this->arenas;
    }

    public function createArena(): Arena {
        $arena = new Arena();
        $this->arenas[] = $arena;
        return $arena;
    }

    public function getAvaible(): Arena  {
        foreach ($this->arenas as $arena) {
            if ($arena->getStatus() === "waiting" && count($arena->getPlayers()) < Arena::MAX_PLAYERS) {
                return $arena;
            }
        }
        return $this->createArena();
    }

    public function join(Player $player, Arena $arena = null): void {
        if ($this->lobby === null) {
            $player->sendMessage(Translator::getInstance()->translate(new TranslationContainer("error.lobby.isNotSet")));
            return;
        }
        if ($arena == null) {
            $arena = $this->getAvaible();
        }
        $arena->join($player);
        $this->lobby->loadChunk($this->lobby->getSafeSpawn()->getFloorX(), $this->lobby->getSafeSpawn()->getFloorZ());
        $player->teleport($this->lobby->getSafeSpawn(), 0, 0);
    }
}