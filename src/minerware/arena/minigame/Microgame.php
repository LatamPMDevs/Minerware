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

namespace minerware\arena\minigame;

use pocketmine\event\Listener;
use pocketmine\player\Player;

abstract class Microgame implements Listener, GameLevel {
    
    /** @var array<int, Player> */
    protected $winners = [];
    
    /** @var array<int, Player> */
    protected $losers = [];
    
    /** @var int */
    protected $level = self::LEVEL_NORMAL;
    
    public function addWinner(Player $player): void {
        $this->winners[] = $player;
    }
    
    public function getWinner(): ?Player {
        return $this->winners[0] ?? null;
    }
    
    /**
     * @return array<int, Player>
     */
    public function getWinners(): array {
        return $this->winners;
    }
    
    public function addLoser(Player $player): void {
        $this->losers[] = $player;
    }
    
    /**
     * @return array<int, Player>
     */
    public function getLosers(): array {
        return $this->losers;
    }
    
    public function getLevel(): int {
        return $this->level;
    }
    
    abstract public function start(): void;
    abstract public function tick(): void;
    abstract public function end(): void;
}