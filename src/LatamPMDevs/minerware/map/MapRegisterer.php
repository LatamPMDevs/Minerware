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

namespace LatamPMDevs\minerware\map;

use InvalidArgumentException;
use LatamPMDevs\minerware\database\DataManager;
use LatamPMDevs\minerware\item\SpecialItems;
use LatamPMDevs\minerware\Minerware;
use LatamPMDevs\minerware\utils\Utils;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\HandlerListManager;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;
use RuntimeException;
use function array_map;
use function explode;
use function strtolower;

/**
 * The interactive map editor: one per player, driven by chat and a blaze rod.
 */
final class MapRegisterer implements Listener {

	private const MODE_PLATFORM = "platform";
	private const MODE_CAGES = "cages";

	/** @var array<int, self> player id => their editor */
	private static array $editors = [];

	public static function createRegisterer(Player $player, World $world) : self {
		(self::$editors[$player->getId()] ?? null)?->close();
		return (self::$editors[$player->getId()] = new self($player, $world));
	}

	private Minerware $plugin;

	private string $mapName;

	/** @var ?array{Vector3, Vector3} two opposite corners of the platform */
	private ?array $platform = null;

	/** @var ?array{Vector3, Vector3} winners and losers cage */
	private ?array $cages = null;

	/** @var list<Vector3> */
	private array $spawns = [];

	/** What the rod is picking now, if anything. */
	private ?string $mode = null;

	/** The first corner picked while setting the platform. */
	private ?Vector3 $pendingCorner = null;

	/** The winners cage picked while setting the cages. */
	private ?Vector3 $pendingWinnersCage = null;

	private function __construct(private Player $player, private World $world) {
		$this->plugin = Minerware::getInstance();
		$this->mapName = $world->getDisplayName();
		$spawn = $this->world->getSafeSpawn();
		$this->world->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
		$player->teleport($spawn, 0, 0);
		$this->tell("configurator.mode.enter");
		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
	}

	/**
	 * @param array<string, string|int> $replacements
	 */
	private function tell(string $key, array $replacements = []) : void {
		$this->player->sendMessage($this->plugin->getTranslator()->translate($this->player, $key, $replacements));
	}

	/**
	 * What Map::fromArray() reads: whatever is still missing is left out, so it reports it.
	 *
	 * @return array<string, mixed>
	 */
	private function toArray() : array {
		$data = ["name" => $this->mapName, "spawns" => array_map(self::position(...), $this->spawns)];
		if ($this->platform !== null) {
			$data["platform"] = ["pos1" => self::position($this->platform[0]), "pos2" => self::position($this->platform[1])];
		}
		if ($this->cages !== null) {
			$data["cages"] = ["winners" => self::position($this->cages[0]), "losers" => self::position($this->cages[1])];
		}
		return $data;
	}

	/**
	 * @return array{X: float, Y: float, Z: float}
	 */
	private static function position(Vector3 $position) : array {
		return ["X" => $position->getX(), "Y" => $position->getY(), "Z" => $position->getZ()];
	}

	private function close() : void {
		unset(self::$editors[$this->player->getId()]);
		HandlerListManager::global()->unregisterAll($this);
	}

	public function onQuit(PlayerQuitEvent $event) : void {
		if ($event->getPlayer() === $this->player) {
			$this->close();
		}
	}

	public function chatCommand(PlayerChatEvent $event) : void {
		if ($event->getPlayer() !== $this->player) {
			return;
		}
		$event->cancel();
		match (strtolower(explode(" ", $event->getMessage())[0])) {
			"setplatform" => $this->select(self::MODE_PLATFORM, "configurator.mode.setplatform"),
			"setcages" => $this->select(self::MODE_CAGES, "configurator.mode.setcage.winners"),
			"setspawn" => $this->addSpawn(),
			"help" => $this->sendHelp(),
			"done" => $this->finish(),
			default => $this->tell("configurator.mode.error.notFound")
		};
	}

