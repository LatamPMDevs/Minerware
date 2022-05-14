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

namespace LatamPMDevs\minerware\arena;

use LatamPMDevs\minerware\database\DataHolder;
use LatamPMDevs\minerware\database\DataManager;
use LatamPMDevs\minerware\Minerware;
use LatamPMDevs\minerware\utils\Utils;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\HandlerListManager;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\item\ItemFactory;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;
use function explode;
use function strtolower;

final class MapRegisterer implements Listener {

	/** @var array<string, self> */
	private static array $mapRegisterer;

	public static function createRegisterer(Player $player, World $world) : self {
		return (self::$mapRegisterer[strtolower($player->getName())] = new self($player, $world));
	}

	private Minerware $plugin;

	/** @var array<string, mixed> */
	private array $data = [];

	/** @var array<string, mixed> */
	private array $tempData = [];

	private function __construct(private Player $player, private World $world) {
		$this->plugin = Minerware::getInstance();
		$this->data["name"] = $world->getDisplayName();
		$this->setConfiguratorMode($player);
		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
	}

	private function setConfiguratorMode(Player $player) : void {
		$this->world->loadChunk($this->world->getSafeSpawn()->getFloorX(), $this->world->getSafeSpawn()->getFloorZ());
		$player->teleport($this->world->getSafeSpawn(), 0, 0);
		$player->sendMessage($this->plugin->getTranslator()->translate($player, "configurator.mode.enter"));
	}

	private function setPlatform(Vector3 $firstPoint, Vector3 $secondPoint) : void {
		$this->data["platform"]["pos1"] = [
			"X" => $firstPoint->getX(),
			"Y" => $firstPoint->getY(),
			"Z" => $firstPoint->getZ()];
		$this->data["platform"]["pos2"] = [
			"X" => $secondPoint->getX(),
			"Y" => $secondPoint->getY(),
			"Z" => $secondPoint->getZ()];
	}

	private function setCages(Vector3 $winners, Vector3 $losers) : void {
		$this->data["cages"]["winners"] = [
			"X" => $winners->getX(),
			"Y" => $winners->getY(),
			"Z" => $winners->getZ()];
		$this->data["cages"]["losers"] = [
			"X" => $losers->getX(),
			"Y" => $losers->getY(),
			"Z" => $losers->getZ()];
	}

	private function setSpawn(Vector3 $spawn) : void {
		$this->data["spawns"][] = [
			"X" => $spawn->getX(),
			"Y" => $spawn->getY(),
			"Z" => $spawn->getZ()];
	}

	private function save() : void {
		DataManager::getInstance()->saveMapData(new DataHolder($this->data));
		unset(self::$mapRegisterer[strtolower($this->player->getName())]);
	}

	public function chatCommand(PlayerChatEvent $event) : void {
		$player = $event->getPlayer();
		$args = explode(" ", $event->getMessage());
		if (strtolower($player->getName()) == strtolower($this->player->getName())) {
			switch (strtolower($args[0])) {
				case "setplatform":
					$player->getInventory()->addItem(ItemFactory::getInstance()->get(369, 0, 1)->setCustomName("§r§aSet platform\n§7Break a corner."));
					$player->sendMessage($this->plugin->getTranslator()->translate($player, "configurator.mode.setplatform"));
				break;

				case "setcages":
					$player->getInventory()->addItem(ItemFactory::getInstance()->get(369, 0, 1)->setCustomName("§r§aSet cages\n§7Break a block."));
					$player->sendMessage($this->plugin->getTranslator()->translate($player, "configurator.mode.setcage.winners"));

				break;

				case "setspawn":
					$this->setSpawn($player->getPosition());
					$player->sendMessage($this->plugin->getTranslator()->translate($player, "configurator.mode.setspawn.successfully"));
				break;

				case "help":
					$player->sendMessage(
						"§6Minerware §bConfiguration Commands" . "\n" . "\n" .
						"§ahelp: §7Help command." . "\n" .
						"§asetplatform: §7Register the platform." . "\n" .
						"§asetcages: §7Register the winners|losser cage." . "\n" .
						"§asetspawn: §7Register a spawn." . "\n" .
						"§adone: §7Finish configurator mode"
					);
				break;

				case "done":
					$this->save();
					$player->sendMessage($this->plugin->getTranslator()->translate($player, "configurator.mode.registered.successfully"));
					$player->teleport($this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
					# Save World
					$folderName = $this->world->getFolderName();
					$this->plugin->getServer()->getWorldManager()->unloadWorld($this->world);
					Utils::setZip(
						$this->plugin->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . $folderName,
						$this->plugin->getDataFolder() . "database" . DIRECTORY_SEPARATOR . "backups" . DIRECTORY_SEPARATOR . $this->data["name"] . ".zip"
					);
					# Unregiter Listener
					HandlerListManager::global()->unregisterAll($this);
				break;

				default:
					$player->sendMessage($this->plugin->getTranslator()->translate($player, "configurator.mode.error.notFound"));
				break;
			}
			$event->cancel();
		}
	}

	public function onBreak(BlockBreakEvent $event) : void {
		$player = $event->getPlayer();
		$block = $event->getBlock();
		if (strtolower($player->getName()) == strtolower($this->player->getName())) {
			$item = $player->getInventory()->getItemInHand();
			$itemId = $item->getId();
			$itemName = $item->getCustomName();
			if ($itemId == 369 && $itemName === "§r§aSet platform\n§7Break a corner.") {
				if (!isset($this->tempData[strtolower($player->getName())]["platform"]["pos1"])) {
					$this->tempData[strtolower($player->getName())]["platform"]["pos1"] = $block->getPosition()->asVector3();
				} else {
					$pos1 = $this->tempData[strtolower($player->getName())]["platform"]["pos1"];
					$pos2 = $block->getPosition()->asVector3();
					$size = Utils::calculateSize($pos1, $pos2);
					if ($size !== "24x24") {
						$player->sendMessage($this->plugin->getTranslator()->translate(
							$player, "configurator.mode.error.invalidSize", [
								"{%good_size}" => "24x24",
								"{%bad_size}" => $size
							]
						));
					} else {
						$this->setPlatform($pos1, $pos2);
						$player->sendMessage($this->plugin->getTranslator()->translate($player, "configurator.mode.setplatform.successfully"));
					}
					$player->getInventory()->removeItem($item);
					unset($this->tempData[strtolower($player->getName())]["platform"]);
				}
				$event->cancel();
			}
			if ($itemId == 369 && $itemName === "§r§aSet cages\n§7Break a block.") {
				if (!isset($this->tempData[strtolower($player->getName())]["cages"]["winners"])) {
					$this->tempData[strtolower($player->getName())]["cages"]["winners"] = $block->getPosition()->asVector3();
					$player->sendMessage($this->plugin->getTranslator()->translate($player, "configurator.mode.setcage.losers"));
				} else {
					$winners = $this->tempData[strtolower($player->getName())]["cages"]["winners"];
					$losers = $block->getPosition()->asVector3();
					$this->setCages($winners, $losers);
					$player->sendMessage($this->plugin->getTranslator()->translate($player, "configurator.mode.setcage.successfully"));
					$player->getInventory()->removeItem($item);
					unset($this->tempData[strtolower($player->getName())]["cages"]);
				}

				$event->cancel();
			}
		}
	}
}
