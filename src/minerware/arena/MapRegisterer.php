<?php

/**
 *  ███╗   ███╗██╗███╗   ██╗███████╗██████╗ ██╗    ██╗ █████╗ ██████╗ ███████╗
 *  ████╗ ████║██║████╗  ██║██╔════╝██╔══██╗██║    ██║██╔══██╗██╔══██╗██╔════╝
 *  ██╔████╔██║██║██╔██╗ ██║█████╗  ██████╔╝██║ █╗ ██║███████║██████╔╝█████╗
 *  ██║╚██╔╝██║██║██║╚██╗██║██╔══╝  ██╔══██╗██║███╗██║██╔══██║██╔══██╗██╔══╝
 *  ██║ ╚═╝ ██║██║██║ ╚████║███████╗██║  ██║╚███╔███╔╝██║  ██║██║  ██║███████╗
 *  ╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝ ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝
 *
 * This is a private project, your not allow to redistribute nor resell it.
 * The only ones with that power are this project's contributors.
 *
 * Copyright 2021 © Minerware
 */

declare(strict_types=1);

namespace minerware\arena;

use minerware\database\DataHolder;
use minerware\database\DataManager;
use minerware\language\Translator;
use minerware\Minerware;
use minerware\utils\Utils;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\item\ItemFactory;
use pocketmine\lang\Translatable;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use function explode;
use function strtolower;

final class MapRegisterer implements Listener {
	use SingletonTrait;

	/** @var array<string, self> */
	private static array $mapRegisterer;

	public static function createRegisterer(Player $player, World $world): self {
		return (self::$mapRegisterer[strtolower($player->getName())] = new self($player, $world));
	}

	/** @var array<string, mixed> */
	private array $data = [];

	/** @var array<string, mixed> */
	private array $tempData = [];

	private function __construct(private Player $player, private World $world) {
		$this->data["name"] = $world->getDisplayName();
		$this->setConfiguratorMode($player);
		Minerware::getInstance()->getServer()->getPluginManager()->registerEvents($this, Minerware::getInstance());
	}

	private function setConfiguratorMode(Player $player): void {
		$this->world->loadChunk($this->world->getSafeSpawn()->getFloorX(), $this->world->getSafeSpawn()->getFloorZ());
		$player->teleport($this->world->getSafeSpawn(), 0, 0);
		$player->sendMessage(Translator::getInstance()->translate(new Translatable("configurator.mode.enter")));
	}

	private function setPlatform(Vector3 $firstPoint, Vector3 $secondPoint): void {
		$this->data["platform"]["pos1"] = [
			"X" => $firstPoint->getX(),
			"Y" => $firstPoint->getY(),
			"Z" => $firstPoint->getZ()];
		$this->data["platform"]["pos2"] = [
			"X" => $secondPoint->getX(),
			"Y" => $secondPoint->getY(),
			"Z" => $secondPoint->getZ()];
		$this->data["platform"]["parameter"] = Utils::calculateParameter($firstPoint, $secondPoint);
	}

	private function setCages(Vector3 $winners, Vector3 $lossers): void {
		$this->data["cages"]["winners"] = [
			"X" => $winners->getX(),
			"Y" => $winners->getY(),
			"Z" => $winners->getZ()];
		$this->data["cages"]["lossers"] = [
			"X" => $lossers->getX(),
			"Y" => $lossers->getY(),
			"Z" => $lossers->getZ()];
	}

	private function setSpawn(Vector3 $spawn): void {
		$this->data["spawns"][] = [
			"X" => $spawn->getX(),
			"Y" => $spawn->getY(),
			"Z" => $spawn->getZ()];
	}

	private function setVoid(float $void): void {
		$this->data["void"]["limit"] = $void;
	}

	private function save(): void {
		DataManager::getInstance()->saveMapData(new DataHolder($this->data));
		unset(self::$mapRegisterer[strtolower($this->player->getName())]);
	}

