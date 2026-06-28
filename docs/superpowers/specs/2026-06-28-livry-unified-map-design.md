# Livry — Carte unifiée en page d’accueil

## Contexte

Le site Livry dispose aujourd’hui de trois modules indépendants :
- **Annuaire artisans** : fiches avec géolocalisation.
- **Services locaux (POI)** : mairie, poste, pharmacie, église, etc.
- **Prospection B2B** : commerces et établissements à contacter.
- **Recettes** : fiches de cuisine liées aux artisans producteurs.

L’objectif est de donner une entrée unique et cohérente à ces contenus via une **carte interactive unifiée** sur la page d’accueil.

## Objectif

Transformer la page d’accueil en un hub de découverte territoriale où l’habitant peut voir, filtrer et explorer artisans, services, commerces et recettes depuis une carte unique.

## Décisions clés

- La carte est le cœur de la page d’accueil.
- Les entités conservent leur propre fiche détaillée ; la carte est un point d’entrée.
- Les calques sont togglables et persistés dans `localStorage`.
- Les recettes sont géolocalisées via les artisans qui les ont inspirées ou qui fournissent les produits.

## Architecture

### Données

Aucune nouvelle table nécessaire. On expose une route API agrégée :

- `GET /cities/:slug/map-data` retourne un objet avec quatre tableaux :
  - `artisans`
  - `pois`
  - `prospects`
  - `recipes` (avec `latitude`/`longitude` héritées du premier artisan lié, ou du contributeur s’il est artisan)

### API

Fichier : `sites/api/routes/cities.php` — ajout de `city_map_data(PDO $pdo, string $slug)`.

La requête SQL agrège les quatre sources en une seule réponse JSON.

### Frontend

Fichiers créés/modifiés :
- `sites/artisans-shared/src/views/Home.vue` : devient un hub carte + liste.
- `sites/artisans-shared/src/components/UnifiedMap.vue` : carte Leaflet avec calques et clustering.
- `sites/artisans-shared/src/components/MapSidebar.vue` : fiche contextuelle au clic sur un marqueur.
- `sites/artisans-shared/src/api.js` : ajout de `fetchMapData(citySlug)`.

### Calques

| Calque | Icône | Couleur | Source |
|--------|-------|---------|--------|
| Artisans | 🛠️ | Vert | `local_artisans` |
| Services publics | 🏛️ | Bleu | `local_pois` |
| Commerces à prospecter | 🏪 | Orange | `local_prospects` |
| Recettes locales | 🍳 | Rouge | `local_recipes` géolocalisées |

### Interactions

- Clic sur un marqueur → ouverture d’une sidebar avec la fiche résumée + lien vers la page détail.
- Barre de recherche → filtre les quatre tableaux simultanément.
- Filtres par catégorie (artisan) / type (POI, prospect) / saison (recette).
- Vue liste sous la carte pour les utilisateurs qui préfèrent le texte.

## SEO et accessibilité

- Le contenu textuel (liste filtrée) reste dans le DOM pour le référencement.
- Les marqueurs ont des labels accessibles (`aria-label`).

## Tests

- `make test-api` : vérifier `GET /cities/livry/map-data`.
- Build Vite sans erreur.
- Vérification visuelle des calques et de la sidebar.

## Ouvertures futures

- Parcours guidés : itinéraires entre plusieurs points de la carte.
- Fiches thématiques : "Manger local", "Bien se loger".
- Mode hors-ligne : cache IndexedDB des données carte.
