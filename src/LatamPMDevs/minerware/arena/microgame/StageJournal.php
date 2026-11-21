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
use LatamPMDevs\minerware\arena\StageGate;
use LatamPMDevs\minerware\utils\Selection;
use pocketmine\block\Block;
use pocketmine\world\World;
use function array_shift;

/**
 * Everything a microgame changes in the world, and how to undo it.
 */
final class StageJournal {

	/** Stage changes waiting for {@see commit}. */
	private ?Selection $stage = null;

	/** Original states of blocks changed by play (mined, placed). */
	private ?Selection $played = null;

	/** @var list<Selection> inverse change sets, newest last */
	private array $undos = [];

	private int $pending = 0;

	private bool $ended = false;

	private bool $restored = false;

	public function __construct(
		private readonly World $world,
		private readonly StageGate $gate
	) { }

	/**
	 * The stage change set, created on first use.
	 */
	public function getStage() : Selection {
		return $this->stage ??= new Selection($this->world);
	}

	/**
	 * Writes the stage set.
	 *
	 * @param Closure() : void|null $onWritten
	 */
	public function commit(?Closure $onWritten = null) : void {
		$stage = $this->stage;
		if ($stage === null) {
			if ($onWritten !== null) {
				$onWritten();
			}
			return;
		}
		$this->stage = null;
		$this->submit($stage, $onWritten);
	}

	/**
	 * Writes a change set asynchronously and remembers how to undo it.
	 *
	 * @param Closure() : void|null $onWritten
	 */
	public function submit(Selection $selection, ?Closure $onWritten = null) : void {
		$this->pending++;
		$this->gate->build($selection, function (?Selection $undo) use ($onWritten) : void {
			$this->pending--;
			if ($undo !== null) {
				$this->undos[] = $undo;
			}
			if ($onWritten !== null) {
				$onWritten();
			}
			$this->restore();
		});
	}

	/**
	 * Remembers a block's state before the game changes it.
	 */
	public function record(Block $block) : void {
		$position = $block->getPosition();
		($this->played ??= new Selection($this->world))
			->addCell((int) $position->x, (int) $position->y, (int) $position->z, $block);
	}

	/**
	 * The game is over: restore the world as soon as nothing is in flight.
	 */
	public function end() : void {
		$this->ended = true;
		$this->restore();
	}

	private function restore() : void {
		if (!$this->ended || $this->restored || $this->pending > 0) {
			return;
		}
		$this->restored = true;
		# Merge everything into one inverse selection. IGNORE_IF_EXISTS keeps the
		# earliest pre-change state of every cell, the only correct rollback target.
		# Played blocks go last: their states, captured after the stage builds,
		# overwrite those cells.
		$undos = $this->undos;
		$this->undos = [];
		if ($this->played !== null) {
			$undos[] = $this->played;
			$this->played = null;
		}
		$merged = array_shift($undos);
		if ($merged === null) {
			return;
		}
		foreach ($undos as $undo) {
			$merged->merge($undo, Selection::IGNORE_IF_EXISTS);
		}
		$this->gate->build($merged);
	}
}