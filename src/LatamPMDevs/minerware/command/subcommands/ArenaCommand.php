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

namespace LatamPMDevs\minerware\command\subcommands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use LatamPMDevs\minerware\arena\Arena;
use LatamPMDevs\minerware\arena\ArenaManager;
use LatamPMDevs\minerware\command\args\ChoiceArgument;
use LatamPMDevs\minerware\form\ArenaAdminForm;
use LatamPMDevs\minerware\Minerware;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function count;
use function implode;

final class ArenaCommand extends BaseSubCommand {

	private const START = "start";
	private const ADMIN = "admin";
	private const LIST = "list";

	public function __construct(private Minerware $plugin) {
		parent::__construct("arena", "Manage the running arenas.");
		$this->setPermission("minerware.command.arena");
	}

	protected function prepare() : void {
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->registerArgument(0, new ChoiceArgument("action", self::START, self::ADMIN, self::LIST));
	}

	/**
	 * @param Player $sender
	 * @param array<string, mixed> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$translator = $this->plugin->getTranslator();
		switch ($args["action"] ?? null) {
			case self::START:
				$arena = ArenaManager::getInstance()->getArenaOf($sender);
				if ($arena === null) {
					$sender->sendMessage($translator->translate($sender, "command.notInArena"));
				} elseif (!$arena->forceStart()) {
					$sender->sendMessage($translator->translate($sender, "game.arena.needMorePlayers"));
				}
				break;

			case self::ADMIN:
				(new ArenaAdminForm())->sendList($sender);
				break;

			case self::LIST:
				$lines = [];
				foreach (ArenaManager::getInstance()->getArenas() as $arena) {
					$lines[] = $translator->translate($sender, "admin.arena", [
						"{%id}" => $arena->getId(),
						"{%map}" => $arena->getMap()->getName(),
						"{%status}" => $arena->getPhase()->getName(),
						"{%players}" => count($arena->getPlayers()),
						"{%max}" => Arena::MAX_PLAYERS
					]);
				}
				$sender->sendMessage(count($lines) === 0 ? $translator->translate($sender, "admin.noArenas") : implode("\n", $lines));
				break;

			default:
				$this->sendUsage();
				break;
		}
	}

	public function getParent() : BaseCommand {
		return $this->parent;
	}
}
