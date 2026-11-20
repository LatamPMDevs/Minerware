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
use LatamPMDevs\minerware\arena\microgame\Microgame;
use LatamPMDevs\minerware\arena\phase\ArenaPhase;
use LatamPMDevs\minerware\arena\phase\EndingPhase;
use LatamPMDevs\minerware\arena\phase\InbetweenPhase;
use LatamPMDevs\minerware\arena\phase\StartingPhase;
use LatamPMDevs\minerware\arena\phase\WaitingPhase;
use LatamPMDevs\minerware\database\DataManager;
use LatamPMDevs\minerware\event\arena\ArenaChangePhaseEvent;
use LatamPMDevs\minerware\event\arena\ArenaCreationEvent;
use LatamPMDevs\minerware\event\arena\ArenaEndEvent;
use LatamPMDevs\minerware\event\arena\PlayerJoinArenaEvent;
use LatamPMDevs\minerware\event\arena\PlayerQuitArenaEvent;
use LatamPMDevs\minerware\item\SpecialItems;
use LatamPMDevs\minerware\map\Map;
use LatamPMDevs\minerware\map\MapWorldGenerator;
use LatamPMDevs\minerware\map\world\MapTemplates;
use LatamPMDevs\minerware\Minerware;
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
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\Position;
use pocketmine\world\World;
use RuntimeException;
use Throwable;
use function array_rand;
use function array_slice;
use function count;
use function implode;
use function reset;
use function str_repeat;
use function time;

final class Arena {

	public const MAX_PLAYERS = 12;

	private const BUILD_TIMEOUT = 30.0; //seconds without build activity

	public const NORMAL_MICROGAMES = 15;

	private const LEAVE_ITEM_SLOT = 8; //hotbar

	public const FIRST_PLACE = 1;
	public const SECOND_PLACE = 2;
	public const THIRD_PLACE = 3;

	private Minerware $plugin;

	private ArenaPhase $phase;

	private World $world;

	private int $minPlayers;

	/** @var Player[] */
	private array $players = [];

	private PointHolder $pointHolder;

	private MicrogameQueue $queue;

	private StageGate $gate;

	private ScoreboardRenderer $scoreboard;

	private ?Microgame $currentMicrogame = null;

	private int $countdown = 0;

	private int $countdownTotal = 0;

	private Cage $winnersCage;

	private Cage $losersCage;

	private bool $areInvisibleBlocksSet = false;

	private bool $closed = false;

	private bool $countdownPaused = false;

	/** @var array<int, array<int, Player>> */
	private array $winners = [];

	/** @var array<int, Player> */
	private array $losers = [];

	private ?int $startTime = null;

	public function __construct(private string $id, private Map $map) {
		$this->plugin = Minerware::getInstance();
		# Built first: it can throw, and nothing has been allocated yet.
		$this->queue = MicrogameQueue::create($this);
		$this->gate = new StageGate($this->id);
		$this->scoreboard = new ScoreboardRenderer();
		$this->world = MapWorldGenerator::generate($this->map, $this->id);
		$this->minPlayers = DataManager::getInstance()->getMinimumStartingPlayers();
		$this->phase = WaitingPhase::getInstance();
		$this->pointHolder = new PointHolder();
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

		(new ArenaCreationEvent($this))->call();
	}

	/**
	 * One second of the arena: the phase of its status, then the scoreboard.
	 */
	public function tick() : void {
		if ($this->closed) {
			return;
		}
		$this->runGuarded(function () : void {
			$this->settleStuckBuilds(self::BUILD_TIMEOUT);
			$this->phase->tick($this);
			if (!$this->closed) {
				$this->scoreboard->update($this);
			}
		});
	}

