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

use LatamPMDevs\minerware\libs\_189c4e77bf695c21\IvanCraft623\languages\Language;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\PotionType;
use pocketmine\item\VanillaItems;

/**
 * The items given to players. Those with a behavior carry a tag named after their constant.
 */
final class SpecialItems {

	public const LEAVE_GAME = "minerware:leave_game";
	public const EDITOR_ROD = "minerware:editor_rod";
	public const COLOR_MISSILE = "minerware:color_missile";

	private function __construct() {
		# Static collection
	}

	public static function LEAVE_GAME_BED(Language $language) : Item {
		return self::create(VanillaBlocks::BED()->setColor(DyeColor::RED)->asItem(), self::LEAVE_GAME, $language, "item.leave");
	}

	public static function EDITOR_BLAZE_ROD(Language $language) : Item {
		return self::create(VanillaItems::BLAZE_ROD(), self::EDITOR_ROD, $language, "item.editor");
	}

	public static function POWER_STICK(Language $language, int $knockbackLevel) : Item {
		return self::create(VanillaItems::STICK(), null, $language, "item.powerstick")
			->addEnchantment(new EnchantmentInstance(VanillaEnchantments::KNOCKBACK(), $knockbackLevel));
	}

	public static function COLORIZER_HOE(Language $language) : Item {
		return self::create(VanillaItems::DIAMOND_HOE(), null, $language, "item.colorizer");
	}

	public static function COLOR_MISSILE(Language $language, int $count) : Item {
		return self::create(VanillaItems::SPLASH_POTION()->setType(PotionType::LEAPING), self::COLOR_MISSILE, $language, "item.colormissile")
			->setCount($count);
	}

	public static function COLOR_CLAY(Language $language, DyeColor $color) : Item {
		return self::create(VanillaBlocks::STAINED_CLAY()->setColor($color)->asItem(), null, $language, "item.yourcolor");
	}

	public static function GOD_PICKAXE(Language $language, int $efficiencyLevel) : Item {
		return self::create(VanillaItems::DIAMOND_PICKAXE(), null, $language, "item.pickaxe")
			->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), $efficiencyLevel));
	}

	public static function ETERNITY_SWORD(Language $language, int $sharpnessLevel) : Item {
		return self::create(VanillaItems::DIAMOND_SWORD(), null, $language, "item.sword")
			->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), $sharpnessLevel));
	}

	private static function create(Item $item, ?string $tag, Language $language, string $nameKey) : Item {
		$item->setCustomName($language->getTranslation($nameKey) ?? $nameKey);
		if ($tag !== null) {
			$item->getNamedTag()->setByte($tag, 1);
		}
		return $item;
	}
}