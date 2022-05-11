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

use InvalidArgumentException;
use LatamPMDevs\minerware\arena\microgame\Level;
use LatamPMDevs\minerware\arena\microgame\Microgame;
use LatamPMDevs\minerware\database\DataManager;
use LatamPMDevs\minerware\Minerware;
use LatamPMDevs\minerware\tasks\ArenaTask;
use LatamPMDevs\minerware\utils\PointHolder;
use LatamPMDevs\minerware\utils\Utils;

use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\Position;
use pocketmine\world\World;
use function array_key_first;
use function array_rand;
use function array_slice;
use function count;
use function implode;
use function shuffle;
use function str_repeat;

final class Arena implements Listener {

	public const MAX_PLAYERS = 12;

	public const INBETWEEN_TIME = 5;
	public const ENDING_TIME = 10;

	public const NORMAL_MICROGAMES = 15;

	private Minerware $plugin;

	private Status $status;

	private World $world;

	private int $minPlayers;

	/** @var Player[] */
	private array $players = [];

	private PointHolder $pointHolder;

	/** @var Microgame[] */
	private array $microgamesQueue = [];

	private ?Microgame $currentMicrogame = null;

	private int $nextMicrogameIndex = 0;

	public int $defaultStartingtime;

	public int $startingtime;

	public int $inbetweentime = 11;

	public int $endingtime = self::ENDING_TIME;

	public bool $isWinnersCageSet = false;

	/** @var array<int, Player> */
	public array $winnersCage = [];

	public bool $isLosersCageSet = false;

	/** @var array<int, Player> */
	public array $losersCage = [];

	public bool $areInvisibleBlocksSet = false;

