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

use minerware\arena\minigame\Microgame;
use minerware\language\Translator;
use minerware\utils\PointHolder;
use pocketmine\lang\TranslationContainer;
use pocketmine\player\Player;
use pocketmine\world\World;

final class Arena {

    public const MIN_PLAYERS = 2;

    public const MAX_PLAYERS = 12;
    
    /** @var string */
    private $status = "waiting";
    
    /** @var ?World */
    private $world = null;
    
    /** @var array<string, Player> */
    private $players = [];
    
    /** @var PointHolder */
    private $pointHolder;
    
    /** @var Microgame */
    private $currentMicrogame = null;
    
    public function __construct() {
        $this->pointHolder = new PointHolder();
    }
    
    public function getWorld(): ?World {
        return $this->world;
    }
    
    public function getStatus(): string {
        return $this->status;
    }
    
    public function setStatus(string $value): void {
        $this->status = $value;
    }
    
    public function join(Player $player): void {
        $this->players[$player->getName()] = $player;
        $this->sendMessage(Translator::getInstance()->translate(new TranslationContainer("game.player.join", [$player->getName(), count($this->players)."/".self::MAX_PLAYERS])));
    }
    
    public function quit(Player $player): void {
        unset($this->players[$player->getName()]);
    }
    
    public function sendMessage(string $message): void {
        foreach ($this->players as $player) {
            $player->sendMessage($message);
        }
    }
    
    public function getPlayers(): array {
        return $this->players;
    }
    
    public function getPointHolder(): PointHolder {
        return $this->pointHolder;
    }
    
    public function getCurrentMicrogame(): ?Microgame {
        return $this->currentMicrogame;
    }
}