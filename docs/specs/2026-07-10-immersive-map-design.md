# Spec — Carte immersive WebIArtisan

## Objectif

Remplacer la carte Leaflet/OpenStreetMap actuelle par une carte plus immersive, plus fluide et plus ludique, avec un style cartoon, tout en gardant la météo et les transports accessibles en un coup d’œil.

## Contexte

L’application Android Flutter n’est qu’une WebView qui affiche la PWA web. Toute l’expérience carte est donc gérée côté web dans `sites/artisans-shared/src/components/ArtisanNearbyMap.vue`. Améliorer la PWA améliore automatiquement l’app Android.

## 1. Architecture technique

### Librairie

- **Avant** : Leaflet + tuiles raster OpenStreetMap.
- **Après** : **MapLibre GL JS** (`maplibre-gl`) + tuiles vectorielles.

### Fournisseur de tuiles

- **Principal** : MapTiler Cloud avec un style personnalisé cartoon/vivid.
  - Plan gratuit : 100k requêtes/jour, largement suffisant pour le volume actuel.
  - Clé API injectée via `VITE_MAPTILER_KEY`.
- **Fallback** : tuiles raster OpenStreetMap classiques en cas d’erreur / quota dépassé.

### Style

- Style vectoriel de base : `positron` ou `carto-voyager`.
- Surcharges custom :
  - fond de carte en teintes vives,
  - bâtiments en aplats colorés par type,
  - routes en traits épais et contrastés,
  - espaces verts en vert saturé,
  - eau en bleu turquoise.

### Intégration

- Nouveau composant `ImmersiveMap.vue` dans `sites/artisans-shared/src/components/`.
- `ArtisanNearbyMap.vue` est remplacé par ce nouveau composant.
- Import dynamique de `maplibre-gl` uniquement sur la route `/carte`.

## 2. Navigation et UI

### Page `/carte`

- Carte plein écran (100vh moins la barre de navigation).
- Accessible depuis la navigation principale.

### Contrôles flottants

- **Bas droite** : bouton géolocalisation.
- **Haut droite** : bouton filtres (catégories, offres, mini-jeux).
- **Haut gauche** : badge météo + badge transports (si dispo).

### Bottom sheet artisan

- S’ouvre au tap sur un marqueur.
- Contenu minimal :
  - nom,
  - catégorie,
  - note/témoignages,
  - bouton "Itinéraire",
  - bouton "Voir la fiche".
- Rétractable pour maximiser la carte.

### Marqueurs

- Icônes SVG par catégorie d’artisan.
- Marqueur utilisateur avec halo pulsé.
- Clustering lorsque plusieurs artisans sont proches.

## 3. Données météo et transports

### Météo

- Fournisseur : **Open-Meteo** (gratuit, sans clé API).
- Endpoint : `GET https://api.open-meteo.com/v1/forecast?latitude=...&longitude=...&current_weather=true`.
- Proxy côté WebiArtisan : `GET /api/weather?lat=&lng=`.
- Rafraîchissement toutes les 15 minutes.
- Affichage : icône + température.

### Transports

- Livry n’a pas de bus.
- L’affichage bus est conditionné à la disponibilité d’une source de données pour la ville affichée.
- Fallback : icône bus qui ouvre l’application/site du réseau local si configuré.

## 4. Performance et WebView

- **Lazy loading** : `maplibre-gl` importé dynamiquement.
- **Cache** : MapLibre gère le cache navigateur ; service worker optionnel pour les tuiles.
- **Mode immersif** : header/nav réductibles au scroll/tap ; double-tap plein écran.
- **Géolocalisation** : `navigator.geolocation.watchPosition` avec fallback ville par défaut.
- **WebView Flutter** : aucun changement côté natif, la PWA gère tout. Vérifier que la WebView autorise la géoloc.

## 5. Découpage en tâches

1. Installer `maplibre-gl` et créer `ImmersiveMap.vue`.
2. Configurer MapTiler et le style cartoon.
3. Créer la route `/carte` et l’entrée de navigation.
4. Implémenter les marqueurs custom et le clustering.
5. Ajouter le bottom sheet artisan.
6. Intégrer Open-Meteo via l’API WebiArtisan.
7. Optimiser le mode immersif et la gestion WebView.
8. Tester sur mobile et dans l’app Flutter.

## 6. Open questions

- A-t-on déjà un compte MapTiler ? Si non, il faut en créer un et récupérer la clé API.
- Quelle ville (outre Livry) pourrait bénéficier d’une source bus ? À intégrer plus tard.
