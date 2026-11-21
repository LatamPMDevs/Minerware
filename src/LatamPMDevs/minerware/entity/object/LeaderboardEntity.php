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

use LatamPMDevs\minerware\database\PlayerData;
use LatamPMDevs\minerware\database\PlayerStorage;
use LatamPMDevs\minerware\leaderboard\Leaderboard;
use LatamPMDevs\minerware\Minerware;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use function str_repeat;

/**
 * A figure that shows the leaderboard and tells whoever hits or clicks it how many wins they have.
 */
abstract class LeaderboardEntity extends PerPlayerTagHuman {

	private const CHECK_INTERVAL = 20; //ticks

	private const INVISIBLE_GEOMETRY = '{"format_version":"1.12.0","minecraft:geometry":[{"description":{"identifier":"geometry.minerware_invisible","texture_width":64,"texture_height":64,"visible_bounds_width":2,"visible_bounds_height":1,"visible_bounds_offset":[0,1,0]},"bones":[]}]}';

	private int $ticksSinceCheck = 0;

	private int $seenVersion = -1;

	/**
	 * A skin with no body at all, its geometry has no bones, so only the name tag shows.
	 */
	public static function invisibleSkin() : Skin {
		return new Skin("minerware_invisible", str_repeat("\x00", 64 * 64 * 4), "", "geometry.minerware_invisible", self::INVISIBLE_GEOMETRY);
	}

	protected function initEntity(CompoundTag $nbt) : void {
		parent::initEntity($nbt);
		$this->setNoClientPredictions();
		$this->setNameTagVisible();
		$this->setNameTagAlwaysVisible();
	}

	abstract protected function buildNameTag(?Player $viewer) : string;

	/**
	 * Called when the leaderboard has new entries.
	 */
	protected function onLeaderboardChanged(Leaderboard $leaderboard) : void {
	}

	protected function resolveNameTag(Player $viewer) : string {
		return $this->buildNameTag($viewer);
	}

	public function attack(EntityDamageEvent $source) : void {
		$source->cancel();
		if ($source instanceof EntityDamageByEntityEvent && $source->getDamager() instanceof Player) {
			$this->use($source->getDamager());
		}
		parent::attack($source);
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool {
		$this->use($player);
		return true;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool {
		$this->ticksSinceCheck += $tickDiff;
		if ($this->ticksSinceCheck >= self::CHECK_INTERVAL) {
			$this->ticksSinceCheck = 0;
			$leaderboard = Minerware::getInstance()->getLeaderboard();
			if ($leaderboard->isLoaded() && $leaderboard->getVersion() !== $this->seenVersion) {
				$this->seenVersion = $leaderboard->getVersion();
				$this->onLeaderboardChanged($leaderboard);
				$this->refreshNameTag();
			}
		}
		return parent::entityBaseTick($tickDiff);
	}

	private function use(Player $player) : void {
		$plugin = Minerware::getInstance();
		$translator = $plugin->getTranslator();
		if ($plugin->getPendingRemovals()->consume($player)) {
			$this->flagForDespawn();
			$player->sendMessage($translator->translate($player, "npc.removed"));
			return;
		}
		PlayerStorage::getInstance()->getPlayerData($player, static function (?PlayerData $data) use ($player, $translator) : void {
			if ($player->isConnected()) {
				$player->sendMessage($translator->translate($player, "leaderboard.yourWins", ["{%wins}" => $data?->getWins() ?? 0]));
			}
		}, null, true);
	}

	private function refreshNameTag() : void {
		$tag = $this->buildNameTag(null);
		if ($tag !== $this->getNameTag()) {
			$this->setNameTag($tag);
		}
		$this->refreshNameTags();
	}
}
