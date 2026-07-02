# Design — Déploiement multi-villes (Combs & Vert-Saint-Denis)

## Contexte

Le projet webiartisan dispose actuellement :
- d’un site frontend déployé pour **Livry** (`sites/webiartisan-livry/`) ;
- d’un code source Vue entièrement partagé dans `sites/artisans-shared/` ;
- d’une API commune (`api.prigent.tech`) servant toutes les villes via le champ `city_slug` ;
- de migrations contenant déjà des seeds pour Combs-la-Ville et Vert-Saint-Denis, mais sans frontend dédié.

L’objectif est de déployer les sites **Combs-la-Ville** et **Vert-Saint-Denis** en production sur les vhosts Gandi locaux, avec des données de test, une version semver affichée publiquement, et une factorisation maximale dans `sites/artisans-shared/`.

## Décisions clés

| Sujet | Décision |
|-------|----------|
| Base de données | **DB partagée** : les trois villes cohabitent dans la même base, filtrées par `city_slug`. |
| Architecture frontend | **Coquilles ville dédiées** : un dossier de build par ville (`webiartisan-combs`, `webiartisan-vert-saint-denis`), tout le code métier reste dans `artisans-shared/`. |
| Version semver | **Version globale du projet** stockée dans le `package.json` racine, affichée dans le footer et retournée par l’API. |
| Données de test | **Jeu complet adapté par ville** : artisans, services, recettes, prospects, offres spin. |
| Déploiement | Build + rsync `--delete` vers `~/mnt/gandi/vhosts/<domaine>/htdocs/`, exécutés en parallèle pour Combs et VSD. |

## Architecture

```
sites/
├── api/                          # API commune (PHP)
├── artisans-shared/              # Source Vue unique
│   ├── src/
│   │   ├── components/AppFooter.vue   # Affiche VITE_APP_VERSION
│   │   ├── views/...
│   │   └── api.js                # Utilise VITE_CITY_SLUG
│   └── ...
├── webiartisan-livry/            # Coquille existante
├── webiartisan-combs/            # Nouvelle coquille
│   ├── package.json
│   ├── vite.config.js
│   ├── index.html
│   ├── Makefile
│   └── dist/
└── webiartisan-vert-saint-denis/ # Nouvelle coquille
    ├── package.json
    ├── vite.config.js
    ├── index.html
    ├── Makefile
    └── dist/
```

## Frontend partagé et version

- `AppFooter.vue` affiche la version globale via `import.meta.env.VITE_APP_VERSION`.
- Le `package.json` racine est la source de vérité (ex. `1.1.0`).
- Chaque coquille expose cette valeur au build via `VITE_APP_VERSION` dans son Makefile.
- L’API retourne la même version dans `sites/api/index.php`.
- Chaque `index.html` de coquille contient les meta tags propres à la ville (titre, description, lang).

## Données de test

Deux fichiers de seed indépendants :

- `data/seeds/combs.sql`
- `data/seeds/vert-saint-denis.sql`

Contenu par seed :

1. Ville dans `local_cities`.
2. Catégories d’artisans.
3. 3 à 5 artisans liés à la ville.
4. Services associés.
5. Quelques POIs / horaires pour la carte.
6. 2 à 4 recettes publiques (certaines marquées `incomplete`).
7. 2 à 3 prospects B2B.
8. 2 à 3 offres Spin actives avec stock positif.

## Build et déploiement

### Variables de build par ville

```bash
VITE_API_URL=https://api.prigent.tech
VITE_CITY_SLUG=combs-la-ville          # ou vert-saint-denis
VITE_CITY_NAME=Combs-la-Ville          # ou Vert-Saint-Denis
VITE_CITY_LAT=...
VITE_CITY_LNG=...
VITE_CITY_CP=...
VITE_APP_VERSION=1.1.0
```

### Makefiles

- `sites/webiartisan-combs/Makefile` : `build` puis `push` vers `~/mnt/gandi/vhosts/artisans-combs.prigent.tech/htdocs/`.
- `sites/webiartisan-vert-saint-denis/Makefile` : idem vers `~/mnt/gandi/vhosts/artisans-vert-saint-denis.prigent.tech/htdocs/`.
- Root `Makefile` : ajout de `build-combs`, `build-vsd`, `push-combs`, `push-vsd`, `deploy-all`.

Le `rsync` utilise `--delete` pour écraser complètement l’ancien contenu.

## Validation

- `make test-api` après migrations + seeds.
- Smoke tests sur les domaines de prod :
  - chargement sans erreur CORS ;
  - footer affichant la version ;
  - tirage roue + création de gain ;
  - affichage recettes et artisans.

## Non-objectifs

- Pas de refonte de l’API (elle est déjà multi-ville).
- Pas de création d’un back-office par ville.
- Pas de CI/CD externe, on reste sur les Makefiles locaux.
