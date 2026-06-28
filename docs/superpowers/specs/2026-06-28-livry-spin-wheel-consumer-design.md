# Livry — Roue consommateur géolocalisée (Spin Wheel)

## Contexte

Après avoir intégré la prospection B2B inspirée de Connect Club, l’étape suivante est d’offrir une expérience ludique aux **habitants et visiteurs de Livry** : une roue de la fortune qui distribue des offres promotionnelles mises à disposition par les artisans locaux. L’objectif est d’attirer du trafic en magasin et de valoriser les artisans.

## Objectif

Permettre à un utilisateur authentifié de tourner une roue **une fois par jour** pour gagner une offre d’un artisan de Livry, dans la limite des stocks définis par l’artisan. L’offre gagnée est matérialisée par un **QR code / code unique** que l’artisan peut valider.

## Décisions clés

- Authentification simple par **email + magic link**.
- Géolocalisation : l’utilisateur doit être **dans la ville de Livry** (pas de contrainte de distance stricte dans le MVP).
- Chaque offre a un **stock global** ; quand il est épuisé, l’offre ne sort plus.
- **Un spin par utilisateur et par jour**.
- L’artisan gère ses offres depuis son espace (`/espace/spin-offers`).
- L’artisan valide le gain via un endpoint protégé par son token (`POST /artisans/me/spin-wins/:code/validate`).

## Architecture

### Modèle de données

`local_users`

| Champ | Type | Description |
|-------|------|-------------|
| `id` | INT PK | |
| `email` | VARCHAR(255) UNIQUE | |
| `magic_token` | VARCHAR(64) | Token temporaire connexion |
| `magic_token_exp` | DATETIME | Expiration |
| `session_token` | VARCHAR(64) | Token de session |
| `session_exp` | DATETIME | |
| `created_at` | TIMESTAMP | |

`local_spin_offers`

| Champ | Type | Description |
|-------|------|-------------|
| `id` | INT PK | |
| `artisan_id` | INT FK | Artisan offreur |
| `label` | VARCHAR(200) | Texte affiché sur la roue |
| `description` | TEXT | Détails |
| `stock_total` | INT | Stock initial |
| `stock_remaining` | INT | Stock restant |
| `is_active` | BOOLEAN | |
| `created_at` / `updated_at` | TIMESTAMP | |

`local_spin_wins`

| Champ | Type | Description |
|-------|------|-------------|
| `id` | INT PK | |
| `user_id` | INT FK | Gagnant |
| `offer_id` | INT FK | Offre gagnée |
| `artisan_id` | INT FK | Artisan concerné |
| `code` | VARCHAR(32) UNIQUE | Code / QR code |
| `status` | ENUM | `pending`, `claimed`, `expired` |
| `spin_date` | DATE | Date du spin |
| `claimed_at` | TIMESTAMP | Date de validation |
| `expires_at` | TIMESTAMP | Offre valable 7 jours |

`local_spin_daily_limits`

| Champ | Type | Description |
|-------|------|-------------|
| `id` | INT PK | |
| `user_id` | INT FK | |
| `city_id` | INT FK | |
| `spin_date` | DATE | |
| `count` | INT | Nombre de spins effectués |
| UNIQUE KEY (`user_id`, `city_id`, `spin_date`) |

### API

**Authentification utilisateur**

- `POST /users/magic-link` — envoie un lien magique.
- `POST /users/auth?token=...` — valide le token et crée une session.
- `GET /users/me` — infos utilisateur connecté.

**Roue**

- `GET /spin/offers?city=livry` — offres actives pour la ville (stock > 0).
- `POST /spin` — effectue un spin.
  - Headers : `Authorization: Bearer <session_token>`.
  - Body : `{ city_slug: 'livry', latitude, longitude }`.
  - Vérifie : utilisateur connecté, ville existante, 1 spin/jour, stock disponible.
  - Retourne : l’offre gagnée, `code`, `expires_at`.
- `GET /spin/wins` — historique des gains.

**Validation artisan**

- `GET /artisans/me/spin-wins?status=pending` — gains en attente sur cet artisan.
- `POST /artisans/me/spin-wins/:code/validate` — marque un gain comme `claimed`.

**Gestion des offres (artisan)**

- `GET /artisans/me/spin-offers`
- `POST /artisans/me/spin-offers`
- `PUT /artisans/me/spin-offers/:id`
- `DELETE /artisans/me/spin-offers/:id`

### Frontend

**Publique**

- `/roue` : page principale.
  - Si non connecté : formulaire email.
  - Si connecté : bouton “Tourner la roue” (si pas déjà tourné aujourd’hui).
  - Animation CSS/Canvas de la roue avec les labels des offres actives.
  - Affichage du gain : offre, artisan, code QR, date d’expiration.
  - Section “Mes gains”.

**Espace artisan**

- `/espace/spin-offers` : CRUD des offres.
- `/espace/spin-wins` : liste des gains à valider + champ de saisie du code.

### Génération du QR code

Le backend génère un code alphanumérique unique (ex. `LIV-XXXXXX`). Le frontend peut afficher ce code sous forme de QR code via une librairie (`qrcode` npm) ou simplement comme texte dans le MVP.

### Sécurité

- Rate-limit sur `/users/magic-link` et `/spin`.
- Le code de validation n’est visible que par l’utilisateur gagnant et l’artisan au moment de la validation.
- Les offres expirées après 7 jours passent au statut `expired`.

## Tests

- `make test-api` étendu avec les nouvelles routes.
- Test du flux : magic link → spin → validation artisan.
- Build Vite sans erreur.

## Phases suggérées

1. **Phase 1** : Auth utilisateur + tables SQL.
2. **Phase 2** : Gestion des offres par l’artisan.
3. **Phase 3** : Endpoint spin + logique stock/journalière.
4. **Phase 4** : Frontend roue + affichage gain.
5. **Phase 5** : Validation artisan + QR code.

## Ouvertures futures

- Géolocalisation stricte (à moins de 500 m d’un artisan).
- QR code physique chez l’artisan pour débloquer un bonus.
- Classement des artisans les plus généreux.
- Notifications email de rappel avant expiration.
