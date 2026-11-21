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

use LatamPMDevs\minerware\map\Map;
use LatamPMDevs\minerware\utils\Selection;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\Position;
use pocketmine\world\World;

/**
 * The ring of invisible bedrock that keeps players on the platform.
 */
final class ArenaWalls {

	private const HEIGHT = 30; //blocks above the platform

	private bool $up = false;

	public function __construct(
		private readonly Map $map,
		private readonly World $world,
		private readonly StageGate $gate
	) { }

	public function isUp() : bool {
		return $this->up;
	}

	/**
	 * Builds the walls if they are not up yet.
	 */
	public function raise() : void {
		if (!$this->up) {
			$this->fill(VanillaBlocks::INVISIBLE_BEDROCK());
			$this->up = true;
		}
	}

	/**
	 * Removes the walls if they are up.
	 */
	public function lower() : void {
		if ($this->up) {
			$this->fill(VanillaBlocks::AIR());
			$this->up = false;
		}
	}

	private function fill(Block $block) : void {
		$min = $this->map->getPlatformMinPos();
		$max = $this->map->getPlatformMaxPos();
		$corner1 = new Position($min->x - 1, $min->y + self::HEIGHT, $min->z - 1, $this->world);
		$corner2 = new Position($max->x + 1, $min->y + self::HEIGHT, $max->z + 1, $this->world);
		$corner3 = new Position($corner1->x, $min->y, $corner2->z, $this->world);
		$corner4 = new Position($corner2->x, $min->y, $corner1->z, $this->world);
		$selection = new Selection($this->world);
		$selection->addFill($corner1, $corner3, $block);
		$selection->addFill($corner3, $corner2, $block);
		$selection->addFill($corner2, $corner4, $block);
		$selection->addFill($corner4, $corner1, $block);
		$this->gate->build($selection);
	}
}