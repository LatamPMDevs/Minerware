<?php

declare(strict_types=1);

namespace minerware\command\subcommands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\BaseSubCommand;
use minerware\command\args\WorldArgument;
use minerware\database\DataManager;
use minerware\language\Translator;
use minerware\Minerware;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\world\World;

final class SetLobbyCommand extends BaseSubCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct("setlobby", "Set the game waiting lobby.");
	}

	protected function prepare() : void {
		$this->registerArgument(0, new WorldArgument($this->plugin));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$world = $args["world"]	?? null;
		if (!$world instanceof World || !$world->isLoaded()) {
			$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.arguments.worldNotFound", [$args["world"]])));
			return;
		}

		DataManager::getInstance()->setLobby($world);
		$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.setLobby.success")));
	}

	public function getParent() : BaseCommand {
		return $this->parent;
	}
}
