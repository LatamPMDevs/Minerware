<?php

declare(strict_types=1);

namespace LatamPMDevs\minerware\libs\_189c4e77bf695c21\IvanCraft623\languages;

use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

use InvalidArgumentException;
use WeakMap;

final class Translator {

	public static array $translators = [];

	public const MINECRAFT_LOCALES = [
		// See  ->  https://github.com/Mojang/bedrock-samples/blob/main/resource_pack/texts/language_names.json
		"en_US" => true, // English (United States)
		"en_GB" => true, // English (United Kingdom)
		"de_DE" => true, // Deutsch (Deutschland)
		"es_ES" => true, // Español (España)
		"es_MX" => true, // Español (México)
		"fr_FR" => true, // Français (France)
		"fr_CA" => true, // Français (Canada)
		"it_IT" => true, // Italiano (Italia)
		"ja_JP" => true, // 日本語 (日本)
		"ko_KR" => true, // 한국어 (대한민국)
		"pt_BR" => true, // Português (Brasil)
		"pt_PT" => true, // Português (Portugal)
		"ru_RU" => true, // Русский (Россия)
		"zh_CN" => true, // 中文(简体)
		"zh_TW" => true, // 中文(繁體)
		"nl_NL" => true, // Nederlands (Nederland)
		"bg_BG" => true, // Български (България)
		"cs_CZ" => true, // Čeština (Česko)
		"da_DK" => true, // Dansk (Danmark)
		"el_GR" => true, // Ελληνικά (Ελλάδα)
		"fi_FI" => true, // Suomi (Suomi)
		"hu_HU" => true, // Magyar (Magyarország)
		"id_ID" => true, // Indonesia (Indonesia)
		"nb_NO" => true, // Norsk bokmål (Norge)
		"pl_PL" => true, // Polski (Polska)
		"sk_SK" => true, // Slovenčina (Slovensko)
		"sv_SE" => true, // Svenska (Sverige)
		"tr_TR" => true, // Türkçe (Türkiye)
		"uk_UA" => true, // Українська (Україна)
	];

	private PluginBase $plugin;

	/** @var Language[] */
	private array $languages = [];

	private Language $defaultLanguage;

	/** @var WeakMap<Player, Language> */
	private WeakMap $playerLanguages;

	public function __construct(PluginBase $plugin) {
		$this->plugin = $plugin;
		$this->playerLanguages = new WeakMap();
	}

	public function getPlugin(): PluginBase{
		return $this->plugin;
	}

	public function getDefaultLanguage(): Language {
		return $this->defaultLanguage;
	}

	public function setDefaultLanguage(Language $language): void {
		if (($this->languages[$language->getLocale()] ?? null) !== $language) {
			throw new InvalidArgumentException("Language is not registered");
		}
		$this->defaultLanguage = $language;
	}

	public function isLanguageRegistered(string $locale): bool {
		return isset($this->languages[$locale]);
	}

	public function registerLanguage(Language $language, bool $override = false): void {
		$locale = $language->getLocale();
		if (!$override && $this->isLanguageRegistered($locale)) {
			throw new InvalidArgumentException("Trying to overwrite an already registered Language");
		}
		$this->languages[$locale] = $language;
	}

	public function getLanguages(): array {
		return $this->languages;
	}

	public function getLanguage(string $locale): ?Language {
		return $this->languages[$locale] ?? null;
	}

	/**
	 * Whether clients can report this locale; others are only seen when chosen.
	 */
	public static function isMinecraftLocale(string $locale): bool {
		return isset(self::MINECRAFT_LOCALES[$locale]);
	}

	/**
	 * Overrides the player's client language until they leave; null removes it.
	 */
	public function setPlayerLanguage(Player $player, ?Language $language): void {
		if ($language === null) {
			unset($this->playerLanguages[$player]);
			return;
		}
		if (($this->languages[$language->getLocale()] ?? null) !== $language) {
			throw new InvalidArgumentException("Language is not registered");
		}
		$this->playerLanguages[$player] = $language;
	}

	/**
	 * The player's chosen language, else their client's, else the default.
	 */
	public function getLanguageOf(?CommandSender $target): Language {
		if (!$target instanceof Player) {
			return $this->defaultLanguage;
		}
		return $this->playerLanguages[$target] ?? $this->languages[$target->getLocale()] ?? $this->defaultLanguage;
	}

	public function translate(?CommandSender $target, string $key, array $replacements = []): string {
		$language = $this->getLanguageOf($target);
		$translation = $language->getTranslation($key);

		if ($translation === null) {
			$defaultTranslation = $this->defaultLanguage->getTranslation($key);
			if ($defaultTranslation === null) {
				$this->plugin->getLogger()->error("Unknown translation key ".$key);
				return "";
			} else {
				$translation = $defaultTranslation;
			}
		}
		return str_replace(array_keys($replacements), array_values($replacements), $translation);
	}
}