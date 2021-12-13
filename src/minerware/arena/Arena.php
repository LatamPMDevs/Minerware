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

use minerware\arena\minigame\IgniteTNT;
use minerware\arena\minigame\Microgame;
use minerware\arena\minigame\WaitForIt;
use minerware\database\DataManager;
use minerware\language\Translator;
use minerware\Minerware;
use minerware\tasks\ArenaTask;

use minerware\utils\PointHolder;
use minerware\utils\Utils;
use minerware\utils\VoteCounter;

use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\lang\Translatable;
use pocketmine\player\GameMode;

use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;
use function array_rand;
use function count;

final class Arena implements Listener {

	public const MIN_PLAYERS = 2;

	public const MAX_PLAYERS = 12;

	private string $status = "waiting";

	private ?World $world = null;

	private ?Map $map = null;

	/** @var Player[] */
	private array $players = [];

	private PointHolder $pointHolder;

	private VoteCounter $voteCounter;

	/** @var Microgame[] */
	private array $microgamesQueue = [];

	private Microgame $currentMicrogame = null;

	public int $waitingtime = 40;

	public int $startingtime = 12;

	public int $gametime = 0;

	public int $endingtime = 10;

	public function __construct(private string $id) {
		$this->pointHolder = new PointHolder();
		$this->voteCounter = new VoteCounter();
		Minerware::getInstance()->getServer()->getPluginManager()->registerEvents($this, Minerware::getInstance());
		Minerware::getInstance()->getScheduler()->scheduleRepeatingTask(new ArenaTask($this), 20);

		$easyGames = [WaitForIt::class];
		$randEasy = [array_rand($easyGames, 1)]; //7, remove []
		$mediumGames = [IgniteTNT::class];
		$randMedium = [array_rand($mediumGames, 1)]; //8, remove []
		$bossGames = [BowSpleef::class];
		$randBoss = array_rand($bossGames);

		foreach ($randEasy as $index) {
			$this->microgamesQueue[] = $easyGames[$index];
		}
		foreach ($randMedium as $index) {
			$this->microgamesQueue[] = $mediumGames[$index];
		}
		$this->microgamesQueue[] = $bossGames[$randBoss];
	}

	public function getId(): string {
		return $this->id;
	}

	public function getWorld(): ?World {
		return $this->world;
	}

	public function setWorld(?World $world): void {
		$this->world = $world;
	}

	public function getMap(): ?Map {
		return $this->map;
	}

	public function setMap(Map $map): void {
		$this->map = $map;
	}

	public function getStatus(): string {
		return $this->status;
	}

	public function setStatus(string $value): void {
		$this->status = $value;
	}

	public function join(Player $player): void {
		$this->players[$player->getName()] = $player;
		$this->sendMessage(Translator::getInstance()->translate(new Translatable("game.player.join", [$player->getName(), count($this->players) . "/" . self::MAX_PLAYERS])));
		//TODO:: #2 Fix $lobby no being null.
		$lobby = ArenaManager::getInstance()->getLobby();
		$lobby->loadChunk($lobby->getSafeSpawn()->getFloorX(), $lobby->getSafeSpawn()->getFloorZ());
		$player->teleport($lobby->getSafeSpawn(), 0, 0);
		$player->getInventory()->clearAll();
		$player->getArmorInventory()->clearAll();
		$player->getCursorInventory()->clearAll();
		$player->setGamemode(GameMode::fromMagicNumber(2));
	}

	public function quit(Player $player): void {
		unset($this->players[$player->getName()]);
		$this->sendMessage(Translator::getInstance()->translate(new Translatable("game.player.quit", [$player->getName(), count($this->players) . "/" . self::MAX_PLAYERS])));
	}

	public function sendMessage(string $message): void {
		foreach ($this->players as $player) {
			$player->sendMessage($message);
		}
	}

	public function inGame(Player $player): bool {
		foreach ($this->players as $name => $pl) {
			if ($pl == $player) {
				return true;
			}
		}
		return false;
	}

	public function getPlayers(): array {
		return $this->players;
	}

	public function getPointHolder(): PointHolder {
		return $this->pointHolder;
	}

	public function getVoteCounter(): VoteCounter {
		return $this->voteCounter;
	}

	public function getCurrentMicrogame(): ?Microgame {
		return $this->currentMicrogame;
	}

	public function startNextMicrogame(): Microgame {
		$microgame = new $this->microgamesQueue[0]($this);
		$this->currentMicrogame = $microgame;
		unset($this->microgamesQueue[0]);
		return $microgame;
	}

	public function tpSpawn(Player $player): void {
		$expectedSpawns = [];
		$spawns = $this->map->getData()->getArray("spawns");
		foreach ($spawns as $index => $data) {
			$expectedSpawns[] = $index;
		}
		$spawn = $spawns[$expectedSpawns[array_rand($expectedSpawns)]];
		$pos = new Position($spawn["X"], $spawn["Y"], $spawn["Z"], $this->world);
		$this->world->loadChunk($pos->getFloorX(), $pos->getFloorZ());
		$player->teleport($pos);
	}

	public function deleteMap(): void {
		if ($this->world !== null) {
			$worldPath = Minerware::getInstance()->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . $this->world->getFolderName() . DIRECTORY_SEPARATOR;
			Minerware::getInstance()->getServer()->getWorldManager()->unloadWorld($this->world, true);
			Utils::removeDir($worldPath);
			$this->world = null;
		}
	}

	#Listener

	public function onQuit(PlayerQuitEvent $event): void {
		$player = $event->getPlayer();
		if ($this->inGame($player)) {
			$this->quit($player);
		}
	}

	public function onWorldChange(EntityTeleportEvent $event): void {
		$player = $event->getEntity();
		if (!$player instanceof Player) return;
		if ($event->getFrom()->getWorld() == $event->getTo()->getWorld()) return;
		if ($this->inGame($player)) {
			if ($this->status === "waiting") {
				if ($event->getTo()->getWorld() != DataManager::getInstance()->getLobby()) {
					$this->quit($player);
				}
			} elseif ($this->status === "starting") {
				if ($event->getTo()->getWorld() != $this->world) {
					$this->quit($player);
				}
			} else {
				$this->quit($player);
			}
		}
	}
}
