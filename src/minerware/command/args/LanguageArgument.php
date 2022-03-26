<?php

declare(strict_types=1);

namespace minerware\command\args;

use CortexPE\Commando\args\StringEnumArgument;
use minerware\Minerware;
use pocketmine\command\CommandSender;
use pocketmine\lang\Language;

final class LanguageArgument extends StringEnumArgument {

	public const VALUES = [
		"english" => "english",
		"spanish" => "spanish"
	];

	public function __construct(private Minerware $plugin) {
		parent::__construct("language", true);
	}

	public function parse(string $argument, CommandSender $sender) : Language {
		return $this->getValue($argument);
	}

	public function getValue(string $string) : Language {
		return new Language($string, $this->plugin->getDataFolder() . "/languages/", $string);
	}

	public function getTypeName() : string {
		return "string";
	}
}
