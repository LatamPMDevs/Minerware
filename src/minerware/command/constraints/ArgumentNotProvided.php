<?php

declare(strict_types=1);

namespace minerware\command\constraints;

use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\constraint\BaseConstraint;
use CortexPE\Commando\IRunnable;
use minerware\language\Translator;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\player\Player;
use function array_key_exists;

final class ArgumentNotProvided extends BaseConstraint {

	/** @var string[] */
	private array $argumentName;

	public function __construct(IRunnable $context, string ...$argumentName) {
		parent::__construct($context);
		$this->argumentName = $argumentName;
	}

	public function test(CommandSender $sender, string $aliasUsed, array $args) : bool {
		foreach ($this->argumentName as $argumentName) {
			if (!array_key_exists($argumentName, $args)) {
				return false;
			}
		}

		return true;
	}

	public function onFailure(CommandSender $sender, string $aliasUsed, array $args) : void {
		/** @var BaseSubCommand $context */
		$context = $this->context;
		$sender->sendMessage(Translator::getInstance()->translate(new Translatable("command.usage", [$context->getParent()->getName() . " " . $context->getUsageMessage()])));
	}

	public function isVisibleTo(CommandSender $sender) : bool {
		return $sender instanceof Player;
	}
}
