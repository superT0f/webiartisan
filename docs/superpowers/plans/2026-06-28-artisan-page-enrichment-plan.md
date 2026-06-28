# Artisan Page Enrichment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enrichir la fiche publique d’un artisan avec les recettes liées à ses produits et les commerces/services à proximité.

**Architecture:** Ajouter une requête SQL avec jointures et calcul Haversine dans `sites/api/routes/artisans.php`, puis créer deux petits composants Vue (`ArtisanNearbyMap.vue`, `RecipeMiniCard.vue`) et intégrer les nouvelles sections dans `sites/artisans-shared/src/views/Artisan.vue`.

**Tech Stack:** PHP 8.4, MySQL 8, Vue 3, Leaflet.

---

## File structure

| File | Responsibility |
|------|----------------|
| `sites/api/routes/artisans.php` | Enrichir `artisan_get()` avec `recipes` et `nearby` |
| `sites/artisans-shared/src/views/Artisan.vue` | Afficher les sections recettes et à proximité |
| `sites/artisans-shared/src/components/ArtisanNearbyMap.vue` | Mini-carte Leaflet avec artisan, prospects, POI |
| `sites/artisans-shared/src/components/RecipeMiniCard.vue` | Carte compacte recette |

---

## Task 1: Enrich API response

**Files:**
- Modify: `sites/api/routes/artisans.php`

- [ ] **Step 1: Read current `artisan_get()`**

Open `sites/api/routes/artisans.php` and locate `function artisan_get(PDO $pdo, int $id): void`.

- [ ] **Step 2: Add SQL helpers**

After `artisan_get()`, add two helper functions:

```php
function artisan_recipes(PDO $pdo, int $artisanId, string $artisanEmail): array
{
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            r.id, r.title, r.slug, r.description, r.image_url,
            r.servings, r.prep_time_minutes, r.cook_time_minutes,
            r.submitted_by,
            (ra.artisan_id IS NOT NULL) AS is_product_recipe
        FROM local_recipes r
        LEFT JOIN local_recipe_artisans ra ON ra.recipe_id = r.id AND ra.artisan_id = ?
        WHERE r.status = 'published'
          AND (ra.artisan_id IS NOT NULL OR r.submitter_email = ?)
        ORDER BY r.created_at DESC
        LIMIT 6
    ");
    $stmt->execute([$artisanId, $artisanEmail]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function artisan_nearby(PDO $pdo, float $lat, float $lng, int $cityId, int $artisanId): array
{
    $sql = "
        SELECT 'prospect' AS kind, id, name, type, address, latitude, longitude,
               (6371000 * acos(
                   cos(radians(?)) * cos(radians(latitude)) *
                   cos(radians(longitude) - radians(?)) +
                   sin(radians(?)) * sin(radians(latitude))
               )) AS distance_meters
        FROM local_prospects
        WHERE city_id = ? AND is_active = 1
        HAVING distance_meters <= 2000

        UNION ALL

        SELECT 'poi' AS kind, id, name, type, address, latitude, longitude,
               (6371000 * acos(
                   cos(radians(?)) * cos(radians(latitude)) *
                   cos(radians(longitude) - radians(?)) +
                   sin(radians(?)) * sin(radians(latitude))
               )) AS distance_meters
        FROM local_pois
        WHERE city_id = ? AND is_active = 1
        HAVING distance_meters <= 2000

        ORDER BY distance_meters ASC
        LIMIT 10
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $lat, $lng, $lat,
        $cityId,
        $lat, $lng, $lat,
        $cityId,
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

- [ ] **Step 3: Wire helpers into `artisan_get()`**

Inside `artisan_get()`, before the final `echo json_encode`, add:

```php
    $artisan['recipes'] = artisan_recipes($pdo, $artisan['id'], $artisan['email'] ?? '');
    $artisan['nearby'] = artisan_nearby(
        $pdo,
        (float)$artisan['latitude'],
        (float)$artisan['longitude'],
        (int)$artisan['city_id'],
        (int)$artisan['id']
    );
