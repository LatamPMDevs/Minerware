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

namespace minerware;

use http\Exception\RuntimeException;
use JetBrains\PhpStorm\Pure;
use minerware\command\CommandFactory;
use minerware\database\DataManager;
use minerware\language\Translator;
use pocketmine\lang\TranslationContainer;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;

final class Minerware extends PluginBase {
    use SingletonTrait;
    
    protected function onLoad(): void {
        $this->checkVersions();
        self::setInstance($this);
        DataManager::getInstance();
        CommandFactory::getInstance();
    }
    
    #[Pure] public function getPrefix(): string {
        return $this->getDescription()->getPrefix();
    }
    
    private function checkVersions(): void {
        if (version_compare("8.0.0", PHP_VERSION) > 0) {
            throw new RuntimeException(Translator::getInstance()->translate(new TranslationContainer("extra.version.phplower", [PHP_VERSION])));
        }
    }
    
    protected function onEnable(): void {
        $this->copyright();
    }
    
    private function copyright(): void {
        $copyright = [
            "",
            "███╗   ███╗██╗███╗   ██╗███████╗██████╗ ██╗    ██╗ █████╗ ██████╗ ███████╗",
            "████╗ ████║██║████╗  ██║██╔════╝██╔══██╗██║    ██║██╔══██╗██╔══██╗██╔════╝",
            "██╔████╔██║██║██╔██╗ ██║█████╗  ██████╔╝██║ █╗ ██║███████║██████╔╝█████╗",
            "██║╚██╔╝██║██║██║╚██╗██║██╔══╝  ██╔══██╗██║███╗██║██╔══██║██╔══██╗██╔══╝",
            "██║ ╚═╝ ██║██║██║ ╚████║███████╗██║  ██║╚███╔███╔╝██║  ██║██║  ██║███████╗",
            "╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝ ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝",
            "",
            "This is a private project, your not allow to redistribute nor resell it.",
            "The only ones with that power are this project's contributors.",
            "",
            "Copyright 2021 © Minerware",
            ""
        ];
        
        foreach ($copyright as $str) {
            $this->getServer()->getLogger()->notice($str);
        }
    }
}