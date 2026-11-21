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

namespace LatamPMDevs\minerware;

use pocketmine\utils\SingletonTrait;
use function is_float;
use function is_int;
use function is_string;
use function max;

/**
 * The options of config.yml, with a default and a lower limit where it makes sense.
 */
final class Settings {
	use SingletonTrait;

	public function getServerIp() : string {
		return $this->string("server-ip", "yourserverip.net");
	}

	public function getDefaultLanguage() : string {
		return $this->string("default-language", "en_US");
	}

	public function getJoinCooldown() : float {
		return max($this->number("join-cooldown", 2), 0.0);
	}

	public function getMaxRuntimeArenas() : int {
		return max((int) $this->number("max-runtime-arenas", 15), 1);
	}

	public function getArenaStartingTime() : int {
		return max((int) $this->number("arena-starting-time", 120), 5);
	}

	public function getMinimumStartingPlayers() : int {
		return max((int) $this->number("minimum-starting-players", 2), 2);
	}

	private function string(string $key, string $default) : string {
		$value = Minerware::getInstance()->getConfig()->get($key, $default);
		return is_string($value) ? $value : $default;
	}

	private function number(string $key, int|float $default) : int|float {
		$value = Minerware::getInstance()->getConfig()->get($key, $default);
		return is_int($value) || is_float($value) ? $value : $default;
	}
}