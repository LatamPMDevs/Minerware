<?php

/**
 *  ███╗   ███╗██╗███╗   ██╗███████╗██████╗ ██╗    ██╗ █████╗ ██████╗ ███████╗
 *  ████╗ ████║██║████╗  ██║██╔════╝██╔══██╗██║    ██║██╔══██╗██╔══██╗██╔════╝
 *  ██╔████╔██║██║██╔██╗ ██║█████╗  ██████╔╝██║ █╗ ██║███████║██████╔╝█████╗
 *  ██║╚██╔╝██║██║██║╚██╗██║██╔══╝  ██╔══██╗██║███╗██║██╔══██║██╔══██╗██╔══╝
 *  ██║ ╚═╝ ██║██║██║ ╚████║███████╗██║  ██║╚███╔███╔╝██║  ██║██║  ██║███████╗
 *  ╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝ ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝
 *
 * A game written in PHP for PocketMine-MP software.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Copyright 2022 © LatamPMDevs
 */

declare(strict_types=1);

namespace LatamPMDevs\minerware\command\constraints;

use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\constraint\BaseConstraint;
use CortexPE\Commando\IRunnable;
use LatamPMDevs\minerware\Minerware;
use pocketmine\command\CommandSender;
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
		$sender->sendMessage(Minerware::getInstance()->getTranslator()->translate(
			$sender, "command.usage", [
				"{%usage}" => $context->getName() . " " . $context->getUsageMessage()
			]
		));
	}

	public function isVisibleTo(CommandSender $sender) : bool {
		return $sender instanceof Player;
	}
}
