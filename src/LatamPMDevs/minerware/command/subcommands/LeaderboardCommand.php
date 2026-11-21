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

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use LatamPMDevs\minerware\command\args\ChoiceArgument;
use LatamPMDevs\minerware\entity\object\LeaderboardEntity;
use LatamPMDevs\minerware\entity\object\LeaderboardText;
use LatamPMDevs\minerware\entity\object\TopPlayerNpc;
use LatamPMDevs\minerware\Minerware;
use pocketmine\command\CommandSender;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use function fmod;
use function is_int;

final class LeaderboardCommand extends BaseSubCommand {

	private const TEXT = "text";
	private const NPC = "npc";
	private const REMOVE = "remove";

	public function __construct(private Minerware $plugin) {
		parent::__construct("leaderboard", "Manage the wins leaderboard and the top player NPCs.");
		$this->setPermission("minerware.command.leaderboard");
	}

	protected function prepare() : void {
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->registerArgument(0, new ChoiceArgument("action", self::TEXT, self::NPC, self::REMOVE));
		$this->registerArgument(1, new IntegerArgument("rank", true));
	}

	/**
	 * @param Player $sender
	 * @param array<string, mixed> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$translator = $this->plugin->getTranslator();
		$location = $sender->getLocation();
		$facing = Location::fromObject($location, $location->getWorld(), fmod($location->getYaw() + 180, 360), 0);
		switch ($args["action"] ?? null) {
			case self::TEXT:
				(new LeaderboardText($facing, LeaderboardEntity::invisibleSkin()))->spawnToAll();
				$sender->sendMessage($translator->translate($sender, "leaderboard.created"));
				break;

			case self::NPC:
				$rank = $args["rank"] ?? null;
				if (!is_int($rank) || $rank < 1 || $rank > TopPlayerNpc::RANKS) {
					$sender->sendMessage($translator->translate($sender, "leaderboard.rankInvalid", ["{%max}" => TopPlayerNpc::RANKS]));
					return;
				}
				(new TopPlayerNpc($facing, LeaderboardEntity::invisibleSkin()))->setRank($rank)->spawnToAll();
				$sender->sendMessage($translator->translate($sender, "leaderboard.created"));
				break;

			case self::REMOVE:
				$this->plugin->getJoinPointRemovals()->arm($sender);
				$sender->sendMessage($translator->translate($sender, "leaderboard.remove.armed"));
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
