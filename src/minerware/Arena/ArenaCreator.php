<?php
namespace minerware\Arena;
use pocketmine\player\Player;

class ArenaCreator {
     private static $data = [
         'world' => []];
    public function __construct(Player $pl, string $world){
        $this->pl = $pl;
        self::$data['world'] = $world;
     $save = new SaveDate($world);
     $save->savingWorld();
    }


}