# Design : Reset qualitatif des données Combs-la-Ville + admin POI + bouton app Android

## Vue d'ensemble

Remplacer les données de test de Combs-la-Ville par un petit échantillon qualitatif de POI, donner les droits d'administration locale au compte `supert0f@proton.me`, ajouter les endpoints API nécessaires pour éditer les POI/horaires, et afficher un bouton d'installation de l'app Android dans le footer des sites ville.

## Scope

- Ville concernée : **Combs-la-Ville** (`local_cities.id = 1`, slug `combs-la-ville`).
- Livry et Vert-Saint-Denis ne sont pas touchés.
- Comptes utilisateurs (`local_users`, `users`) préservés.

## Tables nettoyées pour `city_id = 1`

Dans cet ordre (filles d'abord, parents ensuite) :

- `local_schedules`
- `local_reviews`
- `local_services`
- `local_testimonial_media`
- `local_testimonial_reports`
- `local_testimonials`
- `local_recipe_ingredients`
- `local_recipe_steps`
- `local_recipe_artisans`
- `local_recipe_reports`
- `local_recipes`
- `local_spin_wins`
- `local_spin_offers`
- `local_game_instances`
- `local_prospect_follow_ups`
- `local_prospects`
- `local_pois`
- `local_artisans`

Chaque suppression est précédée d'une backup horodatée (`_backup_YYYYMMDD`) pour Combs uniquement.

## POI qualitatifs à insérer

| Type | Nom | Source principale |
|------|-----|-------------------|
| `piscine` | Centre Aquatique Camille Muffat | OpenStreetMap (Overpass) |
| `tabac` | Tabac La Motte | OpenStreetMap (Overpass) |
| `mairie` | Mairie de Combs-la-Ville | OpenStreetMap (Overpass) |
| `supermarche` | Lidl Combs-la-Ville | OpenStreetMap (Overpass) |
| `supermarche` | Intermarché Combs-la-Ville | OpenStreetMap (Overpass) |

### Intermarché

Un seul POI avec les sous-boutiques listées dans `description` et/ou `meta.sub_shops` :

- Pharmacie
- Opticien
- Vapoteuse
- Boulangerie
- Station essence

### Données par POI

- `city_id = 1`
- `type`, `name`, `address`
- `latitude`, `longitude` (depuis OSM)
- `phone`, `website`, `email` si publics
- `description` courte
- `meta` JSON avec `opening_hours` brut OSM
- `is_active = 1`
- `sort_order` cohérent

### Horaires

Une ligne par jour (`day_of_week` 0-6) dans `local_schedules` avec `open_time`, `close_time`, `break_start`, `break_end`, `is_closed`.

## Compte super-admin local

Un enregistrement sera créé dans `local_artisans` pour `supert0f@proton.me` avec :

- `city_id = 1`
- `company_name = 'Admin Combs-la-Ville'`
- `status = 'active'`
- `email_verified = 1`
- `is_admin = 1`
- `plan = 'premium'`
- `password_hash` généré à partir d'un mot de passe robuste fourni séparément (non commité). Le hash sera produit avec `password_hash($password, PASSWORD_BCRYPT)` et inséré manuellement en production.

Ce compte utilisera les endpoints `/admin/*` via `artisan_require_admin()`.

## Endpoints API admin POI

Ajoutés dans `sites/api/routes/admin.php`, protégés par `artisan_require_admin()` :

- `GET /admin/pois` — liste des POI de la ville de l'admin + horaires
- `GET /admin/pois/:id` — détail d'un POI + horaires
- `POST /admin/pois` — création
- `PUT /admin/pois/:id` — mise à jour
- `DELETE /admin/pois/:id` — suppression (avec horaires)
- `POST /admin/pois/:id/schedules` — ajout d'un horaire
- `PUT /admin/schedules/:id` — modification d'un horaire
- `DELETE /admin/schedules/:id` — suppression d'un horaire

### Validation

- `latitude` ∈ [-90, 90]
- `longitude` ∈ [-180, 180]
- `type` dans une liste autorisée : `mairie`, `piscine`, `tabac`, `supermarche`, `restaurant`, `cafe`, `pharmacie`, `boulangerie`, `coiffeur`, `plombier`, `jardinier`, `autre`
- `name` requis, ≤ 255 caractères

## Bouton Android dans le footer

- URL : `https://appdistribution.firebase.dev/i/1297b31002780ac2`
- Emplacement : footer global de `sites/artisans-shared`
- Label : "Installer l'app Android"
- Cible : `_blank`
- Déploiement sur les 3 sites ville après rebuild.

## Livrables

1. `scripts/reset-combs-quali.sql`
2. `scripts/seed-combs-quali.sql`
3. Modifications dans `sites/api/routes/admin.php`
4. Modifications dans le footer de `sites/artisans-shared`
5. Rebuild et déploiement des 3 villes

## Sécurité

- Les scripts SQL destructeurs sont exécutés manuellement via phpMyAdmin, pas au déploiement automatique.
- Backup horodatée avant suppression.
- Le mot de passe admin n'est pas commité en clair ; le hash est généré localement puis inséré en prod via phpMyAdmin ou variable d'environnement.
