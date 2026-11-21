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

namespace LatamPMDevs\minerware\join;

use LatamPMDevs\minerware\arena\ArenaManager;
use LatamPMDevs\minerware\database\DataManager;
use LatamPMDevs\minerware\map\MapManager;
use LatamPMDevs\minerware\Minerware;
use pocketmine\player\Player;
use RuntimeException;
use function ceil;
use function count;
use function max;
use function microtime;

/**
 * The single way for a player to ask for a game, whatever they used to ask (command, sign, NPC).
 */
final class Joiner {

	private const PRUNE_THRESHOLD = 64; //entries

	/** @var array<int, float> player id => seconds (microtime) of the last attempt */
	private array $lastAttempts = [];

	public function __construct(private readonly Minerware $plugin) { }

	/**
	 * Puts the player in an arena, on the given map if any, and tells them when it did not work.
	 */
	public function join(Player $player, ?string $mapName = null) : JoinResult {
		$result = $this->attempt($player, $mapName);
		$this->notify($player, $result);
		return $result;
	}

	/**
	 * Puts the players of a game that ended in a new arena. They already asked to play, so there is no permission or cooldown.
	 */
	public function requeue(Player $player) : void {
		$this->notify($player, ArenaManager::getInstance()->place($player));
	}

	private function attempt(Player $player, ?string $mapName) : JoinResult {
		if (!$player->hasPermission("minerware.join")) {
			return JoinResult::DENIED;
		}
		$arenaManager = ArenaManager::getInstance();
		if ($arenaManager->getArenaOf($player) !== null) {
			return JoinResult::ALREADY_IN_ARENA;
		}
		if ($this->getCooldownLeft($player) > 0) {
			return JoinResult::COOLDOWN;
		}
		$this->lastAttempts[$player->getId()] = microtime(true);

		$map = null;
		if ($mapName !== null) {
			$map = MapManager::getInstance()->getByName($mapName);
			if ($map === null) {
				return JoinResult::MAP_NOT_FOUND;
			}
		}
		try {
			return $arenaManager->place($player, null, $map);
		} catch (RuntimeException $e) {
			$this->plugin->getLogger()->error("Could not create an arena: " . $e->getMessage());
			return JoinResult::ERROR;
		}
	}

	private function notify(Player $player, JoinResult $result) : void {
		$key = $result->getMessageKey();
		if ($key === null) {
			return;
		}
		$replacements = $result === JoinResult::COOLDOWN ? ["{%seconds}" => (string) (int) ceil($this->getCooldownLeft($player))] : [];
		$player->sendMessage($this->plugin->getTranslator()->translate($player, $key, $replacements));
	}

	private function getCooldownLeft(Player $player) : float {
		$cooldown = DataManager::getInstance()->getJoinCooldown();
		$now = microtime(true);
		if (count($this->lastAttempts) > self::PRUNE_THRESHOLD) {
			foreach ($this->lastAttempts as $id => $time) {
				if ($now - $time >= $cooldown) {
					unset($this->lastAttempts[$id]);
				}
			}
		}
		$last = $this->lastAttempts[$player->getId()] ?? null;
		return $last === null ? 0.0 : max(0.0, $cooldown - ($now - $last));
	}
}
