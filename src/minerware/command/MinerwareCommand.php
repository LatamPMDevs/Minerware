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
use minerware\arena\ArenaManager;
use minerware\arena\MapRegisterer;
use minerware\command\constraints\NoArgumentsConstraint;
use minerware\command\subcommands\ArenasCommand;
use minerware\command\subcommands\HelpCommand;
use minerware\command\subcommands\LanguageCommand;
use minerware\database\DataManager;
use minerware\language\Translator;
use minerware\Minerware;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as T;
use function implode;

final class MinerwareCommand extends BaseCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct($plugin, "minerware", "Minerware main command.");
		$this->setPermission("minerware.command");
		$this->setPermissionMessage(Translator::getInstance()->translate(new Translatable("command.noPermission")));
	}

	protected function prepare(): void {
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->addConstraint(new NoArgumentsConstraint($this));
		$this->registerSubCommand(new ArenasCommand($this->plugin));
		$this->registerSubcommand(new LanguageCommand($this->plugin));
		$this->registerSubcommand(new HelpCommand());
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		/** @var Player $sender */
		switch ($args[0]) {
			case "create":
				if (DataManager::getInstance()->getLobby() === null) {
					$sender->sendMessage(Translator::getInstance()->translate(new Translatable("error.lobby.isNotSet")));
					return;
				}
				if (!isset($args[1])) {
					$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.error.provideWorld")));
					return;
				}

				if (!$this->plugin->getServer()->getWorldManager()->loadWorld($args[1], true)) {
					$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.error.worldWasNotFound")));
					return;
				}

				$world = $this->plugin->getServer()->getWorldManager()->getWorldByName($args[1]);
				MapRegisterer::createRegisterer($sender, $world);
			break;

			case "setlobby":
				if (!isset($args[1])) {
					$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.error.provideWorld")));
					return;
				}

				if (!$this->plugin->getServer()->getWorldManager()->loadWorld($args[1], true)) {
					$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.error.worldWasNotFound")));
					return;
				}
				$world = $this->plugin->getServer()->getWorldManager()->getWorldByName($args[1])->getDisplayName();
				DataManager::getInstance()->setLobby($world);
				$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.setlobby")));
			break;

			case "join":
				ArenaManager::getInstance()->join($sender);
			break;

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

			default:
				$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.notFound")));
			break;
		}
	}
}
