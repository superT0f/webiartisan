# Déploiement multi-villes (Combs & Vert-Saint-Denis) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Créer les coquilles de build pour Combs-la-Ville et Vert-Saint-Denis, afficher une version semver commune, insérer des données de test et déployer en production sur les vhosts Gandi.

**Architecture:** Une coquille Vite par ville importe le code source partagé depuis `sites/artisans-shared/`. Les variables d’environnement `VITE_CITY_*` et `VITE_APP_VERSION` sont injectées au build. Les seeds SQL par ville peuplent la base partagée via `city_slug`.

**Tech Stack:** PHP 8.4, MySQL 8, Vue 3, Vite, Docker Compose, Makefile, rsync.

---

### Task 1 : Version globale du projet

**Files:**
- Create: `package.json`

- [ ] **Step 1 : Créer le `package.json` racine**

```json
{
  "name": "webiartisan",
  "version": "1.1.0",
  "description": "WebiArtisan — plateforme multi-villes",
  "private": true
}
```

- [ ] **Step 2 : Commit**

```bash
git add package.json
git commit -m "chore: add root package.json with semver 1.1.0"
```

---

### Task 2 : Exposer la version dans le code partagé

**Files:**
- Modify: `sites/artisans-shared/src/api.js:1-6`

- [ ] **Step 1 : Ajouter l’export `APP_VERSION`**

Remplacer les lignes 1-6 par :

```js
const API_BASE  = import.meta.env.VITE_API_URL   || 'https://api.prigent.tech'
export const CITY_SLUG  = import.meta.env.VITE_CITY_SLUG  || 'livry'
export const CITY_NAME  = import.meta.env.VITE_CITY_NAME  || 'Livry'
export const CITY_LAT   = parseFloat(import.meta.env.VITE_CITY_LAT   || '49.1081')
export const CITY_LNG   = parseFloat(import.meta.env.VITE_CITY_LNG   || '-0.7658')
export const CITY_CP    = import.meta.env.VITE_CITY_CP    || '14240'
export const APP_VERSION = import.meta.env.VITE_APP_VERSION || '1.0.0'
```

- [ ] **Step 2 : Commit**

```bash
git add sites/artisans-shared/src/api.js
git commit -m "feat: expose APP_VERSION in shared api client"
```

---

### Task 3 : Afficher la version dans le footer

**Files:**
- Modify: `sites/artisans-shared/src/components/AppFooter.vue:52-63`

- [ ] **Step 1 : Mettre à jour l’import**

```js
import { CITY_NAME, CITY_CP, CITY_SLUG, APP_VERSION } from '../api.js'
```

- [ ] **Step 2 : Ajouter la version dans le footer-bottom**

```vue
<div class="footer-bottom">
  <span>© {{ year }} WebiArtisan · {{ CITY_NAME }} · {{ CITY_CP }} · v{{ APP_VERSION }}</span>
  <span>Fait avec ❤️ en Seine-et-Marne</span>
</div>
```

- [ ] **Step 3 : Commit**

```bash
git add sites/artisans-shared/src/components/AppFooter.vue
git commit -m "feat: display semver version in footer"
```

---

### Task 4 : Aligner la version de l’API

**Files:**
- Modify: `sites/api/index.php:183`

- [ ] **Step 1 : Mettre à jour le health check**

```php
echo json_encode([
    'success' => true,
    'service' => 'WebIArtisan API',
    'version' => '1.1.0',
    'time'    => date('c'),
]);
```

- [ ] **Step 2 : Commit**

```bash
git add sites/api/index.php
git commit -m "chore: bump API version to 1.1.0"
```

---

### Task 5 : Propager la version dans le build Livry

**Files:**
- Modify: `sites/webiartisan-livry/Makefile:6-13`

- [ ] **Step 1 : Ajouter `VITE_APP_VERSION` au build**

```makefile
build:
	VITE_API_URL=https://api.prigent.tech \
	VITE_CITY_SLUG=livry \
	VITE_CITY_NAME=Livry \
	VITE_CITY_LAT=49.1081 \
	VITE_CITY_LNG=-0.7658 \
	VITE_CITY_CP=14240 \
	VITE_APP_VERSION=1.1.0 \
		npm run build
```

- [ ] **Step 2 : Commit**

```bash
git add sites/webiartisan-livry/Makefile
git commit -m "chore: pass VITE_APP_VERSION in livry build"
```

---

### Task 6 : Créer la coquille Combs-la-Ville

**Files:**
- Create: `sites/webiartisan-combs/package.json`
- Create: `sites/webiartisan-combs/vite.config.js`
- Create: `sites/webiartisan-combs/index.html`
- Create: `sites/webiartisan-combs/Makefile`

- [ ] **Step 1 : Créer `package.json`**

