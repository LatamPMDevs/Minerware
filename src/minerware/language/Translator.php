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

namespace minerware\language;

use minerware\Minerware;
use pocketmine\lang\Language;
use pocketmine\lang\TranslationContainer;
use pocketmine\utils\SingletonTrait;

final class Translator {
    use SingletonTrait;
    
    private Minerware $plugin;
    
    private Language $language;
    
    public function __construct() {
        $this->plugin = Minerware::getInstance();
        $this->loadLanguages();
        
        $language = $this->plugin->getConfig()->get("language");
        $this->language = new Language($language, $this->plugin->getDataFolder() . "languages" . DIRECTORY_SEPARATOR, $language);
    }
    
    private function loadLanguages(): void {
        $this->plugin->saveResource(DIRECTORY_SEPARATOR . "languages" . DIRECTORY_SEPARATOR . "spanish.ini");
        $this->plugin->saveResource(DIRECTORY_SEPARATOR . "languages" . DIRECTORY_SEPARATOR . "english.ini");
    }
    
    public function changeLanguage(string $newLanguage): void {
        $this->language = new Language("Spanish", $this->plugin->getDataFolder() . "languages" . DIRECTORY_SEPARATOR . $newLanguage . ".ini");
    }
    
    public function translate(TranslationContainer $container): string {
        return $this->language->translate($container);
    }
}