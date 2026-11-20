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

use CortexPE\Commando\PacketHooker;
use IvanCraft623\fakeblocks\FakeBlockManager;
use IvanCraft623\languages\Translator;
use JackMD\ConfigUpdater\ConfigUpdater;
use LatamPMDevs\minerware\arena\ArenaListener;
use LatamPMDevs\minerware\arena\ArenaManager;
use LatamPMDevs\minerware\command\MinerwareCommand;
use LatamPMDevs\minerware\database\DataManager;
use LatamPMDevs\minerware\entity\object\LobbyNpc;
use LatamPMDevs\minerware\join\Joiner;
use LatamPMDevs\minerware\join\JoinPointRemovals;
use LatamPMDevs\minerware\join\JoinSigns;
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

	private Translator $translator;

	private Joiner $joiner;

	private JoinPointRemovals $joinPointRemovals;

	private JoinSigns $joinSigns;

	protected function onLoad() : void {
		self::setInstance($this);

		if (ConfigUpdater::checkUpdate($this, $this->getConfig(), "config-version", self::CONFIG_VERSION)) {
			$this->reloadConfig();
		}

		DataManager::getInstance();
		ArenaManager::getInstance();
		$this->translator = new Translator($this);
		$this->joiner = new Joiner($this);
		$this->joinPointRemovals = new JoinPointRemovals();
		$this->joinSigns = new JoinSigns($this, $this->joiner);
		# Saved with the world, so registered before any world loads.
		EntityFactory::getInstance()->register(
			LobbyNpc::class,
			static fn (World $world, CompoundTag $nbt) : LobbyNpc => new LobbyNpc(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt),
			[LobbyNpc::SAVE_ID]
		);
		DataManager::getInstance()->loadLanguages();
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

		$dataManager = DataManager::getInstance();
		$dataManager->loadMaps();
		$this->getServer()->getPluginManager()->registerEvents(new EventListener($dataManager), $this);
		$this->getServer()->getPluginManager()->registerEvents(new ArenaListener(), $this);
		$this->joinSigns->enable();
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
		DataManager::getInstance()->closeDatabase();
	}

	public function getPrefix() : string {
		return "§aMinerware§r ";
	}

	public function getJoiner() : Joiner {
		return $this->joiner;
	}

	public function getJoinPointRemovals() : JoinPointRemovals {
		return $this->joinPointRemovals;
	}

	public function getJoinSigns() : JoinSigns {
		return $this->joinSigns;
	}

	public function getTranslator() : Translator {
		return $this->translator;
	}
}
