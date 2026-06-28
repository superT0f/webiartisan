# POC Livry — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a working local directory website for Livry (Calvados, 14240) by reorganizing the webiartisan monorepo, adding Docker/local dev, seed data, and a connected artisan dashboard via magic link.

**Architecture:** Keep the existing Vue 3 + Vite + PHP API + MySQL stack from webiartisan. Create a new city instance `sites/webiartisan-livry` that reuses the shared template `sites/artisans-shared`. Run everything locally with Docker Compose orchestrated by a root Makefile.

**Tech Stack:** Vue 3, Vite, Leaflet, PHP 8.4, MySQL 8, Docker Compose, Makefile, Nginx.

---

## File Structure

```
webiartisan-new/
├── docker-compose.yml
├── Makefile
├── .env.example
├── data/
│   └── seeds/
│       └── livry.sql
├── scripts/
│   └── test-api.sh
└── sites/
    ├── api/
    │   ├── index.php
    │   ├── config/
    │   │   ├── database.php
    │   │   └── app.php
    │   ├── middleware/
    │   │   ├── Cors.php
    │   │   ├── Auth.php
    │   │   ├── Tenant.php
    │   │   ├── RateLimit.php
    │   │   └── PlanQuota.php
    │   └── routes/
    │       ├── artisans.php
    │       └── cities.php
    ├── artisans-shared/
    │   ├── package.json
    │   ├── vite.config.js
    │   ├── index.html
    │   ├── src/
    │   │   ├── main.js
    │   │   ├── api.js
    │   │   ├── App.vue
    │   │   ├── style.css
    │   │   ├── components/
    │   │   │   ├── AppNav.vue
    │   │   │   └── AppFooter.vue
    │   │   └── views/
    │   │       ├── Home.vue
    │   │       ├── Artisan.vue
    │   │       ├── Register.vue
    │   │       ├── Dashboard.vue
    │   │       └── Flyer.vue
    └── webiartisan-livry/
        ├── package.json
        ├── vite.config.js
        ├── index.html
        ├── .env
        └── Makefile
```

---

### Task 1: Bootstrap the reorganized monorepo skeleton

**Files:**
- Create: `webiartisan-new/docker-compose.yml`
- Create: `webiartisan-new/Makefile`
- Create: `webiartisan-new/.env.example`
- Create: `webiartisan-new/data/seeds/livry.sql`
- Create: `webiartisan-new/scripts/test-api.sh`

- [ ] **Step 1: Create the project root and directories**

```bash
mkdir -p webiartisan-new/{sites,data/seeds,scripts}
cd webiartisan-new
```

- [ ] **Step 2: Write the Docker Compose file**

Create `docker-compose.yml`:

```yaml
version: "3.8"

services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - ./sites/api:/var/www/api:ro
    depends_on:
      - php
      - mysql
    networks:
      - webiartisan

  php:
    build:
      context: ./docker/php
      dockerfile: Dockerfile
    volumes:
      - ./sites/api:/var/www/api
    environment:
      DB_HOST: mysql
      DB_NAME: webiartisan
      DB_USER: webiartisan
      DB_PASS: webiartisan_dev
    networks:
      - webiartisan

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: webiartisan
      MYSQL_USER: webiartisan
      MYSQL_PASSWORD: webiartisan_dev
    volumes:
      - mysql_data:/var/lib/mysql
      - ./sites/api/migrations:/docker-entrypoint-initdb.d:ro
    ports:
      - "3306:3306"
    networks:
      - webiartisan

  node:
    image: node:20-alpine
    working_dir: /app/sites/webiartisan-livry
    volumes:
      - .:/app
    ports:
      - "5173:5173"
    command: ["sh", "-c", "cd /app/sites/artisans-shared && npm install && cd /app/sites/webiartisan-livry && npm install && npm run dev"]
    networks:
      - webiartisan

volumes:
  mysql_data:

networks:
  webiartisan:
```

- [ ] **Step 3: Create Nginx and PHP Docker files**

Create `docker/nginx/default.conf`:

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/api;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Create `docker/php/Dockerfile`:

```dockerfile
FROM php:8.4-fpm
RUN docker-php-ext-install pdo pdo_mysql
WORKDIR /var/www/api
```

- [ ] **Step 4: Write the root Makefile**

Create `Makefile`:

