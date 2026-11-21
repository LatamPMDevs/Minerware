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

namespace LatamPMDevs\minerware\map\world;

use Generator;
use Logger;
use pocketmine\utils\Utils;
use pocketmine\world\format\io\ChunkData;
use pocketmine\world\format\io\LoadedChunkData;
use pocketmine\world\format\io\WorldData;
use pocketmine\world\format\io\WritableWorldProvider;

/**
 * Serves the chunks of a MapTemplate to one arena world.
 */
final class MapWorldProvider implements WritableWorldProvider {

	private readonly MapWorldData $worldData;

	private bool $closed = false;

	public function __construct(private readonly string $path, private readonly MapTemplate $template) {
		$template->retain();
		$this->worldData = new MapWorldData($template->getWorldData());
	}

	public static function cloneChunk(ChunkData $chunk) : ChunkData {
		return new ChunkData(
			Utils::cloneObjectArray($chunk->getSubChunks()),
			$chunk->isPopulated(),
			Utils::cloneObjectArray($chunk->getEntityNBT()),
			Utils::cloneObjectArray($chunk->getTileNBT())
		);
	}

	public function getWorldMinY() : int {
		return $this->template->getMinY();
	}

	public function getWorldMaxY() : int {
		return $this->template->getMaxY();
	}

	public function getPath() : string {
		return $this->path;
	}

	public function loadChunk(int $chunkX, int $chunkZ) : ?LoadedChunkData {
		$chunk = $this->template->getChunk($chunkX, $chunkZ);
		if ($chunk === null) {
			return null;
		}
		return new LoadedChunkData(self::cloneChunk($chunk), false, LoadedChunkData::FIXER_FLAG_NONE);
	}

	public function saveChunk(int $chunkX, int $chunkZ, ChunkData $chunkData, int $dirtyFlags) : void {
		# Instances are volatile.
	}

	public function doGarbageCollection() : void { }

	public function getWorldData() : WorldData {
		return $this->worldData;
	}

	public function close() : void {
		if ($this->closed) {
			return;
		}
		$this->closed = true;
		$this->template->release();
	}

	/**
	 * @return Generator<array{int, int}, LoadedChunkData, void, void>
	 */
	public function getAllChunks(bool $skipCorrupted = false, ?Logger $logger = null) : Generator {
		foreach ($this->template->getAllChunks() as $xz => $chunk) {
			yield $xz => new LoadedChunkData(self::cloneChunk($chunk), false, LoadedChunkData::FIXER_FLAG_NONE);
		}
	}

	public function calculateChunkCount() : int {
		return $this->template->calculateChunkCount();
	}
}