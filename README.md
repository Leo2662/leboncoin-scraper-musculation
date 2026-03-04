# Leboncoin Scraper - Disques de Musculation

Scraper automatisé pour surveiller les annonces Leboncoin de disques de musculation en fonte et recevoir des alertes Telegram pour les bonnes affaires (< 1,25 €/kg).

## 🎯 Objectif

Identifier automatiquement les annonces Leboncoin proposant des disques de musculation en fonte situés à moins de 50 km de Lille avec un prix inférieur à 1,25 €/kg, puis envoyer les liens correspondants via Telegram.

## 🚀 Fonctionnalités

- **Scraping automatisé** : Récupération des annonces Leboncoin via Goutte + Symfony DomCrawler
- **Extraction intelligente du poids** : Détection des patterns « 2x20kg », « paire de 20 », etc. avec niveaux de confiance
- **Calcul précis du prix/kg** : Filtrage automatique pour les annonces < 1,25 €/kg
- **Gestion des cas ambigus** : Détection des lots mixtes, exclusion des disques non fonte
- **Automatisation GitHub Actions** : Exécution CRON toutes les 15-30 minutes
- **Notifications Telegram** : Messages formatés avec titre, prix, poids, localisation et URL
- **Logs structurés JSON** : Traçabilité complète des exécutions
- **Stratégie anti-blocage** : User-Agent rotation, retry logic, délais respectueux

## 📋 Prérequis

- PHP 8.1+
- Composer
- Compte Telegram Bot (token + chat ID)
- Accès à GitHub Actions (pour l'automatisation)

## 🔧 Installation

### 1. Cloner le projet

```bash
git clone <repository-url>
cd leboncoin-scraper/03_code
```

### 2. Installer les dépendances

```bash
composer install
```

### 3. Configurer les variables d'environnement

Copier le fichier `.env.example` en `.env` et remplir les valeurs requises :

```bash
cp .env.example .env
```

**Variables obligatoires :**
- `TELEGRAM_BOT_TOKEN` : Token du bot Telegram
- `TELEGRAM_CHAT_ID` : ID du chat Telegram destinataire

**Variables optionnelles :**
- `LEBONCOIN_LOCATION_LAT` : Latitude (défaut: 50.6292 pour Lille)
- `LEBONCOIN_LOCATION_LNG` : Longitude (défaut: 3.0573 pour Lille)
- `LEBONCOIN_RADIUS_KM` : Rayon de recherche en km (défaut: 50)
- `PRICE_PER_KG_THRESHOLD` : Seuil d'alerte en €/kg (défaut: 1.25)
- `POSITIVE_KEYWORDS` : Mots-clés positifs (séparés par des virgules)
- `NEGATIVE_KEYWORDS` : Mots-clés négatifs (séparés par des virgules)

## 🏃 Utilisation

### Exécution manuelle

```bash
php bin/scraper.php
```

### Exécution via GitHub Actions

Le scraper s'exécute automatiquement toutes les 20 minutes via GitHub Actions. Vous pouvez aussi le déclencher manuellement :

1. Aller à l'onglet **Actions** du dépôt
2. Sélectionner le workflow **Leboncoin Scraper**
3. Cliquer sur **Run workflow**

## 📊 Résultats

### Logs

Les logs sont générés en format JSON dans `./logs/scraper.log` :

```json
{
  "message": "Alert triggered",
  "context": {
    "execution_id": "exec_abc123",
    "listing": {
      "id": "2847392847",
      "title": "Disques musculation fonte 2x20kg + 2x10kg",
      "price": 50.0,
      "weight": 60.0,
      "price_per_kg": 0.83,
      "location": "Lille (59000)",
      "url": "https://leboncoin.fr/..."
    },
    "telegram_sent": true
  }
}
```

### Notifications Telegram

Les alertes sont envoyées au format HTML avec les informations clés :

```
🎯 BONNE AFFAIRE DISQUES MUSCULATION

Titre : Disques musculation fonte 2x20kg + 2x10kg
Prix : 50,00 €
Poids : 60 kg
Prix/kg : 0,83 €/kg ✅
Localisation : Lille (59000)
Publié : 04/03/2026 14:30

🔗 Voir l'annonce
```

## 🔍 Critères de Filtrage

| Critère | Valeur | Justification |
|---------|--------|--------------|
| **Localisation** | ≤ 50 km de Lille | Rayon de recherche |
| **Mots-clés positifs** | Au moins 1 | Pertinence de l'annonce |
| **Mots-clés négatifs** | Aucun | Exclusion des faux positifs |
| **Poids minimum** | ≥ 5 kg | Annonce pertinente |
| **Prix/kg** | < 1,25 €/kg | Seuil d'alerte |

## 🛡️ Stratégie Anti-Blocage

- **User-Agent rotation** : 5 User-Agents différents
- **Délai entre requêtes** : 2-5 secondes
- **Timeout court** : 10 secondes max par requête
- **Retry avec backoff** : 3 tentatives, délai exponentiel
- **Respect robots.txt** : Vérification avant scraping

## 📈 Métriques

Le scraper enregistre les métriques suivantes à chaque exécution :

- **Annonces scrapées** : Nombre total d'annonces trouvées
- **Annonces filtrées** : Nombre après application des critères
- **Alertes envoyées** : Nombre d'annonces < 1,25 €/kg
- **Temps d'exécution** : Durée totale en ms
- **Taux de faux positifs** : Annonces non pertinentes / Total

## 🧪 Tests

### Exécuter les tests

```bash
composer test
```

### Test de notification Telegram

Pour tester la connexion Telegram :

```php
$notifier = new TelegramNotifier($logger);
$notifier->sendTestMessage();
```

## 📝 Structure du Projet

```
03_code/
├── src/
│   ├── Scraper/
│   │   ├── LeboncoinScraper.php       # Scraper principal
│   │   └── WeightExtractor.php        # Extraction poids
│   ├── Filter/
│   │   ├── ListingFilter.php          # Filtrage principal
│   │   ├── KeywordFilter.php          # Mots-clés
│   │   └── PricePerKgFilter.php       # Prix/kg
│   ├── Notifier/
│   │   ├── TelegramNotifier.php       # Envoi Telegram
│   │   └── NotificationFormatter.php  # Formatage
│   ├── Logger/
│   │   └── StructuredLogger.php       # Logs JSON
│   ├── Config/
│   │   └── Config.php                 # Configuration
│   └── Models/
│       └── Listing.php                # Modèle annonce
├── bin/
│   └── scraper.php                    # Point d'entrée
├── .github/workflows/
│   └── scraper.yml                    # Workflow GitHub Actions
├── composer.json                      # Dépendances
├── .env.example                       # Variables d'environnement
└── README.md                          # Documentation
```

## 🐛 Dépannage

### Erreur : « Configuration requise manquante »

Vérifier que les variables d'environnement `TELEGRAM_BOT_TOKEN` et `TELEGRAM_CHAT_ID` sont définies.

### Erreur : « Access forbidden (403) »

Leboncoin peut bloquer les requêtes. Vérifier :
- Le User-Agent est correct
- Les délais entre requêtes sont respectés
- L'IP n'est pas blacklistée

### Aucune annonce trouvée

Vérifier :
- Les sélecteurs CSS sont à jour (Leboncoin peut changer sa structure HTML)
- Les mots-clés positifs sont pertinents
- La localisation est correcte

## 📞 Support

Pour les problèmes ou les suggestions, ouvrir une issue sur le dépôt GitHub.

## 📄 Licence

MIT

---

**Auteur :** Manus AI
**Date de création :** 4 Mars 2026
**Version :** 1.0.0