```makefile
.PHONY: help up down migrate seed build dev test-api push-livry

help:
	@echo "WebiArtisan — POC Livry"
	@echo "  make up          Start Docker Compose"
	@echo "  make down        Stop Docker Compose"
	@echo "  make migrate     Run SQL migrations"
	@echo "  make seed        Insert Livry demo data"
	@echo "  make dev         Start Vite dev server"
	@echo "  make build       Build frontend for production"
	@echo "  make test-api    Run API smoke tests"
	@echo "  make push-livry  Deploy Livry to Gandi"

up:
	@docker compose up -d --build
	@echo "✅ Stack running on http://localhost"

down:
	@docker compose down

migrate:
	@docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan < sites/api/migrations/025_artisans_local.sql
	@echo "✅ Migrations applied"

seed:
	@docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan < data/seeds/livry.sql
	@echo "✅ Livry seed applied"

dev:
	@docker compose logs -f node

build:
	@docker compose run --rm node sh -c "cd /app/sites/webiartisan-livry && npm run build"

test-api:
	@bash scripts/test-api.sh

push-livry:
	@$(MAKE) -C sites/webiartisan-livry push
```

- [ ] **Step 5: Create `.env.example`**

```bash
# Database (used by PHP container)
DB_HOST=mysql
DB_NAME=webiartisan
DB_USER=webiartisan
DB_PASS=webiartisan_dev

# Frontend (copy to sites/webiartisan-livry/.env)
VITE_API_URL=http://localhost/api
VITE_CITY_SLUG=livry
VITE_CITY_NAME=Livry
VITE_CITY_LAT=49.1081
VITE_CITY_LNG=-0.7658
VITE_CITY_CP=14240
```

- [ ] **Step 6: Commit the skeleton**

```bash
git add .
git commit -m "chore: bootstrap reorganized monorepo with Docker and Makefile"
```

---

### Task 2: Copy and clean the existing API

**Files:**
- Copy from: `extract/webiartisan/sites/api/` → `webiartisan-new/sites/api/`
- Modify: `webiartisan-new/sites/api/config/database.php`
- Modify: `webiartisan-new/sites/api/index.php`

- [ ] **Step 1: Copy the API directory**

```bash
cp -r extract/webiartisan/sites/api webiartisan-new/sites/
```

- [ ] **Step 2: Remove unrelated routes to keep the POC focused**

```bash
cd webiartisan-new/sites/api/routes
rm -f auth.php brand.php client-public.php clients.php docs.php gestion.php mobile.php payments.php public.php reviews.php subscription.php tresorrerie.php website.php
```

- [ ] **Step 3: Update database config to read from environment**

Modify `sites/api/config/database.php`:

```php
function getDatabase(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host    = $_ENV['DB_HOST']    ?? 'mysql';
    $db      = $_ENV['DB_NAME']    ?? 'webiartisan';
    $user    = $_ENV['DB_USER']    ?? 'webiartisan';
    $pass    = $_ENV['DB_PASS']    ?? 'webiartisan_dev';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
}
```

- [ ] **Step 4: Strip protected routes from the front controller**

Modify `sites/api/index.php` to remove references to deleted route files:

```php
$publicRoutes = ['cities', 'artisans'];
$protectedRoutes = [];
```

Also remove the block that requires `config/app.php`, `middleware/Auth.php`, `middleware/Tenant.php`, `middleware/PlanQuota.php` if they are only used by protected routes. If they are harmless, keep them but ensure the files exist.

- [ ] **Step 5: Verify the API starts**

```bash
make up
sleep 5
curl -s http://localhost/api/ | python3 -m json.tool
```

Expected: JSON with `success: true`, `service: WebIArtisan API`.

- [ ] **Step 6: Commit**

```bash
git add sites/api docker Makefile docker-compose.yml .env.example
git commit -m "chore: import and clean webiartisan API"
```

---

### Task 3: Create the Livry seed data

**Files:**
- Create: `webiartisan-new/data/seeds/livry.sql`

- [ ] **Step 1: Write the seed file**

Create `data/seeds/livry.sql`:

