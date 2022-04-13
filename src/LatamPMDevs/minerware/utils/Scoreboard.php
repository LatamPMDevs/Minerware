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

namespace LatamPMDevs\minerware\utils;

use LatamPMDevs\minerware\Minerware;

use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;

final class Scoreboard {
	use SingletonTrait;

	private array $scoreboards = [];

	public function __construct(private Minerware $plugin) { }

	/* create scoreboards */
	public function new(Player $pl, string $objectiveName, string $displayName) : void {
		if (isset($this->scoreboards[$pl->getName()])) {
			$this->remove($pl);
		}
		/* get to packet scoreboard */
		/* and players objetiveName to scoreboard */
		$pk = SetDisplayObjectivePacket::create("sidebar", $objectiveName, $displayName, "dummy", 0);
		$pl->getNetworkSession()->sendDataPacket($pk);
		$this->scoreboards[$pl->getName()] = $objectiveName;
	}

	public function remove(Player|array $pls) : void {
		$pls = ($pls instanceof Player) ? [$pls] : $pls;
		foreach ($pls as $pl) {
			$objectiveName = $this->getObjectiveName($pl);
			if ($objectiveName !== null) {
				/* remove packet, scoreboard */
				$pk = RemoveObjectivePacket::create($objectiveName);
				$pl->getNetworkSession()->sendDataPacket($pk);
				unset($this->scoreboards[$pl->getName()]);
			}
		}
	}

	public function setLine(Player $pl, int $score, string $message) : void {
		if (!isset($this->scoreboards[$pl->getName()])) {
			$this->plugin->getLogger()->info("You not have set to scoreboards");
			return;
		}
		if ($score > 15 || $score < 1) {
			$this->plugin->getLogger()->info("Error, you exceeded the limit of parameters 1-15");
			return;
		}
		$objectiveName = $this->getObjectiveName($pl);
		if ($objectiveName !== null) {
			$entry = new ScorePacketEntry();
			$entry->objectiveName = $objectiveName;
			$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
			$entry->customName = $message;
			$entry->score = $score;
			$entry->scoreboardId = $score;
			$pk = SetScorePacket::create(SetScorePacket::TYPE_CHANGE, [$entry]);
			$pl->getNetworkSession()->sendDataPacket($pk);
		}
	}

	public function getObjectiveName(Player $pl) : ?string {
		return isset($this->scoreboards[$pl->getName()]) ? $this->scoreboards[$pl->getName()] : null;
	}
}