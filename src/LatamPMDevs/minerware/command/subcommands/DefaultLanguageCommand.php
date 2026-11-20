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
use LatamPMDevs\minerware\command\args\LanguageArgument;
use LatamPMDevs\minerware\Minerware;
use pocketmine\command\CommandSender;
use function is_string;

final class DefaultLanguageCommand extends BaseSubCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct("defaultlanguage", "Change the language of the console and of players who have none.");
		$this->setPermission("minerware.command.defaultlanguage");
	}

	protected function prepare() : void {
		$this->registerArgument(0, new LanguageArgument($this->plugin));
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$translator = $this->plugin->getTranslator();
		$language = is_string($args["language"] ?? null) ? $translator->getLanguage($args["language"]) : null;
		if ($language === null) {
			$sender->sendMessage($translator->translate($sender, "command.language.notFound"));
			return;
		}
		$translator->setDefaultLanguage($language);
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
