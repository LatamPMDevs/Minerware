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

namespace LatamPMDevs\minerware\arena\microgame;

use LatamPMDevs\minerware\arena\microgame\boss\ColorFloor;
use LatamPMDevs\minerware\arena\microgame\normal\IgniteTNT;
use LatamPMDevs\minerware\arena\microgame\normal\LastKnightStanding;
use LatamPMDevs\minerware\arena\microgame\normal\MineOre;
use LatamPMDevs\minerware\arena\microgame\normal\NerdPole;
use LatamPMDevs\minerware\arena\microgame\normal\OneInTheChamber;
use LatamPMDevs\minerware\arena\microgame\normal\PlatformPlummet;
use LatamPMDevs\minerware\arena\microgame\normal\Sneaking;
use LatamPMDevs\minerware\arena\microgame\normal\StackBlocks;
use LatamPMDevs\minerware\arena\microgame\normal\StandOnColor;
use LatamPMDevs\minerware\arena\microgame\normal\StandOnDiamond;
use pocketmine\utils\SingletonTrait;

use pocketmine\utils\Utils;
use RuntimeException;

final class MicrogameManager {
	use SingletonTrait;

	/**
	 * @var array<string, class-string<Microgame>>
	 */
	private array $microgames = [];

	/**
	 * @var array<string, class-string<Microgame>>
	 */
	private array $bossgames = [];

	public function __construct() {
		$this->registerBoss(ColorFloor::class, "colorfloor");

		$this->register(IgniteTNT::class, "ignitetnt");
		$this->register(LastKnightStanding::class, "lastknightstanding");
		$this->register(MineOre::class, "mineore");
		$this->register(NerdPole::class, "nerdpole");
		$this->register(OneInTheChamber::class, "oneinthechamber");
		$this->register(PlatformPlummet::class, "platformplummet");
		$this->register(Sneaking::class, "sneaking");
		$this->register(StackBlocks::class, "stackblocks");
		$this->register(StandOnColor::class, "standoncolor");
		$this->register(StandOnDiamond::class, "standondiamond");
	}

	/**
	 * Registers a microgame.
	 *
	 * @param string $className Class that extends Microgame
	 * @param string $saveName Name which this microgame will be identified.
	 * @phpstan-param class-string<Microgame> $className
	 *
	 * @throws RuntimeException if something attempted to override an
	 * already-registered microgame without specifying the $override parameter.
	 */
	public function register(string $className, string $saveName, bool $override = false) : void {
		Utils::testValidInstance($className, Microgame::class);
		if (!$override && $this->isRegistered($saveName)) {
			throw new RuntimeException("Trying to overwrite an already registered microgame");
		}
		$this->microgames[$saveName] = $className;
	}

	/**
	 * Registers a bossgame.
	 *
	 * @param string $className Class that extends Microgame
	 * @param string $saveName Name which this microgame will be identified.
	 * @phpstan-param class-string<Microgame> $className
	 *
	 * @throws RuntimeException if something attempted to override an
	 * already-registered microgame without specifying the $override parameter.
	 */
	public function registerBoss(string $className, string $saveName, bool $override = false) : void {
		Utils::testValidInstance($className, Microgame::class);
		if (!$override && $this->isRegistered($saveName)) {
			throw new RuntimeException("Trying to overwrite an already registered microgame");
		}
		$this->bossgames[$saveName] = $className;
	}

	public function isRegistered(string $saveName) : bool {
		return isset($this->microgames[$saveName]) || isset($this->bossgames[$saveName]);
	}

	/**
	 * @phpstan-return ?class-string<Microgame>
	 */
	public function get(string $saveName) : ?string {
		return $this->microgames[$saveName] ?? $this->bossgames[$saveName] ?? null;
	}

	public function getMicrogames() : array {
		return $this->microgames;
	}

	public function getBossgames() : array {
		return $this->bossgames;
	}
}