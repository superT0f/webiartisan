# WebiArtisan

Plateforme d’annuaires d’artisans locaux, conçue pour être déployée ville par ville autour d’une API PHP centrale et d’un socle Vue.js partagé.

> Version courante : **1.1.0**

## Sommaire

- [Résumé fonctionnel](#résumé-fonctionnel)
- [Stack technique](#stack-technique)
- [Démarrage rapide](#démarrage-rapide)
- [Développement](#développement)
- [Tests](#tests)
- [Déploiement production (Gandi)](#déploiement-production-gandi)
  - [Prérequis](#prérequis)
  - [Ordre de déploiement](#ordre-de-déploiement)
  - [Migrations base de données](#migrations-base-de-données)
- [Architecture du dépôt](#architecture-du-dépôt)
- [Configuration](#configuration)
- [Licence](#licence)

## Résumé fonctionnel

WebiArtisan met en relation artisans et habitants à l’échelle locale. La plateforme offre :

- **Annuaire public par ville** : recherche, carte géographique et fiches détaillées par artisan.
- **Back-office artisans** : gestion de profil, clients, devis et factures.
- **Brand center** : personnalisation de l’identité visuelle et génération de site vitrine.
- **Mobile terrain** : accès dédié aux interventions et déplacements.
- **Abonnements et paiements** : souscription et facturation via Stripe.
- **Multi-villes / multi-tenant** : un même socle technique alimente plusieurs annuaires locaux (Livry, Combs-la-Ville, Vert-Saint-Denis, etc.).
- **Catalogue de services artisanaux** : chaque artisan peut définir et activer ses propres services (plomberie, coiffure, jardinage, couture, recettes locales…).
- **Témoignages / recommandations génériques** : les habitants publient des recommandations sur un service utilisé, avec modération, notation et signalement.
- **Jeux sur la carte** : check-in GPS gratuit (100 XP par jour et par point, puis 10 XP toutes les 10 minutes), coupons offerts par les commerçants, et le jeu premium « Tournez l'avatar » en boutique.
- **Gamification** : XP, niveaux, badges, séries de connexion et avatars débloquables pour animer la communauté.

## Stack technique

- **Backend** : PHP 8, MySQL 8, Nginx
- **Frontend** : Vue 3, Vue Router, Vite, Leaflet
- **Paiement** : Stripe (carte + webhooks)
- **PDF** : Dompdf
- **Conteneurisation** : Docker Compose
- **Déploiement** : Gandi Simple Hosting via `make push-*`

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

L’application est accessible sur `http://localhost` et l’API sur `http://localhost:8080/api`.

## Développement

| Commande | Description |
|----------|-------------|
| `make up` | Démarre Docker Compose (nginx, php, mysql, node) |
| `make down` | Arrête la stack |
| `make migrate` | Exécute toutes les migrations SQL (025 → 033) |
| `make seed` | Injecte les données de démo Livry |
| `make dev` | Affiche les logs du serveur Vite |
| `make build` | Compile le frontend Livry pour la production |
| `make test-api` | Lance les tests de fumée de l’API |
| `make push-api` | Déploie l’API PHP sur Gandi |
| `make push-livry` | Déploie le site Livry sur Gandi |
| `make build-combs` / `make push-combs` | Build / déploiement de Combs-la-Ville |
| `make build-vsd` / `make push-vsd` | Build / déploiement de Vert-Saint-Denis |
| `make deploy-all` | Build et déploie l’API + les trois villes |

## Tests

```bash
make test-api
```

Le script `scripts/test-api.sh` vérifie les endpoints publics, l’authentification artisan, l’authentification consommateur, le jeu de l'avatar (spin), les profils utilisateurs, la gamification, les témoignages, les mini-jeux (coupon) et les check-ins GPS.

## Authentification consommateur

Les visiteurs peuvent créer un compte et se connecter de plusieurs façons :

- **Magic-link** : un lien de connexion est envoyé par email.
- **Email + mot de passe** : inscription classique avec réinitialisation de mot de passe.
- (Phase 2 : connexion via Google OAuth.)

La case **"Rester connecté"** est cochée par défaut et crée un cookie `user_token` valable 365 jours.  
Le logout invalide le token côté serveur.

Les artisans connectés à leur espace peuvent cliquer sur **"Jouer sur la carte"** pour obtenir un compte visiteur lié et participer aux jeux comme un habitant.

## Déploiement production (Gandi)

### Prérequis

- Le dossier Gandi est monté localement sur `~/mnt/gandi/vhosts/`.
- Créer le lien symbolique utilisé par le `Makefile` de l’API :

```bash
ln -s /mnt/gandi/vhosts/api.prigent.tech/htdocs sites/api/production
```

### Ordre de déploiement

1. **Base de données** : si une livraison contient de nouvelles migrations, les jouer en production en premier (via phpMyAdmin ou la CLI Gandi).
2. **API** : pousser le backend.
3. **Frontends** : builder et pousser chaque ville.

```bash
# 1. API
make push-api

# 2. Frontends (Livry, Combs-la-Ville, Vert-Saint-Denis)
make deploy-all
```

> L’API est déployée sur `api.prigent.tech`. Les sites villes sont déployés sur `artisans-livry.prigent.tech`, `artisans-combs.prigent.tech` et `artisans-vert-saint-denis.prigent.tech`.

### Migrations base de données

Les migrations sont versionnées dans `sites/api/migrations/`. Pour la version courante, exécuter en production **dans l’ordre** :

1. `sites/api/migrations/029_testimonials_services.sql`
2. `sites/api/migrations/030_mini_games.sql`
3. `sites/api/migrations/031_email_queue.sql`
4. `sites/api/migrations/032_user_password_auth.sql`
5. `sites/api/migrations/033_artisan_premium.sql`

Ces scripts utilisent `CREATE TABLE IF NOT EXISTS` et des vérifications de colonnes, ils peuvent donc être rejoués sans danger.

## Architecture du dépôt

```text
.
├── sites/api/                    # API PHP centrale
├── sites/artisans-shared/        # Composants Vue partagés entre les villes
├── sites/webiartisan-livry/      # Frontend Livry
├── sites/webiartisan-combs/      # Frontend Combs-la-Ville
├── sites/webiartisan-vert-saint-denis/  # Frontend Vert-Saint-Denis
├── docker/                       # Configuration Nginx et PHP
├── data/seeds/                   # Données de démonstration
├── scripts/                      # Scripts d’administration et de test
└── docs/superpowers/             # Spécifications et plans
```

## Configuration

Copiez `.env.example` vers `.env` et ajustez les variables selon votre environnement :

- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `VITE_API_URL`, `VITE_CITY_SLUG`, `VITE_CITY_NAME`, `VITE_CITY_LAT`, `VITE_CITY_LNG`, `VITE_CITY_CP`
- Clés Stripe (`STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET`)
- Identifiant de prix Stripe Premium (`STRIPE_PREMIUM_MONTHLY_PRICE_ID`)
- Secret JWT (`JWT_SECRET`)

## Licence

Projet privé — WebiArtisan.
