# WebiArtisan

[![LIVE](https://img.shields.io/badge/LIVE-online-brightgreen)](https://api.prigent.tech/api/health)
[![Version](https://img.shields.io/badge/version-1.1.0-blue)](https://api.prigent.tech/api/health)
[![Stack](https://img.shields.io/badge/stack-PHP%208%20%C2%B7%20Vue%203%20%C2%B7%20MySQL%208-informational)](#stack-technique)
[![Licence](https://img.shields.io/badge/licence-priv%C3%A9e-lightgrey)](#licence)

> Plateforme d'annuaires d'artisans locaux **gamifiée** : carte jouable, objets à ramasser, quêtes, combats de boss — au service des artisans et du savoir-faire français.

🔗 **Application Android** : [webiartisan-flutter-app (GitLab)](https://gitlab.com/SuperT0f/webiartisan-flutter-app)

## Sommaire

- [Résumé fonctionnel](#résumé-fonctionnel)
- [Stack technique](#stack-technique)
- [Démarrage rapide](#démarrage-rapide)
- [Développement](#développement)
- [Tests](#tests)
- [Déploiement production](#déploiement-production)
- [Architecture du dépôt](#architecture-du-dépôt)
- [Configuration](#configuration)
- [Licence](#licence)

## Résumé fonctionnel

WebiArtisan met en relation artisans et habitants à l'échelle locale, avec une couche de jeu complète qui anime la communauté :

- **Annuaire public par ville** : recherche, carte géographique et fiches détaillées par artisan.
- **Carte jouable (MapLibre, mode 3D basculable)** : check-in GPS (500 m, 100 XP), objets du monde à ramasser (déchets, canettes, trésors 💎, cadeaux artisans 🎁) dans un rayon de 150 m.
- **Anneau de swipe** : chaque action se valide en balayant un anneau plein écran, avec sons et haptique.
- **Énergie & quêtes** : endurance (100 ⚡, régénérée dans le temps ou en visitant les artisans), 3 quêtes quotidiennes, podium des nettoyeurs et score « ville propre » par ville.
- **Arène Big Brother** 🎩🏭 : le méchant anti-artisan apparaît sur la carte — duels à HP en quiz savoir-faire, mat en 1 coup et duel de cartes (résolus côté serveur, moteur Phaser).
- **Back-office artisans** : gestion de profil, clients, devis et factures, cadeaux sur la carte, photos des POI (revendication validée par l'admin).
- **Abonnements et paiements** : souscription et facturation via Stripe.
- **Multi-villes / multi-tenant** : un même socle alimente plusieurs annuaires (Livry, Combs-la-Ville, Vert-Saint-Denis, Lieusaint).
- **Application Android** : WebView native avec géoloc, haptique, biométrie et deep links (`artisans-*.prigent.tech` → ouverture directe dans l'app).

## Stack technique

| Couche | Technologies |
|--------|--------------|
| **Backend** | PHP 8, MySQL 8, Nginx |
| **Frontend** | Vue 3, Vue Router, Vite, MapLibre GL 5, Phaser 3 |
| **Mobile** | Flutter (repo séparé, voir lien ci-dessus) |
| **Paiement** | Stripe (carte + webhooks) |
| **Conteneurisation** | Docker Compose |
| **Déploiement** | rsync via `make push-*` |

## Démarrage rapide

```bash
# Lancer la stack locale
make up

# Appliquer les migrations
make migrate

# Charger les données de démo (Livry)
make seed

# Démarrer le serveur de développement Vite
make dev
```

L'application est accessible sur `http://localhost` et l'API sur `http://localhost:8080/api`.

## Développement

| Commande | Description |
|----------|-------------|
| `make up` / `make down` | Démarre / arrête la stack Docker |
| `make migrate` | Exécute toutes les migrations SQL |
| `make seed` | Injecte les données de démo Livry |
| `make dev` | Affiche les logs du serveur Vite |
| `make mysql` | Shell MySQL dans le conteneur |
| `make db-apply FILE=…` | Applique un fichier de migration précis |
| `make test-php FILE=…` | Lance un test PHP (`sites/api/tests`) |
| `make test-php-all` | Lance toute la suite PHP |
| `make e2e FILE=…` / `make e2e-all` | Tests e2e puppeteer (stack + Vite requis) |
| `make push-api` | Déploie l'API PHP |
| `make -C sites/<ville> push` | Build + déploie une ville |

## Tests

```bash
make test-php-all   # suites API (auth, gamification, objets, quêtes, boss, POI…)
npm test            # vitest (sites/artisans-shared) : géométrie, énergie, échecs, cartes…
make e2e-all        # parcours complets puppeteer (carte, objets, cadeaux, arène…)
```

## Déploiement production

Déploiement en prod **sur mesure pour Gandi Simple Hosting** : ne marchera pas chez vous.

## Architecture du dépôt

```text
.
├── sites/api/                    # API PHP centrale (routes, libs, migrations, tests)
├── sites/artisans-shared/        # Socle Vue partagé (carte, jeux, arène, composables)
├── sites/webiartisan-{ville}/    # Shells Vite par ville (build + déploiement)
├── sites/app-landing/            # Page d'accueil produit
├── docker/                       # Configuration Nginx et PHP
├── e2e/                          # Tests end-to-end puppeteer
├── data/seeds/                   # Données de démonstration
├── scripts/                      # Scripts d'administration et de test
└── docs/superpowers/             # Spécifications et plans
```

## Configuration

Copiez `.env.example` vers `.env` et ajustez les variables selon votre environnement :

- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `VITE_API_URL`, `VITE_CITY_SLUG`, `VITE_CITY_NAME`, `VITE_CITY_LAT`, `VITE_CITY_LNG`, `VITE_CITY_CP`, `VITE_MAPTILER_KEY`
- Clés Stripe (`STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET`)
- Identifiant de prix Stripe Premium (`STRIPE_PREMIUM_MONTHLY_PRICE_ID`)
- Secret JWT (`JWT_SECRET`)

## Licence

Projet privé — WebiArtisan.
