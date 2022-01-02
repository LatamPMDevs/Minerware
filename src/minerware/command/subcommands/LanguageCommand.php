<?php

declare(strict_types=1);

namespace minerware\command\subcommands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\BaseSubCommand;
use minerware\command\args\LanguageArgument;
use minerware\language\Translator;
use minerware\Minerware;
use pocketmine\command\CommandSender;
use pocketmine\lang\Language;
use pocketmine\lang\Translatable;
use function in_array;
use function strtolower;
use function ucfirst;

final class LanguageCommand extends BaseSubCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct("language", "Change the language of the plugin.");
	}

	protected function prepare(): void {
		$this->registerArgument(0, new LanguageArgument($this->plugin));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		/** @var ?Language $language */
		$language = $args["language"] ?? null;
		$languages = LanguageArgument::VALUES;
		if ($language === null) {
			$list = "";
			foreach ($languages as $lang) {
				$list .= "§e- §f" . ucfirst($lang) . "\n";
			}

			$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.language.list", [$list])));
			return;
		}

		if (!in_array(strtolower($language->getLang()), $languages, true)) {
			$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.language.notFound", [$language])));
			return;
		}

		$language = Translator::getInstance()->changeLanguage($language);
		$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.language.changed", [$language->getName()])));
	}

	public function getParent(): BaseCommand {
		return $this->parent;
	}
}
