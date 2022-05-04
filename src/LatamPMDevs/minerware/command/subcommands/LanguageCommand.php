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

namespace LatamPMDevs\minerware\command\subcommands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\BaseSubCommand;
use LatamPMDevs\minerware\command\args\LanguageArgument;
use LatamPMDevs\minerware\Minerware;
use pocketmine\command\CommandSender;
use function ucfirst;

final class LanguageCommand extends BaseSubCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct("language", "Change the default language of the plugin.");
		$this->setPermission("minerware.command.language");
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
				$list .= "§e- §a" . $lang->getLocale() . " §f(" . ucfirst($lang->getName()) . ")\n";
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
