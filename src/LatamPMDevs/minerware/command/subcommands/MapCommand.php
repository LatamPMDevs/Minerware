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

use LatamPMDevs\minerware\libs\_189c4e77bf695c21\CortexPE\Commando\args\RawStringArgument;
use LatamPMDevs\minerware\libs\_189c4e77bf695c21\CortexPE\Commando\BaseCommand;
use LatamPMDevs\minerware\libs\_189c4e77bf695c21\CortexPE\Commando\BaseSubCommand;
use LatamPMDevs\minerware\libs\_189c4e77bf695c21\CortexPE\Commando\constraint\InGameRequiredConstraint;
use LatamPMDevs\minerware\command\args\ChoiceArgument;
use LatamPMDevs\minerware\map\MapEditor;
use LatamPMDevs\minerware\Minerware;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function is_string;

final class MapCommand extends BaseSubCommand {

	private const CREATE = "create";

	public function __construct(private Minerware $plugin) {
		parent::__construct("map", "Create the maps the arenas are played on.");
		$this->setPermission("minerware.command.map");
	}

	protected function prepare() : void {
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->registerArgument(0, new ChoiceArgument("action", self::CREATE));
		$this->registerArgument(1, new RawStringArgument("world", true));
	}

	/**
	 * @param Player $sender
	 * @param array<string, mixed> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		if (($args["action"] ?? null) !== self::CREATE) {
			$this->sendUsage();
			return;
		}
		$worldName = $args["world"] ?? null;
		$worldManager = $this->plugin->getServer()->getWorldManager();
		if (!is_string($worldName) || !$worldManager->loadWorld($worldName, true) || ($world = $worldManager->getWorldByName($worldName)) === null) {
			$sender->sendMessage($this->plugin->getTranslator()->translate($sender, "command.arguments.worldNotFound", [
				"{%world}" => is_string($worldName) ? $worldName : "unknown"
			]));
			return;
		}
		MapEditor::start($sender, $world);
	}

	public function getParent() : BaseCommand {
		return $this->parent;
	}
}