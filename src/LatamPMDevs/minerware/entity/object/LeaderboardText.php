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

namespace LatamPMDevs\minerware\entity\object;

use LatamPMDevs\minerware\Minerware;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use function count;
use function implode;

/**
 * The players with most wins, as text.
 */
final class LeaderboardText extends LeaderboardEntity {

	public const SAVE_ID = "minerware:leaderboard_text";

	protected function initEntity(CompoundTag $nbt) : void {
		parent::initEntity($nbt);
		# Saved ones may have an older skin.
		$this->setSkin(self::invisibleSkin());
	}

	protected function buildNameTag(?Player $viewer) : string {
		$translator = Minerware::getInstance()->getTranslator();
		$lines = [$translator->translate($viewer, "leaderboard.title")];
		$entries = Minerware::getInstance()->getLeaderboard()->getEntries();
		if (count($entries) === 0) {
			$lines[] = $translator->translate($viewer, "leaderboard.empty");
		}
		foreach ($entries as $index => $entry) {
			$lines[] = $translator->translate($viewer, "leaderboard.line", [
				"{%rank}" => $index + 1,
				"{%player}" => $entry->name,
				"{%wins}" => $entry->wins
			]);
		}
		return implode("\n", $lines);
	}
}
