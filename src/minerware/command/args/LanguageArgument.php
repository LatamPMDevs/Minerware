<?php

declare(strict_types=1);

namespace minerware\command\args;

use CortexPE\Commando\args\StringEnumArgument;
use IvanCraft623\languages\Language;
use minerware\Minerware;
use pocketmine\command\CommandSender;

final class LanguageArgument extends StringEnumArgument {

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