	/**
	 * A few ticks of the running microgame, if there is one.
	 */
	public function tickMicrogame() : void {
		$microgame = $this->currentMicrogame;
		if ($this->closed || $microgame === null || !$microgame->isRunning() || $this->isBuildingStage()) {
			return;
		}
		$this->runGuarded($microgame->tick(...));
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

	public function getPhase() : ArenaPhase {
		return $this->phase;
	}

	public function setPhase(ArenaPhase $phase) : void {
		if ($this->phase === $phase) {
			return;
		}
		$ev = new ArenaChangePhaseEvent($this->phase, $phase, $phase->getDuration(), $this);
		$ev->call();
		$this->phase = $ev->getNewPhase();
		$this->countdown = $this->countdownTotal = $ev->getCountdown();
		$this->scoreboard->reset();
		foreach ($this->players as $player) {
			if (ScoreFactory::hasObjective($player)) {
				ScoreFactory::removeScoreLines($player, true);
			}
		}
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

	public function isCountdownPaused() : bool {
		return $this->countdownPaused;
	}

	/**
	 * Holds the starting countdown (an admin is waiting for something).
	 *
	 * @return $this
	 */
	public function setCountdownPaused(bool $paused) : self {
		$this->countdownPaused = $paused;
		return $this;
	}

	public function forceStart() : void {
		if (!$this->phase instanceof StartingPhase) {
			return;
		}
		$this->countdown = 5;
	}

	public function canJoin() : bool {
		return $this->phase->isLobby() && count($this->players) < self::MAX_PLAYERS;
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
		$player->getInventory()->setItem(self::LEAVE_ITEM_SLOT, SpecialItems::LEAVE_GAME_BED($this->plugin->getTranslator()->getLanguageOf($player)));
		if (!$this->areInvisibleBlocksSet) {
			$this->buildInvisibleBlocks();
		}
		return true;
	}

	public function quit(Player $player) : void {
		unset($this->players[$player->getId()]);
		$this->scoreboard->forget($player);
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
		if (count($this->players) === 0 && !$this->phase instanceof EndingPhase) {
			# Nobody is left: let the ENDING countdown tear the arena down.
			if ($this->currentMicrogame instanceof Listener) {
				HandlerListManager::global()->unregisterAll($this->currentMicrogame);
			}
			$this->setPhase(EndingPhase::getInstance());
		}
	}

	/**
	 * Sends every player a translated message, each in their own language.
	 *
	 * @param array<string, string|int> $replacements
	 */
	public function broadcast(string $key, array $replacements = []) : void {
		$translator = $this->plugin->getTranslator();
		foreach ($this->players as $player) {
			$player->sendMessage($translator->translate($player, $key, $replacements));
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

	public function getQueue() : MicrogameQueue {
		return $this->queue;
	}

	public function getNextMicrogame() : ?Microgame {
		return $this->queue->peek();
	}

	public function getNextMicrogameNonNull() : Microgame {
		return $this->queue->peek() ?? throw new AssumptionFailedError("Next Microgame is null");
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
			$this->queue->advance();
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
	 * Takes a player out of the arena and sends them to the default world.
	 */
	public function kick(Player $player) : void {
		$this->quit($player);
		if (!$player->isConnected()) {
			return;
		}
		Utils::initPlayer($player);
		$player->setGamemode($this->plugin->getServer()->getGamemode());
		$spawn = $this->plugin->getServer()->getWorldManager()->getDefaultWorld()?->getSafeSpawn();
		if ($spawn !== null) {
			$player->teleport($spawn);
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
		foreach ($this->players as $player) {
			$this->kick($player);
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
		if (!$this->phase instanceof StartingPhase) {
			throw new RuntimeException("Arena can only start during the starting status");
		}
		$this->setPhase(InbetweenPhase::getInstance());
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
		$this->setPhase(EndingPhase::getInstance());
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
						"{%points}" => $this->pointHolder->getPlayerPoints(reset($players) ?: throw new AssumptionFailedError("Empty podium place"))
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
	public function settleStuckBuilds(float $timeout) : void {
		$this->gate->settleStuck($timeout);
	}

	public function isBuildingStage() : bool {
		return $this->gate->isBuilding();
	}

	/**
	 * Marks one async build as started. Each start must be paired with exactly
	 * one {@see onStageBuildSettled}.
	 */
	public function onStageBuildStarted() : void {
		$this->gate->started();
	}

	/**
	 * Marks one async build as finished (its completion callback ran).
	 */
	public function onStageBuildSettled() : void {
		$this->gate->settled();
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
