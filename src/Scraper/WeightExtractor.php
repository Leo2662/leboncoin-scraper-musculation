<?php

namespace Leboncoin\Scraper\Scraper;

/**
 * Extraction intelligente du poids avec niveaux de confiance
 */
class WeightExtractor
{
    /**
     * Extrait le poids du texte avec niveau de confiance
     *
     * @return array ['weight' => float|null, 'confidence' => 'high'|'medium'|'low']
     */
    public static function extract(string $text): array
    {
        $text = strtolower($text);
        $weight = null;
        $confidence = 'low';

        // Priorité 1: Patterns explicites (Haute confiance)
        // Pattern: 2x20kg, 2 x 20 kg, 2*20kg
        if (preg_match_all('/(\d+(?:[.,]\d+)?)\s*(?:x|×|fois|\*)\s*(\d+(?:[.,]\d+)?)\s*kg/i', $text, $matches)) {
            $quantities = array_map(fn($v) => (float)str_replace(',', '.', $v), $matches[1]);
            $weights = array_map(fn($v) => (float)str_replace(',', '.', $v), $matches[2]);
            $totalWeight = 0;
            foreach ($quantities as $i => $qty) {
                $totalWeight += $qty * $weights[$i];
            }
            if ($totalWeight > 0) {
                $weight = $totalWeight;
                $confidence = 'high';
                return compact('weight', 'confidence');
            }
        }

        // Pattern: 20kg + 10kg + 5kg
        if (preg_match_all('/(\d+(?:[.,]\d+)?)\s*kg/i', $text, $matches)) {
            $weights = array_map(fn($v) => (float)str_replace(',', '.', $v), $matches[1]);
            if (count($weights) >= 2) {
                $weight = array_sum($weights);
                $confidence = 'high';
                return compact('weight', 'confidence');
            }
        }

        // Priorité 2: Heuristiques (Confiance moyenne)
        // Pattern: paire de 20kg
        if (preg_match('/paire\s+de\s+(\d+(?:[.,]\d+)?)\s*kg/i', $text, $matches)) {
            $weight = (float)str_replace(',', '.', $matches[1]) * 2;
            $confidence = 'medium';
            return compact('weight', 'confidence');
        }

        // Pattern: lot de 4 disques de 20kg
        if (preg_match('/lot\s+de\s+(\d+)\s+disques?\s+de\s+(\d+(?:[.,]\d+)?)\s*kg/i', $text, $matches)) {
            $quantity = (int)$matches[1];
            $unitWeight = (float)str_replace(',', '.', $matches[2]);
            $weight = $quantity * $unitWeight;
            $confidence = 'medium';
            return compact('weight', 'confidence');
        }

        // Pattern: set complet (estimation)
        if (preg_match('/set\s+complet|kit\s+complet|ensemble\s+complet/i', $text)) {
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*kg/i', $text, $matches)) {
                $weight = (float)str_replace(',', '.', $matches[1]);
                $confidence = 'medium';
                return compact('weight', 'confidence');
            }
        }

        // Priorité 3: Fallback (Basse confiance)
        // Chercher simplement un nombre suivi de kg
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*kg/i', $text, $matches)) {
            $weight = (float)str_replace(',', '.', $matches[1]);
            $confidence = 'low';
            return compact('weight', 'confidence');
        }

        return [
            'weight' => null,
            'confidence' => 'low',
        ];
    }

    /**
     * Valide le poids extrait
     */
    public static function isValid(?float $weight, int $minWeight = 5): bool
    {
        return $weight !== null && $weight >= $minWeight && $weight <= 500;
    }
}