	private function select(string $mode, string $messageKey) : void {
		$this->mode = $mode;
		if (!$this->hasRod()) {
			$this->player->getInventory()->addItem(SpecialItems::EDITOR_BLAZE_ROD($this->plugin->getTranslator()->getLanguageOf($this->player)));
		}
		$this->tell($messageKey);
	}

	private function hasRod() : bool {
		foreach ($this->player->getInventory()->getContents() as $item) {
			if ($item->getNamedTag()->getTag(SpecialItems::EDITOR_ROD) !== null) {
				return true;
			}
		}
		return false;
	}

	private function addSpawn() : void {
		$this->spawns[] = $this->player->getPosition()->asVector3();
		$this->tell("configurator.mode.setspawn.successfully");
	}

	private function sendHelp() : void {
		$this->player->sendMessage(
			"§6Minerware §bConfiguration Commands" . "\n" . "\n" .
			"§ahelp: §7Help command." . "\n" .
			"§asetplatform: §7Register the platform." . "\n" .
			"§asetcages: §7Register the winners|losser cage." . "\n" .
			"§asetspawn: §7Register a spawn." . "\n" .
			"§adone: §7Finish configurator mode"
		);
	}

	/**
	 * Validates the map, backs the world up and saves it.
	 */
	private function finish() : void {
		try {
			$map = Map::fromArray($this->toArray());
		} catch (InvalidArgumentException $e) {
			$this->player->sendMessage("§c" . $e->getMessage());
			return;
		}
		$folderName = $this->world->getFolderName();
		$this->world->save(true);
		$defaultWorld = $this->plugin->getServer()->getWorldManager()->getDefaultWorld();
		if ($defaultWorld !== null) {
			$this->player->teleport($defaultWorld->getSafeSpawn());
		}
		$this->plugin->getServer()->getWorldManager()->unloadWorld($this->world);
		if (!Utils::setZip(
			$this->plugin->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . $folderName,
			DataManager::getInstance()->getMapBackupPath($map->getName())
		)) {
			$this->tell("configurator.mode.error.zip");
			return;
		}
		try {
			DataManager::getInstance()->saveMap($map);
		} catch (RuntimeException $e) {
			$this->player->sendMessage("§c" . $e->getMessage());
			return;
		}
		$this->close();
		$this->tell("configurator.mode.registered.successfully");
	}

	public function onBreak(BlockBreakEvent $event) : void {
		if ($event->getPlayer() !== $this->player) {
			return;
		}
		$item = $this->player->getInventory()->getItemInHand();
		$position = $event->getBlock()->getPosition()->asVector3();
		if ($item->getNamedTag()->getTag(SpecialItems::EDITOR_ROD) === null || $this->mode === null) {
			return;
		}
		if ($this->mode === self::MODE_PLATFORM) {
			$this->pickCorner($position);
		} else {
			$this->pickCage($position);
		}
		if ($this->mode === null) {
			$this->player->getInventory()->removeItem($item);
		}
		$event->cancel();
	}

	private function pickCorner(Vector3 $position) : void {
		if ($this->pendingCorner === null) {
			$this->pendingCorner = $position;
			return;
		}
		$size = Utils::calculateSize($this->pendingCorner, $position);
		if ($size !== "24x24") {
			$this->tell("configurator.mode.error.invalidSize", ["{%good_size}" => "24x24", "{%bad_size}" => $size]);
		} else {
			$this->platform = [$this->pendingCorner, $position];
			$this->tell("configurator.mode.setplatform.successfully");
		}
		$this->pendingCorner = null;
		$this->mode = null;
	}

	private function pickCage(Vector3 $position) : void {
		if ($this->pendingWinnersCage === null) {
			$this->pendingWinnersCage = $position;
			$this->tell("configurator.mode.setcage.losers");
			return;
		}
		$this->cages = [$this->pendingWinnersCage, $position];
		$this->tell("configurator.mode.setcage.successfully");
		$this->pendingWinnersCage = null;
		$this->mode = null;
	}
}
