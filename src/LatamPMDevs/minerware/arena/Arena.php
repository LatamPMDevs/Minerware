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
use jackmd\scorefactory\ScoreFactory;
use LatamPMDevs\minerware\arena\microgame\Level;
use LatamPMDevs\minerware\arena\microgame\Microgame;
use LatamPMDevs\minerware\arena\microgame\MicrogameManager;
use LatamPMDevs\minerware\database\DataManager;
use LatamPMDevs\minerware\event\arena\ArenaChangeStatusEvent;
use LatamPMDevs\minerware\event\arena\ArenaCreationEvent;
use LatamPMDevs\minerware\event\arena\ArenaEndEvent;
use LatamPMDevs\minerware\event\arena\PlayerJoinArenaEvent;
use LatamPMDevs\minerware\event\arena\PlayerQuitArenaEvent;
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
use RuntimeException;
use function array_key_first;
use function array_rand;
use function array_slice;
use function count;
use function implode;
use function shuffle;
use function str_repeat;
use function time;

final class Arena implements Listener {

	public const MAX_PLAYERS = 12;

	public const INBETWEEN_TIME = 5;
	public const ENDING_TIME = 10;

	public const NORMAL_MICROGAMES = 15;

	public const FIRST_PLACE = 1;
	public const SECOND_PLACE = 2;
	public const THIRD_PLACE = 3;

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

	private Cage $winnersCage;

	private Cage $losersCage;

	public bool $areInvisibleBlocksSet = false;

	/** @var array<int, array<int, Player>> */
	public array $winners = [];

	/** @var array<int, Player> */
	public array $losers = [];

