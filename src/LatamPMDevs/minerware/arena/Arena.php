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

use InvalidArgumentException;
use jackmd\scorefactory\ScoreFactory;
use LatamPMDevs\minerware\arena\microgame\Microgame;
use LatamPMDevs\minerware\arena\phase\ArenaPhase;
use LatamPMDevs\minerware\arena\phase\EndingPhase;
use LatamPMDevs\minerware\arena\phase\InbetweenPhase;
use LatamPMDevs\minerware\arena\phase\StartingPhase;
use LatamPMDevs\minerware\arena\phase\WaitingPhase;
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
use LatamPMDevs\minerware\Settings;
use LatamPMDevs\minerware\utils\PointHolder;
use LatamPMDevs\minerware\utils\Utils;
use LogicException;

use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\HandlerListManager;
use pocketmine\event\Listener;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\Position;
use pocketmine\world\World;
use function array_rand;
use function count;
use function explode;
use function implode;
use function reset;
use function str_repeat;
use function time;

final class Arena {

	public const MAX_PLAYERS = 12;

	private const BUILD_TIMEOUT = 30.0; //seconds without build activity

	public const NORMAL_MICROGAMES = 15;

	private const LEAVE_ITEM_SLOT = 8; //hotbar
	private const ADMIN_ITEM_SLOT = 4; //hotbar

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

	private Countdown $countdown;

	private Cage $winnersCage;

	private Cage $losersCage;

	private ArenaWalls $walls;

	private bool $closed = false;

	private Podium $podium;

	private ?int $startTime = null;

	private ArenaRules $rules;

	public function __construct(private string $id, private Map $map) {
		$this->plugin = Minerware::getInstance();
		# Built first: it can throw, and nothing has been allocated yet.
		$this->queue = MicrogameQueue::create($this);
		$this->gate = new StageGate($this->id);
		$this->countdown = new Countdown();
		$this->podium = new Podium();
		$this->scoreboard = new ScoreboardRenderer();
		$this->world = MapWorldGenerator::generate($this->map, $this->id);
		$this->minPlayers = Settings::getInstance()->getMinimumStartingPlayers();
		$this->rules = new ArenaRules();
		$this->phase = WaitingPhase::getInstance();
		$this->pointHolder = new PointHolder();
		$this->walls = new ArenaWalls($this->map, $this->world, $this->gate);
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
		$this->winnersCage->setBuildCallbacks($this->gate->started(...), $this->gate->settled(...));
		$this->losersCage->setBuildCallbacks($this->gate->started(...), $this->gate->settled(...));

		(new ArenaCreationEvent($this))->call();
	}

	/**
	 * One second of the arena: the phase of its status, then the scoreboard.
	 */
	public function tick() : void {
		if ($this->closed) {
			return;
		}
		$this->gate->settleStuck(self::BUILD_TIMEOUT);
		$this->phase->tick($this);
		if (!$this->closed) {
			$this->scoreboard->update($this);
		}
	}

	/**
	 * A few ticks of the running microgame, if there is one.
	 */
	public function tickMicrogame() : void {
		$microgame = $this->currentMicrogame;
		if ($this->closed || $microgame === null || !$microgame->isRunning() || $this->isBuildingStage()) {
			return;
		}
		$microgame->tick();
	}

	public function getId() : string {
		return $this->id;
	}

	public function getWorld() : World {
		return $this->world;
	}

	/**
	 * The first part of the id, what people see and say.
	 */
	public function getShortId() : string {
		return explode("-", $this->id)[0];
	}

	public function getRules() : ArenaRules {
		return $this->rules;
	}

	/**
	 * Tells everyone in the arena what an admin wrote.
	 */
	public function sendAnnouncement(string $text) : void {
		$this->broadcast("admin.announcement", ["{%message}" => $text]);
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
		$duration = $phase instanceof StartingPhase ? $this->rules->getStartingTime() : $phase->getDuration();
		$ev = new ArenaChangePhaseEvent($this->phase, $phase, $duration, $this);
		$ev->call();
		$this->phase = $ev->getNewPhase();
		$this->countdown->reset($ev->getCountdown());
	}

	public function getCountdown() : Countdown {
		return $this->countdown;
	}