	public function __construct(private string $id, private Map $map) {
		$this->plugin = Minerware::getInstance();
		$this->world = $this->map->generateWorld($this->id);
		$this->minPlayers = DataManager::getInstance()->getMinimumStartingPlayers();
		$this->startingtime = $this->defaultStartingtime = DataManager::getInstance()->getArenaStartingTime();
		$this->status = Status::WAITING();
		$this->pointHolder = new PointHolder();
		$this->plugin->getScheduler()->scheduleRepeatingTask(new ArenaTask($this), 20);
		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
		$this->world->setTime(World::TIME_NOON);
		$this->world->stopTime();

		# TODO: More Microgames!
		$normalMicrogames = $this->plugin->getNormalMicrogames();
		shuffle($normalMicrogames);
		foreach($normalMicrogames as $microgame) {
			$this->microgamesQueue[] = new $microgame($this);
		}
		$bossMicrogames = $this->plugin->getBossMicrogames();
		$bossgame = $bossMicrogames[array_rand($bossMicrogames)];
		$this->microgamesQueue[] = new $bossgame($this);

		$this->plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () : void {
			if ($this->status->equals(Status::ENDING())) {
				throw new CancelTaskException("Arena is no more in-game");
			}
			if ($this->currentMicrogame !== null && $this->currentMicrogame->isRunning()) {
				$this->currentMicrogame->tick();
			};
		}), 3);

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

	public function getMinPlayers() : int {
		return $this->minPlayers;
	}

	public function setMinPlayers(int $minPlayers) : void {
		if ($minPlayers < 2) {
			throw new InvalidArgumentException("Minimum player must be at least 2, $minPlayers given.");
		}
		$this->minPlayers = $minPlayers;
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
		$player->teleport($this->getRandomSpawn());
		Utils::initPlayer($player);
		$player->setGamemode(GameMode::ADVENTURE());
		if (!$this->areInvisibleBlocksSet) {
			$this->buildInvisibleBlocks();
		}
	}

	public function quit(Player $player) : void {
		unset($this->players[$player->getId()]);
		unset($this->winnersCage[$player->getId()]);
		unset($this->losersCage[$player->getId()]);
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
					foreach ($this->pointHolder->getOrderedByHigherScore() as $playerId => $point) {
						$i++;
						$user = $this->players[$playerId]->getName();
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
			$playersCount = count($this->players);
			foreach ($this->players as $player) {
				$player->sendMessage("\n§l§e" . $microgame->getName());
				if ($winnersCount >= $playersCount) {
					$player->sendMessage($this->plugin->getTranslator()->translate($player, "microgame.nolosers"));
				} elseif ($winnersCount <= 0) {
					$player->sendMessage($this->plugin->getTranslator()->translate(
						$player, "microgame.nowinners", [
							"{%count}" => $playersCount
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
							"{%players_count}" => $playersCount
						]
					));
				}
			}
			$microgame->end();
			$recompense = $microgame->getRecompensePoints();
			$showWorthMessage = $recompense > Microgame::DEFAULT_RECOMPENSE_POINTS;
			foreach ($this->players as $player) {
				Utils::initPlayer($player);
				$player->setGamemode(GameMode::ADVENTURE());
				if ($microgame->isWinner($player)) {
					$this->pointHolder->addPlayerPoint($player, $recompense);
					$player->sendTitle("§1§2", $this->plugin->getTranslator()->translate($player, "microgame.success"), 1, 20, 1);
				} elseif ($microgame->isLoser($player)) {
					$player->sendTitle("§1§2", $this->plugin->getTranslator()->translate($player, "microgame.failed"), 1, 20, 1);
				}
				if ($showWorthMessage) {
					$player->sendMessage($this->plugin->getTranslator()->translate(
						$player, "microgame.worth", [
							"{%points}" => $recompense
						]
					));
				}
				$player->sendMessage("\n");
			}
			foreach ($this->world->getEntities() as $entity) {
				if (!$entity instanceof Player) {
					$entity->flagForDespawn();
				}
			}
			$this->setCurrentMicrogame(null);
		}
		if ($this->areInvisibleBlocksSet) {
			$this->unsetInvisibleBlocks();
		}
		$this->unsetWinnersCage();
		$this->unsetLosersCage();
	}

	public function end() : void {
		$this->status = Status::ENDING();
		$winners = [];
		$scores = array_slice(Utils::chunkScores($this->pointHolder->getOrderedByHigherScore()), 0, 3);
		$tops = [];
		$i = 0;
		foreach ($scores as $points => $playersIds) {
			foreach ($playersIds as $id) {
				$pl = $this->players[$id];
				$tops[$i][] = $pl;
				$winners[$id] = $pl;
			}
			$i++;
		}
		foreach ($this->players as $player) {
			$player->sendMessage("§7" . str_repeat("-", 31));
			foreach ($tops as $key => $players) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "game.arena.top" . $key + 1, [
						"{%players}" => implode("§a, §8", Utils::getPlayersNames($players)),
						"{%points}" => $this->pointHolder->getPlayerPoints($players[array_key_first($players)])
					]
				));
			}
			$player->sendMessage("§7" . str_repeat("-", 31));
			if (isset($winners[$player->getId()])) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "game.arena.youwin"));
			}
		}
	}

	public function buildWinnersCage() : void {
		Utils::buildCage(Position::fromObject($this->map->getWinnersCage(), $this->world), VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::LIME()));
		$this->isWinnersCageSet = true;
	}

	public function unsetWinnersCage() : void {
		Utils::buildCage(Position::fromObject($this->map->getWinnersCage(), $this->world), VanillaBlocks::AIR());
		foreach ($this->winnersCage as $player) {
			$this->tpSafePosition($player);
		}
		$this->isWinnersCageSet = false;
		$this->winnersCage = [];
	}

	public function inWinnersCage(Player $player) : bool {
		return isset($this->winnersCage[$player->getId()]);
	}

	public function buildLosersCage() : void {
		Utils::buildCage(Position::fromObject($this->map->getLosersCage(), $this->world), VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::RED()));
		$this->isLosersCageSet = true;
	}

	public function unsetLosersCage() : void {
		Utils::buildCage(Position::fromObject($this->map->getLosersCage(), $this->world), VanillaBlocks::AIR());
		foreach ($this->losersCage as $player) {
			$this->tpSafePosition($player);
		}
		$this->isLosersCageSet = false;
		$this->losersCage = [];
	}

	public function inLosersCage(Player $player) : bool {
		return isset($this->losersCage[$player->getId()]);
	}

	public function sendToWinnersCage(Player $player) : void {
		if (!$this->isWinnersCageSet) {
			$this->buildWinnersCage();
		}
		Utils::initPlayer($player);
		$player->setGamemode(GameMode::ADVENTURE());
		$player->teleport($this->map->getWinnersCage()->add(0, 2, 0));
		$this->winnersCage[$player->getId()] = $player;
	}

	public function sendToLosersCage(Player $player) : void {
		if (!$this->isLosersCageSet) {
			$this->buildLosersCage();
		}
		Utils::initPlayer($player);
		$player->setGamemode(GameMode::ADVENTURE());
		$player->teleport($this->map->getLosersCage()->add(0, 2, 0));
		$this->losersCage[$player->getId()] = $player;
	}

	public function tpSafePosition(Player $player) : void {
		$location = $player->getLocation();
		if ($this->inWinnersCage($player) ||
			$this->inLosersCage($player) ||
			$location->y < $this->map->getPlatformMinPos()->y
		) {
			$pos = $this->getRandomSpawn();
		} else {
			$pos = $location;
		}
		if ($this->world->getBlock($pos)->isFullCube()) {
			$pos->y = (int) $pos->y;
			$maxY = $this->world->getMaxY() - 2;
			for (; $pos->y < $maxY; $pos->y++) {
				if ($this->world->getBlock($pos)->isTransparent()) {
					break;
				}
			}
		}
		$safe = $this->world->getSafeSpawn($pos);
		$safe->y = $safe->y + 2;
		$player->teleport($safe);
	}

	public function getRandomSpawn() : Position {
		$spawns = $this->map->getSpawns();
		return Position::fromObject($spawns[array_rand($spawns)], $this->world);
	}

	public function areInvisibleBlocksSet() : bool {
		return $this->areInvisibleBlocksSet;
	}

	public function buildInvisibleBlocks() : void {
		$min = $this->map->getPlatformMinPos();
		$max = $this->map->getPlatformMaxPos();
		$pos1 = new Position($min->x - 1, $min->y + 30, $min->z - 1, $this->world);
		$pos2 = new Position($max->x + 1, $min->y + 30, $max->z + 1, $this->world);
		$pos3 = new Position($pos1->x, $min->y, $pos2->z, $this->world);
		$pos4 = new Position($pos2->x, $min->y, $pos1->z, $this->world);
		Utils::fill($pos1, $pos3, VanillaBlocks::INVISIBLE_BEDROCK());
		Utils::fill($pos3, $pos2, VanillaBlocks::INVISIBLE_BEDROCK());
		Utils::fill($pos2, $pos4, VanillaBlocks::INVISIBLE_BEDROCK());
		Utils::fill($pos4, $pos1, VanillaBlocks::INVISIBLE_BEDROCK());
		$this->world->setBlock($pos1, VanillaBlocks::DIAMOND());
		$this->areInvisibleBlocksSet = true;
	}

	public function unsetInvisibleBlocks() : void {
		$min = $this->map->getPlatformMinPos();
		$max = $this->map->getPlatformMaxPos();
		$pos1 = new Position($min->x - 1, $min->y + 30, $min->z - 1, $this->world);
		$pos2 = new Position($max->x + 1, $min->y + 30, $max->z + 1, $this->world);
		$pos3 = new Position($pos1->x, $min->y, $pos2->z, $this->world);
		$pos4 = new Position($pos2->x, $min->y, $pos1->z, $this->world);
		Utils::fill($pos1, $pos3, VanillaBlocks::AIR());
		Utils::fill($pos3, $pos2, VanillaBlocks::AIR());
		Utils::fill($pos2, $pos4, VanillaBlocks::AIR());
		Utils::fill($pos4, $pos1, VanillaBlocks::AIR());
		$this->areInvisibleBlocksSet = false;
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
		if ($event->getFrom()->world === $event->getTo()->getWorld()) return;
		if ($this->inGame($player)) {
			$this->quit($player);
		}
	}

	public function onDropItem(PlayerDropItemEvent $event) : void {
		$player = $event->getPlayer();
		if ($this->inGame($player)) {
			$event->cancel();
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
		if ($this->currentMicrogame === null) {
			if ($event->getCause() === EntityDamageEvent::CAUSE_VOID) {
				$player->teleport($this->getRandomSpawn());
			}
			$event->cancel();
		} else {
			if ($this->currentMicrogame->isLoser($player) ||
				$this->currentMicrogame->isWinner($player)) {
				$event->cancel(); //Winners and losers cannot be damaged
			}
		}
	}
}
