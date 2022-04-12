<?php

/**
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
 * Copyright 2022 © LatamPMDevs
 */

declare(strict_types=1);

namespace LatamPMDevs\minerware\database;

use RuntimeException;
use function json_encode;

final class DataHolder {

	/**
	 * @param array<string, mixed>
	 */
	public function __construct(private array $data) { }

	public function hasData(string $key) : bool {
		return isset($this->data[$key]);
	}

	private function checkKeyExists(string $key) : void {
		if (!$this->hasData($key)) {
			throw new RuntimeException("Unable to find the value for the key '$key'! Key is not in the data.");
		}
	}

	public function getString(string $key) : string {
		$this->checkKeyExists($key);
		return (string) $this->data[$key];
	}

	public function getBool(string $key) : bool {
		$this->checkKeyExists($key);
		return (bool) $this->data[$key];
	}

	public function getInteger(string $key) : int {
		$this->checkKeyExists($key);
		return (int) $this->data[$key];
	}

	public function getFloat(string $key) : float {
		$this->checkKeyExists($key);
		return (float) $this->data[$key];
	}

	public function getArray(string $key) : array {
		$this->checkKeyExists($key);
		return (array) $this->data[$key];
	}

	public function getJsonData() : string {
		return (string) json_encode($this->data);
	}

	public function getAll() : array {
		return $this->data;
	}
}
