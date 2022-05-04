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
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use LatamPMDevs\minerware\arena\MapRegisterer;
use LatamPMDevs\minerware\command\args\ArenaActionArgument;
use LatamPMDevs\minerware\command\args\WorldArgument;
use LatamPMDevs\minerware\command\constraints\ArgumentNotProvided;
use LatamPMDevs\minerware\Minerware;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class ArenasCommand extends BaseSubCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct("arenas", "Manage all the minigame arenas.");
		$this->setPermission("minerware.command.arenas");
	}

	protected function prepare() : void {
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->addConstraint(new ArgumentNotProvided($this, "world"));
		$this->registerArgument(0, new ArenaActionArgument("action"));
		$this->registerArgument(1, new WorldArgument());
	}

	/**
	 * @param Player $sender
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		if (!isset($args["action"])) {
			# TODO...
			return;
		}
		switch ($args["action"]) {
			case ArenaActionArgument::CREATE_ARENA:
				if (!$this->plugin->getServer()->getWorldManager()->loadWorld($args["world"], true) ||
				($world = $this->plugin->getServer()->getWorldManager()->getWorldByName($args["world"])) === null) {
					$sender->sendMessage($this->plugin->getTranslator()->translate(
						$sender, "command.arguments.worldNotFound", [
							"{%world}" => $args["world"]
						]
					));
					return;
				}

				MapRegisterer::createRegisterer($sender, $world);
			break;
		}
	}

	public function getParent() : BaseCommand {
		return $this->parent;
	}
}
