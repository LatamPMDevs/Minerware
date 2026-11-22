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

use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;
use LatamPMDevs\minerware\arena\Arena;
use LatamPMDevs\minerware\arena\ArenaManager;
use LatamPMDevs\minerware\arena\ArenaRules;
use LatamPMDevs\minerware\arena\microgame\Level;
use LatamPMDevs\minerware\arena\microgame\MicrogameManager;
use LatamPMDevs\minerware\arena\phase\StartingPhase;
use LatamPMDevs\minerware\Minerware;
use pocketmine\player\Player;
use function array_merge;
use function array_values;
use function count;
use function gmdate;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function max;
use function trim;

/**
 * Lets an operator look at the running arenas and act on them. The rules of a game can be changed until it starts.
 */
final class ArenaAdminForm {

	private const BUTTON_START = "start";
	private const BUTTON_PAUSE = "pause";
	private const BUTTON_RULES = "rules";
	private const BUTTON_SKIP = "skip";
	private const BUTTON_NEXT = "next";
	private const BUTTON_ANNOUNCE = "announce";
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
				"{%id}" => $arena->getShortId(),
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
					$arena->getCountdown()->setPaused(!$arena->getCountdown()->isPaused());
					break;
				case self::BUTTON_RULES:
					$this->sendRules($admin, $arena);
					break;
				case self::BUTTON_SKIP:
					if ($arena->getCurrentMicrogame()?->isRunning() === true) {
						$arena->broadcast("admin.skipped");
						$arena->endCurrentMicrogame();
					}
					break;
				case self::BUTTON_NEXT:
					$this->sendNext($admin, $arena);
					break;
				case self::BUTTON_ANNOUNCE:
					$this->sendAnnouncement($admin, $arena);
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
		$phase = $arena->getPhase();
		$current = $arena->getCurrentMicrogame();
		$next = $arena->getQueue()->peek();
		$form->setTitle($translator->translate($admin, "admin.arena.title", [
			"{%id}" => $arena->getShortId(),
			"{%map}" => $arena->getMap()->getName()
		]));
		$form->setContent($translator->translate($admin, "admin.arena.info", [
			"{%status}" => $phase->getName(),
			"{%players}" => count($arena->getPlayers()),
			"{%max}" => Arena::MAX_PLAYERS,
			"{%min}" => $arena->getMinPlayers(),
			"{%time}" => gmdate("i:s", max($arena->getCountdown()->getRemaining(), 0)),
			"{%microgame}" => $current?->getName() ?? "-",
			"{%next}" => $next?->getName() ?? "-"
		]));
		if ($phase instanceof StartingPhase) {
			$form->addButton($translator->translate($admin, "admin.forceStart"), -1, "", self::BUTTON_START);
			$form->addButton($translator->translate($admin, $arena->getCountdown()->isPaused() ? "admin.resume" : "admin.pause"), -1, "", self::BUTTON_PAUSE);
		}
		if ($phase->isLobby()) {
			$form->addButton($translator->translate($admin, "admin.rules"), -1, "", self::BUTTON_RULES);
		}
		if ($current?->isRunning() === true) {
			$form->addButton($translator->translate($admin, "admin.skip"), -1, "", self::BUTTON_SKIP);
		}
		if ($next !== null) {
			$form->addButton($translator->translate($admin, "admin.chooseNext"), -1, "", self::BUTTON_NEXT);
		}
		$form->addButton($translator->translate($admin, "admin.announce"), -1, "", self::BUTTON_ANNOUNCE);
		$form->addButton($translator->translate($admin, "admin.kick"), -1, "", self::BUTTON_KICK);
		$form->addButton($translator->translate($admin, "admin.closeArena"), -1, "", self::BUTTON_CLOSE);
		$form->addButton($translator->translate($admin, "admin.back"), -1, "", self::BUTTON_BACK);
		$form->sendToPlayer($admin);
	}

	/**
	 * What the game will be like, until it starts. Everyone in the arena is told that the rules changed.
	 */
	private function sendRules(Player $admin, Arena $arena) : void {
		$translator = Minerware::getInstance()->getTranslator();
		$rules = $arena->getRules();
		$queue = $arena->getQueue();
		$form = new CustomForm(function (Player $admin, mixed $data) use ($arena) : void {
			if (!is_array($data) || $arena->isClosed() || !$arena->getPhase()->isLobby()) {
				return;
			}
			$rules = $arena->getRules();
			$rules->setStartingTime(self::slider($data, "starting", $rules->getStartingTime()));
			$arena->setMinPlayers(max(self::slider($data, "minPlayers", $arena->getMinPlayers()), 2));
			$arena->getQueue()->limit(self::slider($data, "microgames", $arena->getQueue()->getNormalCount()));
			if ($arena->getPhase() instanceof StartingPhase) {
				$arena->getCountdown()->set($rules->getStartingTime());
			}
			$translator = Minerware::getInstance()->getTranslator();
			$arena->broadcast("admin.rules.changed");
			$admin->sendMessage($translator->translate($admin, "admin.rules.saved"));
		});
		$form->setTitle($translator->translate($admin, "admin.rules"));
		$form->addSlider($translator->translate($admin, "admin.rules.microgames"), ArenaRules::MIN_MICROGAMES, $queue->getNormalCount(), 1, $queue->getNormalCount(), "microgames");
		$form->addSlider($translator->translate($admin, "admin.rules.starting"), ArenaRules::MIN_STARTING_TIME, ArenaRules::MAX_STARTING_TIME, 5, $rules->getStartingTime(), "starting");
		$form->addSlider($translator->translate($admin, "admin.rules.minPlayers"), 2, Arena::MAX_PLAYERS, 1, $arena->getMinPlayers(), "minPlayers");
		$form->sendToPlayer($admin);
	}

	/**
	 * Picks the microgame that comes next, boss games included.
	 */
	private function sendNext(Player $admin, Arena $arena) : void {
		$translator = Minerware::getInstance()->getTranslator();
		$manager = MicrogameManager::getInstance();
		$classes = array_values(array_merge($manager->getMicrogames(), $manager->getBossgames()));
		$games = [];
		foreach ($classes as $class) {
			$games[] = new $class($arena);
		}
		$form = new SimpleForm(function (Player $admin, mixed $data) use ($arena, $games) : void {
			$microgame = is_int($data) ? ($games[$data] ?? null) : null;
			if ($microgame === null || $arena->isClosed() || $arena->getQueue()->peek() === null) {
				return;
			}
			$arena->getQueue()->setNext($microgame);
			$translator = Minerware::getInstance()->getTranslator();
			$admin->sendMessage($translator->translate($admin, "admin.next.chosen", ["{%microgame}" => $microgame->getName()]));
		});
		$form->setTitle($translator->translate($admin, "admin.chooseNext"));
		$form->setContent($translator->translate($admin, "admin.next.content"));
		foreach ($games as $game) {
			$form->addButton(($game->getLevel() === Level::BOSS ? "§c" : "") . $game->getName());
		}
		$form->sendToPlayer($admin);
	}

	private function sendAnnouncement(Player $admin, Arena $arena) : void {
		$translator = Minerware::getInstance()->getTranslator();
		$form = new CustomForm(function (Player $admin, mixed $data) use ($arena) : void {
			$text = is_array($data) && is_string($data["text"] ?? null) ? trim($data["text"]) : "";
			if ($text !== "" && !$arena->isClosed()) {
				$arena->sendAnnouncement($text);
			}
		});
		$form->setTitle($translator->translate($admin, "admin.announce"));
		$form->addInput($translator->translate($admin, "admin.announce.text"), "", null, "text");
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

	/**
	 * @param array<mixed, mixed> $data
	 */
	private static function slider(array $data, string $key, int $default) : int {
		$value = $data[$key] ?? null;
		return is_int($value) || is_float($value) ? (int) $value : $default;
	}
}
