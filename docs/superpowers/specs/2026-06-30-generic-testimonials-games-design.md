# Design — Témoignages génériques et hub de mini-jeux

## Contexte

WebiArtisan dispose actuellement de deux modules très spécifiques :
- un module **Recettes**, trop orienté nourriture,
- un module **Roue** (spin wheel), trop mis en avant et isolé.

L’objectif est de les transformer en deux modules plus génériques et cohérents :
- des **témoignages / recommandations d’artisan** pour tout type de service (nourriture, couture, coiffure, jardinage, plomberie, etc.),
- un **hub de mini-jeux** intégrant la roue dans une liste plus large (coupon, sondage, vote/battle, quiz, bingo, rébus, etc.).

## Objectifs

- Ne plus mettre en avant la roue comme fonctionnalité centrale.
- Permettre aux artisans de définir leurs services.
- Généraliser les avis clients au-delà de la nourriture.
- Conserver et enrichir la gamification (XP, niveaux, badges).
- Préparer un modèle freemium simple.

## Décisions clés

| Sujet | Décision |
|-------|----------|
| Témoignages | Texte libre + modèles optionnels selon le type de service. |
| Authentification | **Stricte** : compte utilisateur gratuit obligatoire pour poster. Mise en avant de la création de compte. |
| Services artisan | Catalogue partagé + personnalisation. **5 services max** en version gratuite. |
| Mini-jeux hub | Page ville `/ville/:slug/jeux` + onglet artisan. Configuration JSON par instance. |
| Jeux gratuits V1 | Coupon, sondage, vote/battle. |
| Jeux premium V1 | Roue, quiz, bingo, rébus (affichés mais bloqués). |
| Limites gratuites | 2 jeux actifs simultanés max. Limite par instance, pas par artisan. |
| Gamification | `testimonial_view`, `testimonial_post`, `game_play`, `game_win`. Badges `curieux`, `chanceux`, `ambassadeur`, `joueur`, `vainqueur`. |
| API XP | Exposée en HTTP pour évolutivité et intégrations tierces. |
| Migration | Reset propre. Pas de support des anciennes recettes. Bandeau Beta dans l’UI. |

## Modèle de données

### `local_service_catalog` — Catalogue global de types de services

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | |
| `key` | VARCHAR(50) UNIQUE | Ex: `food_recipe`, `haircut`, `gardening`, `sewing`. |
| `label_fr` | VARCHAR(100) | Libellé français. |
| `icon` | VARCHAR(100) | Icône / emoji. |
| `category` | VARCHAR(50) | Catégorie regroupante. |
| `is_active` | BOOLEAN | |
| `testimonial_templates` | JSON | Modèles de texte optionnels. |

### `local_services` — Services proposés par un artisan (table existante enrichie)

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | |
| `artisan_id` | INT FK | |
| `service_catalog_id` | INT FK NULL | Référence `local_service_catalog`. NULL si service entièrement personnalisé. |
| `name` | VARCHAR(200) | Libellé affiché (catalogue ou personnalisé). |
| `description` | TEXT | |
| `price_range` | VARCHAR(100) | Existant, conservé. |
| `duration` | VARCHAR(100) | Existant, conservé. |
| `is_custom` | BOOLEAN | TRUE si service hors catalogue. |
| `is_active` | BOOLEAN | |
| `sort_order` | SMALLINT | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

### `local_testimonials` — Témoignages / recommandations

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | |
| `artisan_id` | INT FK | |
| `user_id` | INT FK | Obligatoire (authent stricte). |
| `artisan_service_id` | INT FK NULL | Service concerné. |
| `service_type` | VARCHAR(50) | Clé dénormalisée pour filtres. |
| `rating` | TINYINT | 1-5 ou NULL selon le type. |
| `title` | VARCHAR(150) | |
| `content` | TEXT | Texte libre. |
| `status` | ENUM('pending','approved','rejected','flagged') | |
| `helpful_count` | INT | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

### `local_testimonial_media` — Médias associés

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | |
| `testimonial_id` | INT FK | |
| `media_url` | VARCHAR(255) | |
| `media_type` | ENUM('image','video') | |
| `display_order` | INT | |

### `local_testimonial_reports` — Signalements

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | |
| `testimonial_id` | INT FK | |
| `reporter_user_id` | INT FK | |
| `reason` | VARCHAR(100) | |
| `status` | ENUM('open','resolved','dismissed') | |
| `created_at` | TIMESTAMP | |

### `local_game_types` — Types de jeux disponibles

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | |
| `key` | VARCHAR(50) UNIQUE | Ex: `wheel`, `coupon`, `quiz`, `poll`, `vote`, `bingo`, `rebus`. |
| `label_fr` | VARCHAR(100) | |
| `description` | TEXT | |
| `is_premium` | BOOLEAN | |
| `is_active` | BOOLEAN | |
| `default_config` | JSON | Schéma de configuration par défaut. |
| `engine_component` | VARCHAR(50) | Composant frontend associé. |