	public ?int $startTime = null;

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
		$this->winnersCage = new Cage(
			Position::fromObject($this->map->getWinnersCage(), $this->world),
			VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::LIME())
		);
		$this->losersCage = new Cage(
			Position::fromObject($this->map->getLosersCage(), $this->world),
			VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::RED())
		);

		# TODO: More Microgames!
		$microgameManager = MicrogameManager::getInstance();
		$normalMicrogames = $microgameManager->getMicrogames();
		shuffle($normalMicrogames);
		foreach($normalMicrogames as $microgame) {
			$this->microgamesQueue[] = new $microgame($this);
		}
		$bossMicrogames = $microgameManager->getBossgames();
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

		(new ArenaCreationEvent($this))->call();
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
		if ($this->status->equals($status)) {
			return;
		}
		$ev = new ArenaChangeStatusEvent($this->status, $status, $this);
		$ev->call();
		$this->status = $ev->getNewStatus();
		foreach ($this->players as $player) {
			if (ScoreFactory::hasObjective($player)) {
				ScoreFactory::removeScoreLines($player, true);
			}
		}
	}

	public function join(Player $player) : void {
		$ev = new PlayerJoinArenaEvent($player, $this);
		$ev->call();
		if ($ev->isCancelled()) {
			return;
		}
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
		$this->winnersCage->removePlayer($player);
		$this->losersCage->removePlayer($player);
		$this->pointHolder->removePlayer($player);
		$player->getInventory()->clearAll();
		if ($player->isConnected()) {
			ScoreFactory::removeObjective($player, true);
		}
		foreach ($this->players as $pl) {
			$pl->sendMessage($this->plugin->getTranslator()->translate(
				$pl, "game.player.quit", [
					"{%player}" => $player->getName(),
					"{%count}" => count($this->players) . "/" . self::MAX_PLAYERS
				]
			));
		}
		(new PlayerQuitArenaEvent($player, $this))->call();
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

	public function getStartTime() : ?int {
		return $this->startTime;
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
					$lines[] = "§5" . $translator->translate($player, "text.microgame") . ":";
					$lines[] = "§e" . ($this->status->equals(Status::INBETWEEN()) ? "§fIn-between" : $this->getCurrentMicrogameNonNull()->getName());
					$lines[] = "§5(§f" . ($isBoss ? "Bossgame" : $this->nextMicrogameIndex . "§5/§f" . (count($this->microgamesQueue) - 1)) . "§5)";
					$lines[] = "§2";
					$lines[] = "§6" . DataManager::getInstance()->getServerIp();
					break;

				default:
					$remove = true;
					break;
			}
			if ($remove) {
				ScoreFactory::removeObjective($player, true);
			} else {
				if (!ScoreFactory::hasObjective($player)) {
					ScoreFactory::setObjective($player, '§l§eMinerWare');
					ScoreFactory::sendObjective($player);
				}
				$from = 0;
				foreach ($lines as $line) {
					if ($from < 15) {
						$from++;
						ScoreFactory::setScoreLine($player, $from, $line);
					}
				}
				ScoreFactory::sendLines($player);
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
							"{%players}" => implode("§a, §8", Utils::getPlayersNames($winners))
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
				if ($this->winnersCage->isInCage($player) || $this->losersCage->isInCage($player)) {
					$this->tpSafePosition($player);
				}
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
		$this->resetCages();
	}

	public function start() : void {
		if (!$this->status->equals(Status::STARTING())) {
			throw new RuntimeException("Arena can only start during the staring status");
		}
		$this->setStatus(Status::INBETWEEN());
		$this->getPointHolder()->clear();
		foreach ($this->players as $player) {
			$this->pointHolder->addPlayer($player);
		}
		if ($this->areInvisibleBlocksSet()) {
			$this->unsetInvisibleBlocks();
		}
		$this->startTime = time();
	}

	public function end() : void {
		$this->setStatus(Status::ENDING());
		$winners = [];
		$scores = array_slice(Utils::chunkScores($this->pointHolder->getOrderedByHigherScore()), 0, 3);
		$i = 1;
		foreach ($scores as $points => $playersIds) {
			foreach ($playersIds as $id) {
				$pl = $this->players[$id];
				$this->addWinner($i, $pl);
			}
			$i++;
		}
		foreach ($this->players as $player) {
			$player->sendMessage("§7" . str_repeat("-", 31));
			foreach ($this->winners as $topPosition => $players) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "game.arena.top" . $topPosition, [
						"{%players}" => implode("§a, §8", Utils::getPlayersNames($players)),
						"{%points}" => $this->pointHolder->getPlayerPoints($players[array_key_first($players)])
					]
				));
			}
			$player->sendMessage("§7" . str_repeat("-", 31));
			if ($this->isWinner($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "game.arena.youwin"));
			} else {
				$this->addLoser($player);
			}
		}
		(new ArenaEndEvent($this))->call();
	}

	public function getWinners() : array {
		return $this->winners;
	}

	public function addWinner(int $topPosition, Player $player) : void {
		if ($topPosition < self::FIRST_PLACE || $topPosition > self::THIRD_PLACE) {
			throw new InvalidArgumentException("Invalid top position, must be " . self::FIRST_PLACE . "-" . self::THIRD_PLACE);
		}
		$this->winners[$topPosition][$player->getId()] = $player;
	}

	public function isWinner(Player $player) : bool {
		foreach ($this->winners as $topPosition => $players) {
			if (isset($players[$player->getId()])) {
				return true;
			}
		}
		return false;
	}

	public function getLosers() : array {
		return $this->losers;
	}

	public function addLoser(Player $player) : void {
		$this->losers[$player->getId()] = $player;
	}

	public function isLoser(Player $player) : bool {
		return isset($this->losers[$player->getId()]);
	}

	public function getWinnersCage() : Cage {
		return $this->winnersCage;
	}

	public function getLosersCage() : Cage {
		return $this->losersCage;
	}

	public function resetCages() : void {
		$this->winnersCage->unset();
		$this->losersCage->unset();
		$this->winnersCage->setPosition(Position::fromObject($this->map->getWinnersCage(), $this->world));
		$this->losersCage->setPosition(Position::fromObject($this->map->getLosersCage(), $this->world));
	}

	public function getSafePosition(Player $player) : Position {
		$location = $player->getLocation();
		if ($this->winnersCage->isInCage($player) ||
			$this->losersCage->isInCage($player) ||
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
		return $safe;
	}

	public function tpSafePosition(Player $player) : void {
		$player->teleport($this->getSafePosition($player));
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
		if ($player instanceof Player && $this->inGame($player)) {
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
