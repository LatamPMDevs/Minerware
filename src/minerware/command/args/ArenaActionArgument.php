<?php

declare(strict_types=1);

namespace minerware\command\args;

use CortexPE\Commando\args\StringEnumArgument;
use pocketmine\command\CommandSender;

final class ArenaActionArgument extends StringEnumArgument {

	public const CREATE_ARENA = "create";
	public const DELETE_ARENA = "delete";

	protected const VALUES = [
		"create" => self::CREATE_ARENA,
		"delete" => self::DELETE_ARENA
	];

	public function __construct(string $name) {
		parent::__construct($name, true);
	}

	public function parse(string $argument, CommandSender $sender): string {
		return $this->getValue($argument);
	}

	public function getTypeName(): string {
		return "string";
	}
}
