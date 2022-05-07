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

namespace LatamPMDevs\minerware;

use CortexPE\Commando\PacketHooker;
use IvanCraft623\languages\Translator;
use LatamPMDevs\minerware\arena\ArenaManager;
use LatamPMDevs\minerware\arena\microgame\boss\ColorFloor;
use LatamPMDevs\minerware\arena\microgame\normal\IgniteTNT;
use LatamPMDevs\minerware\arena\microgame\normal\LastKnightStanding;
use LatamPMDevs\minerware\arena\microgame\normal\MineOre;
use LatamPMDevs\minerware\arena\microgame\normal\Sneaking;
use LatamPMDevs\minerware\arena\microgame\normal\StackBlocks;
use LatamPMDevs\minerware\arena\microgame\normal\StandOnColor;
use LatamPMDevs\minerware\arena\microgame\normal\StandOnDiamond;
use LatamPMDevs\minerware\command\MinerwareCommand;
use LatamPMDevs\minerware\database\DataManager;
use LatamPMDevs\minerware\utils\Scoreboard;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;

final class Minerware extends PluginBase {
	use SingletonTrait {
		setInstance as protected;
		reset as protected;
	}

	private Scoreboard $scoreboard;

	private Translator $translator;

	protected function onLoad() : void {
		self::setInstance($this);
		DataManager::getInstance();
		ArenaManager::getInstance();
		$this->translator = new Translator($this);
		DataManager::getInstance()->loadLanguages();
		$this->getServer()->getCommandMap()->register("minerware", new MinerwareCommand($this));
	}

	protected function onEnable() : void {
		if(!PacketHooker::isRegistered()) {
			PacketHooker::register($this);
		}

		DataManager::getInstance()->loadMaps();
		$this->scoreboard = new Scoreboard($this);
	}

	protected function onDisable() : void {
		foreach (ArenaManager::getInstance()->getArenas() as $arena) {
			$arena->deleteMap();
		}
		$this->getConfig()->set("default-language", $this->translator->getDefaultLanguage()->getLocale());
	}

	public function getPrefix() : string {
		return $this->getDescription()->getPrefix();
	}

	public function getScoreboard() : Scoreboard {
		return $this->scoreboard;
	}

	public function getTranslator() : Translator {
		return $this->translator;
	}

	public function getNormalMicrogames() : array {
		return [IgniteTNT::class, LastKnightStanding::class, MineOre::class, Sneaking::class, StackBlocks::class, StandOnColor::class, StandOnDiamond::class];
	}

	public function getBossMicrogames() : array {
		return [ColorFloor::class];
	}
}
