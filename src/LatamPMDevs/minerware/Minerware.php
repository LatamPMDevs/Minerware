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
use minerware\arena\ArenaManager;
use minerware\command\MinerwareCommand;
use minerware\database\DataManager;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;

final class Minerware extends PluginBase {
	use SingletonTrait {
		setInstance as protected;
		reset as protected;
	}

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
	}

	protected function onDisable() : void {
		foreach (ArenaManager::getInstance()->getArenas() as $arena) {
			$arena->deleteMap();
		}
	}

	public function getPrefix() : string {
		return $this->getDescription()->getPrefix();
	}

	public function getTranslator() : Translator {
		return $this->translator;
	}
}
