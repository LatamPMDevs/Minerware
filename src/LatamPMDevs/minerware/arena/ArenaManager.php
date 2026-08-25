<?php

/*
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
 * @author LatamPMDevs
 */

declare(strict_types=1);

namespace LatamPMDevs\minerware\arena;

use LatamPMDevs\minerware\database\DataManager;
use LatamPMDevs\minerware\map\Map;
use LatamPMDevs\minerware\map\MapManager;
use LatamPMDevs\minerware\Minerware;
use pocketmine\event\HandlerListManager;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use RuntimeException;
use function count;
use function rand;
use function range;
use function shuffle;

final class ArenaManager {
	use SingletonTrait;

	/** @var array<string, Arena> */
	private array $arenas = [];

	/**
	 * @return array<string, Arena>
	 */
	public function getArenas() : array {
		return $this->arenas;
	}

	public function getById(string $id) : ?Arena {
		return (isset($this->arenas[$id]) ? $this->arenas[$id] : null);
	}

	public function createArena(?Map $map = null) : Arena {
		$id = $this->generateId();
		$arena = new Arena($id, $map ?? MapManager::getInstance()->getRandom() ?? throw new RuntimeException("No maps available to create an arena"));
		$this->arenas[$id] = $arena;
		return $arena;
	}

	public function deleteArena(Arena $arena) : void {
		HandlerListManager::global()->unregisterAll($arena);
		unset($this->arenas[$arena->getId()]);
	}

	public function getAvailable(?Map $map = null, bool $force = false) : ?Arena {
		foreach ($this->arenas as $arena) {
			if (($arena->getStatus() === Status::WAITING || $arena->getStatus() === Status::STARTING) && ($map === null || $arena->getMap() === $map) && count($arena->getPlayers()) < Arena::MAX_PLAYERS) {
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
			if (MapManager::getInstance()->getCount() === 0 || ($arena = $this->getAvailable($map)) === null) {
				$player->sendMessage(Minerware::getInstance()->getTranslator()->translate($player, "game.noArenaAvaiable"));
				return;
			}
		}
		$arena->join($player);
	}
}