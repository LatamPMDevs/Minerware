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

namespace LatamPMDevs\minerware\form;

use jojoe77777\FormAPI\SimpleForm;
use LatamPMDevs\minerware\arena\Arena;
use LatamPMDevs\minerware\arena\ArenaManager;
use LatamPMDevs\minerware\arena\phase\StartingPhase;
use LatamPMDevs\minerware\Minerware;
use pocketmine\player\Player;
use function array_values;
use function count;
use function is_int;

/**
 * Lets an operator look at the running arenas and act on them.
 */
final class ArenaAdminForm {

	private const BUTTON_START = "start";
	private const BUTTON_PAUSE = "pause";
	private const BUTTON_KICK = "kick";
	private const BUTTON_CLOSE = "close";
	private const BUTTON_BACK = "back";

	public function sendList(Player $admin) : void {
		$translator = Minerware::getInstance()->getTranslator();
		$arenas = array_values(ArenaManager::getInstance()->getArenas());
		$form = new SimpleForm(function (Player $admin, mixed $data) use ($arenas) : void {
			if (is_int($data) && isset($arenas[$data])) {
				$this->sendArena($admin, $arenas[$data]);
			}
		});
		$form->setTitle($translator->translate($admin, "admin.title"));
		if (count($arenas) === 0) {
			$form->setContent($translator->translate($admin, "admin.noArenas"));
		}
		foreach ($arenas as $arena) {
			$form->addButton($translator->translate($admin, "admin.arena", [
				"{%id}" => $arena->getId(),
				"{%map}" => $arena->getMap()->getName(),
				"{%status}" => $arena->getPhase()->getName(),
				"{%players}" => count($arena->getPlayers()),
				"{%max}" => Arena::MAX_PLAYERS
			]));
		}
		$form->addButton($translator->translate($admin, "text.close"));
		$form->sendToPlayer($admin);
	}

	public function sendArena(Player $admin, Arena $arena) : void {
		$translator = Minerware::getInstance()->getTranslator();
		$form = new SimpleForm(function (Player $admin, mixed $data) use ($arena) : void {
			if ($arena->isClosed()) {
				return;
			}
			switch ($data) {
				case self::BUTTON_START:
					$arena->forceStart();
					break;
				case self::BUTTON_PAUSE:
					$arena->setCountdownPaused(!$arena->isCountdownPaused());
					break;
				case self::BUTTON_KICK:
					$this->sendKick($admin, $arena);
					break;
				case self::BUTTON_CLOSE:
					$arena->close();
					break;
				case self::BUTTON_BACK:
					$this->sendList($admin);
					break;
			}
		});
		$form->setTitle($translator->translate($admin, "admin.arena", [
			"{%id}" => $arena->getId(),
			"{%map}" => $arena->getMap()->getName(),
			"{%status}" => $arena->getPhase()->getName(),
			"{%players}" => count($arena->getPlayers()),
			"{%max}" => Arena::MAX_PLAYERS
		]));
		if ($arena->getPhase() instanceof StartingPhase) {
			$form->addButton($translator->translate($admin, "admin.forceStart"), -1, "", self::BUTTON_START);
			$form->addButton($translator->translate($admin, $arena->isCountdownPaused() ? "admin.resume" : "admin.pause"), -1, "", self::BUTTON_PAUSE);
		}
		$form->addButton($translator->translate($admin, "admin.kick"), -1, "", self::BUTTON_KICK);
		$form->addButton($translator->translate($admin, "admin.closeArena"), -1, "", self::BUTTON_CLOSE);
		$form->addButton($translator->translate($admin, "admin.back"), -1, "", self::BUTTON_BACK);
		$form->sendToPlayer($admin);
	}

	private function sendKick(Player $admin, Arena $arena) : void {
		$translator = Minerware::getInstance()->getTranslator();
		$players = array_values($arena->getPlayers());
		$form = new SimpleForm(function (Player $admin, mixed $data) use ($arena, $players) : void {
			$target = is_int($data) ? ($players[$data] ?? null) : null;
			if ($target !== null && !$arena->isClosed() && $arena->inGame($target)) {
				$arena->kick($target);
			}
		});
		$form->setTitle($translator->translate($admin, "admin.kick"));
		foreach ($players as $player) {
			$form->addButton($player->getName());
		}
		$form->sendToPlayer($admin);
	}
}