```json
{
  "name": "webiartisan-combs",
  "version": "1.1.0",
  "description": "Annuaire artisans locaux — Combs-la-Ville",
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview"
  },
  "dependencies": {
    "vue": "^3.5.38",
    "vue-router": "^4.6.4"
  },
  "devDependencies": {
    "@vitejs/plugin-vue": "^5.0.0",
    "vite": "^5.0.0"
  },
  "type": "module",
  "keywords": [],
  "author": "",
  "license": "ISC"
}
```

- [ ] **Step 2 : Créer `vite.config.js`**

```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, '../artisans-shared/src'),
      '@shared': resolve(__dirname, '../artisans-shared/src')
    }
  },
  server: {
    port: 5174,
    fs: {
      allow: ['..']
    },
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, '/api')
      }
    }
  },
  build: {
    outDir: 'dist',
    assetsDir: 'assets',
    emptyOutDir: true
  }
})
```

- [ ] **Step 3 : Créer `index.html`**

```html
<!doctype html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Annuaire des artisans et commerçants locaux de Combs-la-Ville et ses environs. Découvrez les services près de chez vous." />
    <meta name="theme-color" content="#1f2937" />
    <title>Artisans de Combs-la-Ville — 77380</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="../artisans-shared/src/main.js"></script>
  </body>
</html>
```

- [ ] **Step 4 : Créer `Makefile`**

```makefile
.PHONY: dev build push clean install

dev:
	npm run dev

build:
	VITE_API_URL=https://api.prigent.tech \
	VITE_CITY_SLUG=combs-la-ville \
	VITE_CITY_NAME=Combs-la-Ville \
	VITE_CITY_LAT=48.6614 \
	VITE_CITY_LNG=2.5628 \
	VITE_CITY_CP=77380 \
	VITE_APP_VERSION=1.1.0 \
		npm run build

push: build
	rsync -avz --no-owner --no-group --delete dist/ $(HOME)/mnt/gandi/vhosts/artisans-combs.prigent.tech/htdocs/

clean:
	rm -rf dist node_modules

install:
	npm install
```

- [ ] **Step 5 : Commit**

```bash
git add sites/webiartisan-combs/
git commit -m "feat: add Combs-la-Ville build shell"
```

---

### Task 7 : Créer la coquille Vert-Saint-Denis

**Files:**
- Create: `sites/webiartisan-vert-saint-denis/package.json`
- Create: `sites/webiartisan-vert-saint-denis/vite.config.js`
- Create: `sites/webiartisan-vert-saint-denis/index.html`
- Create: `sites/webiartisan-vert-saint-denis/Makefile`

- [ ] **Step 1 : Créer `package.json`**

```json
{
  "name": "webiartisan-vert-saint-denis",
  "version": "1.1.0",
  "description": "Annuaire artisans locaux — Vert-Saint-Denis",
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview"
  },
  "dependencies": {
    "vue": "^3.5.38",
    "vue-router": "^4.6.4"
  },
  "devDependencies": {
    "@vitejs/plugin-vue": "^5.0.0",
    "vite": "^5.0.0"
  },
  "type": "module",
  "keywords": [],
  "author": "",
  "license": "ISC"
}
```

- [ ] **Step 2 : Créer `vite.config.js`**

```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, '../artisans-shared/src'),
      '@shared': resolve(__dirname, '../artisans-shared/src')
    }
  },
  server: {
    port: 5175,
    fs: {
      allow: ['..']
    },
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, '/api')
      }
    }
  },
  build: {
    outDir: 'dist',
    assetsDir: 'assets',
    emptyOutDir: true
  }
})
```

- [ ] **Step 3 : Créer `index.html`**

```html
<!doctype html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Annuaire des artisans et commerçants locaux de Vert-Saint-Denis et ses environs. Découvrez les services près de chez vous." />
    <meta name="theme-color" content="#1f2937" />
    <title>Artisans de Vert-Saint-Denis — 77240</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="../artisans-shared/src/main.js"></script>
  </body>
</html>
```

- [ ] **Step 4 : Créer `Makefile`**

```makefile
.PHONY: dev build push clean install

dev:
	npm run dev

build:
	VITE_API_URL=https://api.prigent.tech \
	VITE_CITY_SLUG=vert-saint-denis \
	VITE_CITY_NAME=Vert-Saint-Denis \
	VITE_CITY_LAT=48.5644 \
	VITE_CITY_LNG=2.6186 \
	VITE_CITY_CP=77240 \
	VITE_APP_VERSION=1.1.0 \
		npm run build

push: build
	rsync -avz --no-owner --no-group --delete dist/ $(HOME)/mnt/gandi/vhosts/artisans-vert-saint-denis.prigent.tech/htdocs/

clean:
	rm -rf dist node_modules

install:
	npm install
```

- [ ] **Step 5 : Commit**

