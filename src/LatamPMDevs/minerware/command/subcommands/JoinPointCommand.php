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

namespace LatamPMDevs\minerware\command\subcommands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use LatamPMDevs\minerware\command\args\JoinPointActionArgument;
use LatamPMDevs\minerware\command\args\MapNameArgument;
use LatamPMDevs\minerware\entity\object\LobbyNpc;
use LatamPMDevs\minerware\map\MapManager;
use LatamPMDevs\minerware\Minerware;
use pocketmine\command\CommandSender;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use function fmod;
use function is_string;

final class JoinPointCommand extends BaseSubCommand {

	public function __construct(private Minerware $plugin) {
		parent::__construct("joinpoint", "Manage the NPCs and signs that join players.");
		$this->setPermission("minerware.command.joinpoint");
	}

	protected function prepare() : void {
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->registerArgument(0, new JoinPointActionArgument("action"));
		$this->registerArgument(1, new MapNameArgument());
	}

	/**
	 * @param Player $sender
	 * @param array<string, mixed> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$translator = $this->plugin->getTranslator();
		switch ($args["action"] ?? null) {
			case JoinPointActionArgument::NPC:
				if (!$sender->hasPermission("minerware.joinpoint.create")) {
					$sender->sendMessage($translator->translate($sender, "joinpoint.noPermission"));
					return;
				}
				$mapName = is_string($args["map"] ?? null) ? $args["map"] : null;
				$map = $mapName === null ? null : MapManager::getInstance()->getByName($mapName);
				if ($mapName !== null && $map === null) {
					$sender->sendMessage($translator->translate($sender, "joinpoint.mapNotFound", ["{%map}" => $mapName]));
					return;
				}
				$location = $sender->getLocation();
				$npc = new LobbyNpc(
					Location::fromObject($location, $location->getWorld(), fmod($location->getYaw() + 180, 360), 0),
					$sender->getSkin()
				);
				$npc->setMapFilter($map?->getName())->spawnToAll();
				$sender->sendMessage($translator->translate($sender, "joinpoint.npc.created"));
				break;

			case JoinPointActionArgument::REMOVE:
				if (!$sender->hasPermission("minerware.joinpoint.remove")) {
					$sender->sendMessage($translator->translate($sender, "joinpoint.noPermission"));
					return;
				}
				$this->plugin->getJoinPointRemovals()->arm($sender);
				$sender->sendMessage($translator->translate($sender, "joinpoint.remove.armed"));
				break;

			case JoinPointActionArgument::LIST:
				$npcs = 0;
				foreach ($this->plugin->getServer()->getWorldManager()->getWorlds() as $world) {
					foreach ($world->getEntities() as $entity) {
						if ($entity instanceof LobbyNpc) {
							$npcs++;
						}
					}
				}
				$sender->sendMessage($translator->translate($sender, "joinpoint.list", [
					"{%signs}" => (string) $this->plugin->getJoinSigns()->count(),
					"{%npcs}" => (string) $npcs
				]));
				break;

			default:
				$this->sendUsage();
				break;
		}
	}

	public function getParent() : BaseCommand {
		return $this->parent;
	}
}
