<?php

/**
 *  ███╗   ███╗██╗███╗   ██╗███████╗██████╗ ██╗    ██╗ █████╗ ██████╗ ███████╗
 *  ████╗ ████║██║████╗  ██║██╔════╝██╔══██╗██║    ██║██╔══██╗██╔══██╗██╔════╝
 *  ██╔████╔██║██║██╔██╗ ██║█████╗  ██████╔╝██║ █╗ ██║███████║██████╔╝█████╗
 *  ██║╚██╔╝██║██║██║╚██╗██║██╔══╝  ██╔══██╗██║███╗██║██╔══██║██╔══██╗██╔══╝
 *  ██║ ╚═╝ ██║██║██║ ╚████║███████╗██║  ██║╚███╔███╔╝██║  ██║██║  ██║███████╗
 *  ╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝ ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝
 *
 * This is a private project, your not allow to redistribute nor resell it.
 * The only ones with that power are this project's contributors.
 *
 * Copyright 2022 © Minerware
 */

declare(strict_types=1);

namespace minerware\command;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use minerware\command\constraints\NoArgumentsConstraint;
use minerware\command\subcommands\ArenasCommand;
use minerware\command\subcommands\HelpCommand;
use minerware\command\subcommands\JoinCommand;
use minerware\command\subcommands\LanguageCommand;
use minerware\command\subcommands\SetLobbyCommand;
use minerware\Minerware;
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