```bash
git add sites/webiartisan-vert-saint-denis/
git commit -m "feat: add Vert-Saint-Denis build shell"
```

---

### Task 8 : Ajouter les targets racine

**Files:**
- Modify: `Makefile:1-41`

- [ ] **Step 1 : Mettre à jour le header et ajouter les targets**

```makefile
.PHONY: help up down migrate seed build dev test-api push-livry build-combs push-combs build-vsd push-vsd deploy-all

APP_VERSION := $(shell node -p "require('./package.json').version" 2>/dev/null || echo 1.1.0)

help:
	@echo "WebiArtisan — Multi-villes"
	@echo "  make up              Start Docker Compose"
	@echo "  make down            Stop Docker Compose"
	@echo "  make migrate         Run SQL migrations"
	@echo "  make seed            Insert Livry demo data"
	@echo "  make dev             Start Vite dev server"
	@echo "  make build           Build frontend for Livry"
	@echo "  make test-api        Run API smoke tests"
	@echo "  make push-livry      Deploy Livry to Gandi"
	@echo "  make build-combs     Build Combs-la-Ville frontend"
	@echo "  make push-combs      Deploy Combs-la-Ville to Gandi"
	@echo "  make build-vsd       Build Vert-Saint-Denis frontend"
	@echo "  make push-vsd        Deploy Vert-Saint-Denis to Gandi"
	@echo "  make deploy-all      Build & push Livry, Combs and VSD"

up:
	@docker compose up -d --build
	@echo "✅ Stack running on http://localhost"

down:
	@docker compose down

migrate:
	@docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan < sites/api/migrations/025_artisans_local.sql
	@docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan < sites/api/migrations/026_b2b_recipes.sql
	@docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan < sites/api/migrations/027_spin_wheel.sql
	@echo "✅ Migrations applied"

seed:
	@docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan < data/seeds/livry.sql
	@echo "✅ Livry seed applied"

dev:
	@docker compose logs -f node

build:
	@docker compose run --rm node sh -c "cd /app/sites/webiartisan-livry && VITE_API_URL=https://api.prigent.tech VITE_CITY_SLUG=livry VITE_CITY_NAME=Livry VITE_CITY_LAT=49.1081 VITE_CITY_LNG=-0.7658 VITE_CITY_CP=14240 VITE_APP_VERSION=$(APP_VERSION) npm run build"

test-api:
	@bash scripts/test-api.sh

push-livry:
	@$(MAKE) -C sites/webiartisan-livry push

build-combs:
	@docker compose run --rm node sh -c "cd /app/sites/webiartisan-combs && VITE_API_URL=https://api.prigent.tech VITE_CITY_SLUG=combs-la-ville VITE_CITY_NAME=Combs-la-Ville VITE_CITY_LAT=48.6614 VITE_CITY_LNG=2.5628 VITE_CITY_CP=77380 VITE_APP_VERSION=$(APP_VERSION) npm run build"

push-combs:
	@$(MAKE) -C sites/webiartisan-combs push

build-vsd:
	@docker compose run --rm node sh -c "cd /app/sites/webiartisan-vert-saint-denis && VITE_API_URL=https://api.prigent.tech VITE_CITY_SLUG=vert-saint-denis VITE_CITY_NAME=Vert-Saint-Denis VITE_CITY_LAT=48.5644 VITE_CITY_LNG=2.6186 VITE_CITY_CP=77240 VITE_APP_VERSION=$(APP_VERSION) npm run build"

push-vsd:
	@$(MAKE) -C sites/webiartisan-vert-saint-denis push

deploy-all: build push-livry build-combs push-combs build-vsd push-vsd
```

- [ ] **Step 2 : Commit**

```bash
git add Makefile
git commit -m "chore: add root Makefile targets for Combs and VSD"
```

---

### Task 9 : Seed Combs-la-Ville

**Files:**
- Create: `data/seeds/combs.sql`

- [ ] **Step 1 : Créer le fichier de seed**

