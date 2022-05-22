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

use pocketmine\entity\object\FallingBlock as PMFallingBlock;

/**
 * This class exists just for optimization
 * used on PlatformPlummet microgame.
 */
class FallingBlock extends PMFallingBlock {

	protected int $maxTicksOfLife = 9999;

	public function getMaxTicksOfLife() : int {
		return $this->maxTicksOfLife;
	}

	public function setMaxTicksOfLife(int $maxTicksOfLife) : void {
		$this->maxTicksOfLife = $maxTicksOfLife;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool {
		if ($this->closed) {
			return false;
		}

		$hasUpdate = parent::entityBaseTick($tickDiff);

		if ($this->ticksLived >= $this->maxTicksOfLife) {
			$this->flagForDespawn();
			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	public function canSaveWithChunk() : bool {
		return false;
	}
}