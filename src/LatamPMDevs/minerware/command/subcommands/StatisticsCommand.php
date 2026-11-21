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

use LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\CortexPE\Commando\args\RawStringArgument;
use LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\CortexPE\Commando\BaseCommand;
use LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\CortexPE\Commando\BaseSubCommand;
use LatamPMDevs\minerware\database\PlayerData;
use LatamPMDevs\minerware\database\PlayerStorage;
use LatamPMDevs\minerware\form\StatisticsView;
use LatamPMDevs\minerware\Minerware;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\poggit\libasynql\SqlError;

use function is_string;
use function strcasecmp;

final class StatisticsCommand extends BaseSubCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct("statistics", "View a player's statistics", ["stats"]);
		$this->setPermission("minerware.command.statistics");
	}

	protected function prepare() : void {
		$this->registerArgument(0, new RawStringArgument("user", true));
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$translator = $this->plugin->getTranslator();
		if (!$sender instanceof Player && !is_string($args["user"] ?? null)) {
			$sender->sendMessage($translator->translate($sender, "command.statistics.noUserProvided"));
			return;
		}
		$user = is_string($args["user"] ?? null) ? $args["user"] : $sender->getName();
		$onSuccess = function (?PlayerData $playerdata) use ($sender, $translator, $user) : void {
			if ($playerdata === null) {
				$sender->sendMessage($translator->translate($sender, "command.statistics.notFound", ["{%user}" => $user]));
				return;
			}
			if ($sender instanceof Player && !$sender->isConnected()) {
				return;
			}
			(new StatisticsView())->show($sender, $playerdata);
		};
		$onError = function (SqlError $error) use ($sender, $translator) : void {
			PlayerStorage::getInstance()->onError->__invoke($error);
			$sender->sendMessage($translator->translate($sender, "command.statistics.error"));
		};
		# Someone who never played has no record; only show zeros for yourself.
		if ($sender instanceof Player && strcasecmp($sender->getName(), $user) === 0) {
			PlayerStorage::getInstance()->getPlayerData($sender, $onSuccess, $onError, true);
		} else {
			PlayerStorage::getInstance()->getPlayerDataByName($user, $onSuccess, $onError);
		}
	}

	public function getParent() : BaseCommand {
		return $this->parent;
	}
}