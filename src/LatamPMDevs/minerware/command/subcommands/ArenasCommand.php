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

use LatamPMDevs\minerware\libs\_f657fb06cbb97d69\CortexPE\Commando\BaseCommand;
use LatamPMDevs\minerware\libs\_f657fb06cbb97d69\CortexPE\Commando\BaseSubCommand;
use LatamPMDevs\minerware\libs\_f657fb06cbb97d69\CortexPE\Commando\constraint\InGameRequiredConstraint;
use LatamPMDevs\minerware\arena\ArenaManager;
use LatamPMDevs\minerware\arena\Status;
use LatamPMDevs\minerware\command\args\ArenaActionArgument;
use LatamPMDevs\minerware\command\args\WorldArgument;
use LatamPMDevs\minerware\map\MapRegisterer;
use LatamPMDevs\minerware\Minerware;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function count;

final class ArenasCommand extends BaseSubCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct("arenas", "Manage all the minigame arenas.");
		$this->setPermission("minerware.command.arenas");
	}

	protected function prepare() : void {
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->registerArgument(0, new ArenaActionArgument("action"));
		$this->registerArgument(1, new WorldArgument());
	}

	/**
	 * @param Player $sender
	 * @param array<string, mixed> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		if (!isset($args["action"])) {
			# TODO...
			return;
		}
		switch ($args["action"]) {
			case ArenaActionArgument::CREATE_ARENA:
				if (!isset($args["world"]) ||
					!$this->plugin->getServer()->getWorldManager()->loadWorld($args["world"], true) ||
					($world = $this->plugin->getServer()->getWorldManager()->getWorldByName($args["world"])) === null
				) {
					$sender->sendMessage($this->plugin->getTranslator()->translate(
						$sender, "command.arguments.worldNotFound", [
							"{%world}" => $args["world"] ?? "unknown"
						]
					));
					return;
				}

				MapRegisterer::createRegisterer($sender, $world);
			break;

			case ArenaActionArgument::START_ARENA:
				$arena = null;
				foreach (ArenaManager::getInstance()->getArenas() as $a) {
					if ($a->inGame($sender)) {
						$arena = $a;
						break;
					}
				}

				if ($arena === null) {
					$sender->sendMessage($this->plugin->getTranslator()->translate($sender, "command.notInArena"));
					return;
				}

				if ($arena->getStatus() !== Status::STARTING ||
					count($arena->getPlayers()) < $arena->getMinPlayers()
				) {
					$sender->sendMessage($this->plugin->getTranslator()->translate($sender, "game.arena.needMorePlayers"));
				}

				$arena->forceStart();
			break;
		}
	}

	public function getParent() : BaseCommand {
		return $this->parent;
	}
}