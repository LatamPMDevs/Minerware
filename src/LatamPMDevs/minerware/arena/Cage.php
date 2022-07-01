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

	public function __construct(private Position $position, private Block $material) {
		$this->reloadOffsetPosition();
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
			Utils::buildCage($this->position, $this->material);
			$this->isSet = true;
		}
	}

	public function unset() : void {
		if ($this->isSet) {
			Utils::buildCage($this->position, VanillaBlocks::AIR());
			$this->isSet = false;
			$this->players = [];
		}
	}

	public function getPlayers() : array {
		return $this->players;
	}

	public function isInCage(Player $player) : bool {
		return isset($this->players[$player->getId()]);
	}

	public function addPlayer(Player $player) : void {
		if (!$this->isSet) {
			$this->set();
		}
		Utils::initPlayer($player);
		$player->setGamemode(GameMode::ADVENTURE());
		$player->teleport($this->offsetPosition);
		$this->players[$player->getId()] = $player;
	}

	public function removePlayer(Player $player) : void {
		unset($this->players[$player->getId()]);
	}
}