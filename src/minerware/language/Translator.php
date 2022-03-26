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

namespace minerware\language;

use minerware\Minerware;
use pocketmine\lang\Language;
use pocketmine\lang\Translatable;
use pocketmine\utils\SingletonTrait;

final class Translator {
	use SingletonTrait {
		setInstance as protected;
		reset as protected;
	}

	private Minerware $plugin;

	private Language $language;

	public function __construct() {
		$this->plugin = Minerware::getInstance();
		$this->loadLanguages();

		$language = $this->plugin->getConfig()->get("language");
		$this->language = new Language($language, $this->plugin->getDataFolder() . "/languages/", $language);
	}

	private function loadLanguages() : void {
		$this->plugin->saveResource("/languages/spanish.ini", true);
		$this->plugin->saveResource("/languages/english.ini", true);
	}

	public function changeLanguage(Language $language) : Language {
		$this->language = $language;
		$this->plugin->getConfig()->set("language", $language->getLang());
		$this->plugin->getConfig()->save();
		return $language;
	}

	public function translate(Translatable $container) : string {
		return $this->language->translate($container);
	}
}
