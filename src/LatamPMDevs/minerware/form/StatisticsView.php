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

namespace LatamPMDevs\minerware\form;

use LatamPMDevs\minerware\libs\_5626dbf575c7ee5b\jojoe77777\FormAPI\SimpleForm;
use LatamPMDevs\minerware\database\PlayerData;
use LatamPMDevs\minerware\Minerware;
use LatamPMDevs\minerware\utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function implode;

/**
 * Shows a player's statistics: as a form to a player, as text to the console.
 */
final class StatisticsView {

	public function show(CommandSender $to, PlayerData $playerdata) : void {
		$translator = Minerware::getInstance()->getTranslator();
		$title = $translator->translate($to, "text.statistics.user", ["{%user}" => $playerdata->getName()]);
		$lines = [
			"§a" . $translator->translate($to, "text.statistics.wins") . ": §6" . $playerdata->getWins(),
			"§a" . $translator->translate($to, "text.statistics.bossGamesWon") . ": §6" . $playerdata->getBossgamesWon(),
			"§a" . $translator->translate($to, "text.statistics.microgamesWon") . ": §6" . $playerdata->getMicrogamesWon(),
			"§a" . $translator->translate($to, "text.statistics.gamesPlayed") . ": §6" . $playerdata->getGamesPlayed(),
			"§a" . $translator->translate($to, "text.statistics.microgamesPlayed") . ": §6" . $playerdata->getMicrogamesPlayed(),
			"§a" . $translator->translate($to, "text.statistics.timePlayed") . ": §6" . Utils::getTimeTranslated($playerdata->getTimePlayed(), $translator, $to)
		];
		if (!$to instanceof Player) {
			$to->sendMessage("§b=== §e" . $title . " §b===\n" . implode("\n", $lines));
			return;
		}
		$form = new SimpleForm(null);
		$form->setTitle($title);
		$form->setContent(implode("\n", $lines));
		$form->addButton($translator->translate($to, "text.close"));
		$form->sendToPlayer($to);
	}
}