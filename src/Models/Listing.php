<?php

namespace Leboncoin\Scraper\Models;

use DateTime;

/**
 * Modèle représentant une annonce Leboncoin
 */
class Listing
{
    public string $id;
    public string $title;
    public string $description;
    public float $price;
    public ?float $weight = null;
    public string $weightConfidence = 'low'; // 'high', 'medium', 'low'
    public string $location;
    public string $city;
    public string $postalCode;
    public ?float $latitude = null;
    public ?float $longitude = null;
    public string $url;
    public DateTime $publishedAt;
    public ?float $pricePerKg = null;
    public bool $isAlert = false;
    public array $matchedKeywords = [];
    public array $errors = [];

    /**
     * Calcule le prix par kilogramme
     */
    public function calculatePricePerKg(): ?float
    {
        if ($this->weight === null || $this->weight <= 0) {
            return null;
        }

        if ($this->price <= 0) {
            return null;
        }

        $this->pricePerKg = round($this->price / $this->weight, 2);
        return $this->pricePerKg;
    }

    /**
     * Vérifie si l'annonce est une alerte (< 1,25 €/kg)
     */
    public function isAlertListing(float $threshold = 1.25): bool
    {
        if ($this->pricePerKg === null) {
            return false;
        }

        return $this->pricePerKg < $threshold;
    }

    /**
     * Convertit l'objet en tableau
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => substr($this->description, 0, 200),
            'price' => $this->price,
            'weight' => $this->weight,
            'weight_confidence' => $this->weightConfidence,
            'location' => $this->location,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'url' => $this->url,
            'published_at' => $this->publishedAt->format('Y-m-d H:i:s'),
            'price_per_kg' => $this->pricePerKg,
            'is_alert' => $this->isAlert,
            'matched_keywords' => $this->matchedKeywords,
            'errors' => $this->errors,
        ];
    }

    /**
     * Convertit l'objet en JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
