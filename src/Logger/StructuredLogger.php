<?php

namespace Leboncoin\Scraper\Logger;

use DateTime;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;

/**
 * Logger structuré pour logs JSON
 */
class StructuredLogger
{
    private Logger $logger;
    private string $executionId;

    public function __construct(string $logFile = './logs/scraper.log')
    {
        $this->executionId = uniqid('exec_', true);

        // Créer le répertoire logs s'il n'existe pas
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Initialiser Monolog
        $this->logger = new Logger('leboncoin-scraper');
        $handler = new StreamHandler($logFile, Logger::INFO);
        $handler->setFormatter(new JsonFormatter());
        $this->logger->pushHandler($handler);

        // Aussi afficher sur stdout
        $stdoutHandler = new StreamHandler('php://stdout', Logger::INFO);
        $stdoutHandler->setFormatter(new JsonFormatter());
        $this->logger->pushHandler($stdoutHandler);
    }

    /**
     * Log une annonce trouvée
     */
    public function logListingFound(array $listingData): void
    {
        $this->logger->info('Listing found', [
            'execution_id' => $this->executionId,
            'listing' => $listingData,
        ]);
    }

    /**
     * Log une annonce filtrée
     */
    public function logListingFiltered(string $listingId, string $reason): void
    {
        $this->logger->info('Listing filtered', [
            'execution_id' => $this->executionId,
            'listing_id' => $listingId,
            'reason' => $reason,
        ]);
    }

    /**
     * Log une alerte (annonce < 1,25 €/kg)
     */
    public function logAlert(array $listingData, bool $telegramSent = false): void
    {
        $this->logger->info('Alert triggered', [
            'execution_id' => $this->executionId,
            'listing' => $listingData,
            'telegram_sent' => $telegramSent,
        ]);
    }

    /**
     * Log une erreur
     */
    public function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, array_merge([
            'execution_id' => $this->executionId,
        ], $context));
    }

    /**
     * Log un avertissement
     */
    public function logWarning(string $message, array $context = []): void
    {
        $this->logger->warning($message, array_merge([
            'execution_id' => $this->executionId,
        ], $context));
    }

    /**
     * Log les métriques d'exécution
     */
    public function logExecutionMetrics(array $metrics): void
    {
        $this->logger->info('Execution completed', [
            'execution_id' => $this->executionId,
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            'metrics' => $metrics,
        ]);
    }

    /**
     * Retourne l'ID d'exécution
     */
    public function getExecutionId(): string
    {
        return $this->executionId;
    }
}
