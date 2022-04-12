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

namespace LatamPMDevs\minerware\command;

use CortexPE\Commando\BaseCommand;
use LatamPMDevs\minerware\command\subcommands\ArenasCommand;
use LatamPMDevs\minerware\command\subcommands\CreditsCommand;
use LatamPMDevs\minerware\command\subcommands\HelpCommand;
use LatamPMDevs\minerware\command\subcommands\JoinCommand;
use LatamPMDevs\minerware\command\subcommands\LanguageCommand;
use LatamPMDevs\minerware\command\subcommands\SetLobbyCommand;
use LatamPMDevs\minerware\Minerware;
use pocketmine\command\CommandSender;

final class MinerwareCommand extends BaseCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct($plugin, "minerware", "Minerware main command.");
		$this->setPermission("minerware.command");
		$this->setPermissionMessage($plugin->getTranslator()->translate(null, "command.noPermission"));
	}

	protected function prepare() : void {
		$this->registerSubCommand(new ArenasCommand($this->plugin));
		$this->registerSubCommand(new CreditsCommand($this->plugin));
		$this->registerSubcommand(new HelpCommand());
		$this->registerSubcommand(new JoinCommand());
		$this->registerSubcommand(new LanguageCommand($this->plugin));
		$this->registerSubCommand(new SetLobbyCommand($this->plugin));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$sender->sendMessage($this->plugin->getTranslator()->translate($sender, "command.notFound"));
	}
}
