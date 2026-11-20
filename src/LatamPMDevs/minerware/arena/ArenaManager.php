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
use LatamPMDevs\minerware\join\JoinResult;
use LatamPMDevs\minerware\map\Map;
use LatamPMDevs\minerware\map\MapManager;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use RuntimeException;
use function chr;
use function count;
use function random_int;

final class ArenaManager {
	use SingletonTrait;

	/** @var array<string, Arena> */
	private array $arenas = [];

	/** @var array<int, Arena> player id => the arena they are in */
	private array $playerArenas = [];

	/**
	 * @return array<string, Arena>
	 */
	public function getArenas() : array {
		return $this->arenas;
	}

	public function getById(string $id) : ?Arena {
		return $this->arenas[$id] ?? null;
	}

	public function createArena(?Map $map = null) : Arena {
		$id = $this->generateId();
		$arena = new Arena($id, $map ?? MapManager::getInstance()->getRandom() ?? throw new RuntimeException("No maps available to create an arena"));
		$this->arenas[$id] = $arena;
		return $arena;
	}

	public function deleteArena(Arena $arena) : void {
		unset($this->arenas[$arena->getId()]);
	}

	public function getAvailable(?Map $map = null) : ?Arena {
		foreach ($this->arenas as $arena) {
			if ($arena->getPhase()->isLobby() && ($map === null || $arena->getMap() === $map) && count($arena->getPlayers()) < Arena::MAX_PLAYERS) {
				return $arena;
			}
		}
		if (count($this->arenas) >= DataManager::getInstance()->getMaxRuntimeArenas()) {
			return null;
		}
		return $this->createArena($map);
	}

	public function generateId() : string {
		do {
			$id = chr(random_int(97, 122)) . chr(random_int(97, 122)) . random_int(10, 99);
		} while (isset($this->arenas[$id]));
		return $id;
	}

	/**
	 * Runs one second of every arena.
	 */
	public function tick() : void {
		foreach ($this->arenas as $arena) {
			$arena->tick();
		}
	}

	/**
	 * Runs a few ticks of the microgame of every arena.
	 */
	public function tickMicrogames() : void {
		foreach ($this->arenas as $arena) {
			$arena->tickMicrogame();
		}
	}

	public function getArenaOf(Player $player) : ?Arena {
		return $this->playerArenas[$player->getId()] ?? null;
	}

	/**
	 * Called by Arena when a player joins it.
	 */
	public function bindPlayer(Player $player, Arena $arena) : void {
		$this->playerArenas[$player->getId()] = $arena;
	}

	/**
	 * Called by Arena when a player leaves it.
	 */
	public function unbindPlayer(Player $player, Arena $arena) : void {
		if (($this->playerArenas[$player->getId()] ?? null) === $arena) {
			unset($this->playerArenas[$player->getId()]);
		}
	}

	/**
	 * Puts the player in the given arena, or in an available one (on the given map, if any).
	 *
	 * @throws RuntimeException
	 */
	public function join(Player $player, ?Arena $arena = null, ?Map $map = null) : JoinResult {
		if ($arena === null) {
			if (MapManager::getInstance()->getCount() === 0) {
				return JoinResult::NO_MAPS;
			}
			$arena = $this->getAvailable($map);
			if ($arena === null) {
				return JoinResult::FULL;
			}
		}
		if (!$arena->canJoin()) {
			return JoinResult::FULL;
		}
		return $arena->join($player) ? JoinResult::JOINED : JoinResult::DENIED;
	}
}
