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
 * Copyright 2021 © Minerware
 */

declare(strict_types=1);

namespace minerware\database;

use RuntimeException;

final class DataHolder {
    
    public function __construct(private array $data) { }
    
    public function hasData(string $key): bool {
        return isset($this->data[$key]);
    }
    
    private function checkKeyExists(string $key): void {
        if (!$this->hasData($key)) {
            throw new RuntimeException("Unable to find the value for the key '$key'! Key is not in the data.");
        }
    }
    
    public function getString(string $key): string {
        $this->checkKeyExists($key);
        return (string) $this->data[$key];
    }
    
    public function getBool(string $key): bool {
        $this->checkKeyExists($key);
        return (bool) $this->data[$key];
    }
    
    public function getInteger(string $key): int {
        $this->checkKeyExists($key);
        return (int) $this->data[$key];
    }
    
    public function getFloat(string $key): float {
        $this->checkKeyExists($key);
        return (float) $this->data[$key];
    }
    
    public function getJsonData(): string {
        return (string) json_encode($this->data);
    }
}