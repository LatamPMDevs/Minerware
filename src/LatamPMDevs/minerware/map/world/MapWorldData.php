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

namespace LatamPMDevs\minerware\map\world;

use pocketmine\math\Vector3;
use pocketmine\world\format\io\WorldData;

/**
 * In-memory copy of a template's world data.
 */
final class MapWorldData implements WorldData {

	private string $name;

	private int $time; //ticks

	private Vector3 $spawn;

	private int $difficulty;

	private int $rainTime; //ticks

	private float $rainLevel;

	private int $lightningTime; //ticks

	private float $lightningLevel;

	public function __construct(private readonly WorldData $source) {
		$this->name = $source->getName();
		$this->time = $source->getTime();
		$this->spawn = $source->getSpawn();
		$this->difficulty = $source->getDifficulty();
		$this->rainTime = $source->getRainTime();
		$this->rainLevel = $source->getRainLevel();
		$this->lightningTime = $source->getLightningTime();
		$this->lightningLevel = $source->getLightningLevel();
	}

	public function save() : void {
		# Instances are volatile.
	}

	public function getName() : string {
		return $this->name;
	}

	public function setName(string $value) : void {
		$this->name = $value;
	}

	public function getGenerator() : string {
		return $this->source->getGenerator();
	}

	public function getGeneratorOptions() : string {
		return $this->source->getGeneratorOptions();
	}

	public function getSeed() : int {
		return $this->source->getSeed();
	}

	public function getTime() : int {
		return $this->time;
	}

	public function setTime(int $value) : void {
		$this->time = $value;
	}

	public function getSpawn() : Vector3 {
		return $this->spawn;
	}

	public function setSpawn(Vector3 $pos) : void {
		$this->spawn = $pos;
	}

	public function getDifficulty() : int {
		return $this->difficulty;
	}

	public function setDifficulty(int $difficulty) : void {
		$this->difficulty = $difficulty;
	}

	public function getRainTime() : int {
		return $this->rainTime;
	}

	public function setRainTime(int $ticks) : void {
		$this->rainTime = $ticks;
	}

	public function getRainLevel() : float {
		return $this->rainLevel;
	}

	public function setRainLevel(float $level) : void {
		$this->rainLevel = $level;
	}

	public function getLightningTime() : int {
		return $this->lightningTime;
	}

	public function setLightningTime(int $ticks) : void {
		$this->lightningTime = $ticks;
	}

	public function getLightningLevel() : float {
		return $this->lightningLevel;
	}

	public function setLightningLevel(float $level) : void {
		$this->lightningLevel = $level;
	}
}
