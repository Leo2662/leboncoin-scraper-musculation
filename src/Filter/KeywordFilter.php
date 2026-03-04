<?php

namespace Leboncoin\Scraper\Filter;

use Leboncoin\Scraper\Config\Config;
use Leboncoin\Scraper\Logger\StructuredLogger;
use Leboncoin\Scraper\Models\Listing;

/**
 * Filtrage par mots-clés positifs et négatifs
 */
class KeywordFilter
{
    private StructuredLogger $logger;

    public function __construct(StructuredLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Vérifie si l'annonce contient des mots-clés positifs
     */
    public function hasPositiveKeywords(Listing $listing): bool
    {
        $positiveKeywords = Config::get('positive_keywords', []);
        $text = strtolower($listing->title . ' ' . $listing->description);

        foreach ($positiveKeywords as $keyword) {
            if (strpos($text, strtolower($keyword)) !== false) {
                $listing->matchedKeywords[] = $keyword;
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si l'annonce contient des mots-clés négatifs
     */
    public function hasNegativeKeywords(Listing $listing): bool
    {
        $negativeKeywords = Config::get('negative_keywords', []);
        $text = strtolower($listing->title . ' ' . $listing->description);

        foreach ($negativeKeywords as $keyword) {
            if (strpos($text, strtolower($keyword)) !== false) {
                return true;
            }
        }

        return false;
    }
}
