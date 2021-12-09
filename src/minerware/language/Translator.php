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
 * Copyright 2021 © Minerware
 */

declare(strict_types=1);

namespace minerware\language;

use minerware\Minerware;
use pocketmine\lang\Language;
use pocketmine\lang\TranslationContainer;
use pocketmine\utils\SingletonTrait;

final class Translator {
	use SingletonTrait;

	/** @var Minerware */
	private $plugin;

	/** @var Language */
	private $language;

	public function __construct() {
		$this->plugin = Minerware::getInstance();
		$this->loadLanguages();

		$language = $this->plugin->getConfig()->get("language");
		$this->language = new Language($language, $this->plugin->getDataFolder() . "languages" . DIRECTORY_SEPARATOR, $language);
	}

	private function loadLanguages(): void {
		$this->plugin->saveResource(DIRECTORY_SEPARATOR . "languages" . DIRECTORY_SEPARATOR . "spanish.ini", true);
		$this->plugin->saveResource(DIRECTORY_SEPARATOR . "languages" . DIRECTORY_SEPARATOR . "english.ini", true);
	}

	public function changeLanguage(string $newLanguage): void {
		$this->language = new Language($newLanguage, $this->plugin->getDataFolder() . "languages" . DIRECTORY_SEPARATOR, $newLanguage);
		$this->plugin->getConfig()->set("language", $this->language->getLang());
		$this->plugin->getConfig()->save();
	}

	public function translate(TranslationContainer $container): string {
		return $this->language->translate($container);
	}
}