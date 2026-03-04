<?php

namespace Leboncoin\Scraper\Filter;

use Leboncoin\Scraper\Config\Config;
use Leboncoin\Scraper\Logger\StructuredLogger;
use Leboncoin\Scraper\Models\Listing;

/**
 * Filtrage par prix par kilogramme
 */
class PricePerKgFilter
{
    private StructuredLogger $logger;

    public function __construct(StructuredLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Vérifie si le prix/kg est valide
     */
    public function isValid(Listing $listing): bool
    {
        if ($listing->pricePerKg === null) {
            return false;
        }

        if ($listing->pricePerKg <= 0 || $listing->pricePerKg > 100) {
            return false;
        }

        return true;
    }

    /**
     * Vérifie si l'annonce est une alerte (< 1,25 €/kg)
     */
    public function isAlert(Listing $listing): bool
    {
        if (!$this->isValid($listing)) {
            return false;
        }

        $threshold = Config::get('price_per_kg_threshold', 1.25);
        return $listing->pricePerKg < $threshold;
    }
}
