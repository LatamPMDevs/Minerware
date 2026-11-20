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

use LatamPMDevs\minerware\join\JoinStats;
use LatamPMDevs\minerware\Minerware;
use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;

/**
 * An NPC that joins whoever hits or clicks it.
 */
final class LobbyNpc extends Human {

	public const SAVE_ID = "minerware:lobby_npc";

	private const TAG_MAP = "Map"; //TAG_String, only when set

	private const REFRESH_INTERVAL = 20; //ticks

	private ?string $mapFilter = null;

	private int $ticksSinceRefresh = 0;

	protected function initEntity(CompoundTag $nbt) : void {
		parent::initEntity($nbt);
		$map = $nbt->getString(self::TAG_MAP, "");
		$this->mapFilter = $map === "" ? null : $map;
		$this->setNoClientPredictions();
		$this->setNameTagVisible();
		$this->setNameTagAlwaysVisible();
		$this->refreshNameTag();
	}

	public function saveNBT() : CompoundTag {
		$nbt = parent::saveNBT();
		if ($this->mapFilter !== null) {
			$nbt->setString(self::TAG_MAP, $this->mapFilter);
		}
		return $nbt;
	}

	public function getMapFilter() : ?string {
		return $this->mapFilter;
	}

	/**
	 * @return $this
	 */
	public function setMapFilter(?string $mapFilter) : self {
		$this->mapFilter = $mapFilter;
		$this->refreshNameTag();
		return $this;
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
		$this->ticksSinceRefresh += $tickDiff;
		if ($this->ticksSinceRefresh >= self::REFRESH_INTERVAL) {
			$this->ticksSinceRefresh = 0;
			$this->refreshNameTag();
		}
		return parent::entityBaseTick($tickDiff);
	}

	private function use(Player $player) : void {
		$plugin = Minerware::getInstance();
		if ($plugin->getJoinPointRemovals()->consume($player)) {
			$this->flagForDespawn();
			$player->sendMessage($plugin->getTranslator()->translate($player, "joinpoint.removed"));
			return;
		}
		$plugin->getJoiner()->join($player, $this->mapFilter);
	}

	private function refreshNameTag() : void {
		$translator = Minerware::getInstance()->getTranslator();
		$stats = JoinStats::forMap($this->mapFilter);
		$tag = $translator->translate(null, "joinpoint.npc.title") . "\n" .
			$translator->translate(null, "joinpoint.sign.playing", ["{%players}" => (string) $stats->players]) . "\n" .
			$translator->translate(null, $stats->joinable ? "joinpoint.npc.joinable" : "joinpoint.sign.full");
		if ($tag !== $this->getNameTag()) {
			$this->setNameTag($tag);
		}
	}
}
