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
use pocketmine\world\format\io\ChunkData;
use pocketmine\world\format\io\WorldData;
use pocketmine\world\format\io\WorldProvider;
use pocketmine\world\World;
use function array_key_exists;

/**
 * A map world opened once.
 */
final class MapTemplate {

	/** @var array<int, ?ChunkData> World::chunkHash() => pristine chunk, null if the map has none */
	private array $chunks = [];

	/** Number of instances reading from this template. */
	private int $users = 0;

	private bool $retired = false;

	private bool $closed = false;

	public function __construct(private readonly WorldProvider $provider) { }

	public function getWorldData() : WorldData {
		return $this->provider->getWorldData();
	}

	public function getMinY() : int {
		return $this->provider->getWorldMinY();
	}

	public function getMaxY() : int {
		return $this->provider->getWorldMaxY();
	}

	/**
	 * The pristine chunk.
	 */
	public function getChunk(int $chunkX, int $chunkZ) : ?ChunkData {
		$hash = World::chunkHash($chunkX, $chunkZ);
		if (!array_key_exists($hash, $this->chunks)) {
			$this->chunks[$hash] = $this->provider->loadChunk($chunkX, $chunkZ)?->getData();
		}
		return $this->chunks[$hash];
	}

	/**
	 * @return Generator<array{int, int}, ChunkData, void, void>
	 */
	public function getAllChunks() : Generator {
		foreach ($this->provider->getAllChunks() as $xz => $loaded) {
			yield $xz => $loaded->getData();
		}
	}

	public function calculateChunkCount() : int {
		return $this->provider->calculateChunkCount();
	}

	public function retain() : void {
		$this->users++;
	}

	public function release() : void {
		$this->users--;
		$this->closeIfUnused();
	}

	/**
	 * Called when a newer template replaces this one: closes it once the last instance is gone.
	 */
	public function retire() : void {
		$this->retired = true;
		$this->closeIfUnused();
	}

	public function close() : void {
		if ($this->closed) {
			return;
		}
		$this->closed = true;
		$this->chunks = [];
		$this->provider->close();
	}

	private function closeIfUnused() : void {
		if ($this->retired && $this->users <= 0) {
			$this->close();
		}
	}
}
