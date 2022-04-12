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
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use LatamPMDevs\minerware\command\constraints\NoArgumentsConstraint;
use LatamPMDevs\minerware\command\subcommands\ArenasCommand;
use LatamPMDevs\minerware\command\subcommands\HelpCommand;
use LatamPMDevs\minerware\command\subcommands\JoinCommand;
use LatamPMDevs\minerware\command\subcommands\LanguageCommand;
use LatamPMDevs\minerware\command\subcommands\SetLobbyCommand;
use LatamPMDevs\minerware\Minerware;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as T;
use function implode;

final class MinerwareCommand extends BaseCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct($plugin, "minerware", "Minerware main command.");
		$this->setPermission("minerware.command");
		$this->setPermissionMessage($plugin->getTranslator()->translate(null, "command.noPermission"));
	}

	protected function prepare() : void {
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->addConstraint(new NoArgumentsConstraint($this));
		$this->registerSubCommand(new ArenasCommand($this->plugin));
		$this->registerSubcommand(new HelpCommand());
		$this->registerSubcommand(new JoinCommand());
		$this->registerSubcommand(new LanguageCommand($this->plugin));
		$this->registerSubCommand(new SetLobbyCommand($this->plugin));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		switch ($args[0]) {
			case "credits":
				$credits = [
					T::GRAY . "---- {$this->plugin->getPrefix()}" . T::AQUA . "Credits " . T::GRAY . "----",
					"\n",
					T::YELLOW . "Authors: " . T::GRAY . implode(", ", $this->plugin->getDescription()->getAuthors()),
					T::YELLOW . "Status: " . T::GRAY . "Private"
				];

				foreach ($credits as $str) {
					$sender->sendMessage($str);
				}
			break;
		}
	}
}
