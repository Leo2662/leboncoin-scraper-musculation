<?php

namespace Leboncoin\Scraper\Config;

use Exception;

/**
 * Gestion centralisée de la configuration
 */
class Config
{
    private static array $config = [];

    /**
     * Charge la configuration depuis les variables d'environnement
     */
    public static function load(): void
    {
        // Leboncoin
        self::$config['leboncoin_search_url'] = getenv('LEBONCOIN_SEARCH_URL') ?: 'https://www.leboncoin.fr/search';
        self::$config['leboncoin_location_lat'] = (float)(getenv('LEBONCOIN_LOCATION_LAT') ?: 50.6292);
        self::$config['leboncoin_location_lng'] = (float)(getenv('LEBONCOIN_LOCATION_LNG') ?: 3.0573);
        self::$config['leboncoin_radius_km'] = (int)(getenv('LEBONCOIN_RADIUS_KM') ?: 50);

        // Filtrage
        self::$config['price_per_kg_threshold'] = (float)(getenv('PRICE_PER_KG_THRESHOLD') ?: 1.25);
        self::$config['min_weight_kg'] = (int)(getenv('MIN_WEIGHT_KG') ?: 5);

        // Mots-clés
        $positiveKeywords = getenv('POSITIVE_KEYWORDS') ?: 'disque musculation,disque fonte,poids,haltère,fonte,disques';
        self::$config['positive_keywords'] = array_map('trim', explode(',', $positiveKeywords));

        $negativeKeywords = getenv('NEGATIVE_KEYWORDS') ?: 'frein,auto,vinyle,CD,DVD,ciment,plastique,caoutchouc,bumper';
        self::$config['negative_keywords'] = array_map('trim', explode(',', $negativeKeywords));

        // Telegram
        self::$config['telegram_bot_token'] = getenv('TELEGRAM_BOT_TOKEN');
        self::$config['telegram_chat_id'] = getenv('TELEGRAM_CHAT_ID');

        // Scraper
        self::$config['scraper_timeout_sec'] = (int)(getenv('SCRAPER_TIMEOUT_SEC') ?: 10);
        self::$config['scraper_retry_count'] = (int)(getenv('SCRAPER_RETRY_COUNT') ?: 3);
        self::$config['scraper_delay_sec'] = (int)(getenv('SCRAPER_DELAY_SEC') ?: 2);

        // Logs
        self::$config['log_level'] = getenv('LOG_LEVEL') ?: 'info';
        self::$config['log_file'] = getenv('LOG_FILE') ?: './logs/scraper.log';
        self::$config['log_format'] = getenv('LOG_FORMAT') ?: 'json';

        // Déduplication
        self::$config['dedup_file'] = getenv('DEDUP_FILE') ?: './logs/seen_listings.json';
    }

    /**
     * Récupère une valeur de configuration
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$config[$key] ?? $default;
    }

    /**
     * Définit une valeur de configuration
     */
    public static function set(string $key, mixed $value): void
    {
        self::$config[$key] = $value;
    }

    /**
     * Valide la configuration requise
     */
    public static function validate(): void
    {
        $required = ['telegram_bot_token', 'telegram_chat_id'];

        foreach ($required as $key) {
            if (empty(self::$config[$key])) {
                throw new Exception("Configuration requise manquante: {$key}");
            }
        }
    }

    /**
     * Retourne toute la configuration
     */
    public static function all(): array
    {
        return self::$config;
    }
}
