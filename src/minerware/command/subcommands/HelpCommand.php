<?php

declare(strict_types=1);

namespace minerware\command\subcommands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\BaseSubCommand;
use pocketmine\command\CommandSender;

final class HelpCommand extends BaseSubCommand {

	public function __construct() {
		parent::__construct("help", "Show the list of commands available.");
	}

	protected function prepare() : void {
		$this->registerArgument(0, new RawStringArgument("category", true));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$category = $args["category"] ?? null;
		if ($category === null) {
			$category = "";
		}

		$sender->sendMessage("SOON. Category selected: " . $category);
	}

	public function getParent() : BaseCommand {
		return $this->parent;
	}
}
