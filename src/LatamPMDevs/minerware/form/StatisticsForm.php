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

namespace LatamPMDevs\minerware\form;

use jojoe77777\FormAPI\SimpleForm;
use LatamPMDevs\minerware\database\PlayerData;
use LatamPMDevs\minerware\Minerware;
use LatamPMDevs\minerware\utils\Utils;
use pocketmine\player\Player;

final class StatisticsForm {

	public function send(Player $player, PlayerData $playerdata) : void {
		$plugin = Minerware::getInstance();
		$translator = $plugin->getTranslator();
		$form = new SimpleForm(null);
		$form->setTitle($plugin->getTranslator()->translate($player, "text.statistics.user", ["{%user}" => $playerdata->getName()]));
		$form->setContent(
			"§a" . $translator->translate($player, "text.statistics.wins") . ": §6" . $playerdata->getWins() . "\n" .
			"§a" . $translator->translate($player, "text.statistics.bossGamesWon") . ": §6" . $playerdata->getBossgamesWon() . "\n" .
			"§a" . $translator->translate($player, "text.statistics.microgamesWon") . ": §6" . $playerdata->getMicrogamesWon() . "\n" .
			"§a" . $translator->translate($player, "text.statistics.gamesPlayed") . ": §6" . $playerdata->getGamesPlayed() . "\n" .
			"§a" . $translator->translate($player, "text.statistics.microgamesPlayed") . ": §6" . $playerdata->getMicrogamesPlayed() . "\n" .
			"§a" . $translator->translate($player, "text.statistics.timePlayed") . ": §6" . Utils::getTimeTranslated($playerdata->getTimePlayed(), $translator, $player)
		);
		$form->addButton($translator->translate($player, "text.close"));
		$form->sendToPlayer($player);
	}
}
