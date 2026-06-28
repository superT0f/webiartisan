# POC Livry — Design Specification

> **Date :** 2026-06-28  
> **Statut :** Approuvé par l'utilisateur  
> **Projet parent :** Fusion de webiartisan, Connect Club et Mealzy autour d'une plateforme d'annuaires d'artisans locaux.

---

## 1. Contexte

Trois projets existants sont disponibles dans `./extract/` :

| Projet | Rôle perçu | État technique |
|--------|-----------|----------------|
| `webiartisan` | **Socle principal** : monorepo Vue 3 + PHP API + MySQL avec sites par ville, carte OSM, annuaire artisans, services locaux. | Le plus abouti. Architecture multi-ville claire. |
| `Connect_club-app` | **Prospection commerciale** : React/TS/Vite/Leaflet/shadcn pour du démarchage d'établissements. | Composants carte/liste/fiche réutilisables, mais métier SpinLead. |
| `OKComputer_Mealzy_App_Feature_Expansion` | **Cuisine / recettes** : React/TS/Vite/shadcn, prototype statique de recettes, budget, scan. | UI riche, mais pas de carte ni d'artisans. |

Le choix retenu est de **garder webiartisan comme cœur** et de le réorganiser doucement pour en faire la nouvelle base, avant d'y intégrer les fonctionnalités des deux autres projets dans des phases ultérieures.

---

## 2. Objectif du POC

Créer un site annuaire local fonctionnel pour **Livry (Calvados, 14240)** en s'appuyant sur le monorepo webiartisan réorganisé.

### 2.1 Périmètre fonctionnel

- Page d'accueil ville avec carte OpenStreetMap centrée sur Livry.
- Annuaire des artisans locaux avec fiches détaillées.
- Recherche textuelle et filtres par catégorie de métier.
- Services locaux : mairie, météo, transports, points d'intérêt (POI) avec horaires.
- Données de démonstration réalistes (5–10 artisans et 5–8 POI).
- Espace artisan connecté via **magic link** : consultation et édition simple du profil.

### 2.2 Non-périmètre

