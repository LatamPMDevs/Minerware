<?php

/**
 *  ███╗   ███╗██╗███╗   ██╗███████╗██████╗ ██╗    ██╗ █████╗ ██████╗ ███████╗
 *  ████╗ ████║██║████╗  ██║██╔════╝██╔══██╗██║    ██║██╔══██╗██╔══██╗██╔════╝
 *  ██╔████╔██║██║██╔██╗ ██║█████╗  ██████╔╝██║ █╗ ██║███████║██████╔╝█████╗
 *  ██║╚██╔╝██║██║██║╚██╗██║██╔══╝  ██╔══██╗██║███╗██║██╔══██║██╔══██╗██╔══╝
 *  ██║ ╚═╝ ██║██║██║ ╚████║███████╗██║  ██║╚███╔███╔╝██║  ██║██║  ██║███████╗
 *  ╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝ ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝
 *
 * This is a private project, your not allow to redistribute nor resell it.
 * The only ones with that power are this project's contributors.
 *
 * Copyright 2022 © Minerware
 */

declare(strict_types=1);

namespace minerware\utils;

use minerware\database\DataHolder;
use pocketmine\block\BlockFactory;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\Position;
use function floor;
use function intval;

final class Structure {
	use SingletonTrait;

	private string $name;

	private array $changedBlocks;

	public function __construct(private DataHolder $data, private Position $pos) {
		$this->name = $data->getString("name");
	}

	public function getName(): string {
		return $this->name;
	}

	public function getReference(): Position {
		return $this->pos;
	}

	public function set(): void {
		$this->build($this->data->getArray("Blocks"));
	}

	public function unset(): void {
		$this->build($this->changedBlocks);
	}

	private function build(array $blocks): void {
		$world = $this->pos->getWorld();
		$this->changedBlocks = [];
		foreach ($blocks as $data => $block) {
			$pos = $this->pos->add(intval($block["X"]), intval($block["Y"]), intval($block["Z"]));
			if ($world->isInWorld((int) floor($pos->x), (int) floor($pos->y), (int) floor($pos->z))) {
				$changedBlock = $world->getBlock($pos);
				$this->changedBlocks[] = ["X" => $block["X"], "Y" => $block["Y"], "Z" => $block["Z"], "ID" => $changedBlock->getId(), "Meta" => $changedBlock->getMeta()];
				$world->setBlock($pos, BlockFactory::getInstance()->get($block["ID"], $block["Meta"]));
			}
		}
	}
}
