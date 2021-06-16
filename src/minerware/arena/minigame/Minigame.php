<?php

namespace minerware\arena\minigame;

use pocketmine\event\Listener;
use pocketmine\player\Player;

abstract class Minigame implements Listener {
    
    /** @var array<int, Player> */
    protected $winners = [];
    
    /** @var array<int, Player> */
    protected $losers = [];
    
    /** @var bool */
    protected $lastGame = false;
    
    public function setWinner(Player $player): void {
        $this->winners[] = $player;
    }
    
    public function addLoser(Player $player): void {
        $this->losers[] = $player;
    }
    
    public function getWinners(): array {
        return $this->winners;
    }
    
    public function getLosers(): array {
        return $this->losers;
    }
    
    public function getWinner(): ?Player {
        return $this->winners[0] ?? null;
    }
    
    abstract public function start(): void;
    abstract public function end(): void;
}