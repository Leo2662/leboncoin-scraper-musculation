<?php

namespace Leboncoin\Scraper\Filter;

use Leboncoin\Scraper\Config\Config;
use Leboncoin\Scraper\Logger\StructuredLogger;
use Leboncoin\Scraper\Models\Listing;

/**
 * Filtrage multi-critères des annonces
 */
class ListingFilter
{
    private StructuredLogger $logger;
    private KeywordFilter $keywordFilter;
    private PricePerKgFilter $pricePerKgFilter;

    public function __construct(StructuredLogger $logger)
    {
        $this->logger = $logger;
        $this->keywordFilter = new KeywordFilter($logger);
        $this->pricePerKgFilter = new PricePerKgFilter($logger);
    }

    /**
     * Filtre les annonces selon les critères
     *
     * @param Listing[] $listings
     * @return Listing[]
     */
    public function filter(array $listings): array
    {
        $filtered = [];

        foreach ($listings as $listing) {
            if ($this->isValid($listing)) {
                $filtered[] = $listing;
            }
        }

        return $filtered;
    }

    /**
     * Valide une annonce selon tous les critères
     */
    private function isValid(Listing $listing): bool
    {
        // Critère 1: Mots-clés positifs
        if (!$this->keywordFilter->hasPositiveKeywords($listing)) {
            $this->logger->logListingFiltered($listing->id, 'No positive keywords found');
            return false;
        }

        // Critère 2: Mots-clés négatifs
        if ($this->keywordFilter->hasNegativeKeywords($listing)) {
            $this->logger->logListingFiltered($listing->id, 'Negative keywords detected');
            return false;
        }

        // Critère 3: Poids détecté
        if ($listing->weight === null || $listing->weight < Config::get('min_weight_kg', 5)) {
            $this->logger->logListingFiltered($listing->id, 'Weight not detected or too low');
            return false;
        }

        // Critère 4: Prix/kg valide
        if (!$this->pricePerKgFilter->isValid($listing)) {
            $this->logger->logListingFiltered($listing->id, 'Price per kg invalid');
            return false;
        }

        // Critère 5: Prix/kg < 1,25 €
        if (!$this->pricePerKgFilter->isAlert($listing)) {
            $this->logger->logListingFiltered($listing->id, 'Price per kg >= 1.25');
            return false;
        }

        $listing->isAlert = true;
        return true;
    }

    /**
     * Déduplique les annonces par ID
     *
     * @param Listing[] $listings
     * @return Listing[]
     */
    public function deduplicate(array $listings): array
    {
        $seen = $this->loadSeenListings();
        $filtered = [];

        foreach ($listings as $listing) {
            if (!isset($seen[$listing->id])) {
                $filtered[] = $listing;
                $seen[$listing->id] = true;
            }
        }

        $this->saveSeenListings($seen);
        return $filtered;
    }

    /**
     * Charge les annonces déjà vues
     */
    private function loadSeenListings(): array
    {
        $dedupFile = Config::get('dedup_file', './logs/seen_listings.json');

        if (file_exists($dedupFile)) {
            $content = file_get_contents($dedupFile);
            return json_decode($content, true) ?? [];
        }

        return [];
    }

    /**
     * Sauvegarde les annonces vues
     */
    private function saveSeenListings(array $seen): void
    {
        $dedupFile = Config::get('dedup_file', './logs/seen_listings.json');
        $dir = dirname($dedupFile);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($dedupFile, json_encode($seen, JSON_PRETTY_PRINT));
    }
}