```

- [ ] **Step 4: Test API**

Run:

```bash
curl -s "http://localhost:8080/api/artisans/1" | python3 -m json.tool | tail -40
```

Expected: JSON contains `recipes` and `nearby` arrays.

- [ ] **Step 5: Commit**

```bash
cd /mnt/c/Users/user/code/webiartisan.new
git add sites/api/routes/artisans.php
git commit -m "feat(api): enrich artisan detail with recipes and nearby places"
```

---

## Task 2: Create Vue components

**Files:**
- Create: `sites/artisans-shared/src/components/RecipeMiniCard.vue`
- Create: `sites/artisans-shared/src/components/ArtisanNearbyMap.vue`

- [ ] **Step 1: Write `RecipeMiniCard.vue`**

```vue
<script setup>
defineProps({
  recipe: { type: Object, required: true }
});
</script>

<template>
  <RouterLink :to="`/recette/${recipe.slug}`" class="recipe-mini-card">
    <img v-if="recipe.image_url" :src="recipe.image_url" :alt="recipe.title" />
    <div class="content">
      <h4>{{ recipe.title }}</h4>
      <p>{{ recipe.description }}</p>
      <span v-if="recipe.is_product_recipe" class="badge">Avec ses produits</span>
    </div>
  </RouterLink>
</template>

<style scoped>
.recipe-mini-card {
  display: flex;
  gap: 0.75rem;
  border: 1px solid #eee;
  border-radius: 8px;
  padding: 0.75rem;
  text-decoration: none;
  color: inherit;
}
.recipe-mini-card img {
  width: 80px;
  height: 80px;
  object-fit: cover;
  border-radius: 6px;
}
.recipe-mini-card .content {
  flex: 1;
}
.recipe-mini-card h4 {
  margin: 0 0 0.25rem;
}
.recipe-mini-card p {
  margin: 0;
  font-size: 0.85rem;
  color: #555;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.badge {
  display: inline-block;
  margin-top: 0.4rem;
  font-size: 0.75rem;
  background: #22c55e;
  color: #fff;
  padding: 2px 8px;
  border-radius: 12px;
}
</style>
```

- [ ] **Step 2: Write `ArtisanNearbyMap.vue`**

```vue
<script setup>
import { ref, onMounted, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
  artisan: { type: Object, required: true },
  nearby: { type: Array, default: () => [] }
});

const mapEl = ref(null);
const map = ref(null);

onMounted(() => {
  const lat = parseFloat(props.artisan.latitude);
  const lng = parseFloat(props.artisan.longitude);
  map.value = L.map(mapEl.value).setView([lat, lng], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap'
  }).addTo(map.value);

  L.marker([lat, lng], { title: props.artisan.company_name })
    .addTo(map.value)
    .bindPopup(`<b>${props.artisan.company_name}</b>`);

  renderNearby();
});

watch(() => props.nearby, renderNearby, { deep: true });

function renderNearby() {
  if (!map.value) return;
  props.nearby.forEach(place => {
    const color = place.kind === 'prospect' ? '#f97316' : '#3b82f6';
    const marker = L.circleMarker(
      [parseFloat(place.latitude), parseFloat(place.longitude)],
      { radius: 7, color, fillColor: color, fillOpacity: 0.7 }
    ).addTo(map.value);
    marker.bindPopup(`<b>${place.name}</b><br>${place.type}`);
  });
}
</script>

<template>
  <div ref="mapEl" class="nearby-map"></div>
</template>

<style scoped>
.nearby-map {
  width: 100%;
  height: 280px;
  border-radius: 10px;
  margin: 1rem 0;
}
</style>
```

- [ ] **Step 3: Commit**

```bash
git add sites/artisans-shared/src/components/RecipeMiniCard.vue \
        sites/artisans-shared/src/components/ArtisanNearbyMap.vue
