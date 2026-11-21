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

namespace LatamPMDevs\minerware\entity\object;

use pocketmine\entity\Location;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use function str_repeat;

/**
 * Why not use FloatingTextParticle?
 * Answer: To be able to send a different text per player
 * an exampe are translations.
 */
class TextEntity extends PerPlayerTagHuman implements NeverSavedWithChunkEntity {

	public function __construct(Location $location, ?CompoundTag $nbt = null) {
		parent::__construct($location, new Skin("Standard_Custom", str_repeat("\x00", 8192)), $nbt);
		$this->setScale(0.01);
		$this->setNameTagAlwaysVisible(true);
	}

	public function attack(EntityDamageEvent $event) : void {
		$event->cancel();
		parent::attack($event);
	}

	protected function move(float $dx, float $dy, float $dz) : void {}

	public function canSaveWithChunk() : bool {
		return false;
	}
}