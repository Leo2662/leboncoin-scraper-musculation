<?php

namespace Leboncoin\Scraper\Scraper;

use Goutte\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use Leboncoin\Scraper\Config\Config;
use Leboncoin\Scraper\Logger\StructuredLogger;
use Leboncoin\Scraper\Models\Listing;
use Symfony\Component\DomCrawler\Crawler;
use DateTime;
use Exception;

/**
 * Scraper principal pour Leboncoin
 */
class LeboncoinScraper
{
    private Client $client;
    private StructuredLogger $logger;
    private array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15',
    ];

    public function __construct(StructuredLogger $logger)
    {
        $this->logger = $logger;

        // Initialiser le client Goutte avec Guzzle
        $guzzleClient = new GuzzleClient([
            'timeout' => Config::get('scraper_timeout_sec', 10),
            'connect_timeout' => 5,
            'verify' => true,
        ]);

        $this->client = new Client($guzzleClient);
    }

    /**
     * Scrape les annonces Leboncoin
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

            // Parser les annonces
            $listings = $this->parseListings($html);
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
     * Construit l'URL de recherche Leboncoin
     */
    private function buildSearchUrl(): string
    {
        $baseUrl = Config::get('leboncoin_search_url', 'https://www.leboncoin.fr/search');
        $radius = Config::get('leboncoin_radius_km', 50);
        $lat = Config::get('leboncoin_location_lat', 50.6292);
        $lng = Config::get('leboncoin_location_lng', 3.0573);

        // Paramètres de recherche
        $params = [
            'category' => 'sport_hobby',
            'text' => 'disque musculation fonte',
            'location' => 'Lille',
            'radius' => $radius,
        ];

        return $baseUrl . '?' . http_build_query($params);
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
                        'Accept-Language' => 'fr-FR,fr;q=0.9',
                    ],
                ]);

                return $response->getContent();

            } catch (RequestException $e) {
                $statusCode = $e->getResponse()?->getStatusCode();

                if ($statusCode === 429) {
                    // Rate limit - attendre plus longtemps
                    $waitTime = 60 * (2 ** $attempt);
                    $this->logger->logWarning('Rate limited, waiting', ['wait_seconds' => $waitTime]);
                    sleep($waitTime);
                } elseif ($statusCode === 403) {
                    // Forbidden - arrêter
                    $this->logger->logError('Access forbidden (403)', ['url' => $url]);
                    return null;
                } else {
                    // Autre erreur - retry avec backoff
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
     * Parse les annonces depuis le HTML
     *
     * @return Listing[]
     */
    private function parseListings(string $html): array
    {
        $listings = [];
        $crawler = new Crawler($html);

        // Sélecteurs CSS pour Leboncoin (à adapter selon la structure actuelle)
        $listingNodes = $crawler->filter('div.listingCard, a.listing-item, div[data-qa="aditem"]');

        if ($listingNodes->count() === 0) {
            $this->logger->logWarning('No listings found with current selectors');
            return [];
        }

        $listingNodes->each(function (Crawler $node, $index) use (&$listings) {
            try {
                $listing = $this->parseListingNode($node);
                if ($listing) {
                    $listings[] = $listing;
                }
            } catch (Exception $e) {
                $this->logger->logWarning('Error parsing listing node', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        return $listings;
    }

    /**
     * Parse un nœud d'annonce individuel
     */
    private function parseListingNode(Crawler $node): ?Listing
    {
        $listing = new Listing();

        try {
            // Extraire l'ID
            $listing->id = $this->extractId($node) ?? uniqid();

            // Extraire le titre
            $listing->title = $this->extractText($node, 'h2, .title, [data-qa="aditem_title"]') ?? 'Unknown';

            // Extraire le prix
            $priceText = $this->extractText($node, '.price, [data-qa="aditem_price"]');
            $listing->price = $this->parsePrice($priceText) ?? 0;

            // Extraire la description
            $listing->description = $this->extractText($node, '.description, p, [data-qa="aditem_description"]') ?? '';

            // Extraire la localisation
            $locationText = $this->extractText($node, '.location, [data-qa="aditem_location"]') ?? 'Unknown';
            $this->parseLocation($locationText, $listing);

            // Extraire l'URL
            $listing->url = $this->extractUrl($node) ?? '';

            // Extraire la date de publication
            $dateText = $this->extractText($node, '.date, time, [data-qa="aditem_date"]');
            $listing->publishedAt = $this->parseDate($dateText) ?? new DateTime();

            // Extraire le poids
            $weightData = WeightExtractor::extract($listing->title . ' ' . $listing->description);
            $listing->weight = $weightData['weight'];
            $listing->weightConfidence = $weightData['confidence'];

            // Calculer le prix par kg
            $listing->calculatePricePerKg();

            return $listing;

        } catch (Exception $e) {
            $this->logger->logWarning('Error parsing listing', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Extrait le texte d'un sélecteur
     */
    private function extractText(Crawler $node, string $selector): ?string
    {
        $selectors = explode(',', $selector);
        foreach ($selectors as $sel) {
            $sel = trim($sel);
            try {
                $text = $node->filter($sel)->text();
                if (!empty($text)) {
                    return trim($text);
                }
            } catch (Exception $e) {
                continue;
            }
        }
        return null;
    }

    /**
     * Extrait l'URL d'une annonce
     */
    private function extractUrl(Crawler $node): ?string
    {
        $selectors = ['a', 'a.listing-link', '[data-qa="aditem_link"]'];
        foreach ($selectors as $selector) {
            try {
                $url = $node->filter($selector)->attr('href');
                if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                    return $url;
                }
            } catch (Exception $e) {
                continue;
            }
        }
        return null;
    }

    /**
     * Extrait l'ID de l'annonce
     */
    private function extractId(Crawler $node): ?string
    {
        try {
            // Chercher dans les attributs data
            $id = $node->attr('data-id') ?? $node->attr('data-listing-id');
            if ($id) return $id;

            // Extraire de l'URL
            $url = $this->extractUrl($node);
            if ($url && preg_match('/\/(\d+)\.htm/', $url, $matches)) {
                return $matches[1];
            }
        } catch (Exception $e) {
            // Continuer
        }

        return null;
    }

    /**
     * Parse le prix depuis le texte
     */
    private function parsePrice(string $priceText = null): ?float
    {
        if (!$priceText) return null;

        // Extraire le nombre du texte
        if (preg_match('/(\d+(?:[.,]\d{2})?)\s*€/', $priceText, $matches)) {
            $price = str_replace(',', '.', $matches[1]);
            return (float)$price;
        }

        return null;
    }

    /**
     * Parse la localisation
     */
    private function parseLocation(string $locationText, Listing $listing): void
    {
        $listing->location = $locationText;

        // Extraire la ville et le code postal
        if (preg_match('/(\w+)\s+(\d{5})/', $locationText, $matches)) {
            $listing->city = $matches[1];
            $listing->postalCode = $matches[2];
        } else {
            $listing->city = $locationText;
            $listing->postalCode = '';
        }
    }

    /**
     * Parse la date depuis le texte
     */
    private function parseDate(string $dateText = null): ?DateTime
    {
        if (!$dateText) return null;

        try {
            // Essayer différents formats
            $formats = [
                'Y-m-d H:i:s',
                'd/m/Y H:i',
                'd/m/Y',
                'Y-m-d',
            ];

            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, $dateText);
                if ($date) return $date;
            }

            // Sinon, retourner maintenant
            return new DateTime();

        } catch (Exception $e) {
            return new DateTime();
        }
    }
}
