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
 * Copyright 2021 © Minerware
 */

declare(strict_types=1);

namespace minerware\commands;

use minerware\arena\ArenaManager;
use minerware\arena\MapRegisterer;
use minerware\database\DataManager;
use minerware\language\Translator;
use minerware\Minerware;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as T;
use function implode;
use function in_array;
use function strtolower;
use function ucfirst;

final class MinerwareCommand extends Command {

	public function __construct(private Minerware $plugin) {
		parent::__construct("minerware", "Minerware main command.");
		$this->setPermission("minerware.command");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args): void {
		if (!$sender instanceof Player) {
			$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.error.gameOnly")));
			return;
		}

		if (!$this->testPermission($sender)) {
			return;
		}

		if (!isset($args[0])) {
			$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.error.notFound")));
			return;
		}

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

			case "setlanguage":
			case "setlang":
				if (!isset($args[1])) {
					$sender->sendMessage(T::RED . "Usage: /minerware setlang <language>");
					return;
				}

				$languages = [
					"English",
					"Spanish"
				];

				if ($args[1] == "list") {
					$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.arg.setlang.langList", $languages)));
					return;
				}

				if (!in_array(ucfirst(strtolower($args[1])), $languages, true)) {
					$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.arg.setlang.langNotFound", [$args[1]])));
					return;
				}

				Translator::getInstance()->changeLanguage($args[1]);
				$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.arg.setlang.changed", [strtolower($args[1])])));
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

			case "help":
				$sender->sendMessage("SOON");
			break;

			default:
				$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.error.notfound")));
			break;
		}
	}
}
