<?php

namespace minerware\command\constraints;

use CortexPE\Commando\constraint\BaseConstraint;
use minerware\language\Translator;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\player\Player;

final class NoArgumentsConstraint extends BaseConstraint {

	public function test(CommandSender $sender, string $aliasUsed, array $args): bool {
		return count($args) !== 0;
	}

	public function onFailure(CommandSender $sender, string $aliasUsed, array $args): void {
		$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.error.notFound")));
	}

	public function isVisibleTo(CommandSender $sender): bool {
		return $sender instanceof Player;
	}
}
