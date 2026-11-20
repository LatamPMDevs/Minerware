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

namespace LatamPMDevs\minerware\arena\microgame;

use Closure;
use LatamPMDevs\minerware\arena\Arena;
use LatamPMDevs\minerware\event\arena\microgame\MicrogameEndEvent;
use LatamPMDevs\minerware\event\arena\microgame\MicrogameStartEvent;
use LatamPMDevs\minerware\event\arena\microgame\PlayerLoseMicrogameEvent;
use LatamPMDevs\minerware\event\arena\microgame\PlayerWinMicrogameEvent;
use LatamPMDevs\minerware\Minerware;
use LatamPMDevs\minerware\utils\Selection;
use LatamPMDevs\minerware\utils\Utils;

use pocketmine\block\Block;
use pocketmine\event\HandlerListManager;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use function array_keys;
use function count;
use function implode;
use function microtime;

abstract class Microgame {

	public const DEFAULT_RECOMPENSE_POINTS = 1;
	public const BOSS_RECOMPENSE_POINTS = 3;

	protected Minerware $plugin;

	protected bool $hasStarted = false;

	protected bool $hasEnded = false;

	protected float $startTime = 0.0; //seconds, microtime

	/** @var Player[] */
	protected array $winners = [];

	/** @var Player[] */
	protected array $losers = [];

	private ?StageJournal $journal = null;

	public function __construct(protected Arena $arena) {
		$this->plugin = $this->arena->getPlugin();
	}

	public function getArena() : Arena {
		return $this->arena;
	}

	public function getPlugin() : Minerware {
		return $this->plugin;
	}

	public function hasStarted() : bool {
		return $this->hasStarted;
	}

	public function hasEnded() : bool {
		return $this->hasEnded;
	}

	public function isRunning() : bool {
		return $this->hasStarted && !$this->hasEnded;
	}

	public function getStartTime() : float {
		return $this->startTime;
	}

	public function getTimeLeft() : float {
		return ($this->startTime + $this->getGameDuration()) - microtime(true);
	}

	/**
	 * Ends the game: every player who has not won or lost yet wins or loses together.
	 */
	protected function endRound(bool $remainingWin = false) : void {
		foreach ($this->arena->getPlayers() as $player) {
			if ($this->isWinner($player) || $this->isLoser($player)) {
				continue;
			}
			if ($remainingWin) {
				$this->addWinner($player);
			} else {
				$this->addLoser($player);
			}
		}
		$this->arena->endCurrentMicrogame();
	}

	/**
	 * Makes the player win and sends them to the winners cage.
	 */
	protected function win(Player $player) : void {
		$this->addWinner($player);
		$this->arena->getWinnersCage()->addPlayer($player);
	}

	/**
	 * Makes the player lose and sends them to the losers cage.
	 */
	protected function lose(Player $player) : void {
		$this->addLoser($player);
		$this->arena->getLosersCage()->addPlayer($player);
	}

	/**
	 * Makes the player lose, sends them to the losers cage and says why.
	 */
	protected function eliminate(Player $player, string $messageKey) : void {
		$this->lose($player);
		$player->sendMessage($this->plugin->getTranslator()->translate($player, $messageKey));
	}

	/**
	 * Tells a player the outcome of the game, if they have one.
	 *
	 * @param array<string, string|int> $replacements
	 */
	protected function sendOutcome(Player $player, string $wonKey, string $loseKey, array $replacements = []) : void {
		if ($this->isWinner($player)) {
			$player->sendMessage($this->plugin->getTranslator()->translate($player, $wonKey, $replacements));
		} elseif ($this->isLoser($player)) {
			$player->sendMessage($this->plugin->getTranslator()->translate($player, $loseKey, $replacements));
		}
	}

	/**
	 * Tells every player how the game went, each in their own language.
	 */
	public function announceResults() : void {
		$translator = $this->plugin->getTranslator();
		$winnersCount = count($this->winners);
		$playersCount = count($this->arena->getPlayers());
		foreach ($this->arena->getPlayers() as $player) {
			$player->sendMessage("\n§l§e" . $this->getName());
			if ($winnersCount >= $playersCount) {
				$player->sendMessage($translator->translate($player, "microgame.nolosers"));
			} elseif ($winnersCount <= 0) {
				$player->sendMessage($translator->translate($player, "microgame.nowinners", ["{%count}" => $playersCount]));
			} elseif ($winnersCount <= 3) {
				$player->sendMessage($translator->translate($player, "microgame.winners", [
					"{%players}" => implode("§a, §8", Utils::getPlayersNames($this->winners))
				]));
			} else {
				$player->sendMessage($translator->translate($player, "microgame.winners2", [
					"{%winners_count}" => $winnersCount,
					"{%players_count}" => $playersCount
				]));
			}
		}
	}