```sql
-- ============================================================
-- Seed : Livry (Calvados, 14240)
-- ============================================================

INSERT IGNORE INTO local_cities (slug, name, postal_code, department, region, country,
                    latitude, longitude, population, description,
                    is_active, subdomain)
VALUES (
    'livry',
    'Livry',
    '14240',
    '14',
    'Normandie',
    'FR',
    49.1081000,
    -0.7658000,
    752,
    'Ancienne commune du Calvados, rattachée à Caumont-sur-Aure, au cœur du Pré-Bocage.',
    TRUE,
    'artisans-livry.prigent.tech'
);

SET @livry_id = (SELECT id FROM local_cities WHERE slug = 'livry');

INSERT IGNORE INTO local_categories (slug, name, icon, color, sort_order) VALUES
('plombier',     'Plombier',        '🔧', '#1565C0', 1),
('electricien',  'Électricien',     '⚡', '#F57F17', 2),
('peintre',      'Peintre',         '🎨', '#6A1B9A', 3),
('menuisier',    'Menuisier',       '🪚', '#558B2F', 4),
('boulanger',    'Boulanger',       '🥖', '#8D6E63', 5),
('coiffeur',     'Coiffeur',        '✂️', '#E91E63', 6),
('jardinage',    'Jardinage',       '🌿', '#2E7D32', 7);

-- Demo artisans
SET @cat_plombier    = (SELECT id FROM local_categories WHERE slug = 'plombier');
SET @cat_electricien = (SELECT id FROM local_categories WHERE slug = 'electricien');
SET @cat_peintre     = (SELECT id FROM local_categories WHERE slug = 'peintre');
SET @cat_menuisier   = (SELECT id FROM local_categories WHERE slug = 'menuisier');
SET @cat_boulanger   = (SELECT id FROM local_categories WHERE slug = 'boulanger');
SET @cat_coiffeur    = (SELECT id FROM local_categories WHERE slug = 'coiffeur');
SET @cat_jardinage   = (SELECT id FROM local_categories WHERE slug = 'jardinage');

INSERT IGNORE INTO local_artisans
    (city_id, category_id, company_name, description, phone, email, website, address,
     latitude, longitude, status, is_verified, is_featured)
VALUES
(@livry_id, @cat_plombier,    'Plomberie Livernoise',    'Dépannage plomberie, chauffe-eau et sanitaires à Livry.',                    '06 12 34 56 70', 'contact@plomberie-livernoise.fr',    NULL, '7 rue du Bessin, 14240 Livry',    49.1090, -0.7665, 'active', TRUE,  TRUE),
(@livry_id, @cat_electricien, 'Élec Aure',               'Installation électrique, mise aux normes et dépannage.',                     '06 23 45 67 81', 'elec.aure@orange.fr',                NULL, '12 rue de la Mairie, 14240 Livry', 49.1075, -0.7645, 'active', TRUE,  FALSE),
(@livry_id, @cat_peintre,     'Peintures Poret',         'Peinture intérieure, extérieure et ravalement de façades.',                  '06 34 56 78 92', 'poret.peintures@laposte.net',        NULL, '3 rue Saint-Martin, 14240 Livry',  49.1085, -0.7670, 'active', FALSE, TRUE),
(@livry_id, @cat_menuisier,   'Menuiserie Thomas',       'Menuiserie intérieure, portes, fenêtres et escaliers sur mesure.',           '06 45 67 89 03', 'menuiserie.thomas.livry@gmail.com',  NULL, '18 rue de l\'Église, 14240 Livry', 49.1068, -0.7652, 'active', TRUE,  FALSE),
(@livry_id, @cat_boulanger,   'Boulangerie du Village',  'Pain, viennoiseries et pâtisseries artisanales fabriqués sur place.',        '02 31 09 12 34', 'boulangerie.livry@outlook.fr',       NULL, 'Place de la Mairie, 14240 Livry',  49.1081, -0.7658, 'active', TRUE,  TRUE),
(@livry_id, @cat_coiffeur,    'Salon Coiffure Actuelle', 'Coupe femme, homme, enfants, coloration et soins capillaires.',              '06 56 78 90 14', 'coiffure.actuelle.livry@yahoo.fr',   NULL, '5 rue du Calvados, 14240 Livry',   49.1092, -0.7648, 'active', FALSE, FALSE),
(@livry_id, @cat_jardinage,   'Vert Bocage',             'Entretien de jardins, taille de haies, tonte et création d\'espaces verts.', '06 67 89 01 25', 'contact@vert-bocage.fr',             NULL, 'Route de Caumont, 14240 Livry',    49.1070, -0.7680, 'active', FALSE, FALSE);

-- POIs
INSERT IGNORE INTO local_pois (city_id, type, name, address, phone, website, latitude, longitude, description, is_active, sort_order) VALUES
(@livry_id, 'mairie',    'Mairie annexe de Livry',    'Place de la Mairie, 14240 Livry',      '02 31 09 70 00', NULL, 49.1081, -0.7658, 'Mairie annexe de la commune déléguée de Livry.', TRUE, 1),
(@livry_id, 'eglise',    'Église Notre-Dame de Livry','Rue de l\'Église, 14240 Livry',        NULL,              NULL, 49.1083, -0.7660, 'Église paroissiale de Livry.', TRUE, 2),
(@livry_id, 'poste',     'La Poste — Livry',          'Place de la Mairie, 14240 Livry',      '36 31',           NULL, 49.1080, -0.7657, 'Bureau de Poste.', TRUE, 3),
(@livry_id, 'supermarche','Super U Caumont-Livry',    'Route de Caumont, 14240 Livry',        '02 31 09 80 00', NULL, 49.1072, -0.7642, 'Supermarché de proximité.', TRUE, 4),
(@livry_id, 'pharmacie', 'Pharmacie du Bocage',       '4 rue du Calvados, 14240 Livry',       '02 31 09 65 00', NULL, 49.1091, -0.7649, 'Pharmacie de Livry.', TRUE, 5);

-- Horaires Mairie
SET @livry_mairie_id = (SELECT id FROM local_pois WHERE name = 'Mairie annexe de Livry' AND city_id = @livry_id);
INSERT IGNORE INTO local_schedules (poi_id, day_of_week, open_time, close_time, break_start, break_end, is_closed) VALUES
(@livry_mairie_id, 0, '09:00:00', '17:00:00', '12:00:00', '13:30:00', FALSE),
(@livry_mairie_id, 1, '09:00:00', '17:00:00', '12:00:00', '13:30:00', FALSE),
(@livry_mairie_id, 2, '09:00:00', '17:00:00', '12:00:00', '13:30:00', FALSE),
(@livry_mairie_id, 3, '09:00:00', '17:00:00', '12:00:00', '13:30:00', FALSE),
(@livry_mairie_id, 4, '09:00:00', '16:00:00', '12:00:00', '13:30:00', FALSE),
(@livry_mairie_id, 5, NULL, NULL, NULL, NULL, TRUE),
(@livry_mairie_id, 6, NULL, NULL, NULL, NULL, TRUE);

-- Horaires Poste
SET @livry_poste_id = (SELECT id FROM local_pois WHERE name = 'La Poste — Livry' AND city_id = @livry_id);
INSERT IGNORE INTO local_schedules (poi_id, day_of_week, open_time, close_time, is_closed) VALUES
(@livry_poste_id, 0, '09:00:00', '12:00:00', FALSE),
(@livry_poste_id, 0, '14:00:00', '17:00:00', FALSE),
(@livry_poste_id, 1, '09:00:00', '12:00:00', FALSE),
(@livry_poste_id, 1, '14:00:00', '17:00:00', FALSE),
(@livry_poste_id, 2, '09:00:00', '12:00:00', FALSE),
(@livry_poste_id, 2, '14:00:00', '17:00:00', FALSE),
(@livry_poste_id, 3, '09:00:00', '12:00:00', FALSE),
(@livry_poste_id, 3, '14:00:00', '17:00:00', FALSE),
(@livry_poste_id, 4, '09:00:00', '12:00:00', FALSE),
(@livry_poste_id, 4, '14:00:00', '17:00:00', FALSE),
(@livry_poste_id, 5, '09:00:00', '12:00:00', FALSE),
(@livry_poste_id, 6, NULL, NULL, TRUE);
```