- Modules Connect Club (prospection B2B) et Mealzy (recettes / cuisine).
- Paiements Stripe, abonnements, espace admin avancé.
- Avis clients (la table existe mais n'est pas affichée dans le POC).
- Multi-villes supplémentaires (hors Livry).

---

## 3. Architecture

### 3.1 Stack technique

| Couche | Technologie | Justification |
|--------|-------------|---------------|
| Frontend ville | Vue 3 + Vite + vue-router | Réutilisation directe du template webiartisan. |
| Template partagé | `sites/artisans-shared/` | Permet de créer d'autres villes rapidement. |
| Cartographie | Leaflet 1.9.4 + tuiles OSM | Déjà intégré dans webiartisan. |
| Styling | Bulma (hérité) + CSS custom | Conservé pour ce POC. Migration Tailwind envisagée plus tard. |
| Backend | PHP 8.4 + front-controller maison + PDO | API webiartisan existante, fonctionnelle. |
| Base de données | MySQL 8 + migrations SQL | Schéma déjà défini et cohérent. |
| Dev local | Docker Compose | Reproductible et proche de Gandi. |
| Build / déploiement | Makefile + rsync | Aligné avec l'existant. |

### 3.2 Structure du monorepo réorganisé

```
webiartisan-new/
├── docker-compose.yml
├── Makefile
├── .env.example
├── README.md
├── data/
│   └── seeds/
│       └── livry.sql              # Seed Livry (ville, catégories, artisans, POI, horaires)
└── sites/
    ├── api/                       # API PHP nettoyée
    │   ├── public/index.php
    │   ├── config/
    │   ├── middleware/
    │   ├── routes/
    │   └── migrations/
    ├── artisans-shared/           # Template partagé (Vue 3)
    │   └── src/
    │       ├── main.js
    │       ├── api.js
    │       ├── App.vue
    │       ├── components/
    │       └── views/
    │           ├── Home.vue
    │           ├── Artisan.vue
    │           ├── Register.vue
    │           ├── Dashboard.vue
    │           └── Flyer.vue
    └── webiartisan-livry/         # Instance ville Livry
        ├── .env
        ├── index.html
        ├── vite.config.js
        └── Makefile
```

### 3.3 Services Docker Compose

- `nginx` : reverse-proxy sur le port 80.
- `php` : PHP-FPM 8.4 pour l'API.
- `mysql` : MySQL 8 avec volume persistant.
- `node` : Vite dev server pour le frontend ville (port 5173).

### 3.4 Commandes Makefile principales

```bash
make up          # Lance Docker Compose
make down        # Arrête Docker Compose
make migrate     # Exécute les migrations SQL
make seed        # Insère les données de démo Livry
make build       # Build le frontend ville
make test-api    # Vérifie les endpoints API critiques
make push-livry  # Déploie sur Gandi (rsync)
```

---

## 4. Modèle de données

On reprend les migrations webiartisan existantes sans les modifier :

| Table | Rôle |
|-------|------|
| `local_cities` | Ville (slug, nom, CP, département, coords GPS, sous-domaine). |
| `local_categories` | Catégories de métiers (slug, nom, icône, couleur). |
| `local_artisans` | Profil artisan (contact, géoloc, catégorie, statut, token, vues/contacts). |
| `local_services` | Services proposés par un artisan. |
| `local_reviews` | Avis clients (conservé mais non utilisé dans le POC). |
| `local_pois` | Points d'intérêt locaux (mairie, poste, etc.). |
| `local_schedules` | Horaires d'ouverture des POI. |

### 4.1 Seed Livry

Le fichier `data/seeds/livry.sql` insère :

- 1 ville : Livry, slug `livry`, code postal `14240`, coords GPS 49.1081, -0.7658 (source Wikipédia : 49° 06′ 29″ N, 0° 45′ 57″ O).
- 5–8 catégories de métiers.
- 5–10 artisans fictifs mais réalistes avec coordonnées GPS proches du centre de Livry.
- 5–8 POI (mairie, église, poste, salle des fêtes, boulangerie de référence, etc.).
- Horaires d'ouverture pour les POI administratifs.

---

## 5. Frontend

### 5.1 Template partagé (`sites/artisans-shared/`)

Les vues suivantes sont activées / nettoyées :

- `Home.vue` : hero, recherche, carte Leaflet, filtres catégories, liste artisans, services locaux (météo, POI, transports).
- `Artisan.vue` : fiche détaillée avec services, contact, itinéraire.
- `Register.vue` : inscription artisan (statut `pending`).
- `Dashboard.vue` : **espace artisan connecté** — affichage et édition du profil.
- `Flyer.vue` : kit de com' (optionnel pour ce POC).

Composants partagés :
- `AppNav.vue` : navigation, lien vers inscription/connexion.
- `AppFooter.vue` : footer.

### 5.2 Instance Livry (`sites/webiartisan-livry/`)

Fichiers minimaux par ville :
- `.env` : configuration de la ville.
- `index.html` : point d'entrée HTML avec meta ville.
- `vite.config.js` : alias vers `../artisans-shared/src`.
- `Makefile` : build et push spécifique.

Exemple de `.env` :
```env
VITE_API_URL=http://localhost:8080/api
VITE_CITY_SLUG=livry
VITE_CITY_NAME="Livry"
VITE_CITY_LAT=49.1081
VITE_CITY_LNG=-0.7658
VITE_CITY_CP=14240
```

### 5.3 Carte

- Tuiles OpenStreetMap.
- Marqueur central de la ville.
- Marqueurs pour les artisans (groupés par catégorie, couleur par catégorie).
- Popup au clic affichant nom + catégorie + lien vers fiche.
- Affichage optionnel des POI sur la carte.

---

## 6. API

### 6.1 Endpoints publics (conservés / nettoyés)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/cities/livry` | Détail de la ville. |
| GET | `/api/cities/livry/artisans` | Liste des artisans actifs de Livry. |
| GET | `/api/cities/livry/pois` | Liste des POI de Livry. |
| GET | `/api/cities/livry/schedules` | Horaires des POI. |
| GET | `/api/artisans/:id` | Fiche détaillée d'un artisan. |
| POST | `/api/artisans/register` | Inscription artisan (statut `pending`). |

### 6.2 Endpoints authentifiés (magic link)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/artisans/magic-link` | Demande d'envoi d'un lien magique. |
| GET | `/api/artisans/me` | Profil de l'artisan connecté (JWT). |
| PUT | `/api/artisans/me` | Mise à jour du profil (description, services, contact, horaires). |

### 6.3 Gestion des emails en développement

En local, le lien magique généré est écrit dans les logs du conteneur `php` (`docker compose logs php`). Aucun service SMTP n'est requis pour le POC.

---

## 7. Authentification artisan

### 7.1 Magic link

1. L'artisan saisit son email sur `/espace`.
2. L'API génère un token JWT à durée limitée (15 min) et construit une URL `/espace?token=xxx`.
3. En dev, l'URL est loguée. En prod, elle sera envoyée par un service d'email transactionnel (Mailgun / SendGrid / etc.) dans une phase ultérieure.
4. Le frontend stocke le JWT et appelle `/api/artisans/me`.
5. L'artisan peut consulter et modifier son profil.

### 7.2 Sécurité

- CORS restreint aux domaines autorisés.
- Rate limiting sur `/api/artisans/magic-link`.
- Validation des entrées côté API (Zod-like en PHP ou filtres manuels).

---

## 8. Déploiement

### 8.1 Local

```bash
make up
make migrate
make seed
make build   # ou make dev pour le hot reload
```

Le site est accessible sur `http://localhost` (Nginx) et le dev server sur `http://localhost:5173`.

### 8.2 Production

- Sous-domaine cible : `artisans-livry.prigent.tech` (confirmé par l'utilisateur ; création en cours dans `~/mnt/gandi/vhosts/artisans-livry.prigent.tech/`).
- Commande : `make push-livry`.
- Mécanisme : rsync des fichiers buildés + API PHP vers l'hébergement Gandi Simple Hosting.

---

## 9. Tests et validation

### 9.1 Tests automatisés

- Build frontend sans erreur : `make build`.
- Vérification des endpoints API : `make test-api` (scripts curl).
- Vérification des migrations et du seed : `make migrate && make seed`.

### 9.2 Tests manuels

- La carte affiche les marqueurs artisans et le centre de Livry.
- Les filtres par catégorie fonctionnent.
- La fiche artisan s'affiche correctement.
- Le magic link permet la connexion et l'édition du profil.
- La météo et les POI s'affichent sur la home.

---

## 10. Dépendances externes

| Service | Usage |
|---------|-------|
| OpenStreetMap | Tuiles cartographiques. |
| Open-Meteo API | Météo locale gratuite. |
| Gandi Simple Hosting | Hébergement production (hérité). |

---

## 11. Risques et mitigation

| Risque | Mitigation |
|--------|------------|
| Bulma est vieillissant | Accepté pour le POC. Migration Tailwind envisagée plus tard. |
| L'API PHP a des `TODO` email | Le magic link en dev est logué, pas besoin d'SMTP pour valider le POC. |
| Les marqueurs artisans n'étaient pas affichés dans webiartisan | Corriger `Home.vue` pour afficher les artisans sur la carte. |
| Coordonnées GPS de Livry | Vérifiées sur Wikipédia : 49.1081, -0.7658. |

---

## 12. Décisions enregistrées

1. **Périmètre POC** : site ville Livry + espace artisan connecté (édition profil simple).
2. **Héritage** : réorganisation douce du monorepo webiartisan, non destructif pour les possibilités futures.
3. **Backend** : conservation de l'API PHP/MySQL existante.
4. **Auth artisan** : magic link, logs en dev, service email en prod plus tard.
5. **Données** : seed de démonstration réaliste pour Livry.
6. **Dev local** : Docker Compose + Makefile.
7. **Déploiement** : rsync vers Gandi, sous-domaine à confirmer.

---

## 13. Prochaines étapes après le POC

1. Connect Club : module de prospection B2B pour les artisans.
2. Mealzy : module recettes / ingrédients locaux liés aux artisans producteurs.
3. Activation des avis clients.
4. Ajout d'autres villes via le template partagé.
