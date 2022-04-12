<?php

/**
 *  ███╗   ███╗██╗███╗   ██╗███████╗██████╗ ██╗    ██╗ █████╗ ██████╗ ███████╗
 *  ████╗ ████║██║████╗  ██║██╔════╝██╔══██╗██║    ██║██╔══██╗██╔══██╗██╔════╝
 *  ██╔████╔██║██║██╔██╗ ██║█████╗  ██████╔╝██║ █╗ ██║███████║██████╔╝█████╗
 *  ██║╚██╔╝██║██║██║╚██╗██║██╔══╝  ██╔══██╗██║███╗██║██╔══██║██╔══██╗██╔══╝
 *  ██║ ╚═╝ ██║██║██║ ╚████║███████╗██║  ██║╚███╔███╔╝██║  ██║██║  ██║███████╗
 *  ╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝ ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝
 *
 * This is a private project, your not allow to redistribute nor resell it.
 * The only ones with that power are this project's contributors.
 *
 * Copyright 2022 © Minerware
 */

declare(strict_types=1);

namespace minerware;

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
		$this->copyright();
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

	private function copyright() : void {
		$copyright = [
			"",
			"███╗   ███╗██╗███╗   ██╗███████╗██████╗ ██╗    ██╗ █████╗ ██████╗ ███████╗",
			"████╗ ████║██║████╗  ██║██╔════╝██╔══██╗██║    ██║██╔══██╗██╔══██╗██╔════╝",
			"██╔████╔██║██║██╔██╗ ██║█████╗  ██████╔╝██║ █╗ ██║███████║██████╔╝█████╗",
			"██║╚██╔╝██║██║██║╚██╗██║██╔══╝  ██╔══██╗██║███╗██║██╔══██║██╔══██╗██╔══╝",
			"██║ ╚═╝ ██║██║██║ ╚████║███████╗██║  ██║╚███╔███╔╝██║  ██║██║  ██║███████╗",
			"╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝ ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝",
			"",
			"This is a private project, your not allow to redistribute nor resell it.",
			"The only ones with that power are this project's contributors.",
			"",
			"Copyright 2022 © Minerware",
			""
		];

		foreach ($copyright as $str) {
			$this->getServer()->getLogger()->notice($str);
		}
	}
}
