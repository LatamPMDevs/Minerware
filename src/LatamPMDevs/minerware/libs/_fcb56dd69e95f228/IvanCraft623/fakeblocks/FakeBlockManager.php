<?php

declare(strict_types=1);

namespace LatamPMDevs\minerware\libs\_fcb56dd69e95f228\IvanCraft623\fakeblocks;

use Exception;
use LatamPMDevs\minerware\libs\_fcb56dd69e95f228\muqsit\simplepackethandler\SimplePacketHandler;
use pocketmine\block\Block;
use pocketmine\event\EventPriority;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPostChunkSendEvent;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use pocketmine\world\World;
use function count;
use function spl_object_id;

final class FakeBlockManager implements Listener {
	use SingletonTrait {
		setInstance as private;
		reset as private;
	}

	/**
	 * @var array<int, array<int, FakeBlock[]>>
	 */
	private array $fakeblocks = [];

	private function __construct() {
		self::setInstance($this);
	}

	private static bool $isRegistered = false;

	public static function isRegistered() : bool {
		return self::$isRegistered;
	}

	public static function register(PluginBase $registrant) : void {
		if (self::$isRegistered) {
			throw new Exception("FakeBlock listener is already registered by another plugin.");
		}
		$instance = new self();
		$registrant->getServer()->getPluginManager()->registerEvents($instance, $registrant);

		$interceptor = SimplePacketHandler::createInterceptor($registrant, EventPriority::HIGHEST);
		$interceptor->interceptOutgoing(function (UpdateBlockPacket $packet, NetworkSession $target) use ($instance) : bool {
			$player = $target->getPlayer();
			if ($player !== null) {
				$bpos = $packet->blockPosition;
				foreach ($instance->getFakeBlocksAtPosition(new Position($bpos->getX(), $bpos->getY(), $bpos->getZ(), $player->getWorld())) as $fakeblock) {
					if ($fakeblock->isViewer($player)) {
						if ($fakeblock->isInBlockUpdatePacketQueue($player)) {
							$fakeblock->blockUpdatePacketQueue($player, false);
						} else {
							return false;
						}
					}
				}
			}
			return true;
		});
	}

	public function create(Block $block, Position $position) : FakeBlock {
		$pos = Position::fromObject($position->floor(), $position->getWorld());
		$fakeblock = new FakeBlock($block, $pos);
		$chunkHash = World::chunkHash($pos->getFloorX() >> Chunk::COORD_BIT_SIZE, $pos->getFloorZ() >> Chunk::COORD_BIT_SIZE);
		$this->fakeblocks[spl_object_id($pos->getWorld())][$chunkHash][spl_object_id($fakeblock)] = $fakeblock;
		return $fakeblock;
	}

	public function destroy(FakeBlock $fakeblock) : void {
		foreach ($fakeblock->getViewers() as $viewer) {
			$fakeblock->removeViewer($viewer);
		}
		$pos = $fakeblock->getPosition();
		$chunkHash = World::chunkHash($pos->getFloorX() >> Chunk::COORD_BIT_SIZE, $pos->getFloorZ() >> Chunk::COORD_BIT_SIZE);
		unset($this->fakeblocks[spl_object_id($pos->getWorld())][$chunkHash][spl_object_id($fakeblock)]);
	}

	/**
	 * Returns the FakeBlocks in a Chunk
	 * @return FakeBlock[]
	 */
	public function getFakeBlocksAt(World $world, int $chunkX, int $chunkZ) : array {
		return $this->fakeblocks[spl_object_id($world)][World::chunkHash($chunkX, $chunkZ)] ?? [];
	}

	/**
	 * Returns the FakeBlocks in a Position
	 * @return FakeBlock[]
	 */
	public function getFakeBlocksAtPosition(Position $position) : array {
		$pos = $position->floor();
		$fakeblocks = [];
		foreach ($this->getFakeBlocksAt($position->getWorld(), $pos->getX() >> Chunk::COORD_BIT_SIZE, $pos->getZ() >> Chunk::COORD_BIT_SIZE) as $fakeblock) {
			if ($fakeblock->getPosition()->equals($pos)) {
				$fakeblocks[] = $fakeblock;
			}
		}
		return $fakeblocks;
	}

	/**
	 * @param array<int, Block|FakeBlock> $blocks
	 *
	 * @return UpdateBlockPacket[]
	 */
	public function createBlockUpdatePackets(Player $player, array $blocks) : array {
		$packets = [];

		$blockTranslator = $player->getNetworkSession()->getTypeConverter()->getBlockTranslator();
		foreach ($blocks as $b) {
			if ($b instanceof FakeBlock) {
				$b->blockUpdatePacketQueue($player, true);
				$stateId = $b->getBlock()->getStateId();
			} elseif ($b instanceof Block) {
				$stateId = $b->getStateId();
			}

			$blockPosition = BlockPosition::fromVector3($b->getPosition());
			$packets[] = UpdateBlockPacket::create(
				$blockPosition,
				$blockTranslator->internalIdToNetworkId($stateId),
				UpdateBlockPacket::FLAG_NETWORK,
				UpdateBlockPacket::DATA_LAYER_NORMAL
			);
		}

		return $packets;
	}

	public function onChunkSend(PlayerPostChunkSendEvent $event) : void {
		$player = $event->getPlayer();
		$fakeblocks = [];

		foreach ($this->getFakeBlocksAt($player->getWorld(), $event->getChunkX(), $event->getChunkZ()) as $fakeblock) {
			if ($fakeblock->isViewer($player)) {
				$fakeblocks[] = $fakeblock;
			}
		}

		if (count($fakeblocks) > 0) {
			foreach ($this->createBlockUpdatePackets($player, $fakeblocks) as $packet) {
				$player->getNetworkSession()->sendDataPacket($packet);
			}
		}
	}
}