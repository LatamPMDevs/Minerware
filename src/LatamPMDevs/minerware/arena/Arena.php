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

use LatamPMDevs\minerware\arena\microgame\Microgame;
use LatamPMDevs\minerware\database\DataManager;
use LatamPMDevs\minerware\Minerware;
use LatamPMDevs\minerware\tasks\ArenaTask;

use LatamPMDevs\minerware\utils\PointHolder;
use LatamPMDevs\minerware\utils\Utils;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\GameMode;

use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;
use function array_rand;
use function count;

final class Arena implements Listener {

	public const MIN_PLAYERS = 2;
	public const MAX_PLAYERS = 12;

	public const STARTING_TIME = 121;
	public const INBETWEEN_TIME = 7;
	public const ENDING_TIME = 10;

	private Minerware $plugin;

	private Status $status;

	private World $world;

	/** @var Player[] */
	private array $players = [];

	private PointHolder $pointHolder;

	/** @var Microgame[] */
	private array $microgamesQueue = [];

	private ?Microgame $currentMicrogame = null;

	private int $currentMicrogameIndex = -1;

	public int $startingtime = self::STARTING_TIME;

	public int $inbetweentime = self::INBETWEEN_TIME;

	public int $endingtime = self::ENDING_TIME;

	public bool $isInFirstInBetween = false;

	public function __construct(private string $id, private Map $map) {
		$this->plugin = Minerware::getInstance();
		$this->world = $this->map->generateWorld($this->id);
		$this->status = Status::WAITING();
		$this->pointHolder = new PointHolder();
		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
		$this->plugin->getScheduler()->scheduleRepeatingTask(new ArenaTask($this), 20);
		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);

