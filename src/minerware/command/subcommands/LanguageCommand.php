<?php

namespace minerware\command\subcommands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use minerware\language\Translator;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use function in_array;
use function strtolower;
use function ucfirst;

final class LanguageCommand extends BaseSubCommand {

	public function __construct() {
		parent::__construct("language", "Change the language of the plugin.", ["lang"]);
	}

	protected function prepare(): void {
		$this->registerArgument(0, new RawStringArgument("language", true));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		$language = $args["language"] ?? null;
		$languages = [
			"English", "Spanish"
		];

		if ($language === null) {
			$list = "";
			foreach ($languages as $lang) {
				$list .= "§e- §f" . $lang . "\n";
			}

			$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.arg.setlang.langList", [$list])));
			return;
		}

		if (!in_array(ucfirst(strtolower($language)), $languages, true)) {
			$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.arg.setlang.langNotFound", [$language])));
			return;
		}

		Translator::getInstance()->changeLanguage($language);
		$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.arg.setlang.changed", [strtolower($language)])));
	}
}
