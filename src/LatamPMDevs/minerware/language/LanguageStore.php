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

namespace LatamPMDevs\minerware\language;

use LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\IvanCraft623\languages\Language;
use LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\IvanCraft623\languages\Translator;
use LatamPMDevs\minerware\Minerware;
use LatamPMDevs\minerware\Settings;
use pocketmine\utils\AssumptionFailedError;
use function array_map;
use function basename;
use function glob;
use function parse_ini_file;
use function str_ends_with;
use function str_starts_with;
use function stripcslashes;
use const INI_SCANNER_RAW;

/**
 * The language files in the data folder.
 */
final class LanguageStore {

	public function __construct(private readonly Minerware $plugin) { }

	/**
	 * Copies the language files that come with the plugin, replacing the ones already there.
	 */
	public function saveDefaults() : void {
		foreach ($this->plugin->getResources() as $path => $_) {
			if (str_starts_with($path, "languages/") && str_ends_with($path, ".ini")) {
				$this->plugin->saveResource($path, true);
			}
		}
	}

	/**
	 * Registers every language file and picks the default one from the config.
	 */
	public function load(Translator $translator) : void {
		foreach (glob($this->plugin->getDataFolder() . "languages" . DIRECTORY_SEPARATOR . "*.ini") ?: [] as $file) {
			$content = parse_ini_file($file, false, INI_SCANNER_RAW);
			if ($content === false) {
				throw new AssumptionFailedError("Missing or inaccessible required resource files");
			}
			$translator->registerLanguage(new Language(basename($file, ".ini"), array_map(stripcslashes(...), $content)));
		}
		$locale = Settings::getInstance()->getDefaultLanguage();
		$language = $translator->getLanguage($locale);
		if ($language === null) {
			$this->plugin->getLogger()->warning("Language $locale not found, using en_US");
			$language = $translator->getLanguage("en_US") ?? throw new AssumptionFailedError("The en_US language is missing");
		}
		$translator->setDefaultLanguage($language);
	}
}