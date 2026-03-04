<?php

namespace Leboncoin\Scraper\Scraper;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use Leboncoin\Scraper\Config\Config;
use Leboncoin\Scraper\Logger\StructuredLogger;
use Leboncoin\Scraper\Models\Listing;
use DateTime;
use Exception;

/**
 * Scraper principal pour Leboncoin
 * Utilise l'extraction JSON depuis __NEXT_DATA__ (Next.js SSR)
 */
class LeboncoinScraper
{
    private GuzzleClient $client;
    private StructuredLogger $logger;
    private array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
    ];

    public function __construct(StructuredLogger $logger)
    {
        $this->logger = $logger;

        // Initialiser le client HTTP Guzzle
        $this->client = new GuzzleClient([
            'timeout' => Config::get('scraper_timeout_sec', 15),
            'connect_timeout' => 10,
            'verify' => true,
        ]);
    }

    /**
     * Scrape les annonces Leboncoin via __NEXT_DATA__ JSON
     *
     * @return Listing[]
     */
    public function scrape(): array
    {
        $listings = [];
        $maxRetries = Config::get('scraper_retry_count', 3);
        $delay = Config::get('scraper_delay_sec', 2);

        try {
            // Construire l'URL de recherche
            $url = $this->buildSearchUrl();
            $this->logger->logWarning('Starting scrape', ['url' => $url]);

            // Récupérer la page avec retry logic
            $html = $this->fetchWithRetry($url, $maxRetries);

            if (!$html) {
                $this->logger->logError('Failed to fetch Leboncoin page after retries');
                return [];
            }

            // Extraire les données JSON depuis __NEXT_DATA__
            $ads = $this->extractAdsFromNextData($html);

            if (empty($ads)) {
                $this->logger->logWarning('No ads found in __NEXT_DATA__');
                return [];
            }

            $this->logger->logWarning('Ads extracted from JSON', ['count' => count($ads)]);

            // Convertir les ads JSON en objets Listing
            foreach ($ads as $ad) {
                try {
                    $listing = $this->parseAdData($ad);
                    if ($listing) {
                        $listings[] = $listing;
                    }
                } catch (Exception $e) {
                    $this->logger->logWarning('Error parsing ad', ['error' => $e->getMessage()]);
                }
            }

            $this->logger->logWarning('Listings parsed', ['count' => count($listings)]);

            // Appliquer un délai pour respecter les limites de taux
            sleep($delay);

        } catch (Exception $e) {
            $this->logger->logError('Scraper error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return $listings;
    }

    /**
     * Construit l'URL de recherche Leboncoin (format Next.js)
     */
    private function buildSearchUrl(): string
    {
        $radius = Config::get('leboncoin_radius_km', 50) * 1000; // Convert to meters
        $lat = Config::get('leboncoin_location_lat', 50.62925);
        $lng = Config::get('leboncoin_location_lng', 3.05726);

        // Format Leboncoin : /recherche?category=29&text=...&locations=Lille_59000__LAT_LNG_RADIUS&sort=time
        $params = [
            'category' => '29', // Sport & Hobbies
            'text' => 'disque musculation fonte',
            'locations' => "Lille_59000__{$lat}_{$lng}_{$radius}",
            'sort' => 'time',
        ];

        return 'https://www.leboncoin.fr/recherche?' . http_build_query($params);
    }

    /**
     * Récupère le HTML avec retry logic et backoff exponentiel
     */
    private function fetchWithRetry(string $url, int $maxRetries = 3): ?string
    {
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                $userAgent = $this->userAgents[array_rand($this->userAgents)];

                $response = $this->client->request('GET', $url, [
                    'headers' => [
                        'User-Agent' => $userAgent,
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language' => 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
                        'Accept-Encoding' => 'gzip, deflate, br',
                        'Cache-Control' => 'no-cache',
                        'Connection' => 'keep-alive',
                        'Sec-Fetch-Dest' => 'document',
                        'Sec-Fetch-Mode' => 'navigate',
                        'Sec-Fetch-Site' => 'none',
                        'Sec-Fetch-User' => '?1',
                        'Upgrade-Insecure-Requests' => '1',
                    ],
                ]);

                $body = (string) $response->getBody();

                // Vérifier que la page contient bien __NEXT_DATA__
                if (strpos($body, '__NEXT_DATA__') !== false) {
                    return $body;
                }

                $this->logger->logWarning('Page fetched but no __NEXT_DATA__ found', [
                    'attempt' => $attempt + 1,
                    'body_length' => strlen($body),
                ]);

            } catch (RequestException $e) {
                $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;

                if ($statusCode === 429) {
                    $waitTime = 60 * (2 ** $attempt);
                    $this->logger->logWarning('Rate limited, waiting', ['wait_seconds' => $waitTime]);
                    sleep($waitTime);
                } elseif ($statusCode === 403) {
                    $this->logger->logError('Access forbidden (403)', ['url' => $url, 'attempt' => $attempt + 1]);
                    // Wait and retry with different user agent
                    $waitTime = 5 * (2 ** $attempt);
                    sleep($waitTime);
                } else {
                    $waitTime = 2 ** $attempt;
                    if ($attempt < $maxRetries - 1) {
                        $this->logger->logWarning('Request failed, retrying', [
                            'attempt' => $attempt + 1,
                            'max_retries' => $maxRetries,
                            'wait_seconds' => $waitTime,
                            'status_code' => $statusCode,
                        ]);
                        sleep($waitTime);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extrait les annonces depuis le JSON __NEXT_DATA__
     */
    private function extractAdsFromNextData(string $html): array
    {
        // Chercher le tag script __NEXT_DATA__
        if (!preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $matches)) {
            $this->logger->logWarning('__NEXT_DATA__ script tag not found');
            return [];
        }

        $jsonData = json_decode($matches[1], true);
        if (!$jsonData) {
            $this->logger->logError('Failed to parse __NEXT_DATA__ JSON', ['json_error' => json_last_error_msg()]);
            return [];
        }

        // Naviguer dans la structure Next.js
        $ads = $jsonData['props']['pageProps']['searchData']['ads'] ?? [];

        $this->logger->logWarning('Extracted ads from __NEXT_DATA__', [
            'total' => $jsonData['props']['pageProps']['searchData']['total'] ?? 0,
            'ads_on_page' => count($ads),
        ]);

        return $ads;
    }

    /**
     * Convertit les données JSON d'une annonce en objet Listing
     */
    private function parseAdData(array $ad): ?Listing
    {
        $listing = new Listing();

        try {
            $listing->id = (string) ($ad['list_id'] ?? uniqid());
            $listing->title = $ad['subject'] ?? 'Unknown';
            $listing->description = $ad['body'] ?? '';
            $listing->url = $ad['url'] ?? '';

            // Prix
            $prices = $ad['price'] ?? [];
            $listing->price = !empty($prices) ? (float) $prices[0] : 0;

            // Localisation
            $location = $ad['location'] ?? [];
            $listing->city = $location['city'] ?? '';
            $listing->postalCode = $location['zipcode'] ?? '';
            $listing->location = trim(($listing->city ?? '') . ' ' . ($listing->postalCode ?? ''));

            // Date de publication
            $dateStr = $ad['first_publication_date'] ?? $ad['index_date'] ?? null;
            if ($dateStr) {
                try {
                    $listing->publishedAt = new DateTime($dateStr);
                } catch (Exception $e) {
                    $listing->publishedAt = new DateTime();
                }
            } else {
                $listing->publishedAt = new DateTime();
            }

            // Extraire le poids depuis titre + description
            $fullText = $listing->title . ' ' . $listing->description;
            $weightData = WeightExtractor::extract($fullText);
            $listing->weight = $weightData['weight'];
            $listing->weightConfidence = $weightData['confidence'];

            // Calculer le prix par kg
            $listing->calculatePricePerKg();

            return $listing;

        } catch (Exception $e) {
            $this->logger->logWarning('Error parsing ad data', [
                'ad_id' => $ad['list_id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
