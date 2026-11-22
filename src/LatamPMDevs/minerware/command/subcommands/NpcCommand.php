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

use LatamPMDevs\minerware\libs\_eec78460fd20c565\CortexPE\Commando\args\RawStringArgument;
use LatamPMDevs\minerware\libs\_eec78460fd20c565\CortexPE\Commando\BaseCommand;
use LatamPMDevs\minerware\libs\_eec78460fd20c565\CortexPE\Commando\BaseSubCommand;
use LatamPMDevs\minerware\libs\_eec78460fd20c565\CortexPE\Commando\constraint\InGameRequiredConstraint;
use LatamPMDevs\minerware\command\args\ChoiceArgument;
use LatamPMDevs\minerware\entity\object\JoinNpc;
use LatamPMDevs\minerware\entity\object\LeaderboardEntity;
use LatamPMDevs\minerware\entity\object\LeaderboardText;
use LatamPMDevs\minerware\entity\object\TopPlayerNpc;
use LatamPMDevs\minerware\map\MapManager;
use LatamPMDevs\minerware\Minerware;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function ctype_digit;
use function is_string;

final class NpcCommand extends BaseSubCommand {

	private const JOIN = "join";
	private const BOARD = "board";
	private const TOP = "top";
	private const REMOVE = "remove";
	private const LIST = "list";

	public function __construct(private Minerware $plugin) {
		parent::__construct("npc", "Place and remove the join NPCs, the top player NPCs and the leaderboard.");
		$this->setPermission("minerware.command.npc");
	}

	protected function prepare() : void {
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->registerArgument(0, new ChoiceArgument("action", self::JOIN, self::BOARD, self::TOP, self::REMOVE, self::LIST));
		$this->registerArgument(1, new RawStringArgument("value", true));
	}

	/**
	 * @param Player $sender
	 * @param array<string, mixed> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$translator = $this->plugin->getTranslator();
		$value = is_string($args["value"] ?? null) ? $args["value"] : null;
		$location = $sender->getLocation();
		switch ($args["action"] ?? null) {
			case self::JOIN:
				$map = $value === null ? null : MapManager::getInstance()->getByName($value);
				if ($value !== null && $map === null) {
					$sender->sendMessage($translator->translate($sender, "join.mapNotFound"));
					return;
				}
				(new JoinNpc($location, $sender->getSkin()))->setMapFilter($map?->getName())->spawnToAll();
				$sender->sendMessage($translator->translate($sender, "npc.join.created"));
				break;

			case self::BOARD:
				(new LeaderboardText($location, LeaderboardEntity::invisibleSkin()))->spawnToAll();
				$sender->sendMessage($translator->translate($sender, "leaderboard.created"));
				break;

			case self::TOP:
				$rank = $value !== null && ctype_digit($value) ? (int) $value : 0;
				if ($rank < 1 || $rank > TopPlayerNpc::RANKS) {
					$sender->sendMessage($translator->translate($sender, "leaderboard.rankInvalid", ["{%max}" => TopPlayerNpc::RANKS]));
					return;
				}
				(new TopPlayerNpc($location, LeaderboardEntity::invisibleSkin()))->setRank($rank)->spawnToAll();
				$sender->sendMessage($translator->translate($sender, "leaderboard.created"));
				break;

			case self::REMOVE:
				$this->plugin->getPendingRemovals()->arm($sender);
				$sender->sendMessage($translator->translate($sender, "npc.remove.armed"));
				break;

			case self::LIST:
				$counts = [JoinNpc::class => 0, TopPlayerNpc::class => 0, LeaderboardText::class => 0];
				foreach ($this->plugin->getServer()->getWorldManager()->getWorlds() as $world) {
					foreach ($world->getEntities() as $entity) {
						if (isset($counts[$entity::class])) {
							$counts[$entity::class]++;
						}
					}
				}
				$sender->sendMessage($translator->translate($sender, "npc.list", [
					"{%signs}" => $this->plugin->getJoinSigns()->count(),
					"{%join}" => $counts[JoinNpc::class],
					"{%top}" => $counts[TopPlayerNpc::class],
					"{%boards}" => $counts[LeaderboardText::class]
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