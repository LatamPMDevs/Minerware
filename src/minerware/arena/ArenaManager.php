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

use minerware\database\DataManager;
use minerware\language\Translator;
use minerware\Minerware;
use pocketmine\lang\Translatable;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use function count;
use function rand;
use function range;
use function shuffle;

final class ArenaManager {
	use SingletonTrait;

	private Minerware $plugin;

	private ?World $lobby;

	/** @var array<string, Arena> */
	private array $arenas = [];

	/** @var array<string, World> */
	private array $registeredMaps = [];

	public function __construct() {
		$this->plugin = Minerware::getInstance();
		$this->lobby = DataManager::getInstance()->getLobby();
	}

	public function getArenas(): array  {
		return $this->arenas;
	}

	public function getById(string $id): ?Arena {
		return (isset($this->arenas[$id]) ? $this->arenas[$id] : null);
	}

	public function getLobby(): ?World{
		return $this->lobby;
	}

	public function createArena(): Arena {
		$id = $this->generateId();
		$arena = new Arena($id);
		$this->arenas[$id] = $arena;
		return $arena;
	}

	public function deleteArena(Arena|string $arena): void  {
		$id = ($arena instanceof Arena) ? $arena->getId() : $arena;
		unset($this->arenas[$id]);
	}

	public function getAvaible(): Arena  {
		foreach ($this->arenas as $arena) {
			if ($arena->getStatus() === "waiting" && count($arena->getPlayers()) < Arena::MAX_PLAYERS) {
				return $arena;
			}
		}
		return $this->createArena();
	}

	public function generateId(): string {
		$az = range("a", "z");
		shuffle($az);

		$name = "";
		$name .= $az[0];
		$name .= $az[1];
		$name .= rand(10, 99);
		return $name;
	}

	public function join(Player $player, Arena $arena = null): void {
		if ($this->lobby === null) {
			$player->sendMessage(Translator::getInstance()->translate(new Translatable("error.lobby.isNotSet")));
			return;
		}
		if ($arena === null) {
			$arena = $this->getAvaible();
		}
		$arena->join($player);
	}
}
