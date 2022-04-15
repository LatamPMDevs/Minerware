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

use LatamPMDevs\minerware\arena\microgame\Level;
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
use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\Position;
use pocketmine\world\World;
use function array_rand;
use function count;

final class Arena implements Listener {

	public const MIN_PLAYERS = 2;
	public const MAX_PLAYERS = 12;

	public const STARTING_TIME = 10;//121
	public const INBETWEEN_TIME = 5;
	public const ENDING_TIME = 10;

	public const NORMAL_MICROGAMES = 15;

	private Minerware $plugin;

	private Status $status;

	private World $world;

	/** @var Player[] */
	private array $players = [];

	private PointHolder $pointHolder;

	/** @var Microgame[] */
	private array $microgamesQueue = [];

	private ?Microgame $currentMicrogame = null;

	private int $nextMicrogameIndex = 0;

	public int $startingtime = self::STARTING_TIME;

	public int $inbetweentime = 11;

	public int $endingtime = self::ENDING_TIME;

	public function __construct(private string $id, private Map $map) {
		$this->plugin = Minerware::getInstance();
		$this->world = $this->map->generateWorld($this->id);
		$this->status = Status::WAITING();
		$this->pointHolder = new PointHolder();
		$this->plugin->getScheduler()->scheduleRepeatingTask(new ArenaTask($this), 20);
		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);

		# TODO: More Microgames!
		$normalMicrogames = $this->plugin->getNormalMicrogames();
		shuffle($normalMicrogames);
		foreach($normalMicrogames as $microgame) {
			$this->microgamesQueue[] = new $microgame($this);
		}
		/*$bossMicrogames = $this->plugin->getBossMicrogames();
		$this->microgamesQueue[] = new $bossMicrogames[array_rand($bossMicrogames)];*/

		$this->plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () : void {
			if ($this->status->equals(Status::ENDING())) {
				throw new CancelTaskException("Arena is no more in-game");
			}
			if ($this->currentMicrogame !== null && $this->currentMicrogame->isRunning()) {
				$this->currentMicrogame->tick();
			};
		}), 2);

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

	public function getPlugin() : Minerware {
		return $this->plugin;
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
		$player->getOffHandInventory()->clearAll();
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

	public function setCurrentMicrogame(?Microgame $microgame) : void {
		$this->currentMicrogame = $microgame;
	}

	public function getCurrentMicrogame() : ?Microgame {
		return $this->currentMicrogame;
	}

	public function getCurrentMicrogameNonNull() : Microgame {
		if ($this->currentMicrogame === null) {
			throw new AssumptionFailedError("Microgame is null");
		}
		return $this->currentMicrogame;
	}

	public function getNextMicrogame() : ?Microgame {
		return $this->microgamesQueue[$this->nextMicrogameIndex] ?? null;
	}

	public function getNextMicrogameNonNull() : Microgame {
		return $this->microgamesQueue[$this->nextMicrogameIndex] ?? throw new AssumptionFailedError("Next Microgame is null");

	}

	public function startNextMicrogame() : ?Microgame {
		$microgame = $this->getNextMicrogame();
		if ($microgame !== null) {
			$this->setCurrentMicrogame($microgame);
			$microgame->start();
			$this->nextMicrogameIndex++;
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
		$isBoss = false;
		$currentMicrogame = $this->getCurrentMicrogame();
		if ($currentMicrogame !== null && $currentMicrogame->getLevel()->equals(Level::BOSS())) {
			$isBoss = true;
		}
		foreach ($this->players as $player) {
			$lines = [];
			$remove = false;
			switch (true) {
				case ($this->status->equals(Status::WAITING())):
				case ($this->status->equals(Status::STARTING())):
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
					$lines[] = "§e" . ($this->status->equals(Status::INBETWEEN()) ? "§fIn-between" : $this->getCurrentMicrogameNonNull()->getName());
					$lines[] = "§5(§f" . ($isBoss ? "Bossgame" : $this->nextMicrogameIndex . "§5/§f" . count($this->microgamesQueue) - 1) . "§5)";
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

	public function endCurrentMicrogame() : void {
		$microgame = $this->getCurrentMicrogameNonNull();
		if ($microgame->isRunning()) {
			$winners = $microgame->getWinners();
			$winnersCount = count($winners);
			foreach ($this->players as $player) {
				$player->sendMessage("\n§l§e" . $microgame->getName());
				if ($winnersCount <= 0) {
					$player->sendMessage($this->plugin->getTranslator()->translate(
						$player, "microgame.nowinners", [
							"{%count}" => count($this->players)
						]
					));
				} elseif ($winnersCount <= 3) {
					$player->sendMessage($this->plugin->getTranslator()->translate(
						$player, "microgame.winners", [
							"{%players}" => implode(", ", Utils::getPlayersNames($winners))
						]
					));
				} else {
					$player->sendMessage($this->plugin->getTranslator()->translate(
						$player, "microgame.winners2", [
							"{%winners_count}" => $winnersCount,
							"{%players_count}" => count($this->players)
						]
					));
				}
			}
			$microgame->end();
			$recompense = $microgame->getRecompensePoints();
			$showWorthMessage = $recompense > Microgame::DEFAULT_RECOMPENSE_POINTS;
			foreach ($this->players as $player) {
				$player->getInventory()->clearAll();
				$player->getArmorInventory()->clearAll();
				$player->getCursorInventory()->clearAll();
				$player->getOffHandInventory()->clearAll();
				$player->setGamemode(GameMode::ADVENTURE());
				if ($microgame->isWinner($player)) {
					$this->pointHolder->addPlayerPoint($player, $recompense);
					$player->sendTitle("§1§2", $this->plugin->getTranslator()->translate($player, "microgame.success"), 1, 20, 1);
				} else {
					$player->sendTitle("§1§2", $this->plugin->getTranslator()->translate($player, "microgame.failed"), 1, 20, 1);
				}
				if ($showWorthMessage) {
					$player->sendMessage($this->plugin->getTranslator()->translate(
						$player, "microgame.worth", [
							"{%points}" => $recompense
						]
					));
				}
			}
			foreach ($this->world->getEntities() as $entity) {
				if (!$entity instanceof Player) {
					$entity->flagForDespawn();
				}
			}
			$this->setCurrentMicrogame(null);
		}
	}

	public function sendToWinnersCage(Player $player) : void {
		# TODO!
	}

	public function sendToLosersCage(Player $player) : void {
		# TODO!
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
		if ($this->status->equals(Status::WAITING()) || $this->status->equals(Status::STARTING()) || $this->status->equals(Status::INBETWEEN())) {
			$event->cancel();
		}
	}
}