```sql
-- ============================================================
-- WebiArtisan — Seed data : Combs-la-Ville (Seine-et-Marne, 77380)
-- ============================================================

SET NAMES utf8mb4;

SET @combs_id = (SELECT id FROM local_cities WHERE slug = 'combs-la-ville' LIMIT 1);

-- Catégories déjà créées par migration 025 ; on récupère les IDs utiles
SET @cat_boulanger = (SELECT id FROM local_categories WHERE slug = 'boulanger' LIMIT 1);
SET @cat_coiffeur  = (SELECT id FROM local_categories WHERE slug = 'coiffeur' LIMIT 1);
SET @cat_peintre   = (SELECT id FROM local_categories WHERE slug = 'peintre' LIMIT 1);
SET @cat_menuisier = (SELECT id FROM local_categories WHERE slug = 'menuisier' LIMIT 1);
SET @cat_jardinage = (SELECT id FROM local_categories WHERE slug = 'jardinage' LIMIT 1);
SET @cat_informatique = (SELECT id FROM local_categories WHERE slug = 'informatique' LIMIT 1);

-- Artisans de démonstration
INSERT IGNORE INTO local_artisans
    (city_id, category_id, company_name, description, phone, email, website, address,
     latitude, longitude, status, is_verified, is_featured, email_verified)
VALUES
(@combs_id, @cat_boulanger, 'Boulangerie Combs Centre',
 'Pain artisanal, viennoiseries et pâtisseries maison au centre de Combs-la-Ville.',
 '01 60 60 11 22', 'contact@boulangerie-combs.fr', NULL,
 '10 place Charles de Gaulle, 77380 Combs-la-Ville',
 48.6614, 2.5628, 'active', TRUE, TRUE, TRUE),

(@combs_id, @cat_coiffeur, 'Salon L\'Hair Combs',
 'Coiffure mixte, coupes, couleurs et soins capillaires sans rendez-vous.',
 '01 60 60 33 44', 'salon.lhair@orange.fr', NULL,
 '22 avenue du Général de Gaulle, 77380 Combs-la-Ville',
 48.6602, 2.5641, 'active', TRUE, FALSE, TRUE),

(@combs_id, @cat_peintre, 'Peintures & Décors 77',
 'Peinture intérieure, extérieure, papier peint et conseil déco pour Combs et environs.',
 '01 60 60 55 66', 'contact@peintures77.fr', 'https://peintures77.fr',
 '5 rue du Bois de la Grange, 77380 Combs-la-Ville',
 48.6621, 2.5610, 'active', TRUE, FALSE, TRUE),

(@combs_id, @cat_menuisier, 'Menuiserie Combs Bois',
 'Fabrication et pose de menuiseries, portes, fenêtres et meubles sur mesure.',
 '01 60 60 77 88', 'menuiserie@combsbois.fr', NULL,
 '8 rue de la Garenne, 77380 Combs-la-Ville',
 48.6595, 2.5655, 'active', TRUE, TRUE, TRUE),

(@combs_id, @cat_jardinage, 'Verts & Jardins Combs',
 'Entretien de jardins, taille de haies, débroussaillage et aménagement paysager.',
 '01 60 60 99 00', 'contact@verts-jardins-combs.fr', NULL,
 '15 route de Lieusaint, 77380 Combs-la-Ville',
 48.6580, 2.5680, 'active', TRUE, FALSE, TRUE);

-- Services
SET @boulangerie_combs_id = (SELECT id FROM local_artisans WHERE company_name = 'Boulangerie Combs Centre' AND city_id = @combs_id LIMIT 1);
SET @coiffeur_combs_id    = (SELECT id FROM local_artisans WHERE company_name = 'Salon L\'Hair Combs' AND city_id = @combs_id LIMIT 1);
SET @peintre_combs_id     = (SELECT id FROM local_artisans WHERE company_name = 'Peintures & Décors 77' AND city_id = @combs_id LIMIT 1);
SET @menuisier_combs_id   = (SELECT id FROM local_artisans WHERE company_name = 'Menuiserie Combs Bois' AND city_id = @combs_id LIMIT 1);

INSERT IGNORE INTO local_services (artisan_id, name, description, price_range, duration, sort_order) VALUES
(@boulangerie_combs_id, 'Pain traditionnel', 'Pain au levain et baguettes tradition', '1€-5€', 'Tous les jours', 1),
(@boulangerie_combs_id, 'Pâtisseries maison', 'Tartes, éclairs et gâteaux sur commande', '3€-25€', 'Sur commande', 2),
(@coiffeur_combs_id, 'Coupe homme', 'Coupe et shampoing', '15€', '30 min', 1),
(@coiffeur_combs_id, 'Coupe femme', 'Coupe, brushing et conseil', '35€', '1h', 2),
(@peintre_combs_id, 'Peinture intérieure', 'Murs et plafonds', 'Sur devis', '1j-3j', 1),
(@menuisier_combs_id, 'Pose de fenêtres', 'Fenêtres PVC, bois ou alu', 'Sur devis', '1j-2j', 1);

-- Recettes
INSERT IGNORE INTO local_recipes
    (city_id, title, slug, description, image_url, prep_time_minutes, cook_time_minutes, servings, difficulty, season, is_premium, is_incomplete, status, submitted_by)
VALUES
(@combs_id, 'Croissants maison', 'croissants-maison-combs', 'Recette de croissants feuilletés à faire avec le beurre et la farine du boulanger local.', 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=800', 30, 20, 8, 'hard', 'winter', FALSE, TRUE, 'published', 'Boulangerie Combs Centre'),
(@combs_id, 'Tarte aux mirabelles', 'tarte-mirabelles-combs', 'Tarte aux mirabelles de Seine-et-Marne, simple et fruitée.', 'https://images.unsplash.com/photo-1519915028121-7d3463d20b13?w=800', 20, 35, 6, 'easy', 'summer', FALSE, FALSE, 'published', 'Boulangerie Combs Centre');

SET @croissants_id = (SELECT id FROM local_recipes WHERE slug = 'croissants-maison-combs' LIMIT 1);
SET @mirabelles_id = (SELECT id FROM local_recipes WHERE slug = 'tarte-mirabelles-combs' LIMIT 1);

INSERT IGNORE INTO local_recipe_ingredients (recipe_id, name, quantity, unit, is_local, is_optional, sort_order) VALUES
(@croissants_id, 'Farine T55', 500, 'g', TRUE, FALSE, 1),
(@croissants_id, 'Beurre', 250, 'g', TRUE, FALSE, 2),
(@croissants_id, 'Levure fraîche', 20, 'g', FALSE, FALSE, 3),
(@mirabelles_id, 'Mirabelles', 500, 'g', TRUE, FALSE, 1),
(@mirabelles_id, 'Pâte brisée', 1, 'pièce', FALSE, FALSE, 2),
(@mirabelles_id, 'Sucre', 80, 'g', FALSE, FALSE, 3);

INSERT IGNORE INTO local_recipe_steps (recipe_id, step_number, instruction) VALUES
(@croissants_id, 1, 'Préparer la détrempe avec farine, eau, sucre, sel et levure.'),
(@croissants_id, 2, 'Incorporer le beurre de tourage en plusieurs étapes.'),
(@croissants_id, 3, 'Façonner les croissants et laisser pousser.'),
(@croissants_id, 4, 'Cuire 20 minutes à 180°C.'),
(@mirabelles_id, 1, 'Dénoyauter les mirabelles.'),
(@mirabelles_id, 2, 'Étaler la pâte dans un moule.'),
(@mirabelles_id, 3, 'Disposer les mirabelles et saupoudrer de sucre.'),
(@mirabelles_id, 4, 'Cuire 35 minutes à 180°C.');

INSERT IGNORE INTO local_recipe_artisans (recipe_id, artisan_id) VALUES
(@croissants_id, @boulangerie_combs_id),
(@mirabelles_id, @boulangerie_combs_id);

-- Prospects (à partir des POI existants + compléments)
INSERT IGNORE INTO local_prospects
    (city_id, source_poi_id, name, type, zone, address, phone, email, website, latitude, longitude, pitch, weakness, is_active)
SELECT
    @combs_id,
    p.id,
    p.name,
    p.type,
    'Centre-ville',
    p.address,
    p.phone,
    p.email,
    p.website,
    p.latitude,
    p.longitude,
    NULL,
    NULL,
    TRUE
FROM local_pois p
WHERE p.city_id = @combs_id;

INSERT IGNORE INTO local_prospects
    (city_id, name, type, zone, address, phone, email, latitude, longitude, pitch, weakness, is_active)
VALUES
(@combs_id, 'Brasserie Le Combsien', 'restaurant', 'Centre-ville', '3 place Charles de Gaulle, 77380 Combs-la-Ville', '01 60 60 12 34', 'contact@brasserie-combsien.fr', 48.6615, 2.5630, 'Valoriser les produits locaux dans la carte.', 'Peu de visibilité en ligne.', TRUE),
(@combs_id, 'Fleuriste Combs Fleurs', 'fleuriste', 'Centre-ville', '7 avenue de la Gare, 77380 Combs-la-Ville', '01 60 60 56 78', NULL, 48.6608, 2.5645, 'Fournir des compositions florales aux artisans et restaurants.', 'Pas de présence digitale.', TRUE);

-- Offres Spin
INSERT IGNORE INTO local_spin_offers (artisan_id, label, description, stock_total, stock_remaining, is_active) VALUES
(@boulangerie_combs_id, '1 croissant offert', 'Un croissant offert avec un café acheté.', 100, 100, TRUE),
(@coiffeur_combs_id, '-20% sur la coupe', 'Réduction de 20% sur toute coupe.', 50, 50, TRUE),
(@menuisier_combs_id, 'Devis gratuit', 'Devis gratuit pour tout projet menuiserie.', 200, 200, TRUE);
```

