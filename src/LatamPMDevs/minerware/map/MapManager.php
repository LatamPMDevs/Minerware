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

namespace LatamPMDevs\minerware\map;

use pocketmine\utils\SingletonTrait;
use function array_rand;
use function count;

final class MapManager {
	use SingletonTrait;

	/** @var Map[] */
	private array $maps = [];

	public function add(Map $map) : void {
		$this->maps[$map->getName()] = $map;
	}

	public function remove(Map $map) : void {
		unset($this->maps[$map->getName()]);
	}

	/**
	 * @return Map[]
	 */
	public function getAll() : array {
		return $this->maps;
	}

	public function getByName(string $name) : ?Map {
		return $this->maps[$name] ?? null;
	}

	public function getRandom() : ?Map {
		if (count($this->maps) === 0) {
			return null;
		}
		return $this->maps[array_rand($this->maps)];
	}

	public function getCount() : int {
		return count($this->maps);
	}
}