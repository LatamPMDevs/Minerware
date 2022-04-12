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
use LatamPMDevs\minerware\command\args\WorldArgument;
use LatamPMDevs\minerware\database\DataManager;
use LatamPMDevs\minerware\Minerware;
use pocketmine\command\CommandSender;

final class SetLobbyCommand extends BaseSubCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct("setlobby", "Set the game waiting lobby.");
	}

	protected function prepare() : void {
		$this->registerArgument(0, new WorldArgument($this->plugin));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		if ($this->plugin->getServer()->getWorldManager()->loadWorld($args["world"], true) ||
			($world = $this->plugin->getServer()->getWorldManager()->getWorldByName($args["world"]))) {
			$sender->sendMessage($this->plugin->getTranslator()->translate(
				$sender, "command.arguments.worldNotFound", [
					"{%world}" => $args["world"]
				]
			));
			return;
		}

		DataManager::getInstance()->setLobby($world);
		$sender->sendMessage($this->plugin->getTranslator()->translate($sender, "command.setLobby.success"));
	}

	public function getParent() : BaseCommand {
		return $this->parent;
	}
}
