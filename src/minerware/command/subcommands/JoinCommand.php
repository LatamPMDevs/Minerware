<?php

declare(strict_types=1);

namespace minerware\command\subcommands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\BaseSubCommand;
use minerware\arena\ArenaManager;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class JoinCommand extends BaseSubCommand {

	public function __construct() {
		parent::__construct("join", "Join a random arena. (Degub purposes)");
	}

	/**
	 * @deprecated
	 */
	protected function prepare() : void { }

	/**
	 * @param Player $sender
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		ArenaManager::getInstance()->join($sender);
	}

	public function getParent() : BaseCommand {
		return $this->parent;
	}
}
