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

namespace LatamPMDevs\minerware\join;

use LatamPMDevs\minerware\Minerware;
use pocketmine\block\BaseSign;
use pocketmine\block\tile\Sign as SignTile;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\event\world\ChunkUnloadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\World;
use function array_filter;
use function count;

/**
 * Signs whose first line is [Minerware] join the player who clicks them.
 */
final class JoinSigns implements Listener {

	private const REFRESH_INTERVAL = 20; //ticks

	/** @var array<string, array{string, int, int, int}> key => world folder, x, y, z */
	private array $signs = [];

	public function __construct(
		private readonly Minerware $plugin,
		private readonly Joiner $joiner
	) { }

	public function enable() : void {
		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
		foreach ($this->plugin->getServer()->getWorldManager()->getWorlds() as $world) {
			foreach ($world->getLoadedChunks() as $chunk) {
				foreach ($chunk->getTiles() as $tile) {
					$this->indexTile($world, $tile);
				}
			}
		}
		$this->plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () : void {
			$this->refresh();
		}), self::REFRESH_INTERVAL);
	}

	public function count() : int {
		return count($this->signs);
	}

	/**
	 * @priority MONITOR
	 */
	public function onChunkLoad(ChunkLoadEvent $event) : void {
		foreach ($event->getChunk()->getTiles() as $tile) {
			$this->indexTile($event->getWorld(), $tile);
		}
	}

	/**
	 * @priority MONITOR
	 */
	public function onChunkUnload(ChunkUnloadEvent $event) : void {
		$folder = $event->getWorld()->getFolderName();
		$this->signs = array_filter(
			$this->signs,
			static fn (array $s) => $s[0] !== $folder || ($s[1] >> 4) !== $event->getChunkX() || ($s[3] >> 4) !== $event->getChunkZ()
		);
	}

	/**
	 * @priority MONITOR
	 */
	public function onWorldUnload(WorldUnloadEvent $event) : void {
		$folder = $event->getWorld()->getFolderName();
		$this->signs = array_filter($this->signs, static fn (array $s) => $s[0] !== $folder);
	}

	public function onSignChange(SignChangeEvent $event) : void {
		if (!$event->isFrontFace() || !JoinSignFormat::isJoinSign($event->getNewText())) {
			return;
		}
		$player = $event->getPlayer();
		if (!$player->hasPermission("minerware.sign.create")) {
			$event->cancel();
			$player->sendMessage($this->plugin->getTranslator()->translate($player, "sign.noPermission"));
			return;
		}
		$position = $event->getSign()->getPosition();
		$this->index($position->getWorld(), $position->getFloorX(), $position->getFloorY(), $position->getFloorZ());
		# The text is applied after the event.
		$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function () : void {
			$this->refresh();
		}), 1);
	}

	public function onInteract(PlayerInteractEvent $event) : void {
		$block = $event->getBlock();
		if ($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK || !$block instanceof BaseSign) {
			return;
		}
		$text = $block->getFaceText(true);
		if (!JoinSignFormat::isJoinSign($text)) {
			return;
		}
		$event->cancel();
		$this->joiner->join($event->getPlayer(), JoinSignFormat::getMapFilter($text));
	}

	public function onBreak(BlockBreakEvent $event) : void {
		$block = $event->getBlock();
		if (!$block instanceof BaseSign || !JoinSignFormat::isJoinSign($block->getFaceText(true))) {
			return;
		}
		$player = $event->getPlayer();
		if (!$player->hasPermission("minerware.sign.remove")) {
			$event->cancel();
			$player->sendMessage($this->plugin->getTranslator()->translate($player, "sign.noPermission"));
		}
	}

	private function indexTile(World $world, \pocketmine\block\tile\Tile $tile) : void {
		if ($tile instanceof SignTile && JoinSignFormat::isJoinSign($tile->getText())) {
			$position = $tile->getPosition();
			$this->index($world, $position->getFloorX(), $position->getFloorY(), $position->getFloorZ());
		}
	}

	private function index(World $world, int $x, int $y, int $z) : void {
		$this->signs[$world->getFolderName() . ":$x:$y:$z"] = [$world->getFolderName(), $x, $y, $z];
	}

	/**
	 * Rewrites the state lines of every join sign, only when they changed.
	 */
	private function refresh() : void {
		$worldManager = $this->plugin->getServer()->getWorldManager();
		$translator = $this->plugin->getTranslator();
		$stats = [];
		foreach ($this->signs as $key => [$folder, $x, $y, $z]) {
			$world = $worldManager->getWorldByName($folder);
			if ($world === null || !$world->isChunkLoaded($x >> 4, $z >> 4)) {
				unset($this->signs[$key]);
				continue;
			}
			$sign = $world->getBlockAt($x, $y, $z);
			if (!$sign instanceof BaseSign || !JoinSignFormat::isJoinSign($sign->getFaceText(true))) {
				unset($this->signs[$key]);
				continue;
			}
			$text = $sign->getFaceText(true);
			$filter = JoinSignFormat::getMapFilter($text);
			$stats[$filter ?? ""] ??= JoinStats::forMap($filter);
			$new = JoinSignFormat::withState(
				$text,
				$translator->translate(null, $stats[$filter ?? ""]->joinable ? "sign.joinable" : "status.full"),
				$translator->translate(null, "status.playing", ["{%players}" => (string) $stats[$filter ?? ""]->players])
			);
			if ($new->getLines() !== $text->getLines()) {
				$sign->setFaceText(true, $new);
				$world->setBlockAt($x, $y, $z, $sign, false);
			}
		}
	}
}
