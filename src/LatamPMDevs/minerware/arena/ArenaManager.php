<?php

/**
 *  ███╗   ███╗██╗███╗   ██╗███████╗██████╗ ██╗    ██╗ █████╗ ██████╗ ███████╗
 *  ████╗ ████║██║████╗  ██║██╔════╝██╔══██╗██║    ██║██╔══██╗██╔══██╗██╔════╝
 *  ██╔████╔██║██║██╔██╗ ██║█████╗  ██████╔╝██║ █╗ ██║███████║██████╔╝█████╗
 *  ██║╚██╔╝██║██║██║╚██╗██║██╔══╝  ██╔══██╗██║███╗██║██╔══██║██╔══██╗██╔══╝
 *  ██║ ╚═╝ ██║██║██║ ╚████║███████╗██║  ██║╚███╔███╔╝██║  ██║██║  ██║███████╗
 *  ╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝ ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝
 *
 * A game written in PHP for PocketMine-MP software.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Copyright 2022 © LatamPMDevs
 */

declare(strict_types=1);

namespace LatamPMDevs\minerware\arena;

use LatamPMDevs\minerware\database\DataManager;
use LatamPMDevs\minerware\Minerware;
use pocketmine\event\HandlerListManager;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use function array_rand;
use function count;
use function rand;
use function range;
use function shuffle;

final class ArenaManager {
	use SingletonTrait;

	/** @var array<string, Arena> */
	private array $arenas = [];

	public function getArenas() : array {
		return $this->arenas;
	}

	public function getById(string $id) : ?Arena {
		return (isset($this->arenas[$id]) ? $this->arenas[$id] : null);
	}

	public function createArena(?Map $map = null) : Arena {
		$id = $this->generateId();
		$arena = new Arena($id, $map ?? Map::$maps[array_rand(Map::$maps)]);
		$this->arenas[$id] = $arena;
		return $arena;
	}

	public function deleteArena(Arena $arena) : void {
		HandlerListManager::global()->unregisterAll($arena);
		unset($this->arenas[$arena->getId()]);
	}

	public function getAvaible(?Map $map = null, bool $force = false) : ?Arena {
		foreach ($this->arenas as $arena) {
			if (($arena->getStatus()->equals(Status::WAITING()) || $arena->getStatus()->equals(Status::STARTING())) && ($map === null || $arena->getMap() === $map) && count($arena->getPlayers()) < Arena::MAX_PLAYERS) {
				return $arena;
			}
		}
		if ($force && count($this->arenas) >= DataManager::getInstance()->getMaxRuntimeArenas()) {
			return null;
		}
		return $this->createArena($map);
	}

	public function generateId() : string {
		$az = range("a", "z");
		shuffle($az);

		$name = "";
		$name .= $az[0];
		$name .= $az[1];
		$name .= rand(10, 99);
		return $name;
	}

	public function join(Player $player, Arena $arena = null, Map $map = null) : void {
		if ($arena === null) {
			if (count(Map::$maps) === 0 || ($arena = $this->getAvaible($map)) === null) {
				$player->sendMessage(Minerware::getInstance()->getTranslator()->translate($player, "game.noArenaAvaiable"));
				return;
			}
		}
		$arena->join($player);
	}
}
