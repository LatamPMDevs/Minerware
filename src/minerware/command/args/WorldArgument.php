<?php

namespace minerware\command\args;

use CortexPE\Commando\args\RawStringArgument;
use minerware\Minerware;
use pocketmine\command\CommandSender;
use pocketmine\world\World;

class WorldArgument extends RawStringArgument {

	public function __construct(private Minerware $plugin) {
		parent::__construct("world", true);
	}

	public function canParse(string $testString, CommandSender $sender): bool {
		return true;
	}

	public function parse(string $argument, CommandSender $sender): ?World {
		return $this->plugin->getServer()->getWorldManager()->getWorldByName($argument);
	}

	public function getTypeName(): string {
		return "string";
	}
}
