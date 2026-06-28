# WebIArtisan API

API backend de la plateforme WebIArtisan, déployée sur `api.prigent.tech`.

## Stack

- PHP 8
- MySQL / MariaDB
- Stripe
- Dompdf

## Rôle

L’API centralise la logique métier :

- authentification
- multi-tenant
- brand center
- génération et publication de sites
- gestion clients / devis / factures
- mobile terrain
- utilisateurs et rôles
- abonnements et paiements
- endpoints publics

## Point d’entrée

- front controller : `index.php`

Toutes les requêtes transitent par ce point d’entrée.

## Configuration

Fichiers principaux :

- `config/app.php`
- `config/database.php`
- `.env`
- `.env.example`

Variables importantes :

- `APP_URL`
- `API_URL`
- `SITE_BASE_URL`
- `SITE_OUTPUT_DIR`
- `JWT_SECRET`
- `STRIPE_SECRET_KEY`
- `STRIPE_PUBLISHABLE_KEY`
- `STRIPE_WEBHOOK_SECRET`

## Middleware

- `middleware/Cors.php`
- `middleware/Auth.php`
- `middleware/Tenant.php`
- `middleware/PlanQuota.php`

## Routes

### Publiques

- `auth.php`
- `public.php`
- `status.php`
- `webhooks.php`

### Authentifiées

- `brand.php`
- `website.php`
- `gestion.php`
- `mobile.php`
- `dashboard.php`
- `users.php`
- `payments.php`
- `subscription.php`

### Test / administration

- `payments-test.php`

## Dossiers utiles

```text
sites/api/
├── config/
├── lib/
├── middleware/
├── migrations/
├── models/
├── routes/
├── tests/
├── views/
├── index.php
└── composer.json
```

## Développement local

Depuis la racine :

```bash
make dev-api
```

Depuis `sites/api/` :

```bash
make dev
make test
```

## Déploiement

```bash
make push
```

Le déploiement utilise le symlink local `production/` vers `api.prigent.tech`.

## Paiements Stripe

Voir aussi :

- `STRIPE.md`
- `routes/payments.php`
- `routes/subscription.php`
- `routes/webhooks.php`
