<?php

namespace minerware\arena;

use minerware\language\Translator;
use minerware\database\DataHolder;
use minerware\database\DataManager;
use pocketmine\lang\TranslationContainer;
use pocketmine\player\Player;
use pocketmine\world\World;

final class MapRegisterer {
    
    /** @var Array $data */
    public $data;
    
    /** @var String $name */
    private $name;
    
    /** @var World $world */
    private $world;
    
    public function __construct(Player $player, string $name) {
        $this->name = $name);
        $this->data["name"] = $this->name;
        $this->loadWorld();
        $this->setConfiguratorMode($player);
    }

    private function loadWorld() {
        Server::getInstance()->getWorldManager()->loadWorld($this->name, true);
        $this->world = Server::getInstance()->getWorldManager()->getWorldByName($this->name);
    }

    private function setConfiguratorMode(Player $player) {
        $this->world->loadChunk($this->world->getSafeSpawn()->getFloorX(), $this->world->getSafeSpawn()->getFloorZ());
        $player->teleport($this->world->getSafeSpawn(), 0, 0);
        $player->sendMessage($this->plugin->getPrefix() . Translator::getInstance()->translate(new TranslationContainer("configurator.mode.youAre")));
    }
    
    public function getName(): string {
        return $this->name;
    }
    
    public function save(): void {
        DataManager::getInstance()->saveArenaData(new DataHolder($this->data));
    }
}