- [ ] **Step 2: Apply migrations and seed**

```bash
make migrate
make seed
```

- [ ] **Step 3: Verify seed via API**

```bash
curl -s "http://localhost/api/artisans?city=livry" | python3 -m json.tool
curl -s "http://localhost/api/cities/livry/pois" | python3 -m json.tool
```

Expected: JSON containing 7 artisans and 5 POIs.

- [ ] **Step 4: Commit**

```bash
git add data/seeds/livry.sql
git commit -m "data: add Livry demo artisans, categories and POIs"
```

---

### Task 4: Prepare the shared template

**Files:**
- Copy from: `extract/webiartisan/sites/artisans-shared/` → `webiartisan-new/sites/artisans-shared/`
- Modify: `webiartisan-new/sites/artisans-shared/src/api.js`
- Modify: `webiartisan-new/sites/artisans-shared/src/views/Home.vue`
- Modify: `webiartisan-new/sites/artisans-shared/src/views/Dashboard.vue`
- Modify: `webiartisan-new/sites/artisans-shared/src/components/AppNav.vue`

- [ ] **Step 1: Copy the shared template**

```bash
cp -r extract/webiartisan/sites/artisans-shared webiartisan-new/sites/
```

- [ ] **Step 2: Update `api.js` to add magic-link and authenticated profile helpers**

