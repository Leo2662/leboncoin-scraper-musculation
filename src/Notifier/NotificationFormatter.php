<?php

namespace Leboncoin\Scraper\Notifier;

use Leboncoin\Scraper\Models\Listing;

/**
 * Formatage des messages de notification
 */
class NotificationFormatter
{
    /**
     * Formate une alerte pour Telegram
     */
    public function formatAlert(Listing $listing): string
    {
        $emoji = '🎯';
        $title = htmlspecialchars($listing->title, ENT_QUOTES, 'UTF-8');
        $price = number_format($listing->price, 2, ',', ' ');
        $weight = $listing->weight ? number_format($listing->weight, 1, ',', ' ') : 'N/A';
        $pricePerKg = $listing->pricePerKg ? number_format($listing->pricePerKg, 2, ',', ' ') : 'N/A';
        $location = htmlspecialchars($listing->location, ENT_QUOTES, 'UTF-8');
        $date = $listing->publishedAt->format('d/m/Y H:i');
        $url = htmlspecialchars($listing->url, ENT_QUOTES, 'UTF-8');

        $message = "{$emoji} <b>BONNE AFFAIRE DISQUES MUSCULATION</b>\n\n";
        $message .= "<b>Titre :</b> {$title}\n";
        $message .= "<b>Prix :</b> {$price} €\n";
        $message .= "<b>Poids :</b> {$weight} kg\n";
        $message .= "<b>Prix/kg :</b> <u>{$pricePerKg} €/kg</u> ✅\n";
        $message .= "<b>Localisation :</b> {$location}\n";
        $message .= "<b>Publié :</b> {$date}\n\n";
        $message .= "🔗 <a href=\"{$url}\">Voir l'annonce</a>";

        return $message;
    }

    /**
     * Formate un message d'erreur
     */
    public function formatError(string $errorMessage): string
    {
        $message = "⚠️ <b>Erreur Scraper</b>\n\n";
        $message .= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');
        $message .= "\n\nTimestamp: " . date('Y-m-d H:i:s');

        return $message;
    }

    /**
     * Formate un rapport d'exécution
     */
    public function formatExecutionReport(array $metrics): string
    {
        $message = "📊 <b>Rapport d'Exécution</b>\n\n";
        $message .= "<b>Annonces scrapées :</b> " . ($metrics['scraped_count'] ?? 0) . "\n";
        $message .= "<b>Annonces filtrées :</b> " . ($metrics['filtered_count'] ?? 0) . "\n";
        $message .= "<b>Alertes envoyées :</b> " . ($metrics['alerted_count'] ?? 0) . "\n";
        $message .= "<b>Temps d'exécution :</b> " . ($metrics['execution_time_ms'] ?? 0) . " ms\n";
        $message .= "<b>Timestamp :</b> " . date('Y-m-d H:i:s');

        return $message;
    }
}