		# TODO: Microgames!
	}

	public function getId() : string {
		return $this->id;
	}

	public function getWorld() : World {
		return $this->world;
	}

	public function getMap() : ?Map {
		return $this->map;
	}

	public function getStatus() : Status {
		return $this->status;
	}

	public function setStatus(Status $status) : void {
		$this->status = $status;
	}

	public function join(Player $player) : void {
		$this->players[$player->getId()] = $player;
		foreach ($this->players as $pl) {
			$pl->sendMessage($this->plugin->getTranslator()->translate(
				$pl, "game.player.join", [
					"{%player}" => $player->getName(),
					"{%count}" => count($this->players) . "/" . self::MAX_PLAYERS
				]
			));
		}
		$spawns = $this->map->getSpawns();
		$spawn = Position::fromObject($spawns[array_rand($spawns)], $this->world);
		$player->teleport($spawn);
		$player->getInventory()->clearAll();
		$player->getArmorInventory()->clearAll();
		$player->getCursorInventory()->clearAll();
		$player->setGamemode(GameMode::ADVENTURE());
	}

	public function quit(Player $player) : void {
		unset($this->players[$player->getId()]);
		$this->pointHolder->removePlayer($player);
		foreach ($this->players as $pl) {
			$pl->sendMessage($this->plugin->getTranslator()->translate(
				$pl, "game.player.quit", [
					"{%player}" => $player->getName(),
					"{%count}" => count($this->players) . "/" . self::MAX_PLAYERS
				]
			));
		}
	}

	public function sendMessage(string $message) : void {
		foreach ($this->players as $player) {
			$player->sendMessage($message);
		}
	}

	public function inGame(Player $player) : bool {
		return isset($this->players[$player->getId()]);
	}

	public function getPlayers() : array {
		return $this->players;
	}

	public function getPointHolder() : PointHolder {
		return $this->pointHolder;
	}

	public function getCurrentMicrogame() : ?Microgame {
		return $this->currentMicrogame;
	}

	public function initNextMicrogame() : ?Microgame {
		$microgame = null;
		$this->currentMicrogameIndex++;
		if (isset($this->microgamesQueue[$this->currentMicrogameIndex])) {
			$this->currentMicrogame = new $this->microgamesQueue[$this->currentMicrogameIndex]($this);
			$microgame = $this->currentMicrogame;
		}
		return $microgame;
	}

	public function deleteMap() : void {
		if ($this->world !== null) {
			$worldPath = $this->plugin->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . $this->world->getFolderName() . DIRECTORY_SEPARATOR;
			$this->plugin->getServer()->getWorldManager()->unloadWorld($this->world, true);
			Utils::removeDir($worldPath);
		}
	}

	public function updateScoreboard() : void {
		$scoreboard = $this->plugin->getScoreboard();
		$translator = $this->plugin->getTranslator();
		foreach ($this->players as $player) {
			$remove = false;
			switch (true) {
				case ($this->status->equals(Status::WAITING())):
				case ($this->status->equals(Status::STARTING())):
					$lines = [];
					$lines[] = "§5" . $translator->translate($player, "text.map") . ":";
					$lines[] = "§f" . $this->map->getName();
					$lines[] = "§1";
					$lines[] = "§5" . $translator->translate($player, "text.players") . ":";
					$lines[] = "§f" . count($this->players) . "/" . self::MAX_PLAYERS;
					$lines[] = "§2";
					$lines[] = "§6" . DataManager::getInstance()->getServerIp();
					break;

				case ($this->status->equals(Status::INBETWEEN())):
				case ($this->status->equals(Status::INGAME())):
					$isOnTop = false;
					$i = 0;
					$lines = [];
					$lines[] = "§5" . $translator->translate($player, "text.scores") . ":";
					foreach ($this->pointHolder->getOrderedByHigherScore() as $user => $point) {
						$i++;
						if ($user === $player->getName()) {
							$lines[] = "§f" . "§a" . $point . " §8" . $user;
							$isOnTop = true;
						} elseif ($i === 5 && !$isOnTop) {
							$lines[] = "§f" . "§a" . $this->pointHolder->getPlayerPoints($player) . " §8" . $player->getName();
						} else {
							$lines[] = "§f" . "§e" . $point . " §8" . $user;
						}
						if ($i === 5) {
							break;
						}
					}
					$lines[] = "§1";
					$lines[] = "§5Microgame:";
					$lines[] = "§f" . ($this->status->equals(Status::INBETWEEN()) ? "In-between" : $this->getCurrentMicrogame()->getName());
					$lines[] = "§5(§f" . ($this->status->equals(Status::INBETWEEN()) ? 0 : $this->currentMicrogameIndex + 1) . "§5/§f" . count($this->microgamesQueue) . "§5)";
					$lines[] = "§2";
					$lines[] = "§6" . DataManager::getInstance()->getServerIp();
					break;

				default:
					$remove = true;
					break;
			}
			if ($remove) {
				$scoreboard->remove($player);
			} else {
				$from = 0;
				$scoreboard->new($player, $player->getName(), '§l§eMinerWare');
				foreach ($lines as $line) {
					if ($from < 15) {
						$from++;
						$scoreboard->setLine($player, $from, $line);
					}
				}
			}
		}
	}

	#Listener

	public function onQuit(PlayerQuitEvent $event) : void {
		$player = $event->getPlayer();
		if ($this->inGame($player)) {
			$this->quit($player);
		}
	}

	/**
	 * @ignoreCancelled
	 * @priority MONITOR
	 */
	public function onWorldChange(EntityTeleportEvent $event) : void {
		$player = $event->getEntity();
		if (!$player instanceof Player) return;
		if ($event->getTo()->getWorld() === $this->world) return;
		if ($event->getFrom()->getWorld() === $event->getTo()->getWorld()) return;
		if ($this->inGame($player)) {
			$this->quit($player);
		}
	}

	public function onExhaust(PlayerExhaustEvent $event) : void {
		$player = $event->getPlayer();
		if ($this->inGame($player)) {
			$event->cancel();
		}
	}

	public function onDamage(EntityDamageEvent $event) : void {
		$player = $event->getEntity();
		if (!$player instanceof Player) return;
		if (!$this->inGame($player)) return;
		if ($this->status->equals(Status::WAITING()) || $this->status->equals(Status::STARTING())) {
			$event->cancel();
		}
	}
}
