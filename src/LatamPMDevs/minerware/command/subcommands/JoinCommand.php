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

use LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\CortexPE\Commando\args\RawStringArgument;
use LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\CortexPE\Commando\BaseCommand;
use LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\CortexPE\Commando\BaseSubCommand;
use LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\CortexPE\Commando\constraint\InGameRequiredConstraint;
use LatamPMDevs\minerware\Minerware;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function is_string;

final class JoinCommand extends BaseSubCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct("join", "Join an arena, on the map you choose if you name one.");
		$this->setPermission("minerware.command.join");
	}

	protected function prepare() : void {
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->registerArgument(0, new RawStringArgument("map", true));
	}

	/**
	 * @param Player $sender
	 * @param array<string, mixed> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$this->plugin->getJoiner()->join($sender, is_string($args["map"] ?? null) ? $args["map"] : null);
	}

	public function getParent() : BaseCommand {
		return $this->parent;
	}
}