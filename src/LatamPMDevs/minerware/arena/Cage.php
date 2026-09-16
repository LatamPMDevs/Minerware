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
use LatamPMDevs\minerware\utils\AsyncBlockManager;
use LatamPMDevs\minerware\utils\Selection;
use LatamPMDevs\minerware\utils\Utils;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\world\Position;
use RuntimeException;

final class Cage {

	private Position $offsetPosition;

	private bool $isSet = false;

	/** @var Player[] */
	private array $players = [];

	/** @var Player[] players waiting for the async cage build before teleporting in */
	private array $pendingPlayers = [];

	private ?Closure $onBuildStarted = null;

	private ?Closure $onBuildSettled = null;

	public function __construct(private Position $position, private Block $material) {
		$this->reloadOffsetPosition();
	}

	/**
	 * Registers the arena's async-build accounting callbacks. Called once by the
	 * owning arena so cage builds keep {@see Arena::isBuildingStage} accurate.
	 */
	public function setBuildCallbacks(?Closure $onStarted, ?Closure $onSettled) : void {
		$this->onBuildStarted = $onStarted;
		$this->onBuildSettled = $onSettled;
	}

	private function reloadOffsetPosition() : void {
		$this->offsetPosition = clone $this->position;
		$this->offsetPosition->y += 2;
	}

	public function getPosition() : Position {
		return $this->position;
	}

	public function setPosition(Position $position) : void {
		if ($this->isSet) {
			throw new RuntimeException("Cannot set position while cage is set");
		}
		$this->position = clone $position;
		$this->reloadOffsetPosition();
	}

	public function getOffsetPosition() : Position {
		return $this->offsetPosition;
	}

	public function getMaterial() : Block {
		return $this->material;
	}

	public function setMaterial(Block $material) : void {
		if ($this->isSet) {
			throw new RuntimeException("Cannot set material while cage is set");
		}
		$this->material = clone $material;
	}

	public function isSet() : bool {
		return $this->isSet;
	}

	public function set() : void {
		if (!$this->isSet) {
			$this->callOnBuildStarted();
			AsyncBlockManager::getInstance()->executeSet($this->buildSelection($this->material), $this->onBuilt(...));
			$this->isSet = true;
		}
	}

	public function unset() : void {
		if ($this->isSet) {
			$this->callOnBuildStarted();
			AsyncBlockManager::getInstance()->executeSet($this->buildSelection(VanillaBlocks::AIR()), $this->callOnBuildSettled(...));
			$this->isSet = false;
			$this->players = [];
			$this->pendingPlayers = [];
		}
	}

	/**
	 * Invokes the registered build-started callback, if any.
	 */
	private function callOnBuildStarted() : void {
		$callback = $this->onBuildStarted;
		if ($callback !== null) {
			$callback();
		}
	}

	/**
	 * Invokes the registered build-settled callback, if any.
	 */
	private function callOnBuildSettled() : void {
		$callback = $this->onBuildSettled;
		if ($callback !== null) {
			$callback();
		}
	}

	/**
	 * Runs once the cage build has landed: settles the arena's build accounting
	 * and teleports in any players who arrived while the build was in flight.
	 */
	private function onBuilt() : void {
		$this->callOnBuildSettled();
		foreach ($this->pendingPlayers as $player) {
			if ($player->isConnected() && isset($this->players[$player->getId()])) {
				$player->teleport($this->offsetPosition);
			}
		}
		$this->pendingPlayers = [];
	}

	/**
	 * Builds the 7x7x5 cage box (base + four walls) as an async selection,
	 * mirroring {@see Utils::buildCage}.
	 */
	private function buildSelection(Block $block) : Selection {
		$world = $this->position->getWorld();
		$selection = new Selection($world);
		$pos1 = new Position($this->position->x + 3, $this->position->y, $this->position->z + 3, $world);
		$pos2 = new Position($this->position->x - 3, $this->position->y, $this->position->z - 3, $world);
		$selection->addFill($pos1, $pos2, $block);
		$pos3 = new Position($pos1->x, $this->position->y + 4, $pos2->z, $world);
		$selection->addFill($pos1, $pos3, $block);
		$selection->addFill($pos3, $pos2, $block);
		$pos4 = new Position($pos2->x, $this->position->y + 4, $pos1->z, $world);
		$selection->addFill($pos2, $pos4, $block);
		$selection->addFill($pos4, $pos1, $block);
		return $selection;
	}

	/**
	 * @return Player[]
	 */
	public function getPlayers() : array {
		return $this->players;
	}

	public function isInCage(Player $player) : bool {
		return isset($this->players[$player->getId()]);
	}

	public function addPlayer(Player $player) : void {
		$mustBuild = !$this->isSet;
		if ($mustBuild) {
			$this->set();
		}
		Utils::initPlayer($player);
		$player->setGamemode(GameMode::ADVENTURE);
		$this->players[$player->getId()] = $player;
		if ($mustBuild) {
			# The glass box is still being written asynchronously; teleport the
			# player inside from the build's completion callback instead of
			# dropping them where the cage doesn't exist yet.
			$this->pendingPlayers[$player->getId()] = $player;
		} else {
			$player->teleport($this->offsetPosition);
		}
	}

	public function removePlayer(Player $player) : void {
		unset($this->players[$player->getId()]);
	}
}