Modify `sites/artisans-shared/src/api.js` to append the following exports at the end of the file:

```js
// --- Artisan authentication (magic link) ---

export async function requestMagicLink(email) {
  const res = await fetch(`${API_BASE}/artisans/magic-link`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email }),
  })
  return res.json()
}

export async function fetchMe(token) {
  const res = await fetch(`${API_BASE}/artisans/me`, {
    headers: { 'X-Artisan-Token': token },
  })
  if (!res.ok) throw new Error('Session invalide')
  return res.json()
}

export async function updateMe(token, data) {
  const res = await fetch(`${API_BASE}/artisans/me`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
  })
  return res.json()
}
```

- [ ] **Step 3: Add artisan markers to the map in Home.vue**

Modify `sites/artisans-shared/src/views/Home.vue`:

Find the block inside `onMounted` that initializes the map:

```js
  // Init OpenStreetMap
  const map = L.map('osm-map').setView([CITY_LAT, CITY_LNG], 14)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map)
  L.marker([CITY_LAT, CITY_LNG]).addTo(map)
    .bindPopup(`<b>${CITY_NAME}</b><br/>Centre ville`)
    .openPopup()
```

Replace it with:

```js
  // Init OpenStreetMap
  const map = L.map('osm-map').setView([CITY_LAT, CITY_LNG], 14)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map)

  // Centre ville
  L.marker([CITY_LAT, CITY_LNG]).addTo(map)
    .bindPopup(`<b>${CITY_NAME}</b><br/>Centre ville`)

  // Marqueurs artisans
  artisans.value.forEach(a => {
    if (!a.latitude || !a.longitude) return
    const marker = L.circleMarker([a.latitude, a.longitude], {
      radius: 10,
      fillColor: a.category_color || '#2D6A4F',
      color: '#fff',
      weight: 2,
      opacity: 1,
      fillOpacity: 0.9,
    }).addTo(map)
    marker.bindPopup(`
      <b>${a.company_name}</b><br/>
      <span>${a.category_name || 'Artisan'}</span><br/>
      <a href="#/artisan/${a.id}">Voir la fiche →</a>
    `)
  })
```

- [ ] **Step 4: Build the connected artisan dashboard**

Replace `sites/artisans-shared/src/views/Dashboard.vue` with:

