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

namespace LatamPMDevs\minerware\command\subcommands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\BaseSubCommand;
use LatamPMDevs\minerware\database\DataManager;
use LatamPMDevs\minerware\database\PlayerData;
use LatamPMDevs\minerware\form\FormManager;
use LatamPMDevs\minerware\Minerware;
use LatamPMDevs\minerware\utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class StatisticsCommand extends BaseSubCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct("statistics", "View a player's statistics", ["stats"]);
		$this->setPermission("minerware.command.statistics");
	}

	protected function prepare() : void {
		$this->registerArgument(0, new RawStringArgument("user", true));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$translator = $this->plugin->getTranslator();
		if (!($isPlayer = $sender instanceof Player) && !isset($args["user"])) {
			$sender->sendMessage($translator->translate($sender, "command.statistics.noUserProvided"));
			return;
		}
		DataManager::getInstance()->getPlayerData($args["user"] ?? $sender->getName(), function (PlayerData $playerdata) use ($sender, $translator, $isPlayer) {
			if ($isPlayer) {
				FormManager::getInstance()->sendStatistics($sender, $playerdata);
			} else {
				$sender->sendMessage(
					"§b=== §e" . $translator->translate($sender, "text.statistics.user",
						["{%user}" => $playerdata->getName()]
					) . " §b===" . "\n" .
					"§a" . $translator->translate($sender, "text.statistics.wins") . ": §6" . $playerdata->getWins() . "\n" .
					"§a" . $translator->translate($sender, "text.statistics.bossGamesWon") . ": §6" . $playerdata->getBossgamesWon() . "\n" .
					"§a" . $translator->translate($sender, "text.statistics.microgamesWon") . ": §6" . $playerdata->getMicrogamesWon() . "\n" .
					"§a" . $translator->translate($sender, "text.statistics.gamesPlayed") . ": §6" . $playerdata->getGamesPlayed() . "\n" .
					"§a" . $translator->translate($sender, "text.statistics.microgamesPlayed") . ": §6" . $playerdata->getMicrogamesPlayed() . "\n" .
					"§a" . $translator->translate($sender, "text.statistics.timePlayed") . ": §6" . Utils::getTimeTranslated($playerdata->getTimePlayed(), $translator, $sender)
				);
			}
		}, null, true);
	}

	public function getParent() : BaseCommand {
		return $this->parent;
	}
}
