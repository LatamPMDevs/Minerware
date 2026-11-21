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

use LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\CortexPE\Commando\BaseCommand;
use LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\CortexPE\Commando\BaseSubCommand;
use LatamPMDevs\minerware\Minerware;
use pocketmine\command\CommandSender;
use function spl_object_id;

final class HelpCommand extends BaseSubCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct("help", "Show the list of available commands.");
		$this->setPermission("minerware.command.help");
	}

	protected function prepare() : void {
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$translator = $this->plugin->getTranslator();
		$sender->sendMessage($translator->translate($sender, "command.help.header"));
		$shown = [];
		foreach ($this->parent->getSubCommands() as $subCommand) {
			if (isset($shown[spl_object_id($subCommand)]) || !$subCommand->testPermissionSilent($sender)) {
				continue;
			}
			$shown[spl_object_id($subCommand)] = true;
			$sender->sendMessage($translator->translate($sender, "command.help.line", [
				"{%name}" => $subCommand->getName(),
				"{%description}" => $subCommand->getDescription()
			]));
		}
	}

	public function getParent() : BaseCommand {
		return $this->parent;
	}
}