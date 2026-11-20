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

namespace LatamPMDevs\minerware\item;

use IvanCraft623\languages\Language;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

/**
 * The items with a custom behavior. Each carries a tag named after its constant,
 * which is what whoever handles it looks for in the item's named tag.
 */
final class SpecialItems {

	public const LEAVE_GAME = "minerware:leave_game";
	public const EDITOR_ROD = "minerware:editor_rod";

	private function __construct() {
		# Static collection
	}

	public static function LEAVE_GAME_BED(Language $language) : Item {
		return self::create(VanillaBlocks::BED()->setColor(DyeColor::RED)->asItem(), self::LEAVE_GAME, $language, "item.leave");
	}

	public static function EDITOR_BLAZE_ROD(Language $language) : Item {
		return self::create(VanillaItems::BLAZE_ROD(), self::EDITOR_ROD, $language, "item.editor");
	}

	private static function create(Item $item, string $tag, Language $language, string $nameKey) : Item {
		$item->setCustomName($language->getTranslation($nameKey) ?? $nameKey);
		$item->getNamedTag()->setByte($tag, 1);
		return $item;
	}
}
