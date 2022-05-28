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

namespace LatamPMDevs\minerware\entity\object;

use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\StringMetadataProperty;
use pocketmine\player\Player;
use pocketmine\nbt\tag\CompoundTag;
use function str_repeat;

/**
 * Why not use FloatingTextParticle?
 * Answer: To be able to send a different text per player
 * an exampe are translations.
 */
class TextEntity extends Human {

	/** @var array<int, string> */
	protected array $nameTagPerPlayer = [];

	public function __construct(Location $location, ?CompoundTag $nbt = null) {
		parent::__construct($location, new Skin("Standard_Custom", str_repeat("\x00", 8192)), $nbt);
		$this->setScale(0.01);
		$this->setNameTagAlwaysVisible(true);
	}

	public function getNameTagPerPlayer() : array {
		return $this->nameTagPerPlayer;
	}

	public function setNameTagToPlayer(Player $player, string $nameTag) : void {
		$this->nameTagPerPlayer[$player->getId()] = $nameTag;
		$this->networkPropertiesDirty = true;
	}

	public function getNameTagToPlayer(Player $player) : string {
		return $this->nameTagPerPlayer[$player->getId()]  ?? $this->nameTag;
	}

	public function attack(EntityDamageEvent $event): void {
		$event->cancel();
		parent::attack($event);
	}

	public function sendData(?array $targets, ?array $data = null) : void {
		$targets = $targets ?? $this->hasSpawned;
		$data = $data ?? $this->getAllNetworkData();

		foreach ($targets as $p) {
			if (isset($data[EntityMetadataProperties::NAMETAG])) {
				$data[EntityMetadataProperties::NAMETAG] = new StringMetadataProperty($this->getNameTagToPlayer($p));
			}
			$p->getNetworkSession()->syncActorData($this, $data);
		}
	}

	protected function move(float $dx, float $dy, float $dz): void {}

	public function canSaveWithChunk() : bool {
		return false;
	}
}