```vue
<template>
  <div class="container section">
    <div v-if="loading" class="text-center">Chargement…</div>

    <div v-else-if="!token">
      <h1>Mon espace artisan</h1>
      <p>Connectez-vous pour gérer votre fiche.</p>
      <form @submit.prevent="sendLink" class="form-box">
        <label>Email professionnel</label>
        <input v-model="email" type="email" class="form-input" required placeholder="votre@email.fr" />
        <button type="submit" class="btn btn-primary" :disabled="sending">
          {{ sending ? 'Envoi…' : 'Recevoir mon lien de connexion' }}
        </button>
        <p v-if="message" class="form-message">{{ message }}</p>
      </form>
    </div>

    <div v-else-if="artisan">
      <h1>Bonjour, {{ artisan.company_name }}</h1>
      <form @submit.prevent="save" class="form-box">
        <label>Nom de l'entreprise</label>
        <input v-model="form.company_name" class="form-input" required />

        <label>Téléphone</label>
        <input v-model="form.phone" class="form-input" />

        <label>Site web</label>
        <input v-model="form.website" class="form-input" />

        <label>Adresse</label>
        <textarea v-model="form.address" class="form-input" rows="2"></textarea>

        <label>Description</label>
        <textarea v-model="form.description" class="form-input" rows="4"></textarea>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ saving ? 'Enregistrement…' : 'Enregistrer les modifications' }}
          </button>
          <button type="button" class="btn btn-outline" @click="logout">Se déconnecter</button>
        </div>
        <p v-if="message" class="form-message">{{ message }}</p>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { requestMagicLink, fetchMe, updateMe } from '../api.js'

const route  = useRoute()
const router = useRouter()

const token    = ref(localStorage.getItem('artisan_token') || '')
const email    = ref('')
const artisan  = ref(null)
const form     = ref({})
const loading  = ref(false)
const sending  = ref(false)
const saving   = ref(false)
const message  = ref('')

onMounted(async () => {
  const urlToken = route.query.token
  if (urlToken) {
    token.value = urlToken
    localStorage.setItem('artisan_token', urlToken)
    router.replace('/espace')
  }
  if (token.value) await loadProfile()
})

async function loadProfile() {
  loading.value = true
  try {
    const res = await fetchMe(token.value)
    if (res.success) {
      artisan.value = res.data
      form.value = {
        company_name: res.data.company_name || '',
        phone:        res.data.phone || '',
        website:      res.data.website || '',
        address:      res.data.address || '',
        description:  res.data.description || '',
      }
    } else {
      logout()
    }
  } catch (e) {
    logout()
  } finally {
    loading.value = false
  }
}

async function sendLink() {
  sending.value = true
  message.value = ''
  try {
    const res = await requestMagicLink(email.value)
    message.value = res.message || 'Si cet email est enregistré, un lien vous a été envoyé.'
  } finally {
    sending.value = false
  }
}

async function save() {
  saving.value = true
  message.value = ''
  try {
    const res = await updateMe(token.value, form.value)
    message.value = res.success ? 'Profil mis à jour.' : (res.error || 'Erreur lors de la mise à jour.')
  } finally {
    saving.value = false
  }
}

function logout() {
  token.value = ''
  artisan.value = null
  localStorage.removeItem('artisan_token')
}
</script>

<style scoped>
.form-box { max-width: 560px; margin-top: 24px; }
.form-box label { display: block; margin: 16px 0 6px; font-weight: 600; font-size: 0.9rem; }
.form-input { width: 100%; padding: 10px 14px; border: 1px solid var(--c-border); border-radius: var(--r-md); font-family: inherit; }
.form-actions { display: flex; gap: 12px; margin-top: 24px; }
.form-message { margin-top: 16px; color: var(--c-green); font-weight: 500; }
</style>
```

- [ ] **Step 5: Add `/espace` link to navigation**

Modify `sites/artisans-shared/src/components/AppNav.vue`:

In the `.nav-links` div, add before the inscription button:

```vue
<RouterLink to="/espace" class="nav-link">Mon espace</RouterLink>
```

In the mobile menu, add:

```vue
<RouterLink to="/espace" class="nav-mobile-link">🔑 Mon espace artisan</RouterLink>
```

- [ ] **Step 6: Add `/api/artisans/me` route**

Modify `sites/api/routes/artisans.php`:

In the `case 'GET':` block, add before the fallback:

```php
        } elseif ($action === 'me') {
            artisan_me($pdo);
```

Add the function at the end of the file:

```php
/**
 * GET /artisans/me — Profil de l'artisan connecté
 */
function artisan_me(PDO $pdo): void
{
    $token = $_SERVER['HTTP_X_ARTISAN_TOKEN'] ?? '';
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token requis']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT
            a.id, a.company_name, a.description, a.phone, a.email,
            a.website, a.address, a.latitude, a.longitude,
            a.logo_url, a.cover_url,
            cat.slug AS category_slug, cat.name AS category_name,
            cat.icon AS category_icon, cat.color AS category_color,
            c.slug AS city_slug, c.name AS city_name
        FROM local_artisans a
        JOIN local_cities c ON a.city_id = c.id
        LEFT JOIN local_categories cat ON a.category_id = cat.id
        WHERE a.auth_token = ? AND a.auth_token_exp > NOW() AND a.status = 'active'
    ");
    $stmt->execute([$token]);
    $artisan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$artisan) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token invalide ou expiré']);
        return;
    }

    echo json_encode(['success' => true, 'data' => $artisan]);
}
```