git commit -m "feat(front): add RecipeMiniCard and ArtisanNearbyMap components"
```

---

## Task 3: Update Artisan.vue

**Files:**
- Modify: `sites/artisans-shared/src/views/Artisan.vue`

- [ ] **Step 1: Import components**

Add near the top of `<script setup>`:

```js
import RecipeMiniCard from '../components/RecipeMiniCard.vue';
import ArtisanNearbyMap from '../components/ArtisanNearbyMap.vue';
```

- [ ] **Step 2: Add template sections**

After the existing reviews/contact section, add:

```vue
  <section v-if="artisan.recipes?.length" class="section">
    <div class="container">
      <h2 class="section-title">Recettes avec ses produits</h2>
      <div class="recipe-grid">
        <RecipeMiniCard
          v-for="recipe in artisan.recipes"
          :key="recipe.id"
          :recipe="recipe"
        />
      </div>
    </div>
  </section>

  <section v-if="artisan.nearby?.length" class="section">
    <div class="container">
      <h2 class="section-title">Autour de {{ artisan.company_name }}</h2>
      <ArtisanNearbyMap :artisan="artisan" :nearby="artisan.nearby" />
      <div class="nearby-list">
        <div v-for="place in artisan.nearby" :key="`${place.kind}-${place.id}`" class="nearby-item">
          <span class="kind" :class="place.kind">{{ place.kind === 'prospect' ? 'Commerce' : 'Service' }}</span>
          <strong>{{ place.name }}</strong>
          <span class="type">{{ place.type }}</span>
          <span class="distance">{{ Math.round(place.distance_meters) }} m</span>
        </div>
      </div>
    </div>
  </section>
```

- [ ] **Step 3: Add scoped styles**

Append to `<style scoped>`:

```css
.recipe-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 1rem;
}
.nearby-list {
  display: grid;
  gap: 0.5rem;
}
.nearby-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem;
  background: #f8f8f8;
  border-radius: 6px;
}
.nearby-item .kind {
  font-size: 0.7rem;
  text-transform: uppercase;
  padding: 2px 6px;
  border-radius: 4px;
  color: #fff;
}
.nearby-item .kind.prospect { background: #f97316; }
.nearby-item .kind.poi { background: #3b82f6; }
.nearby-item .type { color: #666; font-size: 0.85rem; }
.nearby-item .distance { margin-left: auto; font-size: 0.85rem; color: #888; }
```

- [ ] **Step 4: Commit**

```bash
git add sites/artisans-shared/src/views/Artisan.vue
git commit -m "feat(front): display recipes and nearby places on artisan page"
```

---

## Task 4: Tests and build

- [ ] **Step 1: Run API smoke test**

```bash
cd /mnt/c/Users/user/code/webiartisan.new
curl -s "http://localhost:8080/api/artisans/1" | python3 -m json.tool | grep -E '"recipes"|"nearby"'
```

Expected output contains `"recipes"` and `"nearby"` keys.

- [ ] **Step 2: Build frontend**

```bash
make build
```

Expected: build succeeds with no errors.

- [ ] **Step 3: Commit any final fixes**

```bash
git add -A
git commit -m "test(build): validate artisan page enrichment"
```

---

## Self-review

### Spec coverage

| Spec requirement | Task |
|------------------|------|
| Recettes liées via `local_recipe_artisans` | Task 1 |
| Recettes proposées par l’artisan | Task 1 |
| Prospects à proximité | Task 1 |
| POI à proximité | Task 1 |
| Mini-carte | Task 2 |
| Affichage sur la fiche artisan | Task 3 |
| Tests + build | Task 4 |

### Placeholder scan

No placeholders. All steps contain concrete code, commands, and expected outputs.

### Type consistency

- `artisan.latitude` / `artisan.longitude` are cast to `float` in PHP.
- Vue props use `Object` and `Array` types consistently.