### `local_game_instances` — Instances de jeux actives

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | |
| `game_type_id` | INT FK | |
| `artisan_id` | INT FK | |
| `city_id` | INT FK | |
| `title` | VARCHAR(150) | |
| `description` | TEXT | |
| `config` | JSON | Configuration spécifique. |
| `is_active` | BOOLEAN | |
| `starts_at` | TIMESTAMP NULL | |
| `ends_at` | TIMESTAMP NULL | |
| `max_plays_per_user` | INT DEFAULT 1 | Limite par instance. |
| `play_cooldown_hours` | INT DEFAULT 24 | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

### `local_game_plays` — Participations

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | |
| `game_instance_id` | INT FK | |
| `user_id` | INT FK | |
| `result` | JSON | Réponse / gain / score. |
| `xp_awarded` | INT | |
| `created_at` | TIMESTAMP | |

### `local_game_rewards` — Récompenses associées à une instance

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | |
| `game_instance_id` | INT FK | |
| `label` | VARCHAR(150) | |
| `reward_type` | ENUM('coupon','points','badge','nothing') | |
| `reward_value` | JSON | |
| `probability` | DECIMAL(5,4) NULL | Pour jeux probabilistes. |
| `stock` | INT NULL | |
| `claimed_count` | INT DEFAULT 0 | |

### Gamification — tables existantes enrichies

Mise à jour de `local_xp_events` :
- supprimer / ne plus utiliser : `recipe_view`, `spin_play`, `recipe_suggest`, `review`.
- ajouter : `testimonial_view`, `testimonial_post`, `game_play`, `game_win`.

Mise à jour de `local_badges` :
- `curieux` : X témoignages vus.
- `chanceux` : X jeux joués.
- `ambassadeur` : X témoignages publiés.
- `joueur` : X parties jouées.
- `vainqueur` : X récompenses gagnées.

## API endpoints

### Témoignages — `/api/testimonials`

| Méthode | Endpoint | Rôle | Description |
|---|---|---|---|
| GET | `/api/testimonials` | public | Liste paginée (filtres: `artisan_id`, `city_id`, `service_type`, `rating`, `sort`). |
| GET | `/api/testimonials/:id` | public | Détail. |
| POST | `/api/testimonials` | user | Créer. Vérifie `can_user_testify(user_id, artisan_id)`. |
| PATCH | `/api/testimonials/:id` | auteur/admin | Modifier (fenêtre 24h ou modération). |
| DELETE | `/api/testimonials/:id` | auteur/admin | Suppression logique. |
| POST | `/api/testimonials/:id/report` | user | Signaler. |
| POST | `/api/testimonials/:id/helpful` | user | Marquer utile. |
| GET | `/api/testimonials/templates` | public | Modèles par type de service. |

### Services artisan — `/api/artisans/:id/services`

| Méthode | Endpoint | Rôle | Description |
|---|---|---|---|
| GET | `/api/artisans/:id/services` | public | Services de l’artisan. |
| POST | `/api/artisans/:id/services` | artisan | Ajouter (catalogue ou personnalisé). |
| PATCH | `/api/artisans/:id/services/:service_id` | artisan | Modifier / activer / désactiver. |
| DELETE | `/api/artisans/:id/services/:service_id` | artisan | Supprimer. |
| GET | `/api/service-catalog` | public | Catalogue global. |

### Mini-jeux — `/api/games`

| Méthode | Endpoint | Rôle | Description |
|---|---|---|---|
| GET | `/api/games/types` | public | Types + flag freemium. |
| GET | `/api/games` | public | Instances actives (ville / artisan). |
| GET | `/api/games/:instance_id` | public | Détail + règles + état utilisateur. |
| POST | `/api/games/:instance_id/play` | user | Jouer. |
| POST | `/api/games/:instance_id/claim` | user | Réclamer un gain. |
| GET | `/api/games/:instance_id/leaderboard` | public | Classement local. |
| POST | `/api/artisans/:id/games` | artisan | Créer une instance. |
| PATCH | `/api/artisans/:id/games/:instance_id` | artisan | Modifier / activer / désactiver. |
| DELETE | `/api/artisans/:id/games/:instance_id` | artisan | Supprimer. |

### Gamification — `/api/gamification`

| Méthode | Endpoint | Rôle | Description |
|---|---|---|---|
| GET | `/api/gamification/events` | public | Liste événements XP. |
| GET | `/api/users/:id/xp` | user | XP, level, badges, streak. |
| POST | `/api/gamification/xp` | interne/scope limité | Enregistrer un événement XP. |
| GET | `/api/leaderboards/city/:city_id` | public | Classement ville. |

## Frontend / composants

### Pages publiques

| Page | Objectif | Composants clés |
|---|---|---|
| `/ville/:slug/jeux` | Hub mini-jeux ville | `GameCardGrid`, `GameFilterBar`, `CityLeaderboard` |
| `/ville/:slug/artisans/:artisan_slug` | Fiche artisan | `ArtisanHeader`, `ServiceList`, `TestimonialList`, `ActiveGames` |
| `/jeu/:instance_id` | Participation | `GameRenderer`, `RulesPanel`, `RewardModal` |
| `/temoignages` | Flux de recommandations | `TestimonialFeed`, `TestimonialComposerCTA` |
| `/inscription` | Conversion compte gratuit | `SignupBenefits` |

