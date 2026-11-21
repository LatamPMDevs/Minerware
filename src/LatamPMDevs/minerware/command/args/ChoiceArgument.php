<?php

/*
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
 * @author LatamPMDevs
 */

declare(strict_types=1);

namespace LatamPMDevs\minerware\command\args;

use LatamPMDevs\minerware\libs\_189c4e77bf695c21\CortexPE\Commando\args\StringEnumArgument;
use pocketmine\command\CommandSender;
use function array_values;
use function strtolower;

/**
 * One of a fixed list of words, such as the action of a command.
 */
final class ChoiceArgument extends StringEnumArgument {

	/** @var list<string> */
	private array $choices;

	public function __construct(string $name, string ...$choices) {
		$this->choices = array_values($choices);
		parent::__construct($name, true);
	}

	/**
	 * @return list<string>
	 */
	public function getEnumValues() : array {
		return $this->choices;
	}

	public function getValue(string $string) : string {
		return strtolower($string);
	}

	public function parse(string $argument, CommandSender $sender) : string {
		return $this->getValue($argument);
	}

	public function getTypeName() : string {
		return "string";
	}
}