	public function chatCommand(PlayerChatEvent $event): void {
		$player = $event->getPlayer();
		$args = explode(" ", $event->getMessage());
		if (strtolower($player->getName()) == strtolower($this->player->getName())) {
			switch (strtolower($args[0])) {
				case "setplatform":
				   $player->getInventory()->addItem(ItemFactory::getInstance()->get(369, 0, 1)->setCustomName("§r§aSet platform\n§7Break a corner."));
				   $player->sendMessage(Translator::getInstance()->translate(new Translatable("configurator.mode.setplatform")));
				break;

				case "setcages":
					$player->getInventory()->addItem(ItemFactory::getInstance()->get(369, 0, 1)->setCustomName("§r§aSet cages\n§7Break a block."));
					   $player->sendMessage(Translator::getInstance()->translate(new Translatable("configurator.mode.setcage.winners")));
				break;

				case "setspawn":
					$this->setSpawn($player->getPosition());
					$player->sendMessage(Translator::getInstance()->translate(new Translatable("configurator.mode.setspawn.successfully")));
				break;

				case "setvoid":
					$y = $player->getPosition()->getFloorY();
					$this->setVoid($y);
					$player->sendMessage(Translator::getInstance()->translate(new Translatable("configurator.mode.setvoid.successfully", [$y])));
				break;

				case "help":
					$player->sendMessage(
						"§6Minerware §bConfiguration Commands" . "\n" . "\n" .
						"§ahelp: §7Help commands." . "\n" .
						"§asetplatform: §7Register the platform." . "\n" .
						"§asetcages: §7Register the winners|losser cage." . "\n" .
						"§asetspawn: §7Register a spawn." . "\n" .
						"§asetvoid: §7Set the void position." . "\n" .
						"§adone: §7Finish configurator mode"
					);
				break;

				case "done":
					$this->save();
					$player->sendMessage(Translator::getInstance()->translate(new Translatable("configurator.mode.registered.successfully")));
					$player->teleport(Minerware::getInstance()->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
					# Save World
					$folderName = $this->world->getFolderName();
					Minerware::getInstance()->getServer()->getWorldManager()->unloadWorld($this->world);
					Utils::setZip(
						Minerware::getInstance()->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . $folderName,
						Minerware::getInstance()->getDataFolder() . "database" . DIRECTORY_SEPARATOR . "backups" . DIRECTORY_SEPARATOR . $this->data["name"] . ".zip"
					);
				break;

				default:
					$player->sendMessage(Translator::getInstance()->translate(new Translatable("configurator.mode.error.notFound")));
				break;
			}
			$event->cancel();
		}
	}

	public function onBreak(BlockBreakEvent $event): void {
		$player = $event->getPlayer();
		$block = $event->getBlock();
		if (strtolower($player->getName()) == strtolower($this->player->getName())) {
			$item = $player->getInventory()->getItemInHand();
			$itemId = $item->getId();
			$itemName = $item->getCustomName();
			if ($itemId == 369 && $itemName === "§r§aSet platform\n§7Break a corner.") {
				if (!isset($this->tempData[strtolower($player->getName())]["platform"]["pos1"])) {
				   $this->tempData[strtolower($player->getName())]["platform"]["pos1"] = $block->getPos()->asVector3();
				} else {
					$pos1 = $this->tempData[strtolower($player->getName())]["platform"]["pos1"];
					$pos2 = $block->getPos()->asVector3();
					$size = Utils::calculateSize($pos1, $pos2);
					if ($size !== "24x24") {
						$player->sendMessage(Translator::getInstance()->translate(new Translatable("configurator.mode.error.invalidSize", ["24x24", $size])));
					} else {
						$this->setPlatform($pos1, $pos2);
						$player->sendMessage(Translator::getInstance()->translate(new Translatable("configurator.mode.setplatform.successfully")));
					}
					$player->getInventory()->removeItem($item);
					unset($this->tempData[strtolower($player->getName())]["platform"]);
				}
				$event->cancel();
			}
			if ($itemId == 369 && $itemName === "§r§aSet cages\n§7Break a block.") {
				if (!isset($this->tempData[strtolower($player->getName())]["cages"]["winners"])) {
					$this->tempData[strtolower($player->getName())]["cages"]["winners"] = $block->getPos()->asVector3();
					$player->sendMessage(Translator::getInstance()->translate(new Translatable("configurator.mode.setcage.lossers")));
				} else {
					$winners = $this->tempData[strtolower($player->getName())]["cages"]["winners"];
					$lossers = $block->getPos()->asVector3();
					$this->setCages($winners, $lossers);
					$player->sendMessage(Translator::getInstance()->translate(new Translatable("configurator.mode.setcage.successfully")));
					$player->getInventory()->removeItem($item);
					unset($this->tempData[strtolower($player->getName())]["cages"]);
				}

				$event->cancel();
			}
		}
	}
}
