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

namespace LatamPMDevs\minerware\command\args;

use CortexPE\Commando\args\StringEnumArgument;
use IvanCraft623\languages\Language;
use LatamPMDevs\minerware\Minerware;
use pocketmine\command\CommandSender;

final class LanguageArgument extends StringEnumArgument {

	protected const VALUES = [
		"en_US" => "en_US",
		"es_MX" => "es_MX"
	];

	public function __construct(private Minerware $plugin) {
		parent::__construct("language", true);
	}

	public function parse(string $argument, CommandSender $sender) : string {
		return $this->getValue($argument)?->getLocale() ?? "";
	}

	public function getValue(string $string) : ?Language {
		return $this->plugin->getTranslator()->getLanguage($string);
	}

	public function getEnumValues(): array {
		$values = [];
		foreach ($this->plugin->getTranslator()->getLanguages() as $lang) {
			$values[] = $lang->getLocale();
		}
		return $values;
	}

	public function getTypeName() : string {
		return "string";
	}
}