- [ ] **Step 2 : Commit**

```bash
git add data/seeds/combs.sql
git commit -m "feat: add Combs-la-Ville seed data"
```

---

### Task 10 : Seed Vert-Saint-Denis

**Files:**
- Create: `data/seeds/vert-saint-denis.sql`

- [ ] **Step 1 : Créer le fichier de seed**

```sql
-- ============================================================
-- WebiArtisan — Seed data : Vert-Saint-Denis (Seine-et-Marne, 77240)
-- ============================================================

SET NAMES utf8mb4;

SET @vsd_id = (SELECT id FROM local_cities WHERE slug = 'vert-saint-denis' LIMIT 1);

-- Catégories déjà créées par migration 025
SET @cat_boulanger = (SELECT id FROM local_categories WHERE slug = 'boulanger' LIMIT 1);
SET @cat_coiffeur  = (SELECT id FROM local_categories WHERE slug = 'coiffeur' LIMIT 1);
SET @cat_electricien = (SELECT id FROM local_categories WHERE slug = 'electricien' LIMIT 1);
SET @cat_plombier  = (SELECT id FROM local_categories WHERE slug = 'plombier' LIMIT 1);
SET @cat_jardinage = (SELECT id FROM local_categories WHERE slug = 'jardinage' LIMIT 1);

-- Artisans de démonstration
INSERT IGNORE INTO local_artisans
    (city_id, category_id, company_name, description, phone, email, website, address,
     latitude, longitude, status, is_verified, is_featured, email_verified)
VALUES
(@vsd_id, @cat_boulanger, 'Boulangerie Vert-Saint-Denis',
 'Boulangerie familiale : pain, viennoiseries et sandwiches préparés sur place.',
 '01 64 10 10 10', 'boulangerie@vsd-local.fr', NULL,
 '12 rue Pasteur, 77240 Vert-Saint-Denis',
 48.5644, 2.6186, 'active', TRUE, TRUE, TRUE),

(@vsd_id, @cat_coiffeur, 'Coiffure VSD',
 'Salon de coiffure mixte, coupes modernes et soins bio.',
 '01 64 10 20 20', 'coiffure.vsd@free.fr', NULL,
 '5 rue de la République, 77240 Vert-Saint-Denis',
 48.5650, 2.6175, 'active', TRUE, FALSE, TRUE),

(@vsd_id, @cat_electricien, 'Élec Services VSD',
 'Installation, rénovation et dépannage électrique pour particuliers et entreprises.',
 '01 64 10 30 30', 'contact@elec-services-vsd.fr', 'https://elec-services-vsd.fr',
 '18 rue Pasteur, 77240 Vert-Saint-Denis',
 48.5635, 2.6195, 'active', TRUE, FALSE, TRUE),

(@vsd_id, @cat_plombier, 'Plomberie Vert-Saint-Denis',
 'Dépannage plomberie, chauffage et sanitaires. Intervention rapide.',
 '01 64 10 40 40', 'plomberie@vsd-local.fr', NULL,
 '7 rue des Écoles, 77240 Vert-Saint-Denis',
 48.5655, 2.6165, 'active', TRUE, TRUE, TRUE),

(@vsd_id, @cat_jardinage, 'Jardins de Sénart',
 'Entretien de jardins, élagage, création de massifs et tonte.',
 '01 64 10 50 50', 'contact@jardins-senart.fr', NULL,
 '25 rue de la Garenne, 77240 Vert-Saint-Denis',
 48.5625, 2.6205, 'active', TRUE, FALSE, TRUE);

-- Services
SET @boulangerie_vsd_id = (SELECT id FROM local_artisans WHERE company_name = 'Boulangerie Vert-Saint-Denis' AND city_id = @vsd_id LIMIT 1);
SET @coiffeur_vsd_id    = (SELECT id FROM local_artisans WHERE company_name = 'Coiffure VSD' AND city_id = @vsd_id LIMIT 1);
SET @elec_vsd_id        = (SELECT id FROM local_artisans WHERE company_name = 'Élec Services VSD' AND city_id = @vsd_id LIMIT 1);
SET @plombier_vsd_id    = (SELECT id FROM local_artisans WHERE company_name = 'Plomberie Vert-Saint-Denis' AND city_id = @vsd_id LIMIT 1);

INSERT IGNORE INTO local_services (artisan_id, name, description, price_range, duration, sort_order) VALUES
(@boulangerie_vsd_id, 'Pain traditionnel', 'Baguettes et pains spéciaux', '1€-5€', 'Tous les jours', 1),
(@boulangerie_vsd_id, 'Plateau sandwich', 'Plateaux pour réceptions et entreprises', 'Sur devis', 'Sur commande', 2),
(@coiffeur_vsd_id, 'Coupe femme', 'Coupe, brushing et soin', '35€', '1h', 1),
(@elec_vsd_id, 'Mise aux normes', 'Diagnostic et mise aux normes électrique', 'Sur devis', '1j-2j', 1),
(@plombier_vsd_id, 'Dépannage urgence', 'Intervention rapide 7j/7', '80€', '1h', 1);

-- Recettes
INSERT IGNORE INTO local_recipes
    (city_id, title, slug, description, image_url, prep_time_minutes, cook_time_minutes, servings, difficulty, season, is_premium, is_incomplete, status, submitted_by)
VALUES
(@vsd_id, 'Pain perdu à la brioche', 'pain-perdu-brioche-vsd', 'Recette familiale pour utiliser la brioche de la boulangerie locale.', 'https://images.unsplash.com/photo-1484723091739-30a097e8f929?w=800', 10, 10, 4, 'very_easy', 'winter', FALSE, FALSE, 'published', 'Boulangerie Vert-Saint-Denis'),
(@vsd_id, 'Galette des rois maison', 'galette-rois-vsd', 'Galette frangipane à partager en famille.', 'https://images.unsplash.com/photo-1517433670267-08bbd4be890f?w=800', 40, 30, 8, 'medium', 'winter', FALSE, TRUE, 'published', 'Boulangerie Vert-Saint-Denis');

SET @pain_vsd_id = (SELECT id FROM local_recipes WHERE slug = 'pain-perdu-brioche-vsd' LIMIT 1);
SET @galette_vsd_id = (SELECT id FROM local_recipes WHERE slug = 'galette-rois-vsd' LIMIT 1);

INSERT IGNORE INTO local_recipe_ingredients (recipe_id, name, quantity, unit, is_local, is_optional, sort_order) VALUES
(@pain_vsd_id, 'Brioche', 6, 'tranche', TRUE, FALSE, 1),
(@pain_vsd_id, 'Œufs', 2, 'pièce', TRUE, FALSE, 2),
(@pain_vsd_id, 'Lait', 250, 'ml', TRUE, FALSE, 3),
(@galette_vsd_id, 'Pâte feuilletée', 2, 'pièce', FALSE, FALSE, 1),
(@galette_vsd_id, 'Poudre d\'amandes', 100, 'g', FALSE, FALSE, 2),
(@galette_vsd_id, 'Sucre', 80, 'g', FALSE, FALSE, 3);

INSERT IGNORE INTO local_recipe_steps (recipe_id, step_number, instruction) VALUES
(@pain_vsd_id, 1, 'Battre les œufs avec le lait et le sucre.'),
(@pain_vsd_id, 2, 'Tremper les tranches de brioche dans le mélange.'),
(@pain_vsd_id, 3, 'Faire dorer 2-3 minutes de chaque côté dans une poêle beurrée.'),
(@galette_vsd_id, 1, 'Préparer la crème frangipane avec beurre, sucre, œufs et amandes.'),
(@galette_vsd_id, 2, 'Étaler la crème sur un disque de pâte feuilletée.'),
(@galette_vsd_id, 3, 'Recouvrir du second disque et souder les bords.'),
(@galette_vsd_id, 4, 'Dorer au jaune d\'œuf et cuire 30 minutes à 180°C.');

INSERT IGNORE INTO local_recipe_artisans (recipe_id, artisan_id) VALUES
(@pain_vsd_id, @boulangerie_vsd_id),
(@galette_vsd_id, @boulangerie_vsd_id);

-- Prospects
INSERT IGNORE INTO local_prospects
    (city_id, source_poi_id, name, type, zone, address, phone, email, website, latitude, longitude, pitch, weakness, is_active)
SELECT
    @vsd_id,
    p.id,
    p.name,
    p.type,
    'Centre-bourg',
    p.address,
    p.phone,
    p.email,
    p.website,
    p.latitude,
    p.longitude,
    NULL,
    NULL,
    TRUE
FROM local_pois p
WHERE p.city_id = @vsd_id;

INSERT IGNORE INTO local_prospects
    (city_id, name, type, zone, address, phone, email, latitude, longitude, pitch, weakness, is_active)
VALUES
(@vsd_id, 'Restaurant Le Sénart', 'restaurant', 'Centre-bourg', '9 rue Pasteur, 77240 Vert-Saint-Denis', '01 64 10 60 60', 'contact@restaurant-senart.fr', 48.5640, 2.6190, 'Mettre en avant les produits locaux sur la carte.', 'Faible présence en ligne.', TRUE),
(@vsd_id, 'Boucherie de la Garenne', 'boucherie', 'Nord', '14 rue de la Garenne, 77240 Vert-Saint-Denis', '01 64 10 70 70', NULL, 48.5660, 2.6150, 'Proposer viandes locales aux restaurants et traiteurs.', 'Pas de site internet.', TRUE);

-- Offres Spin
INSERT IGNORE INTO local_spin_offers (artisan_id, label, description, stock_total, stock_remaining, is_active) VALUES
(@boulangerie_vsd_id, '1 pain offert', 'Un pain offert pour tout achat supérieur à 5€.', 100, 100, TRUE),
(@coiffeur_vsd_id, '-15% sur la coupe', 'Réduction de 15% sur la première coupe.', 50, 50, TRUE),
(@plombier_vsd_id, 'Diagnostic gratuit', 'Diagnostic gratuit pour tout dépannage.', 200, 200, TRUE);
```