	/**
	 * Starts the countdown to the first game now, if the arena is waiting and has enough players.
	 */
	public function forceStart() : bool {
		if (!$this->phase instanceof StartingPhase || count($this->players) < $this->minPlayers) {
			return false;
		}
		$this->countdown->set(5);
		return true;
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
		$language = $this->plugin->getTranslator()->getLanguageOf($player);
		$player->getInventory()->setItem(self::LEAVE_ITEM_SLOT, SpecialItems::LEAVE_GAME_BED($language));
		if ($player->hasPermission("minerware.command.arena")) {
			$player->getInventory()->setItem(self::ADMIN_ITEM_SLOT, SpecialItems::ADMIN_PANEL($language));
		}
		$this->walls->raise();
		return true;
	}

	/**
	 * Lets the player go without moving or telling anyone: they already are in another arena.
	 */
	public function release(Player $player) : void {
		unset($this->players[$player->getId()]);
		ArenaManager::getInstance()->unbindPlayer($player, $this);
		$this->winnersCage->removePlayer($player);
		$this->losersCage->removePlayer($player);
		$this->pointHolder->removePlayer($player);
		(new PlayerQuitArenaEvent($player, $this))->call();
	}

	public function quit(Player $player) : void {
		$this->release($player);
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
		if (count($this->players) === 0 && !$this->phase instanceof EndingPhase) {
			# Nobody is left: let the ENDING countdown tear the arena down.
			if ($this->currentMicrogame instanceof Listener) {
				HandlerListManager::global()->unregisterAll($this->currentMicrogame);
			}
			$this->setPhase(EndingPhase::getInstance());
		} elseif (count($this->players) === 1 && !$this->closed && !$this->phase->isLobby() && !$this->phase instanceof EndingPhase) {
			# One player cannot play against nobody: finish the game now.
			if ($this->currentMicrogame?->isRunning()) {
				$this->endCurrentMicrogame();
			}
			$this->broadcast("game.arena.endedEarly");
			$this->end(false);
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
		$microgame = $this->queue->peek();
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
		MapTemplates::getInstance()->removeInstance($this->world);
	}

	public function endCurrentMicrogame() : void {
		$microgame = $this->getCurrentMicrogameNonNull();
		if ($microgame->isRunning()) {
			$microgame->announceResults();
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
					$player->teleport($this->getSafePosition($player));
				}
			}
			foreach ($this->world->getEntities() as $entity) {
				if (!$entity instanceof Player) {
					$entity->flagForDespawn();
				}
			}
			$this->setCurrentMicrogame(null);
		}
		$this->walls->lower();
		$this->resetCages();
	}

	public function start() : void {
		if (!$this->phase instanceof StartingPhase) {
			throw new LogicException("Arena can only start during the starting status");
		}
		$this->setPhase(InbetweenPhase::getInstance());
		$this->getPointHolder()->clear();
		foreach ($this->players as $player) {
			$this->pointHolder->addPlayer($player);
		}
		$this->walls->lower();
		$this->startTime = time();
	}

	/**
	 * @param bool $completed false when the game ends early because the players left
	 */
	public function end(bool $completed = true) : void {
		$this->setPhase(EndingPhase::getInstance());
		$this->podium->rank($this->players, $this->pointHolder);
		foreach ($this->players as $player) {
			$player->sendMessage("§7" . str_repeat("-", 31));
			foreach ($this->podium->getWinners() as $place => $placed) {
				$player->sendMessage($this->plugin->getTranslator()->translate(
					$player, "game.arena.top" . $place, [
						"{%players}" => implode("§a, §8", Utils::getPlayersNames($placed)),
						"{%points}" => $this->pointHolder->getPlayerPoints(reset($placed) ?: throw new AssumptionFailedError("Empty podium place"))
					]
				));
			}
			$player->sendMessage("§7" . str_repeat("-", 31));
			if ($this->podium->isWinner($player)) {
				$player->sendMessage($this->plugin->getTranslator()->translate($player, "game.arena.youwin"));
			}
		}
		(new ArenaEndEvent($this, $completed))->call();
	}

	public function getPodium() : Podium {
		return $this->podium;
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

	public function getRandomSpawn() : Position {
		$spawns = $this->map->getSpawns();
		return Position::fromObject($spawns[array_rand($spawns)], $this->world);
	}

	public function getWalls() : ArenaWalls {
		return $this->walls;
	}

	public function getGate() : StageGate {
		return $this->gate;
	}

	/**
	 * Whether async block builds are still draining.
	 */
	public function isBuildingStage() : bool {
		return $this->gate->isBuilding();
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
