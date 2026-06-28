# Livry — Modules Prospection B2B & Recettes locales

## Contexte

Le POC Livry (`artisans-livry.prigent.tech`) dispose déjà d’un annuaire public d’artisans, d’un espace artisan connecté par lien magique, et d’un back-office léger en PHP + MySQL. Les deux nouveaux modules doivent s’inscrire dans cette architecture existante avec le moins de friction possible.

## Objectifs

1. **Module Prospection B2B** — donner aux artisans un outil simple de prospection commerciale sur leur territoire, inspiré de *Connect Club*.
2. **Module Recettes** — mettre en valeur la cuisine locale et les produits des artisans, inspiré de *Mealzy*.

## Décisions clés validées

- **Prospection B2B** : annuaire public des cibles (importées depuis les POI de la ville) + espace artisan privé pour les suivis.
- **Prospection B2B** : statuts et notes persistés **côté serveur** (MVP pragmatique).
- **Recettes** : recettes seed/admin + possibilité pour artisans et particuliers de proposer des recettes.
- **Recettes** : publication immédiate + bouton de signalement.
- **Recettes** : ingrédients structurés avec quantité, unité et nombre de portions.
- **Recettes** : prevoir un ajout "incomplet", qui puisse etre ameliorer completer par la communaute.

---

## Module 1 — Prospection B2B

### Modèle de données

Nouvelle table principale `local_prospects` :

| Champ | Type | Description |
|-------|------|-------------|
| `id` | INT PK | Identifiant |
| `city_id` | INT FK | Ville cible |
| `source_poi_id` | INT FK nullable | Lien vers `local_pois` si importé |
| `name` | VARCHAR(200) | Nom de l’établissement |
| `type` | VARCHAR(100) | Type métier (restaurant, boulangerie, etc.) |
| `zone` | VARCHAR(100) | Quartier / zone |
| `address` | TEXT | Adresse |
| `phone` | VARCHAR(20) | Téléphone |
| `email` | VARCHAR(255) | Email |
| `website` | VARCHAR(500) | Site web |
| `instagram` | VARCHAR(100) | Réseau social |
| `latitude` / `longitude` | DECIMAL | Coordonnées GPS |
| `pitch` | TEXT | Proposition de valeur / angle commercial |
| `weakness` | TEXT | Point de douleur observé |
| `is_active` | BOOLEAN | Affichage public |
| `created_at` / `updated_at` | TIMESTAMP | Dates |

Pipeline de suivi par artisan : `local_prospect_follow_ups` :

| Champ | Type | Description |
|-------|------|-------------|
| `id` | INT PK | |
| `prospect_id` | INT FK | Cible |
| `artisan_id` | INT FK | Artisan connecté |
| `status` | ENUM | `tocontact`, `contacted`, `meeting`, `converted`, `declined` |
| `notes` | TEXT | Notes privées |
| `updated_at` | TIMESTAMP | Dernière mise à jour |

Contrainte d’unicité : `(prospect_id, artisan_id)`.

### API

Routes publiques (module `prospects`) :

- `GET /prospects?city=livry&zone=&type=&search=` — liste des cibles actives.
- `GET /prospects/:id` — fiche publique d’une cible.

Routes privées artisan (module `artisans`) :

- `GET /artisans/me/prospects` — suivis de l’artisan connecté (avec jointure sur prospects).
- `POST /artisans/me/prospects/:id/follow` — créer / mettre à jour le suivi (status + notes).
- `DELETE /artisans/me/prospects/:id/follow` — supprimer le suivi.

### Frontend

- **Page publique** `/prospection` :
  - Toggle carte / liste.
  - Filtres zone / type / recherche.
  - Carte Leaflet avec marqueurs colorés par zone et bordure par statut global (si connecté).
  - Carte cliquable vers fiche prospect.
- **Fiche prospect** `/prospect/:id` :
  - Infos publiques, pitch, weakness, contact.
  - Si artisan connecté : sélecteur de statut + textarea notes.
- **Dashboard artisan** `/espace/prospection` :
  - Liste de ses suivis triés par pipeline.
  - Stats simples : nombre de cibles par statut.

### Import initial

Un script SQL `data/seeds/livry_prospects.sql` crée les prospects à partir des POI existants de Livry (types `supermarche`, `pharmacie`, `poste`, etc.) et complète avec quelques commerces fictifs. Il est monté dans `docker-entrypoint-initdb.d` pour le dev.

---

## Module 2 — Recettes locales

### Modèle de données

`local_recipes` :

