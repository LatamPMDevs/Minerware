<?php

declare(strict_types=1);

namespace minerware\command\subcommands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\BaseSubCommand;
use IvanCraft623\languages\Language;
use minerware\command\args\LanguageArgument;
use minerware\language\Translator;
use minerware\Minerware;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use function in_array;
use function strtolower;
use function ucfirst;

final class LanguageCommand extends BaseSubCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct("language", "Change the language of the plugin.");
	}

	protected function prepare() : void {
		$this->registerArgument(0, new LanguageArgument($this->plugin));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$language = null;
		$languages = $this->plugin->getTranslator()->getLanguages();
		if (isset($args["language"])) $language = $this->plugin->getTranslator()->getLanguage($args["language"]);
		if ($language === null) {
			$list = "";
			foreach ($languages as $lang) {
				$list .= "§e- §a" .$lang->getLocale() . " §f(" . ucfirst($lang->getName()) . ")\n";
			}

			$sender->sendMessage($this->plugin->getTranslator()->translate(
				$sender, "command.language.list", [
					"{%list}" => $list
				]
			));
			return;
		}

		if (!$this->plugin->getTranslator()->isLanguageRegistered($language->getLocale())) {
			$sender->sendMessage($this->plugin->getTranslator()->translate($sender, "command.language.notFound"));
			return;
		}

		$this->plugin->getTranslator()->setDefaultLanguage($language);
		$sender->sendMessage($this->plugin->getTranslator()->translate(
			$sender, "command.language.changed", [
				"{%language}" => $language->getName()
			]
		));
	}

	public function getParent() : BaseCommand {
		return $this->parent;
	}
}
