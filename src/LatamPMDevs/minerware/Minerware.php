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

namespace LatamPMDevs\minerware;

use Closure;
use LatamPMDevs\minerware\libs\_eec78460fd20c565\CortexPE\Commando\PacketHooker;
use LatamPMDevs\minerware\libs\_eec78460fd20c565\IvanCraft623\fakeblocks\FakeBlockManager;
use LatamPMDevs\minerware\libs\_eec78460fd20c565\IvanCraft623\languages\Translator;
use LatamPMDevs\minerware\libs\_eec78460fd20c565\JackMD\ConfigUpdater\ConfigUpdater;
use LatamPMDevs\minerware\arena\ArenaListener;
use LatamPMDevs\minerware\arena\ArenaManager;
use LatamPMDevs\minerware\command\MinerwareCommand;
use LatamPMDevs\minerware\database\PlayerStorage;
use LatamPMDevs\minerware\entity\object\JoinNpc;
use LatamPMDevs\minerware\entity\object\LeaderboardText;
use LatamPMDevs\minerware\entity\object\TopPlayerNpc;
use LatamPMDevs\minerware\entity\PendingRemovals;
use LatamPMDevs\minerware\join\Joiner;
use LatamPMDevs\minerware\join\JoinSigns;
use LatamPMDevs\minerware\language\LanguageStore;
use LatamPMDevs\minerware\leaderboard\Leaderboard;
use LatamPMDevs\minerware\leaderboard\LeaderboardListener;
use LatamPMDevs\minerware\map\MapStorage;
use LatamPMDevs\minerware\map\world\MapTemplates;
use LatamPMDevs\minerware\map\world\MapWorldProviderEntry;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;

final class Minerware extends PluginBase {
	use SingletonTrait {
		setInstance as protected;
		reset as protected;
	}

	public const CONFIG_VERSION = 2;

	private const LEADERBOARD_REFRESH_TICKS = 1200;

	private Translator $translator;

	private Joiner $joiner;

	private PendingRemovals $pendingRemovals;

	private JoinSigns $joinSigns;

	private Leaderboard $leaderboard;

	protected function onLoad() : void {
		self::setInstance($this);

		if (ConfigUpdater::checkUpdate($this, $this->getConfig(), "config-version", self::CONFIG_VERSION)) {
			$this->reloadConfig();
		}

		$playerStorage = PlayerStorage::getInstance();
		MapStorage::getInstance()->install();
		$languageStore = new LanguageStore($this);
		$languageStore->saveDefaults();
		ArenaManager::getInstance();
		$this->translator = new Translator($this);
		$this->joiner = new Joiner($this);
		$this->pendingRemovals = new PendingRemovals();
		$this->joinSigns = new JoinSigns($this, $this->joiner);
		$this->leaderboard = new Leaderboard(static fn (Closure $onSuccess, Closure $onError) => $playerStorage->getTopWins(Leaderboard::SIZE, $onSuccess, $onError));
		# Saved with the world, so registered before any world loads.
		EntityFactory::getInstance()->register(
			JoinNpc::class,
			static fn (World $world, CompoundTag $nbt) : JoinNpc => new JoinNpc(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt),
			[JoinNpc::SAVE_ID]
		);
		EntityFactory::getInstance()->register(
			LeaderboardText::class,
			static fn (World $world, CompoundTag $nbt) : LeaderboardText => new LeaderboardText(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt),
			[LeaderboardText::SAVE_ID]
		);
		EntityFactory::getInstance()->register(
			TopPlayerNpc::class,
			static fn (World $world, CompoundTag $nbt) : TopPlayerNpc => new TopPlayerNpc(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt),
			[TopPlayerNpc::SAVE_ID]
		);
		$languageStore->load($this->translator);
		$this->getServer()->getCommandMap()->register("minerware", new MinerwareCommand($this));
	}

	protected function onEnable() : void {
		if (!PacketHooker::isRegistered()) {
			PacketHooker::register($this);
		}
		if (!FakeBlockManager::isRegistered()) {
			FakeBlockManager::register($this);
		}

		$this->getServer()->getWorldManager()->getProviderManager()->addProvider(new MapWorldProviderEntry(), "minerware");
		MapTemplates::getInstance()->cleanupStaleInstances();

		MapStorage::getInstance()->loadAll();
		$this->getServer()->getPluginManager()->registerEvents(new PlayerDataListener(PlayerStorage::getInstance()), $this);
		$this->getServer()->getPluginManager()->registerEvents(new ArenaListener(), $this);
		$this->getServer()->getPluginManager()->registerEvents(new LeaderboardListener($this), $this);
		$this->joinSigns->enable();
		$this->leaderboard->refresh();
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask($this->leaderboard->refresh(...)), self::LEADERBOARD_REFRESH_TICKS);
		$arenaManager = ArenaManager::getInstance();
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask($arenaManager->tick(...)), 20); //ticks
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask($arenaManager->tickMicrogames(...)), 3); //ticks
	}

	protected function onDisable() : void {
		foreach (ArenaManager::getInstance()->getArenas() as $arena) {
			$arena->close();
		}
		$this->getConfig()->set("default-language", $this->translator->getDefaultLanguage()->getLocale());
		$this->saveConfig();
		MapTemplates::getInstance()->closeAll();
		PlayerStorage::getInstance()->close();
	}

	public function getPrefix() : string {
		return "§l§6Minerware§r";
	}

	public function getJoiner() : Joiner {
		return $this->joiner;
	}

	public function getPendingRemovals() : PendingRemovals {
		return $this->pendingRemovals;
	}

	public function getJoinSigns() : JoinSigns {
		return $this->joinSigns;
	}

	public function getLeaderboard() : Leaderboard {
		return $this->leaderboard;
	}

	public function getTranslator() : Translator {
		return $this->translator;
	}
}