- [ ] **Step 2 : Commit**

```bash
git add data/seeds/vert-saint-denis.sql
git commit -m "feat: add Vert-Saint-Denis seed data"
```

---

### Task 11 : Valider en local

- [ ] **Step 1 : Lancer la stack et appliquer migrations + seeds**

```bash
make up
make migrate
make seed
docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan < data/seeds/combs.sql
docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan < data/seeds/vert-saint-denis.sql
```

- [ ] **Step 2 : Lancer les tests API**

```bash
make test-api
```

Expected: all tests pass.

- [ ] **Step 3 : Vérifier le footer en dev (Livry)**

```bash
cd sites/webiartisan-livry && npm install && npm run dev
```

Ouvrir `http://localhost:5173`, vérifier que le footer affiche `v1.1.0`.

---

### Task 12 : Déployer Combs-la-Ville

- [ ] **Step 1 : Installer et build**

```bash
cd sites/webiartisan-combs && make install && make build
```

- [ ] **Step 2 : Pousser vers le vhost local**

```bash
cd sites/webiartisan-combs && make push
```

Expected: rsync termine sans erreur et écrase le contenu existant.

- [ ] **Step 3 : Commit du dist (optionnel selon convention git)**

Si `dist/` est versionné pour Livry, faire de même :

