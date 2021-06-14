<?php

namespace minerware\arena;

use minerware\database\DataHolder;
use minerware\database\DataManager;
use pocketmine\player\Player;
use pocketmine\world\World;

final class ArenaCreator {
    
    private array $data;
    
    private string $id;
    
    public function __construct(private Player $player, private World $world) {
        $this->id = $this->generateID();
        $this->data["id"] = $this->id;
    }
    
    private function generateID(): string {
        $az = range("a", "z");
        shuffle($az);
        
        $name = "";
        $name .= $az[0];
        $name .= $az[1];
        $name .= rand(10, 99);
        return $name;
    }
    
    public function getID(): string {
        return $this->id;
    }
    
    public function save(): void {
        DataManager::getInstance()->saveArenaData(new DataHolder($this->data));
    }
}