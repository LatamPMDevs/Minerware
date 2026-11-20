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

use Closure;
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
use LatamPMDevs\minerware\map\Map;
use LatamPMDevs\minerware\map\MapWorldGenerator;
use LatamPMDevs\minerware\map\world\MapTemplates;
use LatamPMDevs\minerware\Minerware;
use LatamPMDevs\minerware\tasks\ArenaTask;
use LatamPMDevs\minerware\utils\AsyncBlockManager;
use LatamPMDevs\minerware\utils\PointHolder;
use LatamPMDevs\minerware\utils\Selection;
use LatamPMDevs\minerware\utils\Utils;

use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\HandlerListManager;
use pocketmine\event\Listener;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\Position;
use pocketmine\world\World;
use RuntimeException;
use Throwable;
use function array_key_first;
use function array_rand;
use function array_slice;
use function count;
use function implode;
use function microtime;
use function shuffle;
use function str_repeat;
use function time;

final class Arena {

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

	private int $countdown = 0;

	private int $countdownTotal = 0;

	private Cage $winnersCage;

	private Cage $losersCage;

	public bool $areInvisibleBlocksSet = false;

	/**
	 * Number of async block builds (invisible blocks, cages, microgame stage)
	 * still draining for this arena. Gameplay is gated on this reaching zero.
	 */
	private int $pendingBuilds = 0;

	/** Seconds (microtime) of the last build started or settled; see {@see settleStuckBuilds()}. */
	private float $lastBuildActivity = 0.0;

	private bool $closed = false;

	/** @var array<int, list<string>> player id => the lines last sent to them */
	private array $scoreboardLines = [];

	/** @var array<int, array<int, Player>> */
	public array $winners = [];

	/** @var array<int, Player> */
	public array $losers = [];

	public ?int $startTime = null;