```bash
git add sites/webiartisan-combs/dist/
git commit -m "deploy: build Combs-la-Ville v1.1.0" || true
```

---

### Task 13 : Déployer Vert-Saint-Denis

- [ ] **Step 1 : Installer et build**

```bash
cd sites/webiartisan-vert-saint-denis && make install && make build
```

- [ ] **Step 2 : Pousser vers le vhost local**

```bash
cd sites/webiartisan-vert-saint-denis && make push
```

Expected: rsync termine sans erreur et écrase le contenu existant.

- [ ] **Step 3 : Commit du dist (optionnel)**

```bash
git add sites/webiartisan-vert-saint-denis/dist/
git commit -m "deploy: build Vert-Saint-Denis v1.1.0" || true
```

---

### Task 14 : Mettre à jour la base de production

- [ ] **Step 1 : Appliquer les migrations manquantes en prod**

Se connecter à phpMyAdmin de production, sélectionner la base `webiartisan`, puis exécuter dans l’ordre :

1. `sites/api/migrations/025_artisans_local.sql`
2. `sites/api/migrations/026_b2b_recipes.sql`
3. `sites/api/migrations/027_spin_wheel.sql`

- [ ] **Step 2 : Insérer les seeds en prod**

Toujours dans phpMyAdmin, exécuter :

