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
use IvanCraft623\fakeblocks\FakeBlockManager;
use IvanCraft623\languages\Translator;
use JackMD\ConfigUpdater\ConfigUpdater;
use JackMD\UpdateNotifier\UpdateNotifier;
use LatamPMDevs\minerware\arena\ArenaManager;
use LatamPMDevs\minerware\command\MinerwareCommand;
use LatamPMDevs\minerware\database\DataManager;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;

final class Minerware extends PluginBase {
	use SingletonTrait {
		setInstance as protected;
		reset as protected;
	}

	public const CONFIG_VERSION = 1;

	private Translator $translator;

	protected function onLoad() : void {
		self::setInstance($this);

		UpdateNotifier::checkUpdate($this->getDescription()->getName(), $this->getDescription()->getVersion());
		if (ConfigUpdater::checkUpdate($this, $this->getConfig(), "config-version", self::CONFIG_VERSION)) {
			$this->reloadConfig();
		}

		DataManager::getInstance();
		ArenaManager::getInstance();
		$this->translator = new Translator($this);
		DataManager::getInstance()->loadLanguages();
		$this->getServer()->getCommandMap()->register("minerware", new MinerwareCommand($this));
	}

	protected function onEnable() : void {
		if (!PacketHooker::isRegistered()) {
			PacketHooker::register($this);
		}
		if (!FakeBlockManager::isRegistered()) {
			FakeBlockManager::register($this);
		}

		$dataManager = DataManager::getInstance();
		$dataManager->loadMaps();
		$this->getServer()->getPluginManager()->registerEvents(new EventListener($dataManager), $this);
	}

	protected function onDisable() : void {
		foreach (ArenaManager::getInstance()->getArenas() as $arena) {
			$arena->deleteMap();
		}
		$this->getConfig()->set("default-language", $this->translator->getDefaultLanguage()->getLocale());
		DataManager::getInstance()->closeDatabase();
	}

	public function getPrefix() : string {
		return "§aMinerware§r ";
	}

	public function getTranslator() : Translator {
		return $this->translator;
	}
}