- [ ] **Step 7: Update magic-link to return token in dev**

Modify `sites/api/routes/artisans.php` in `artisan_magic_link`:

Replace the TODO comment block with:

```php
    // Development: log the link so we can test without SMTP
    error_log("[MAGIC-LINK] https://artisans-livry.prigent.tech/espace?token={$token}");
```

- [ ] **Step 8: Install dependencies and verify build**

```bash
cd webiartisan-new/sites/artisans-shared
npm install
cd ../webiartisan-livry
npm install
npm run build
```

Expected: `dist/` folder created without errors.

- [ ] **Step 9: Commit**

```bash
git add sites/artisans-shared
git commit -m "feat: shared template with artisan map markers and dashboard"
```

---

### Task 5: Create the Livry city instance

**Files:**
- Create: `webiartisan-new/sites/webiartisan-livry/package.json`
- Create: `webiartisan-new/sites/webiartisan-livry/vite.config.js`
- Create: `webiartisan-new/sites/webiartisan-livry/index.html`
- Create: `webiartisan-new/sites/webiartisan-livry/.env`
- Create: `webiartisan-new/sites/webiartisan-livry/Makefile`
- Create: `webiartisan-new/sites/webiartisan-livry/src/main.js`

- [ ] **Step 1: Create package.json**

```json
{
  "name": "webiartisan-livry",
  "version": "1.0.0",
  "private": true,
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview"
  },
  "dependencies": {
    "leaflet": "^1.9.4",
    "vue": "^3.5.38",
    "vue-router": "^4.6.4"
  },
  "devDependencies": {
    "@vitejs/plugin-vue": "^5.0.0",
    "vite": "^5.0.0"
  }
}
```

- [ ] **Step 2: Create vite.config.js**

```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, '../artisans-shared/src'),
      '@shared': resolve(__dirname, '../artisans-shared/src'),
    }
  },
  server: {
    port: 5173,
    fs: { allow: ['..'] },
    proxy: {
      '/api': {
        target: 'http://localhost/api',
        changeOrigin: true,
        rewrite: path => path.replace(/^\/api/, '')
      }
    }
  },
  build: {
    outDir: 'dist',
    assetsDir: 'assets'
  }
})
```

- [ ] **Step 3: Create index.html**

```html
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Artisans de Livry — 14240</title>
    <meta name="description" content="Annuaire des artisans locaux de Livry (Calvados). Trouvez plombier, électricien, boulanger, coiffeur… près de chez vous." />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="../artisans-shared/src/main.js"></script>
  </body>
</html>
```

- [ ] **Step 4: Create `.env`**

```bash
VITE_API_URL=http://localhost/api
VITE_CITY_SLUG=livry
VITE_CITY_NAME=Livry
VITE_CITY_LAT=49.1081
VITE_CITY_LNG=-0.7658
VITE_CITY_CP=14240
```

- [ ] **Step 5: Create the instance Makefile**

```makefile
SITE_DIR := /mnt/gandi/vhosts/artisans-livry.prigent.tech/htdocs
DIST_DIR := dist

.PHONY: dev build push clean install

dev:
	@npm run dev

build:
	@npm run build

push: build
	@test -d "$(SITE_DIR)" || (echo "❌ Gandi non monté" && exit 1)
	@rsync -av --no-owner --no-group --delete $(DIST_DIR)/ $(SITE_DIR)/
	@echo "✅ Déployé sur https://artisans-livry.prigent.tech"

clean:
	@rm -rf $(DIST_DIR) node_modules

install:
	@npm install
```

- [ ] **Step 6: Build the Livry instance**

```bash
cd webiartisan-new/sites/webiartisan-livry
npm install
npm run build
```

Expected: `dist/` created with `index.html` and `assets/`.

- [ ] **Step 7: Commit**

```bash
git add sites/webiartisan-livry
git commit -m "feat: add Livry city instance"
```

---

### Task 6: Add API smoke tests

**Files:**
- Create: `webiartisan-new/scripts/test-api.sh`

- [ ] **Step 1: Write the test script**

