#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Leboncoin\Scraper\Config\Config;
use Leboncoin\Scraper\Logger\StructuredLogger;
use Leboncoin\Scraper\Scraper\LeboncoinScraper;
use Leboncoin\Scraper\Filter\ListingFilter;
use Leboncoin\Scraper\Notifier\TelegramNotifier;

// Charger les variables d'environnement
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Initialiser la configuration
Config::load();

// Valider la configuration
try {
    Config::validate();
} catch (Exception $e) {
    echo "Configuration error: " . $e->getMessage() . "\n";
    exit(1);
}

// Initialiser le logger
$logger = new StructuredLogger(Config::get('log_file', './logs/scraper.log'));

try {
    $startTime = microtime(true);

    $logger->logWarning('Scraper started', [
        'execution_id' => $logger->getExecutionId(),
        'timestamp' => date('Y-m-d H:i:s'),
    ]);

    // Initialiser les services
    $scraper = new LeboncoinScraper($logger);
    $filter = new ListingFilter($logger);
    $notifier = new TelegramNotifier($logger);

    // Scraper les annonces
    $listings = $scraper->scrape();
    $scrapedCount = count($listings);

    $logger->logWarning('Scraping completed', ['count' => $scrapedCount]);

    // Filtrer les annonces
    $filteredListings = $filter->filter($listings);
    $filteredCount = count($filteredListings);

    $logger->logWarning('Filtering completed', ['count' => $filteredCount]);

    // Dédupliquer
    $uniqueListings = $filter->deduplicate($filteredListings);
    $uniqueCount = count($uniqueListings);

    $logger->logWarning('Deduplication completed', ['count' => $uniqueCount]);

    // Envoyer les notifications
    $alertedCount = 0;
    $failedCount = 0;

    foreach ($uniqueListings as $listing) {
        $sent = $notifier->notify($listing);

        if ($sent) {
            $alertedCount++;
            $logger->logAlert($listing->toArray(), true);
        } else {
            $failedCount++;
            $logger->logAlert($listing->toArray(), false);
        }
    }

    $logger->logWarning('Notifications completed', [
        'sent' => $alertedCount,
        'failed' => $failedCount,
    ]);

    // Calculer les métriques
    $endTime = microtime(true);
    $executionTime = (int)(($endTime - $startTime) * 1000);

    $metrics = [
        'scraped_count' => $scrapedCount,
        'filtered_count' => $filteredCount,
        'unique_count' => $uniqueCount,
        'alerted_count' => $alertedCount,
        'failed_count' => $failedCount,
        'execution_time_ms' => $executionTime,
    ];

    $logger->logExecutionMetrics($metrics);

    $logger->logWarning('Scraper completed successfully', $metrics);

    exit(0);

} catch (Exception $e) {
    $logger->logError('Fatal error: ' . $e->getMessage(), [
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    exit(1);
}
