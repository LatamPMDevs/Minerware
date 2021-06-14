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

namespace minerware\command\commands;

//use minerware\arena\ArenaCreator;
use minerware\language\Translator;
use minerware\Minerware;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\lang\TranslationContainer;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as T;

final class MinerwareCommand extends Command {

//    private static $creating = [];
    
    public function __construct(private Minerware $plugin) {
        parent::__construct("minerware", "Minerware main command.");
        $this->setPermission("minerware.command");
    }
    
    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(T::RED . Translator::getInstance()->translate(new TranslationContainer("command.error.gameonly")));
            return;
        }
        
        if (!$this->testPermission($sender)) {
            return;
        }
        
        if (!isset($args[0])) {
            $sender->sendMessage(T::RED . Translator::getInstance()->translate(new TranslationContainer("command.error.notfound")));
            return;
        }
        
        switch ($args[0]) {
            case "create":
                $sender->sendMessage(T::RED . Translator::getInstance()->translate(new TranslationContainer("extra.feature.underDevelopment")));
    
                /**
                 * TODO:: Check if arena and world exist.
                 */
                //self::$creating[strtolower($sender->getName())] = new ArenaCreator($sender, $args[1]);
            break;
            
            case "setlanguage":
            case "setlang":
                if (!isset($args[1])) {
                    $sender->sendMessage(T::RED . "Usage: /minerware setlang <language>");
                    return;
                }
                
                $languages = [
                    "English",
                    "Spanish"
                ];
                
                if ($args[1] == "list") {
                    $sender->sendMessage(T::RED . Translator::getInstance()->translate(new TranslationContainer("command.arg.setlang.langList", $languages)));
                    return;
                }
                
                if (!in_array(ucfirst(strtolower($args[1])), $languages)) {
                    $sender->sendMessage(T::RED . Translator::getInstance()->translate(new TranslationContainer("command.arg.setlang.langNotFound", [$args[1]])));
                    return;
                }
                
                Translator::getInstance()->changeLanguage($args[1]);
                $sender->sendMessage(T::GREEN . Translator::getInstance()->translate(new TranslationContainer("command.arg.setlang.changed", [strtolower($args[1])])));
            break;
            
            case 'credits':
                $credits = [
                    T::YELLOW . "---- {$this->plugin->getPrefix()} " . T::WHITE . "Credits " . T::YELLOW . "----",
                    "\n",
                    T::YELLOW . "Authors: " . T::WHITE . "JustJ0rd4n, IvanCraft623, TheModDev",
                    T::YELLOW . "Status: " . T::WHITE . "§7Private"
                ];
                
                foreach ($credits as $str) {
                    $sender->sendMessage($str);
                }
            break;
            
            case "help":
                $sender->sendMessage("SOON");
            break;
            
            default:
                $sender->sendMessage(T::RED . Translator::getInstance()->translate(new TranslationContainer("command.error.notfound")));
            break;
        }
    }
}
