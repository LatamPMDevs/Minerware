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

use LatamPMDevs\minerware\leaderboard\Leaderboard;
use LatamPMDevs\minerware\Minerware;
use pocketmine\entity\Skin;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\Server;
use Ramsey\Uuid\Uuid;

/**
 * Wears the skin of the player at a rank of the leaderboard.
 */
final class TopPlayerNpc extends LeaderboardEntity {

	public const SAVE_ID = "minerware:top_player_npc";

	public const RANKS = 3;

	private const TAG_RANK = "Rank"; //TAG_Int
	private const TAG_HOLDER = "Holder"; //TAG_String, UUID of the player the skin belongs to, only when set

	private int $rank = 1;

	private ?string $holder = null;

	protected function initEntity(CompoundTag $nbt) : void {
		parent::initEntity($nbt);
		$this->rank = $nbt->getInt(self::TAG_RANK, 1);
		$holder = $nbt->getString(self::TAG_HOLDER, "");
		$this->holder = $holder === "" ? null : $holder;
	}

	public function saveNBT() : CompoundTag {
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_RANK, $this->rank);
		if ($this->holder !== null) {
			$nbt->setString(self::TAG_HOLDER, $this->holder);
		}
		return $nbt;
	}

	public function getRank() : int {
		return $this->rank;
	}

	/**
	 * @return $this
	 */
	public function setRank(int $rank) : self {
		$this->rank = $rank;
		return $this;
	}

	/**
	 * UUID of the player whose skin it wears.
	 */
	public function getHolder() : ?string {
		return $this->holder;
	}

	public function applySkin(Skin $skin) : void {
		$this->setSkin($skin);
		$this->sendSkin();
	}

	protected function onLeaderboardChanged(Leaderboard $leaderboard) : void {
		$entry = $leaderboard->getEntry($this->rank);
		$holder = $entry?->uuid;
		if ($holder === $this->holder) {
			return;
		}
		$this->holder = $holder;
		# Without them online it has no skin to copy: it gets theirs when they join.
		$player = $holder === null ? null : Server::getInstance()->getPlayerByUUID(Uuid::fromString($holder));
		$this->applySkin($player?->getSkin() ?? self::invisibleSkin());
	}

	protected function buildNameTag(?Player $viewer) : string {
		$translator = Minerware::getInstance()->getTranslator();
		$title = $translator->translate($viewer, "leaderboard.npc.title", ["{%rank}" => $this->rank]);
		$entry = Minerware::getInstance()->getLeaderboard()->getEntry($this->rank);
		if ($entry === null) {
			return $title . "\n" . $translator->translate($viewer, "leaderboard.empty");
		}
		return $title . "\n" .
			$translator->translate($viewer, "leaderboard.npc.player", ["{%player}" => $entry->name]) . "\n" .
			$translator->translate($viewer, "leaderboard.npc.wins", ["{%wins}" => $entry->wins]);
	}
}