```bash
#!/usr/bin/env bash
set -euo pipefail

BASE="http://localhost/api"
FAIL=0

check() {
  local desc="$1"; shift
  local url="$1"; shift
  echo -n "[$desc] $url ... "
  if curl -s -o /tmp/last.json -w "%{http_code}" "$url" | grep -q "^20"; then
    echo "OK"
  else
    echo "FAIL"
    cat /tmp/last.json
    FAIL=1
  fi
}

check "health" "$BASE/"
check "city-livry" "$BASE/cities/livry"
check "artisans-livry" "$BASE/artisans?city=livry"
check "pois-livry" "$BASE/cities/livry/pois"
check "schedules-livry" "$BASE/cities/livry/schedules"

exit $FAIL
```

Make it executable:

```bash
chmod +x scripts/test-api.sh
```

- [ ] **Step 2: Run the tests**

```bash
make test-api
```

Expected: All 5 checks return OK.

- [ ] **Step 3: Commit**

```bash
git add scripts/test-api.sh
git commit -m "test: add API smoke tests"
```

---

### Task 7: Verify the frontend locally

**Files:**
- None (manual verification)

- [ ] **Step 1: Start the dev server**

```bash
make up
make dev
```

Wait for the `node` container to install and start. Then open `http://localhost:5173`.

- [ ] **Step 2: Manual checklist**

1. Home page loads with "Livry · 14240".
2. Map is centered on Livry.
3. 7 colored artisan markers appear on the map.
4. Clicking a marker opens a popup with a link to the artisan detail.
5. Category filters work and update the list.
6. "Services locaux" section shows the 5 POIs with opening status.
7. Weather widget shows local weather.
8. Clicking "Mon espace" shows the magic-link form.
9. Submitting the magic-link form for `contact@plomberie-livernoise.fr` logs a URL in the PHP container logs.
10. Visiting that URL loads the dashboard with the artisan profile editable.

- [ ] **Step 3: Check the PHP logs for the magic link**

```bash
docker compose logs php | grep MAGIC-LINK
```

Expected: A line like `[MAGIC-LINK] https://artisans-livry.prigent.tech/espace?token=...`

- [ ] **Step 4: Commit a note or screenshot**

No file changes. Continue when verified.

---

### Task 8: Production build and deploy

**Files:**
- Modify: `webiartisan-new/sites/webiartisan-livry/Makefile`

- [ ] **Step 1: Build for production**

```bash
make build
```

- [ ] **Step 2: Verify the production dist**

```bash
ls -la sites/webiartisan-livry/dist/
```

Expected: `index.html` and `assets/` folder.

- [ ] **Step 3: Mount Gandi and deploy**

```bash
# Ensure /mnt/gandi/vhosts/artisans-livry.prigent.tech/htdocs exists
make push-livry
```

Expected: rsync completes and prints the deployed URL.

- [ ] **Step 4: Verify production URL**

```bash
curl -s https://artisans-livry.prigent.tech/ | head -20
```

Expected: HTML containing "Livry" and the app mount point.

- [ ] **Step 5: Commit and tag**

```bash
git add sites/webiartisan-livry/Makefile
git commit -m "chore: production build and deploy targets for Livry"
git tag v0.1.0-livry-poc
```

---

## Self-Review

### Spec coverage

| Spec requirement | Task that implements it |
|------------------|------------------------|
| Reorganized monorepo skeleton | Task 1 |
| Docker Compose + Makefile | Task 1 |
| PHP API cleaned and runnable | Task 2 |
| Livry seed data | Task 3 |
| Shared template with map + artisan markers | Task 4 |
| Connected artisan dashboard (magic link) | Task 4 |
| Livry city instance | Task 5 |
| API smoke tests | Task 6 |
| Local frontend verification | Task 7 |
| Production build + deploy | Task 8 |

### Placeholder scan

No `TBD`, `TODO`, or vague instructions remain. Each step includes exact file paths, code snippets, commands, and expected outputs.

### Type consistency

- Map coordinates use `latitude`/`longitude` matching the database schema.
- Magic link token is passed via `X-Artisan-Token` in both frontend helpers and PHP `artisan_me`.
- The `/api/artisans/me` endpoint is added to the GET switch in `artisans.php`.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-06-28-livry-poc-implementation.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration.
2. **Inline Execution** — Execute tasks in this session using `executing-plans`, batch execution with checkpoints.

Which approach would you like?
