<?php

declare(strict_types=1);

namespace LatamPMDevs\minerware\libs\_fcb56dd69e95f228\IvanCraft623\fakeblocks;

use pocketmine\block\Block;
use pocketmine\player\Player;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;

class FakeBlock {

	/**
	 * @var array<int, Player>
	 */
	protected array $viewers = [];

	/**
	 * @var array<int, Player>
	 */
	protected array $blockUpdatePacketQueue = [];

	public function __construct(protected Block $block, Position $pos) {
		$block->position($pos->getWorld(), (int) $pos->x,  (int) $pos->y,  (int) $pos->z);
	}

	public function getBlock() : Block {
		return $this->block;
	}

	public function getPosition() : Position {
		return $this->block->getPosition();
	}

	public function getViewers() : array {
		return $this->viewers;
	}

	public function isViewer(Player $player) : bool {
		return isset($this->viewers[$player->getId()]);
	}

	public function addViewer(Player $player) : void {
		$this->viewers[$player->getId()] = $player;

		$pos = $this->getPosition();
		if ($pos->getWorld() === $player->getWorld() &&
			$player->isUsingChunk($pos->getFloorX() >> Chunk::COORD_BIT_SIZE, $pos->getFloorZ() >> Chunk::COORD_BIT_SIZE)
		) {
			$packets = FakeBlockManager::getInstance()->createBlockUpdatePackets($player, [$this]);
			foreach ($packets as $packet) {
				$player->getNetworkSession()->sendDataPacket($packet);
			}
		}
	}

	public function removeViewer(Player $player) : void {
		unset($this->viewers[$player->getId()]);

		$pos = $this->getPosition();
		$world = $this->getPosition()->getWorld();
		if ($world === $player->getWorld() &&
			$player->isUsingChunk($pos->getFloorX() >> Chunk::COORD_BIT_SIZE, $pos->getFloorZ() >> Chunk::COORD_BIT_SIZE)
		) {
			$packets = FakeBlockManager::getInstance()->createBlockUpdatePackets($player, [$world->getBlock($pos)]);
			foreach ($packets as $packet) {
				$player->getNetworkSession()->sendDataPacket($packet);
			}
		}
	}

	/**
	 * @internal
	 */
	public function isInBlockUpdatePacketQueue(Player $player) : bool {
		return isset($this->blockUpdatePacketQueue[$player->getId()]);
	}

	/**
	 * @internal
	 */
	public function blockUpdatePacketQueue(Player $player, bool $bool) : void {
		if ($bool) {
			$this->blockUpdatePacketQueue[$player->getId()] = $player;
		} else {
			unset($this->blockUpdatePacketQueue[$player->getId()]);
		}
	}
}