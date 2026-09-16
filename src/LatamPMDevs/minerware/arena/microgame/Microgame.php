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
use LatamPMDevs\minerware\utils\AsyncBlockManager;
use LatamPMDevs\minerware\utils\Selection;

use pocketmine\block\Block;
use pocketmine\event\HandlerListManager;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use function array_keys;
use function array_shift;
use function microtime;

abstract class Microgame {

	public const DEFAULT_RECOMPENSE_POINTS = 1;
	public const BOSS_RECOMPENSE_POINTS = 3;

	protected Minerware $plugin;

	protected bool $hasStarted = false;

	protected bool $hasEnded = false;

	protected float $startTime;

	/** @var Player[] */
	protected array $winners = [];

	/** @var Player[] */
	protected array $losers = [];

	/** Stage terrain changes awaiting {@see commitStage}; applied asynchronously. */
	protected ?Selection $selection = null;

	/**
	 * Original states of blocks changed by gameplay interactions (mined /
	 * player-placed), recorded via {@see recordOriginalBlock} and restored
	 * asynchronously in {@see end} as one extra stage operation.
	 */
	protected ?Selection $interactionSelection = null;

	/**
	 * Inverse change sets returned by this game's async operations, newest last.
	 * {@see trySubmitRollback} merges them (and the interaction rollback) into
	 * one selection so the whole stage rolls back in a single async task.
	 *
	 * @var list<Selection>
	 */
	protected array $stageUndos = [];

	/** Number of this game's async stage operations still in flight. */
	private int $pendingStageOperations = 0;

	/** Whether the end-of-game rollback has already been submitted (or found empty). */
	private bool $rollbackSubmitted = false;

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
	abstract public function getRecompensePoints() : int;

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
	 * Returns the stage terrain change set, creating it lazily against the aria's
	 * world. Microgames that build their stage asynchronously add cells to this
	 * selection in start() instead of writing blocks synchronously.
	 */
	protected function getStageSelection() : Selection {
		if ($this->selection === null) {
			$this->selection = new Selection($this->arena->getWorld());
		}
		return $this->selection;
	}

	/**
	 * Records a block's current state for rollback after gameplay interactions
	 * (mined blocks, player placements — writes the game itself performs
	 * synchronously). The original state is captured eagerly at interaction
	 * time; {@see end} restores all recorded cells asynchronously.
	 */
	protected function recordOriginalBlock(Block $block) : void {
		if ($this->interactionSelection === null) {
			$this->interactionSelection = new Selection($this->arena->getWorld());
		}
		$this->interactionSelection->addCell((int) $block->getPosition()->x, (int) $block->getPosition()->y, (int) $block->getPosition()->z, $block);
	}

	/**
	 * Submits the stage {@see Selection} for asynchronous writing. The worker
	 * captures every applied cell's pre-change state and hands back an inverse
	 * Selection, pushed onto {@see $stageUndos} so the game's rollback can
	 * restore the stage. The arena's {@see Arena::isBuildingStage} gate pauses
	 * {@see tick} until the write lands. Safe to call only once per game: the
	 * selection is consumed by the first commit.
	 *
	 * @param ?Closure $onComplete optional zero-arg closure run once the stage
	 * has been written.
	 */
	protected function commitStage(?Closure $onComplete = null) : void {
		if ($this->selection === null) {
			if ($onComplete !== null) {
				$onComplete();
			}
			return;
		}
		$selection = $this->selection;
		# Consumed: a second commitStage() must not re-submit the same change set.
		$this->selection = null;
		$this->runStageOperation($selection, $onComplete);
	}

	/**
	 * Submits an out-of-band stage change (e.g. {@see StandOnDiamond
	 * ::breakFloor}) asynchronously, pushing its inverse onto {@see $stageUndos}
	 * like {@see commitStage} does. Must not be called while the stage is
	 * building.
	 *
	 * @param ?Closure $onComplete optional zero-arg closure run once the change
	 * has been written.
	 */
	protected function pushStageOperation(Selection $selection, ?Closure $onComplete = null) : void {
		$this->runStageOperation($selection, $onComplete);
	}

	/**
	 * Shared submit path for {@see commitStage} and {@see pushStageOperation}.
	 * The inverse selection arrives (and {@see $pendingStageOperations} drops to
	 * zero) on the main thread once the operation lands — which may happen after
	 * {@see end}, so the rollback submission is deferred to
	 * {@see trySubmitRollback}.
	 *
	 * @param ?Closure $onComplete optional zero-arg closure run once the change
	 * has been written.
	 */
	private function runStageOperation(Selection $selection, ?Closure $onComplete) : void {
		$this->pendingStageOperations++;
		$this->arena->onStageBuildStarted();
		AsyncBlockManager::getInstance()->executeSet($selection, function (?Selection $undo) use ($onComplete) : void {
			$this->pendingStageOperations--;
			if ($undo !== null) {
				$this->stageUndos[] = $undo;
			}
			$this->arena->onStageBuildSettled();
			if ($onComplete !== null) {
				$onComplete();
			}
			$this->trySubmitRollback();
		});
	}

	/**
	 * Submits the game's single merged rollback task as soon as both the game
	 * has ended and every one of its async stage operations has landed. Deferring
	 * guarantees no inverse change set is orphaned when a game ends while an
	 * operation (e.g. StandOnDiamond's breakFloor) is still in flight.
	 */
	private function trySubmitRollback() : void {
		if (!$this->hasEnded || $this->rollbackSubmitted || $this->pendingStageOperations > 0) {
			return;
		}
		$this->rollbackSubmitted = true;

		# Merge everything into one inverse selection. IGNORE_IF_EXISTS keeps the
		# earliest pre-change state of every cell, which is the only correct
		# rollback target. The gameplay interaction rollback (mined/player-placed
		# blocks) goes last so its states, captured after the stage builds,
		# overwrite those cells.
		$undos = $this->stageUndos;
		$this->stageUndos = [];
		if ($this->interactionSelection !== null) {
			$undos[] = $this->interactionSelection;
			$this->interactionSelection = null;
		}
		if ($undos === []) {
			return;
		}
		$merged = array_shift($undos);
		foreach ($undos as $undo) {
			$merged->merge($undo, Selection::IGNORE_IF_EXISTS);
		}
		$this->arena->onStageBuildStarted();
		AsyncBlockManager::getInstance()->executeSet($merged, $this->arena->onStageBuildSettled(...));
	}

	public function end() : void {
		if ($this instanceof Listener) {
			HandlerListManager::global()->unregisterAll($this);
		}

		$this->hasEnded = true;
		$this->trySubmitRollback();
		(new MicrogameEndEvent($this))->call();
	}
}
