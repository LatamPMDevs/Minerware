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
use PHPUnit\Framework\TestCase;
use pocketmine\math\Vector3;
use pocketmine\world\format\io\ChunkData;
use pocketmine\world\format\io\LoadedChunkData;
use pocketmine\world\format\io\WorldData;
use pocketmine\world\format\io\WorldProvider;

final class MapWorldProviderTest extends TestCase {

	/**
	 * @return array{WorldProvider, object}
	 */
	private static function fakeProvider() : array {
		$data = new class implements WorldProvider {
			public int $loads = 0;
			public bool $closed = false;
			public WorldData $worldData;

			public function getWorldMinY() : int {
				return 0;
			}

			public function getWorldMaxY() : int {
				return 256;
			}

			public function getPath() : string {
				return "template";
			}

			public function loadChunk(int $chunkX, int $chunkZ) : ?LoadedChunkData {
				$this->loads++;
				return $chunkX === 0 ? new LoadedChunkData(new ChunkData([], true, [], []), false, LoadedChunkData::FIXER_FLAG_NONE) : null;
			}

			public function doGarbageCollection() : void { }

			public function getWorldData() : WorldData {
				return $this->worldData;
			}

			public function close() : void {
				$this->closed = true;
			}

			public function getAllChunks(bool $skipCorrupted = false, ?Logger $logger = null) : Generator {
				yield from [];
			}

			public function calculateChunkCount() : int {
				return 1;
			}
		};
		return [$data, $data];
	}

	private function template() : MapTemplate {
		[$provider] = self::fakeProvider();
		$worldData = $this->createMock(WorldData::class);
		$worldData->method("getSpawn")->willReturn(new Vector3(1, 2, 3));
		$worldData->method("getName")->willReturn("Map");
		$provider->worldData = $worldData;
		return new MapTemplate($provider);
	}

	public function testChunksAreClonedAndCachedOnce() : void {
		$template = $this->template();
		$provider = new MapWorldProvider("a", $template);

		$first = $provider->loadChunk(0, 0);
		$second = $provider->loadChunk(0, 0);
		self::assertNotNull($first);
		self::assertNotNull($second);
		self::assertNotSame($first->getData(), $second->getData());
		self::assertNotSame($template->getChunk(0, 0), $first->getData());
		self::assertNull($provider->loadChunk(1, 0));
		self::assertNull($provider->loadChunk(1, 0));
	}

	public function testSavingDoesNotTouchTheTemplate() : void {
		$template = $this->template();
		$provider = new MapWorldProvider("a", $template);
		$pristine = $template->getChunk(0, 0);
		$provider->saveChunk(0, 0, new ChunkData([], false, [], []), 0);
		self::assertSame($pristine, $template->getChunk(0, 0));
	}

	public function testWorldDataIsACopy() : void {
		$provider = new MapWorldProvider("a", $this->template());
		$provider->getWorldData()->setName("changed");
		self::assertSame("changed", $provider->getWorldData()->getName());
		self::assertEquals(new Vector3(1, 2, 3), $provider->getWorldData()->getSpawn());
	}

	public function testRetiredTemplateClosesWhenLastInstanceCloses() : void {
		[$fake] = self::fakeProvider();
		$fake->worldData = $this->createMock(WorldData::class);
		$template = new MapTemplate($fake);
		$a = new MapWorldProvider("a", $template);
		$b = new MapWorldProvider("b", $template);

		$template->retire();
		self::assertFalse($fake->closed);
		$a->close();
		$a->close();
		self::assertFalse($fake->closed);
		$b->close();
		self::assertTrue($fake->closed);
	}
}
