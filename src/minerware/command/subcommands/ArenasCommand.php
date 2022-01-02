<?php

declare(strict_types=1);

namespace minerware\command\subcommands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\BaseSubCommand;
use minerware\arena\MapRegisterer;
use minerware\command\args\ArenaActionArgument;
use minerware\command\args\WorldArgument;
use minerware\command\constraints\ArgumentNotProvided;
use minerware\database\DataManager;
use minerware\language\Translator;
use minerware\Minerware;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\player\Player;
use pocketmine\world\World;

final class ArenasCommand extends BaseSubCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct("arenas", "Manage all the minigame arenas.");
	}

	protected function prepare(): void {
		$this->addConstraint(new ArgumentNotProvided($this, "world"));
		$this->registerArgument(0, new ArenaActionArgument("action"));
		$this->registerArgument(1, new WorldArgument($this->plugin));
	}

	/**
	 * @param Player $sender
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		$world = $args["world"]	?? null;
		switch ($args["action"]) {
			case ArenaActionArgument::CREATE_ARENA:
				if (DataManager::getInstance()->getLobby() === null) {
					$sender->sendMessage(Translator::getInstance()->translate(new Translatable("database.lobby.notSet")));
					return;
				}

				if (!$world instanceof World || !$world->isLoaded()) {
					$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.arguments.worldNotFound", [$args["world"]])));
					return;
				}

				MapRegisterer::createRegisterer($sender, $world);
			break;
		}
	}

	public function getParent(): BaseCommand {
		return $this->parent;
	}
}
