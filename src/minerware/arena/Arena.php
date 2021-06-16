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

use minerware\arena\minigame\Minigame;
use minerware\arena\utils\PointHolder;
use pocketmine\player\Player;
use pocketmine\world\World;

final class Arena {
    
    /** @var World */
    private $world;
    
    /** @var array<string, Player> */
    private $players = [];
    
    /** @var PointHolder */
    private $pointHolder;
    
    /** @var Minigame */
    private $currentMinigame = null;
    
    public function __construct(World $world) {
        $this->world = $world;
        $this->pointHolder = new PointHolder();
    }
    
    public function getWorld(): World {
        return $this->world;
    }
    
    public function join(Player $player): void {
        $this->players[$player->getName()] = $player;
    }
    
    public function quit(Player $player): void {
        unset($this->players[$player->getName()]);
    }
    
    public function getPlayers(): array {
        return $this->players;
    }
    
    public function getPointHolder(): PointHolder {
        return $this->pointHolder;
    }
    
    public function getCurrentMinigame(): ?Minigame {
        return $this->currentMinigame;
    }
}