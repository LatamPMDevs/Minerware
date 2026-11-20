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

namespace LatamPMDevs\minerware\command\subcommands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use LatamPMDevs\minerware\command\args\LanguageArgument;
use LatamPMDevs\minerware\database\DataManager;
use LatamPMDevs\minerware\Minerware;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function is_string;
use function ucfirst;

final class LanguageCommand extends BaseSubCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct("language", "Choose the language you see.");
		$this->setPermission("minerware.command.language");
	}

	protected function prepare() : void {
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->registerArgument(0, new LanguageArgument($this->plugin));
	}

	/**
	 * @param Player $sender
	 * @param array<string, mixed> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$language = null;
		$languages = $this->plugin->getTranslator()->getLanguages();
		if (is_string($args["language"] ?? null)) {
			$language = $this->plugin->getTranslator()->getLanguage($args["language"]);
		}
		if ($language === null) {
			$list = "";
			foreach ($languages as $lang) {
				$list .= "§e- §a" . $lang->getLocale() . " §f(" . ucfirst($lang->getName()) . ")\n";
			}

			$sender->sendMessage($this->plugin->getTranslator()->translate(
				$sender, "command.language.list", [
					"{%list}" => $list
				]
			));
			return;
		}

		$translator = $this->plugin->getTranslator();
		$translator->setPlayerLanguage($sender, $language);
		DataManager::getInstance()->setPlayerLanguage($sender, $language->getLocale());
		$sender->sendMessage($translator->translate(
			$sender, "command.language.changed", [
				"{%language}" => $language->getName()
			]
		));
	}

	public function getParent() : BaseCommand {
		return $this->parent;
	}
}
