# Livry — Fiche artisan enrichie

## Contexte

La fiche publique d’un artisan (`/artisan/:id`) affiche aujourd’hui ses informations, services et avis. Elle ne fait pas le lien avec les autres modules : recettes qui utilisent ses produits et commerces/services à proximité.

## Objectif

Enrichir la fiche artisan pour qu’elle devienne un **hub local** autour de l’artisan : ce qu’on peut cuisiner avec ses produits et ce qui se trouve près de chez lui.

## Décisions clés

- Afficher les recettes liées à l’artisan (produits + recettes proposées par lui).
- Afficher les prospects B2B et les POI dans un rayon de 2 km.
- Utiliser une mini-carte Leaflet pour visualiser l’artisan et son voisinage.
- Ne pas alourdir le chargement initial : les données enrichies sont chargées via l’API existante `GET /artisans/:id`.

## API

Modifier `sites/api/routes/artisans.php` dans la fonction `artisan_get()`.

Après la récupération de l’artisan, ajouter :

1. **Recettes liées**
   - Jointure via `local_recipe_artisans`.
   - Recettes où `submitter_email` correspond à l’email de l’artisan.
   - Retourner `id`, `title`, `slug`, `description`, `image_url`, `servings`, `prep_time_minutes`, `cook_time_minutes`.

2. **Commerces à proximité**
   - Prospects (`local_prospects`) dans un rayon de 2 km.
   - POI (`local_pois`) dans un rayon de 2 km.
   - Retourner `id`, `name`, `type`, `address`, `distance_meters`, `latitude`, `longitude`.
   - Trier par distance croissante.
   - Limiter à 10 éléments au total (5 prospects + 5 POI par exemple).

Calcul de distance : formule Haversine en SQL :

```sql
(6371000 * acos(
    cos(radians(?)) * cos(radians(latitude)) *
    cos(radians(longitude) - radians(?)) +
    sin(radians(?)) * sin(radians(latitude))
)) AS distance_meters
```

## Frontend

Modifier `sites/artisans-shared/src/views/Artisan.vue`.

### Nouvelles sections

1. **"Recettes avec ses produits"**
   - Affichée uniquement si `recipes.length > 0`.
   - Grille de cartes cliquables vers `/recette/:slug`.
   - Badge "Sa recette" si `submitter_email === artisan.email`.

2. **"Autour de [nom artisan]"**
   - Mini-carte Leaflet centrée sur l’artisan.
   - Marqueur artisan en vert.
   - Marqueurs prospects en orange.
   - Marqueurs POI en bleu.
   - Liste des 10 lieux les plus proches sous la carte.

### Composants créés

- `sites/artisans-shared/src/components/ArtisanNearbyMap.vue` : mini-carte Leaflet.
- `sites/artisans-shared/src/components/RecipeMiniCard.vue` : carte recette compacte.

## SEO

- Les recettes et les lieux proches sont rendus côté serveur via le JSON API, donc disponibles pour les crawlers via les liens.

## Tests

- `make test-api` : vérifier `GET /artisans/:id` retourne `recipes` et `nearby`.
- Build Vite sans erreur.
- Vérification visuelle de la fiche artisan.

## Ouvertures futures

- Rayon configurable (1 km, 5 km).
- Filtrer par type de commerce.
- Bouton "Voir sur la carte unifiée" redirigeant vers `/` avec le bon calque actif.
