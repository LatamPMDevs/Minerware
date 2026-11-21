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

use Logger;
use pocketmine\world\format\io\exception\CorruptedWorldException;
use pocketmine\world\format\io\WorldProvider;
use pocketmine\world\format\io\WorldProviderManagerEntry;
use function file_get_contents;
use function is_file;
use function trim;

/**
 * Recognises the dummy folders of arena worlds by their marker file.
 */
final class MapWorldProviderEntry extends WorldProviderManagerEntry {

	public function __construct() {
		parent::__construct(static fn (string $path) : bool => is_file($path . DIRECTORY_SEPARATOR . MapTemplates::INSTANCE_MARKER));
	}

	public function fromPath(string $path, Logger $logger) : WorldProvider {
		$map = file_get_contents($path . DIRECTORY_SEPARATOR . MapTemplates::INSTANCE_MARKER);
		if ($map === false) {
			throw new CorruptedWorldException("Unreadable instance marker");
		}
		$template = MapTemplates::getInstance()->get(trim($map)) ?? throw new CorruptedWorldException("Map template '" . trim($map) . "' is not loaded");
		return new MapWorldProvider($path, $template);
	}
}