	public function __construct(private string $id, private Map $map) {
		$this->plugin = Minerware::getInstance();
		# Built first: it can throw, and nothing has been allocated yet.
		$this->buildMicrogamesQueue();
		$this->world = MapWorldGenerator::generate($this->map, $this->id);
		$this->minPlayers = DataManager::getInstance()->getMinimumStartingPlayers();
		$this->status = Status::WAITING;
		$this->pointHolder = new PointHolder();
		$this->plugin->getScheduler()->scheduleRepeatingTask(new ArenaTask($this), 20); //ticks
		$this->world->setTime(World::TIME_NOON);
		$this->world->stopTime();
		$this->winnersCage = new Cage(
			Position::fromObject($this->map->getWinnersCage(), $this->world),
			VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::LIME)
		);
		$this->losersCage = new Cage(
			Position::fromObject($this->map->getLosersCage(), $this->world),
			VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::RED)
		);
		$this->winnersCage->setBuildCallbacks($this->onStageBuildStarted(...), $this->onStageBuildSettled(...));
		$this->losersCage->setBuildCallbacks($this->onStageBuildStarted(...), $this->onStageBuildSettled(...));

		$this->plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () : void {
			if ($this->closed || $this->status === Status::ENDING) {
				throw new CancelTaskException("Arena is no more in-game");
			}
			if ($this->currentMicrogame !== null && $this->currentMicrogame->isRunning() && !$this->isBuildingStage()) {
				$microgame = $this->currentMicrogame;
				if (!$this->runGuarded($microgame->tick(...))) {
					throw new CancelTaskException("Arena crashed");
				}
			}
		}), 3); //ticks

		(new ArenaCreationEvent($this))->call();
	}

	private function buildMicrogamesQueue() : void {
		$microgameManager = MicrogameManager::getInstance();
		$normalMicrogames = $microgameManager->getMicrogames();
		shuffle($normalMicrogames);
		foreach ($normalMicrogames as $microgame) {
			$this->microgamesQueue[] = new $microgame($this);
		}
		$bossMicrogames = $microgameManager->getBossgames();
		if (count($bossMicrogames) === 0) {
			throw new AssumptionFailedError("No boss microgames are registered");
		}
		$bossgame = $bossMicrogames[array_rand($bossMicrogames)];
		$this->microgamesQueue[] = new $bossgame($this);
	}

	public function getId() : string {
		return $this->id;
	}

	public function getWorld() : World {
		return $this->world;
	}

	public function getMap() : Map {
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
		if ($this->status === $status) {
			return;
		}
		$ev = new ArenaChangeStatusEvent($this->status, $status, $this->getStatusDuration($status), $this);
		$ev->call();
		$this->status = $ev->getNewStatus();
		$this->countdown = $this->countdownTotal = $ev->getCountdown();
		$this->scoreboardLines = [];
		foreach ($this->players as $player) {
			if (ScoreFactory::hasObjective($player)) {
				ScoreFactory::removeScoreLines($player, true);
			}
		}
	}

	/**
	 * Default seconds a status lasts before the arena transitions again.
	 */
	public function getStatusDuration(Status $status) : int {
		return match ($status) {
			Status::STARTING => DataManager::getInstance()->getArenaStartingTime(),
			Status::INBETWEEN => self::INBETWEEN_TIME,
			Status::ENDING => self::ENDING_TIME,
			default => 0,
		};
	}

	public function getCountdown() : int {
		return $this->countdown;
	}

	public function getCountdownTotal() : int {
		return $this->countdownTotal;
	}

	public function setCountdown(int $countdown) : void {
		$this->countdown = $countdown;
	}

	public function decrementCountdown() : void {
		$this->countdown--;
	}

	public function forceStart() : void {
		if ($this->status !== Status::STARTING) {
			return;
		}
		$this->countdown = 5;
	}

	public function canJoin() : bool {
		return ($this->status === Status::WAITING || $this->status === Status::STARTING) && count($this->players) < self::MAX_PLAYERS;
	}

	public function join(Player $player) : bool {
		if (!$this->canJoin() || $this->inGame($player)) {
			return false;
		}
		$ev = new PlayerJoinArenaEvent($player, $this);
		$ev->call();
		if ($ev->isCancelled()) {
			return false;
		}
		$this->players[$player->getId()] = $player;
		ArenaManager::getInstance()->bindPlayer($player, $this);
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
		$player->setGamemode(GameMode::ADVENTURE);
		if (!$this->areInvisibleBlocksSet) {
			$this->buildInvisibleBlocks();
		}
		return true;
	}

	public function quit(Player $player) : void {
		unset($this->players[$player->getId()]);
		unset($this->scoreboardLines[$player->getId()]);
		ArenaManager::getInstance()->unbindPlayer($player, $this);
		$this->winnersCage->removePlayer($player);
		$this->losersCage->removePlayer($player);
		$this->pointHolder->removePlayer($player);
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
		if (count($this->players) === 0 && $this->status !== Status::ENDING) {
			# Nobody is left: let the ENDING countdown tear the arena down.
			if ($this->currentMicrogame instanceof Listener) {
				HandlerListManager::global()->unregisterAll($this->currentMicrogame);
			}
			$this->setStatus(Status::ENDING);
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

	/**
	 * @return Player[]
	 */
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
		if ($this->isBuildingStage()) {
			return null;
		}
		$microgame = $this->getNextMicrogame();
		if ($microgame !== null) {
			$this->setCurrentMicrogame($microgame);
			$microgame->start();
			$this->nextMicrogameIndex++;
		}
		return $microgame;
	}

	public function isClosed() : bool {
		return $this->closed;
	}

	/**
	 * Runs one step of the arena (a tick).
	 */
	public function runGuarded(Closure $step) : bool {
		try {
			$step();
			return true;
		} catch (CancelTaskException $e) {
			throw $e;
		} catch (Throwable $e) {
			$this->plugin->getLogger()->logException($e);
			$this->sendMessage($this->plugin->getTranslator()->translate(null, "game.arena.crashed"));
			$this->close();
			return false;
		}
	}

	/**
	 * Sends the players away and removes the arena and its world, whatever its state.
	 */
	public function close() : void {
		if ($this->closed) {
			return;
		}
		$this->closed = true;
		$spawn = $this->plugin->getServer()->getWorldManager()->getDefaultWorld()?->getSafeSpawn();
		foreach ($this->players as $player) {
			$this->quit($player);
			if ($player->isConnected()) {
				Utils::initPlayer($player);
				$player->setGamemode($this->plugin->getServer()->getGamemode());
				if ($spawn !== null) {
					$player->teleport($spawn);
				}
			}
		}
		if ($this->currentMicrogame instanceof Listener) {
			HandlerListManager::global()->unregisterAll($this->currentMicrogame);
		}
		ArenaManager::getInstance()->deleteArena($this);
		$this->deleteMap();
	}

	public function deleteMap() : void {
		MapTemplates::getInstance()->removeInstance($this->world);
	}

	public function updateScoreboard() : void {
		$translator = $this->plugin->getTranslator();
		$isBoss = false;
		$scores = $this->status === Status::INBETWEEN || $this->status === Status::INGAME ? $this->pointHolder->getOrderedByHigherScore() : [];
		$serverIp = DataManager::getInstance()->getServerIp();
		$currentMicrogame = $this->getCurrentMicrogame();
		if ($currentMicrogame !== null && $currentMicrogame->getLevel() === Level::BOSS) {
			$isBoss = true;
		}
		foreach ($this->players as $player) {
			$lines = [];
			$remove = false;
			switch ($this->status) {
				case Status::WAITING:
				case Status::STARTING:
					$lines[] = "§5" . $translator->translate($player, "text.map") . ":";
					$lines[] = "§f" . $this->map->getName();
					$lines[] = "§1";
					$lines[] = "§5" . $translator->translate($player, "text.players") . ":";
					$lines[] = "§f" . count($this->players) . "/" . self::MAX_PLAYERS;
					$lines[] = "§2";
					$lines[] = "§6" . $serverIp;
					break;

				case Status::INBETWEEN:
				case Status::INGAME:
					$isOnTop = false;
					$i = 0;
					$lines[] = "§5" . $translator->translate($player, "text.scores") . ":";
					foreach ($scores as $playerId => $point) {
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
					$lines[] = "§e" . ($this->status === Status::INBETWEEN ? "§fIn-between" : ($this->currentMicrogame?->getName() ?? ""));
					$lines[] = "§5(§f" . ($isBoss ? "Bossgame" : $this->nextMicrogameIndex . "§5/§f" . (count($this->microgamesQueue) - 1)) . "§5)";
					$lines[] = "§2";
					$lines[] = "§6" . $serverIp;
					break;

				default:
					$remove = true;
					break;
			}
			if ($remove) {
				unset($this->scoreboardLines[$player->getId()]);
				ScoreFactory::removeObjective($player, true);
			} else {
				if (($this->scoreboardLines[$player->getId()] ?? null) === $lines && ScoreFactory::hasObjective($player)) {
					continue;
				}
				$this->scoreboardLines[$player->getId()] = $lines;
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
				$player->setGamemode(GameMode::ADVENTURE);
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
		if ($this->status !== Status::STARTING) {
			throw new RuntimeException("Arena can only start during the starting status");
		}
		$this->setStatus(Status::INBETWEEN);
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
		$this->setStatus(Status::ENDING);
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

	/**
	 * @return array<int, array<int, Player>>
	 */
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

	/**
	 * @return array<int, Player>
	 */
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

	/**
	 * Returns true while one or more async block builds for this arena are still
	 * draining. Callers that read or mutate the stage (e.g. a microgame tick)
	 * must not proceed until this returns false.
	 */
	/**
	 * Releases the stage gate when a build never reported back (a lost worker),
	 * so the arena can't wait for it forever.
	 *
	 * @param float $timeout seconds without any build activity
	 */
	public function settleStuckBuilds(float $timeout) : void {
		if ($this->pendingBuilds > 0 && microtime(true) - $this->lastBuildActivity > $timeout) {
			$this->plugin->getLogger()->error("Arena " . $this->id . ": " . $this->pendingBuilds . " stage build(s) never finished, releasing them");
			$this->pendingBuilds = 0;
		}
	}

	public function isBuildingStage() : bool {
		return $this->pendingBuilds > 0;
	}

	/**
	 * Marks one async build as started. Each {@see buildStarted} must be paired
	 * with exactly one {@see buildSettled}.
	 */
	public function onStageBuildStarted() : void {
		$this->pendingBuilds++;
		$this->lastBuildActivity = microtime(true);
	}

	/**
	 * Marks one async build as finished (its completion callback ran).
	 */
	public function onStageBuildSettled() : void {
		$this->lastBuildActivity = microtime(true);
		if ($this->pendingBuilds > 0) {
			$this->pendingBuilds--;
		} else {
			$this->plugin->getLogger()->warning("Arena " . $this->id . ": a stage build settled twice");
		}
	}

	public function buildInvisibleBlocks() : void {
		$min = $this->map->getPlatformMinPos();
		$max = $this->map->getPlatformMaxPos();
		$pos1 = new Position($min->x - 1, $min->y + 30, $min->z - 1, $this->world);
		$pos2 = new Position($max->x + 1, $min->y + 30, $max->z + 1, $this->world);
		$pos3 = new Position($pos1->x, $min->y, $pos2->z, $this->world);
		$pos4 = new Position($pos2->x, $min->y, $pos1->z, $this->world);

		$this->onStageBuildStarted();
		$selection = new Selection($this->world);
		$selection->addFill($pos1, $pos3, VanillaBlocks::INVISIBLE_BEDROCK());
		$selection->addFill($pos3, $pos2, VanillaBlocks::INVISIBLE_BEDROCK());
		$selection->addFill($pos2, $pos4, VanillaBlocks::INVISIBLE_BEDROCK());
		$selection->addFill($pos4, $pos1, VanillaBlocks::INVISIBLE_BEDROCK());
		AsyncBlockManager::getInstance()->executeSet($selection, $this->onStageBuildSettled(...));
		$this->areInvisibleBlocksSet = true;
	}

	public function unsetInvisibleBlocks() : void {
		$min = $this->map->getPlatformMinPos();
		$max = $this->map->getPlatformMaxPos();
		$pos1 = new Position($min->x - 1, $min->y + 30, $min->z - 1, $this->world);
		$pos2 = new Position($max->x + 1, $min->y + 30, $max->z + 1, $this->world);
		$pos3 = new Position($pos1->x, $min->y, $pos2->z, $this->world);
		$pos4 = new Position($pos2->x, $min->y, $pos1->z, $this->world);

		$this->onStageBuildStarted();
		$selection = new Selection($this->world);
		$selection->addFill($pos1, $pos3, VanillaBlocks::AIR());
		$selection->addFill($pos3, $pos2, VanillaBlocks::AIR());
		$selection->addFill($pos2, $pos4, VanillaBlocks::AIR());
		$selection->addFill($pos4, $pos1, VanillaBlocks::AIR());
		AsyncBlockManager::getInstance()->executeSet($selection, $this->onStageBuildSettled(...));
		$this->areInvisibleBlocksSet = false;
	}

	/**
	 * Called by ArenaListener for the damage a player of this arena takes.
	 */
	public function handleDamage(EntityDamageEvent $event, Player $player) : void {
		if ($this->currentMicrogame === null) {
			if ($event->getCause() === EntityDamageEvent::CAUSE_VOID) {
				$player->teleport($this->getRandomSpawn());
			}
			$event->cancel();
		} elseif ($this->currentMicrogame->isLoser($player) || $this->currentMicrogame->isWinner($player)) {
			$event->cancel(); //Winners and losers cannot be damaged
		}
	}
}
