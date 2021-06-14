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

namespace minerware\database;

use minerware\Minerware;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;

final class DataManager {
    use SingletonTrait;
    
    private Minerware $plugin;
    
    private string $pluginPath;
    
    private Config $config;
    
    private int $arenaStorageType = 1;
    
    private int $playerStorageType = 1;
    
    public function __construct() {
        $this->plugin = Minerware::getInstance();
        $this->pluginPath = $this->plugin->getDataFolder();
        $this->config = $this->plugin->getConfig();
        
        $formats = Config::$formats;
        $formats["nbt"] = 6;
        $formats["namedtag"] = $formats["nbt"];
        
        $this->arenaStorageType = $formats[$this->config->getNested("storage-format.arena-data")];
        $this->playerStorageType = $formats[$this->config->getNested("storage-format.player-data")];
        
        @mkdir($this->pluginPath . "database" . DIRECTORY_SEPARATOR);
        @mkdir($this->pluginPath . "database" . DIRECTORY_SEPARATOR . "players" . DIRECTORY_SEPARATOR);
    }
    
    /**
     * TODO:: Add multi storage type support.
     */
    public function getPlayerData(string|Player $player): ?DataHolder {
        $filePath = "players" . DIRECTORY_SEPARATOR . (($player instanceof Player) ? $player->getName() : $player) . ".json";
        $path = $this->pluginPath . "database" . DIRECTORY_SEPARATOR . $path;
        if (file_exists($path)) {
            return new DataHolder((new Config($path, $this->playerStorageType))->getAll());
        }
        
        return null;
    }
}
