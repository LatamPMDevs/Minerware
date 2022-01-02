<?php

declare(strict_types=1);

namespace minerware\command\subcommands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\BaseSubCommand;
use minerware\command\args\ArenaActionArgument;
use minerware\command\args\WorldArgument;
use minerware\command\constraints\ArgumentNotProvided;
use minerware\Minerware;
use pocketmine\command\CommandSender;
use function var_dump;

final class ArenasCommand extends BaseSubCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct("arenas", "Manage all the minigame arenas.");
	}

	protected function prepare(): void {
		$this->addConstraint(new ArgumentNotProvided($this, "world"));
		$this->registerArgument(0, new ArenaActionArgument("action"));
		$this->registerArgument(1, new WorldArgument($this->plugin));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		var_dump($args["world"] ?? "nothing");
	}

	public function getParent(): BaseCommand {
		return $this->parent;
	}
}
