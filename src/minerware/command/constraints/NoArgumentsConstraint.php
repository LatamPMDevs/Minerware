<?php

declare(strict_types=1);

namespace minerware\command\constraints;

use CortexPE\Commando\constraint\BaseConstraint;
use minerware\language\Translator;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\player\Player;
use function count;

final class NoArgumentsConstraint extends BaseConstraint {

	public function test(CommandSender $sender, string $aliasUsed, array $args): bool {
		return count($args) !== 0;
	}

	public function onFailure(CommandSender $sender, string $aliasUsed, array $args): void {
		$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.notFound")));
	}

	public function isVisibleTo(CommandSender $sender): bool {
		return $sender instanceof Player;
	}
}
