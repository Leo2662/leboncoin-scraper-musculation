<?php

namespace Leboncoin\Scraper\Notifier;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Leboncoin\Scraper\Config\Config;
use Leboncoin\Scraper\Logger\StructuredLogger;
use Leboncoin\Scraper\Models\Listing;
use Exception;

/**
 * Envoi des notifications Telegram
 */
class TelegramNotifier
{
    private Client $client;
    private string $botToken;
    private string $chatId;
    private StructuredLogger $logger;
    private NotificationFormatter $formatter;

    public function __construct(StructuredLogger $logger)
    {
        $this->logger = $logger;
        $this->botToken = Config::get('telegram_bot_token');
        $this->chatId = Config::get('telegram_chat_id');
        $this->client = new Client(['timeout' => 10]);
        $this->formatter = new NotificationFormatter();

        if (empty($this->botToken) || empty($this->chatId)) {
            throw new Exception('Telegram configuration missing: bot_token or chat_id');
        }
    }

    /**
     * Envoie une notification pour une alerte
     */
    public function notify(Listing $listing): bool
    {
        try {
            $message = $this->formatter->formatAlert($listing);
            return $this->sendMessage($message);
        } catch (Exception $e) {
            $this->logger->logError('Notification error: ' . $e->getMessage(), [
                'listing_id' => $listing->id,
            ]);
            return false;
        }
    }

    /**
     * Envoie un message via Telegram Bot API
     */
    private function sendMessage(string $message): bool
    {
        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";

            $response = $this->client->post($url, [
                'json' => [
                    'chat_id' => $this->chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => false,
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 200) {
                return true;
            } else {
                $this->logger->logWarning('Telegram API returned non-200 status', [
                    'status_code' => $statusCode,
                ]);
                return false;
            }

        } catch (RequestException $e) {
            $this->logger->logError('Telegram request failed: ' . $e->getMessage(), [
                'status_code' => $e->getResponse()?->getStatusCode(),
            ]);
            return false;
        } catch (Exception $e) {
            $this->logger->logError('Telegram error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoie un message de test
     */
    public function sendTestMessage(): bool
    {
        $message = "✅ <b>Test Leboncoin Scraper</b>\n\n";
        $message .= "Le scraper est opérationnel et prêt à envoyer les alertes.\n";
        $message .= "Timestamp: " . date('Y-m-d H:i:s');

        return $this->sendMessage($message);
    }
}
