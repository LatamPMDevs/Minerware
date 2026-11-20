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

use pocketmine\entity\Human;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\StringMetadataProperty;
use pocketmine\player\Player;
use function spl_object_id;

/**
 * A human whose name tag can differ for each player who sees it, for example to show every player the text in their own language.
 */
abstract class PerPlayerTagHuman extends Human {

	/** @var array<int, string> player id => the tag set for that player */
	protected array $nameTagPerPlayer = [];

	/** @var array<int, string> player id => the tag last sent to that player */
	private array $sentNameTags = [];

	/**
	 * The tag a player should see.
	 */
	protected function resolveNameTag(Player $viewer) : string {
		return $this->nameTagPerPlayer[$viewer->getId()] ?? $this->nameTag;
	}

	/**
	 * @return $this
	 */
	public function setNameTagToPlayer(Player $player, string $nameTag) : self {
		$this->nameTagPerPlayer[$player->getId()] = $nameTag;
		$this->refreshNameTagOf($player);
		return $this;
	}

	public function getNameTagToPlayer(Player $player) : string {
		return $this->resolveNameTag($player);
	}

	/**
	 * Sends the tag to every player who sees this entity, if it changed for them.
	 */
	protected function refreshNameTags() : void {
		foreach ($this->getViewers() as $viewer) {
			$this->refreshNameTagOf($viewer);
		}
	}

	private function refreshNameTagOf(Player $viewer) : void {
		if (!isset($this->getViewers()[spl_object_id($viewer)])) {
			return;
		}
		if (($this->sentNameTags[$viewer->getId()] ?? null) === $this->resolveNameTag($viewer)) {
			return;
		}
		$this->sendData([$viewer], [EntityMetadataProperties::NAMETAG => new StringMetadataProperty($this->resolveNameTag($viewer))]);
	}

	public function sendData(?array $targets, ?array $data = null) : void {
		$targets ??= $this->getViewers();
		$data ??= $this->getAllNetworkData();
		if (!isset($data[EntityMetadataProperties::NAMETAG])) {
			parent::sendData($targets, $data);
			return;
		}
		foreach ($targets as $target) {
			$tag = $this->resolveNameTag($target);
			$this->sentNameTags[$target->getId()] = $tag;
			parent::sendData([$target], [EntityMetadataProperties::NAMETAG => new StringMetadataProperty($tag)] + $data);
		}
	}

	protected function sendSpawnPacket(Player $player) : void {
		parent::sendSpawnPacket($player);
		# The spawn packet has the default tag.
		unset($this->sentNameTags[$player->getId()]);
		$this->refreshNameTagOf($player);
	}

	public function despawnFrom(Player $player, bool $send = true) : void {
		unset($this->sentNameTags[$player->getId()]);
		parent::despawnFrom($player, $send);
	}
}