	public function addWinner(Player $player) : void {
		(new PlayerWinMicrogameEvent($player, $this))->call();
		$this->winners[$player->getId()] = $player;
	}

	public function isWinner(Player $player) : bool {
		return isset($this->winners[$player->getId()]);
	}

	/**
	 * @return Player[]
	 */
	public function getWinners() : array {
		return $this->winners;
	}

	public function addLoser(Player $player) : void {
		(new PlayerLoseMicrogameEvent($player, $this))->call();
		$this->losers[$player->getId()] = $player;
	}

	public function isLoser(Player $player) : bool {
		return isset($this->losers[$player->getId()]);
	}

	/**
	 * @return Player[]
	 */
	public function getLosers() : array {
		return $this->losers;
	}

	abstract public function getName() : string;
	abstract public function getLevel() : Level;
	abstract public function getGameDuration() : float; // in seconds
	public function getRecompensePoints() : int {
		return $this->getLevel() === Level::BOSS ? self::BOSS_RECOMPENSE_POINTS : self::DEFAULT_RECOMPENSE_POINTS;
	}

	public function start() : void {
		if ($this instanceof Listener) {
			$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
		}
		$this->startTime = microtime(true);
		$this->hasStarted = true;
		(new MicrogameStartEvent($this))->call();
	}

	abstract public function tick() : void;

	/**
	 * Fills the player XP bar based on the time left.
	 */
	protected function updateTimeBar(float $timeLeft) : void {
		foreach ($this->arena->getPlayers() as $player) {
			$player->getXpManager()->setXpAndProgress((int) $timeLeft, $timeLeft / $this->getGameDuration());
		}
	}

	/**
	 * Stages a mini-platform change (2×2 spawn tiles, optionally only the given
	 * platform keys) into the given async stage selection. Games that replace
	 * the whole platform call this first so the raised spawn tiles don't survive
	 * the build; {@see end} restores the tiles through the merged inverse
	 * selection.
	 *
	 * @param int[] $keys
	 */
	protected function setMiniPlatformsAsync(Selection $selection, Block $block, array $keys = []) : void {
		$map = $this->arena->getMap();
		$minPos = $map->getPlatformMinPos();
		$miniPlatforms = $map->getMiniPlatforms();
		$keys = $keys === [] ? array_keys($miniPlatforms) : $keys;
		foreach ($keys as $key) {
			foreach ($miniPlatforms[$key] as $blockPos) {
				$selection->addCell(
					(int) ($minPos->x + $blockPos[0]),
					(int) ($minPos->y + $blockPos[1]),
					(int) ($minPos->z + $blockPos[2]),
					$block
				);
			}
		}
	}

	/**
	 * Created on first use: the queue builds games before the arena has a world.
	 */
	private function getJournal() : StageJournal {
		return $this->journal ??= new StageJournal($this->arena->getWorld(), $this->arena->getGate());
	}

	/**
	 * The stage change set: add cells to it in start(), then commitStage.
	 */
	protected function getStageSelection() : Selection {
		return $this->getJournal()->getStage();
	}

	/**
	 * Remembers a block before gameplay changes it (mined, placed); end restores it.
	 */
	protected function recordOriginalBlock(Block $block) : void {
		$this->getJournal()->record($block);
	}

	/**
	 * Writes the stage asynchronously; the arena holds the game until it lands.
	 *
	 * @param Closure() : void|null $onComplete
	 */
	protected function commitStage(?Closure $onComplete = null) : void {
		$this->getJournal()->commit($onComplete);
	}

	/**
	 * Writes an out-of-band stage change (e.g. a breaking floor) asynchronously.
	 *
	 * @param Closure() : void|null $onComplete
	 */
	protected function pushStageOperation(Selection $selection, ?Closure $onComplete = null) : void {
		$this->getJournal()->submit($selection, $onComplete);
	}

	public function end() : void {
		if ($this->hasEnded) {
			return;
		}
		if ($this instanceof Listener) {
			HandlerListManager::global()->unregisterAll($this);
		}

		$this->hasEnded = true;
		$this->getJournal()->end();
		(new MicrogameEndEvent($this))->call();
	}
}