| Champ | Type | Description |
|-------|------|-------------|
| `id` | INT PK | |
| `city_id` | INT FK | Ville concernée |
| `title` | VARCHAR(200) | Titre |
| `slug` | VARCHAR(220) UNIQUE | URL |
| `description` | TEXT | Description courte |
| `image_url` | VARCHAR(500) | Image |
| `prep_time_minutes` | INT | Temps de préparation |
| `cook_time_minutes` | INT | Temps de cuisson |
| `servings` | INT | Portions de base |
| `difficulty` | ENUM | `very_easy`, `easy`, `medium`, `hard` |
| `season` | VARCHAR(50) | `spring`, `summer`, `autumn`, `winter`, `all` |
| `is_premium` | BOOLEAN | Mise en avant |
| `is_incomplete` | BOOLEAN | Recette marquée comme incomplète ; ouverte aux compléments |
| `parent_recipe_id` | INT FK nullable | Lien vers la recette d’origine pour les variantes / compléments |
| `status` | ENUM | `published`, `reported`, `archived` |
| `submitted_by` | VARCHAR(100) | Nom affiché du contributeur |
| `submitter_email` | VARCHAR(255) | Email du contributeur |
| `created_at` / `updated_at` | TIMESTAMP | |

`local_recipe_ingredients` :

| Champ | Type | Description |
|-------|------|-------------|
| `id` | INT PK | |
| `recipe_id` | INT FK | |
| `name` | VARCHAR(150) | Ingrédient |
| `quantity` | DECIMAL(10,2) | Quantité pour `servings` |
| `unit` | VARCHAR(50) | g, ml, cuillère à soupe, etc. |
| `is_local` | BOOLEAN | Ingrédient local |
| `is_optional` | BOOLEAN | Optionnel |
| `sort_order` | INT | Ordre d’affichage |

`local_recipe_steps` :

| Champ | Type | Description |
|-------|------|-------------|
| `id` | INT PK | |
| `recipe_id` | INT FK | |
| `step_number` | INT | Ordre |
| `instruction` | TEXT | Texte de l’étape |

`local_recipe_artisans` (liens recette ↔ artisans producteurs) :

| Champ | Type | Description |
|-------|------|-------------|
| `id` | INT PK | |
| `recipe_id` | INT FK | |
| `artisan_id` | INT FK | Artisan local |

### API

Routes publiques (module `recipes`) :

- `GET /recipes?city=livry&difficulty=&season=&search=` — liste paginée.
- `GET /recipes/:slug` — détail d’une recette (ingrédients, étapes, artisans liés).
- `POST /recipes` — proposition d’une recette par un visiteur ou artisan.
- `POST /recipes/:id/report` — signalement.
- `POST /recipes/:id/suggest` — proposer un complément / variante (crée une nouvelle recette liée via `parent_recipe_id`).

Routes privées admin (dans l’espace artisan, protégées par un flag `is_admin` sur le compte artisan) :

- `GET /artisans/me/admin/recipes?status=reported` — recettes signalées.
- `PUT /artisans/me/admin/recipes/:id/archive` — archiver.

### Frontend

- **Page publique** `/recettes` :
  - Grille de cartes recette (image, titre, temps, difficulté, badges `local` / `premium`).
  - Filtres difficulté / saison / recherche.
- **Fiche recette** `/recette/:slug` :
  - Image hero, description, temps, portions, difficulté.
  - Sélecteur de portions qui recalcule les quantités.
  - Liste des ingrédients (locaux mis en avant avec lien vers artisan).
  - Étapes numérotées.
  - Bouton “Proposer une recette”.
  - Badge “Recette incomplète” + bouton “Proposer un complément / variante”.
  - Liste des variantes / compléments proposés par la communauté.
- **Formulaire** `/recette/nouvelle` :
  - Champs titre, description, image URL, temps, portions, difficulté, saison.
  - Ajout dynamique d’ingrédients (nom, quantité, unité, local, optionnel).
  - Ajout dynamique d’étapes.
  - Nom/email du contributeur.

---

## Sécurité & règles de gestion

- Toutes les entrées utilisateur sont échappées / validées côté API.
- Longueurs maximales respectées côté DB et PHP.
- Le formulaire recette est public : rate-limit strict (`public` 120 req/min).
- Signalement : un seul signalement par IP + recette dans un délai glissant.
- CORS : origines autorisées explicitement.

## Tests

- Test API manuel via `curl` :
  - `GET /cities/livry/prospects`
  - `POST /artisans/me/prospects/1/follow` avec token
  - `GET /recipes`
  - `POST /recipes`
- Vérification encodage UTF-8 sur les noms accentués (`Église`, `crème`, etc.).
- Build et déploiement frontend via `make push-livry`.

## Livrables

1. Schéma SQL mis à jour (`sites/api/migrations/026_b2b_recipes.sql`).
2. Seed prospects Livry (`data/seeds/livry_prospects.sql`) et recettes seed (`data/seeds/livry_recipes.sql`).
3. Routes API PHP (`sites/api/routes/prospects.php`, `sites/api/routes/recipes.php`).
4. Pages Vue (`sites/artisans-shared/src/views/Prospects.vue`, `ProspectDetail.vue`, `Recipes.vue`, `RecipeDetail.vue`, `RecipeForm.vue`).
5. Mise à jour du routeur et de `api.js`.
6. Déploiement en production et réinitialisation de la base si nécessaire.

## Décisions complémentaires

- Interface d’administration simple dans `/espace/admin` pour archiver les recettes signalées.
- Images recette sous forme d’URL externe uniquement pour le MVP (pas d’upload de fichiers).
