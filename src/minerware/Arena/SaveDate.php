<?php

namespace minerware\Arena;


use minerware\Minerware;
use pocketmine\utils\Config;

class SaveDate {
    private static $world;
    private Minerware $plugin;
    private Config $cfg;
    public function __construct(string $world)
{
    self::$world = $world;
}
    public function savingWorld() {
        return new Config($this->plugin->getDataFolder(). 'arenas/'.self::$world.'.yml', Config::YAML);
    }
}