### Espace artisan (`/artisan/dashboard`)

| Vue | Objectif | Composants clés |
|---|---|---|
| `Services` | Gérer services | `ServiceCatalogPicker`, `ServiceEditor`, `ServiceVisibilityToggle` |
| `Avis & témoignages` | Modérer / répondre | `TestimonialModerationList`, `TestimonialReplyForm` |
| `Mini-jeux` | Créer jeux | `GameInstanceBuilder`, `GameTypeSelector`, `FreemiumLimitBanner` |
| `Statistiques` | Performance | `EngagementChart`, `ConversionFunnel`, `TopTestimonials` |

### Espace admin

- Modération des témoignages signalés.
- CRUD catalogue de services.
- Gestion des types de jeux et flags premium.
- Paramètres XP, badges, seuils.

### Composants transverses

- `TestimonialCard` — auteur, service, rating, contenu, photos, actions.
- `ServiceTag` — filtre témoignages et jeux.
- `GameCard` — type, titre, artisan, récompense, CTA.
- `GameRenderer` — rendu dynamique selon `game_type`.
- `XpToast` — notification +XP.
- `FreemiumBadge` — badge Gratuit / Premium.
- `BetaBanner` — bandeau Beta sur les nouvelles sections.

### Navigation

- Header ville : `Découvrir | Artisans | Jeux | Avis`.
- Fiche artisan : onglets `Services | Avis | Jeux actifs | À propos`.
- CTA conversion dans les flux publics.

## Plan de migration (allégé)

Le service étant en beta, on opte pour un **reset propre** :

- **Pas de migration des recettes existantes** vers les témoignages.
- **Anciennes tables** (`local_recipes`, `local_spin_offers`, etc.) conservées en lecture seule temporairement pour référence, puis supprimées dans une migration future après validation.
- **Anciennes URLs** (`/recette/:slug`, `/ville/:slug/roue`) renvoient une page 410 ou redirection vers le nouveau hub avec explication.
- **Bandeau Beta** affiché sur les sections témoignages et jeux pour gérer les attentes.

### Ordre de déploiement

1. Créer les nouvelles tables.
2. Déployer les nouvelles API et pages en parallèle des anciennes.
3. Remplacer les liens existants par les nouvelles pages.
4. Ajouter le bandeau Beta.
5. Planifier la suppression des anciennes tables dans une migration ultérieure.

## MVP & phasage

### V1 — Livraison

#### Témoignages
- [ ] Tables services et témoignages.
- [ ] API CRUD + signalement.
- [ ] Fiche artisan avec onglet Avis.
- [ ] Configuration des services par l’artisan (5 max gratuit).
- [ ] Bandeau Beta.

#### Mini-jeux
- [ ] Tables types et instances.
- [ ] Jeux gratuits : coupon, sondage, vote/battle.
- [ ] Jeux premium affichés mais bloqués : roue, quiz, bingo, rébus.
- [ ] Hub ville + onglet artisan (2 jeux actifs gratuits max).

#### Gamification
- [ ] Nouveaux event types XP.
- [ ] Badges mis à jour.
- [ ] API XP HTTP.

### V2 — Enrichissements
- Réponses des artisans aux témoignages (premium).
- Système de “vérifié”.
- Activation des jeux premium.
- Analytics détaillées.
- Templates intelligents selon service.

### Grille freemium

| Fonctionnalité | Gratuit | Premium |
|---|---|---|
| Services affichés | 5 max | Illimité |
| Types de jeux | coupon, sondage, vote/battle | + roue, quiz, bingo, rébus |
| Jeux actifs simultanés | 2 max | Illimité |
| Personnalisation visuelle | Basique | Avancée |
| Réponses aux avis | Non | Oui |
| Export stats | Non | Oui |

## Risques & hypothèses

### Risques

| Risque | Mitigation |
|---|---|
| Perte d’engagement en retirant la roue du premier plan | Garder la roue en premium, proposer des jeux gratuits attractifs, mesurer la participation. |
| Spam de témoignages | Authentification stricte, rate limiting, signalements, modération admin. |
| Artisans ne configurent pas leurs services | Onboarding guidé, templates par métier, valeurs par défaut. |
| Confusion des utilisateurs sur la disparition des recettes | Bandeau Beta, wording explicite, page d’information. |

### Hypothèses à valider

1. Les utilisateurs accepteront de créer un compte gratuit pour poster un avis.
2. Les artisans comprendront l’intérêt de définir leurs services.
3. Les jeux simples généreront suffisamment d’engagement.
4. Le freemium incitera à passer premium sans frustrer les utilisateurs gratuits.

## Open questions / notes

- Quelle est la politique exacte de modération préalable vs postérieure des témoignages ?
- Faut-il un système de “preuve d’achat” dès V1 ou en V2 ?
- Quels sont les paliers exacts de XP et les seuils de badges ?
- Quel provider d’envoi de magic link / auth utilise-t-on actuellement ?