1. `data/seeds/combs.sql`
2. `data/seeds/vert-saint-denis.sql`

- [ ] **Step 3 : Pousser l’API en prod**

```bash
cd sites/api && make push
```

---

### Task 15 : Smoke tests en production

- [ ] **Step 1 : Vérifier les pages d’accueil**

```bash
curl -s https://artisans-combs.prigent.tech/ | head -n 20
curl -s https://artisans-vert-saint-denis.prigent.tech/ | head -n 20
```

Expected: HTML contient `Artisans de Combs-la-Ville` / `Artisans de Vert-Saint-Denis` et `v1.1.0`.

- [ ] **Step 2 : Vérifier les endpoints API**

```bash
curl -s "https://api.prigent.tech/api/artisans?city=combs-la-ville" | jq '.data | length'
curl -s "https://api.prigent.tech/api/recipes?city=vert-saint-denis" | jq '.data | length'
curl -s "https://api.prigent.tech/api/spin/offers?city=combs-la-ville" | jq '.data | length'
```

Expected: chaque endpoint retourne au moins 2 éléments.

- [ ] **Step 3 : Tester la roue**

Sur `https://artisans-combs.prigent.tech/roue`, saisir un email et jouer. Vérifier qu’un gain est créé avec un QR code.

---

### Task 16 : Commit final

- [ ] **Step 1 : Committer tout le reste**

```bash
git status
git add -A
git commit -m "feat: deploy Combs-la-Ville and Vert-Saint-Denis v1.1.0"
```

---

## Self-Review Checklist

- [ ] Spec coverage : chaque section du design a au moins une tâche.
- [ ] Placeholder scan : aucun `TBD`, `TODO` ou code non fourni.
- [ ] Type consistency : `VITE_APP_VERSION` est passé partout, `APP_VERSION` exporté dans `api.js`.
- [ ] Pas de duplication de logique métier : les coquilles ne contiennent que config/build.
- [ ] Les seeds respectent les clés étrangères et utilisent `INSERT IGNORE`.
