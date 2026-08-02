# HUD héros + FPS par défaut + avatar animé contextuel — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (inline) ou superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** HUD joueur (avatar/XP/niveau/énergie/inventaire) en haut à gauche de la carte, avatar animé seulement en déplacement, vue FPS par défaut avec bouton de changement de vue masqué (option profil).

**Architecture:** Nouveau composant `HeroWidget.vue` auto-suffisant (fetch profil + inventaire, `useEnergy`). `ImmersiveMap.vue` gère la bascule statique/GIF via classe CSS. `MapView.vue` change le défaut de mode et la visibilité du bouton. `UserProfile.vue` gagne une section Paramètres.

**Tech Stack:** Vue 3 SFC, vitest, MapLibre marker DOM, localStorage.

## Global Constraints

- Copie UI en français ; commits conventionnels français.
- Pas de nouvelle dépendance.
- Enveloppe API `{ success, data, status, error }`.
- `useGeolocation` est un singleton — utiliser `refreshPosition()` one-shot pour l'activation, ne pas instancier d'état GPS parallèle.

## File Structure

| Fichier | Rôle |
|---|---|
| `src/utils/inventoryItems.js` | `ITEM_META` + `groupInventoryByType()` (partagé HeroWidget + Inventory.vue) |
| `src/utils/inventoryItems.test.js` | tests vitest du regroupement |
| `src/components/HeroWidget.vue` | HUD : avatar+anneau XP, niveau, énergie, chips inventaire, popover confirmation |
| `src/components/ImmersiveMap.vue` | classe `is-moving` + CSS statique/GIF |
| `src/views/MapView.vue` | intègre HeroWidget, retire EnergyBar, défaut `fp`, bouton vue conditionnel |
| `src/views/Inventory.vue` | importe ITEM_META depuis utils (au lieu de le définir localement) |
| `src/views/UserProfile.vue` | section Paramètres + interrupteur changement de vue |

---

### Task 1: Module `inventoryItems.js` + tests (TDD)

**Files:**
- Create: `sites/artisans-shared/src/utils/inventoryItems.js`
- Create: `sites/artisans-shared/src/utils/inventoryItems.test.js`
- Modify: `sites/artisans-shared/src/views/Inventory.vue` (remplace la définition locale d'`ITEM_META` par l'import)

**Interfaces:**
- Produces: `ITEM_META` (objet `{ type: { emoji, label, description } }`), `groupInventoryByType(items)` → `[{ type, emoji, label, count, firstId }]`

- [ ] **Step 1: Écrire les tests (échec attendu)**

```js
// src/utils/inventoryItems.test.js
import { describe, it, expect } from 'vitest'
import { groupInventoryByType, ITEM_META } from './inventoryItems.js'

describe('groupInventoryByType', () => {
  it('regroupe par type avec compteurs et premier id', () => {
    const items = [
      { id: 3, type: 'energy_store' },
      { id: 7, type: 'boss_spawner' },
      { id: 9, type: 'energy_store' },
    ]
    expect(groupInventoryByType(items)).toEqual([
      { type: 'energy_store', emoji: ITEM_META.energy_store.emoji, label: ITEM_META.energy_store.label, count: 2, firstId: 3 },
      { type: 'boss_spawner', emoji: ITEM_META.boss_spawner.emoji, label: ITEM_META.boss_spawner.label, count: 1, firstId: 7 },
    ])
  })
  it('retourne [] pour un inventaire vide', () => {
    expect(groupInventoryByType([])).toEqual([])
  })
  it('ignore les types inconnus (fallback emoji générique)', () => {
    const [g] = groupInventoryByType([{ id: 1, type: 'mystere' }])
    expect(g.count).toBe(1)
    expect(g.emoji).toBe('🎁')
  })
})
```

- [ ] **Step 2: Vérifier l'échec** — `npx vitest run src/utils/inventoryItems.test.js` → module introuvable.

- [ ] **Step 3: Implémenter**

```js
// src/utils/inventoryItems.js
// Métadonnées d'affichage des objets d'inventaire (emojis — pas d'images dédiées).
export const ITEM_META = {
  boss_spawner: { emoji: '🎯', label: 'Leurre à Big Brother', description: 'Invoque Affamer de Gaffe près de vous.' },
  energy_store: { emoji: '🔋', label: "Réserve d'énergie", description: '+30 ⚡ immédiatement.' },
}

const FALLBACK = { emoji: '🎁', label: 'Objet', description: '' }

/**
 * Regroupe les items d'inventaire (lignes individuelles) par type.
 * @param {Array<{id:number, type:string}>} items
 * @returns {Array<{type:string, emoji:string, label:string, count:number, firstId:number}>}
 */
export function groupInventoryByType(items) {
  const groups = new Map()
  for (const item of items || []) {
    const g = groups.get(item.type)
    if (g) g.count += 1
    else groups.set(item.type, { type: item.type, count: 1, firstId: item.id })
  }
  return [...groups.values()].map((g) => {
    const meta = ITEM_META[g.type] || FALLBACK
    return { ...g, emoji: meta.emoji, label: meta.label }
  })
}
```

- [ ] **Step 4: Inventory.vue utilise l'import** — remplacer la définition locale `ITEM_META` (lignes 18-21) par `import { ITEM_META } from '../utils/inventoryItems.js'`.

- [ ] **Step 5: Tests passent** — `npx vitest run` (56+3 tests).

- [ ] **Step 6: Commit** — `feat(inventory): extraction ITEM_META et groupInventoryByType partagés`

---

### Task 2: Avatar animé au déplacement (ImmersiveMap.vue)

**Files:**
- Modify: `sites/artisans-shared/src/components/ImmersiveMap.vue` (script ~lignes 228-245, style ~lignes 602-614)

**Interfaces:**
- Consumes: `props.userPosition` (déjà existant)
- Produces: classe `is-moving` sur le marker joueur (usage interne)

- [ ] **Step 1: Logique de mouvement** — ajouter dans le `<script setup>` (près des refs du marker) :

```js
// Animation de marche : GIF seulement pendant 5 s après un déplacement > 3 m.
let lastMoveLngLat = null
let moveTimer = null
const MOVE_THRESHOLD_M = 3
const MOVE_ANIM_MS = 5000

function distanceM(a, b) {
  const R = 6371000, toRad = (d) => (d * Math.PI) / 180
  const dLat = toRad(b.lat - a.lat), dLng = toRad(b.lng - a.lng)
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.sin(dLng / 2) ** 2
  return 2 * R * Math.asin(Math.sqrt(h))
}

function markUserMoving(el, next) {
  const moved = !lastMoveLngLat || distanceM(lastMoveLngLat, next) > MOVE_THRESHOLD_M
  lastMoveLngLat = next
  if (!moved) return
  el.classList.add('is-moving')
  clearTimeout(moveTimer)
  moveTimer = setTimeout(() => el.classList.remove('is-moving'), MOVE_ANIM_MS)
}
```

Dans `upsertUserPosition()`, juste après la création/mise à jour du marker (là où `userMarker.setLngLat(...)` est appelé), appeler :

```js
markUserMoving(userMarkerEl, { lat: lngLat[1], lng: lngLat[0] })
```

(où `userMarkerEl` est le div du marker — réutiliser la variable `el` existante, la stocker dans une ref module si nécessaire.)

- [ ] **Step 2: CSS statique par défaut + GIF sous .is-moving** — remplacer dans le bloc `:deep(.user-location-marker--avatar)` :

```css
:deep(.user-location-marker--avatar) {
  width: 52px;
  height: 46px;
  background: none;
  border: none;
  border-radius: 0;
  box-shadow: none;
  background-image: url('/avatar/player-back-512.png');
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center bottom;
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.45));
}
:deep(.user-location-marker--avatar.is-moving) {
  background-image: url('/avatar/player-back-walk-128.gif');
}
```

- [ ] **Step 3: Vérifier le build** — `cd sites/webiartisan-livry && npm run build` OK.

- [ ] **Step 4: Commit** — `feat(map): avatar animé seulement pendant le déplacement (5 s)`

---

### Task 3: Composant `HeroWidget.vue`

**Files:**
- Create: `sites/artisans-shared/src/components/HeroWidget.vue`

**Interfaces:**
- Consumes: `getUserToken, fetchUserMe, getInventory, activateInventoryItem, resolveAvatarUrl, authEvents` (api.js), `useEnergy`, `useGeolocation` (`refreshPosition`), `groupInventoryByType` (Task 1)
- Produces: `<HeroWidget />` sans props, utilisé par MapView (Task 4)

- [ ] **Step 1: Créer le composant**

```vue
<template>
  <div class="hero-widget card">
    <div class="hero-ring">
      <svg class="hero-ring-svg" viewBox="0 0 76 76" aria-hidden="true">
        <circle class="ring-bg" cx="38" cy="38" r="35" />
        <circle class="ring-fill" cx="38" cy="38" r="35"
          :stroke-dasharray="circumference" :stroke-dashoffset="offset"
          transform="rotate(-90 38 38)" />
      </svg>
      <img :src="avatarUrl" class="hero-avatar" alt="" />
    </div>
    <div class="hero-level">Nv.{{ profile?.level ?? '–' }}</div>
    <div v-if="energyCurrent !== null" class="hero-energy" :title="regenLabel">
      <span>⚡</span>
      <div class="hero-energy-track">
        <div class="hero-energy-fill" :style="{ width: energyPercent + '%' }"></div>
      </div>
      <span class="hero-energy-num">{{ energyCurrent }}/{{ energy.max }}</span>
    </div>
    <div v-if="groups.length" class="hero-items">
      <button v-for="g in groups" :key="g.type" type="button" class="hero-item-chip"
        @click="askConfirm(g)">{{ g.emoji }}×{{ g.count }}</button>
    </div>

    <div v-if="confirmGroup" class="hero-confirm card">
      <p>{{ confirmText }}</p>
      <p v-if="consumeError" class="hero-error">{{ consumeError }}</p>
      <div class="hero-confirm-actions">
        <button type="button" class="btn btn-primary" :disabled="consuming" @click="consume">
          {{ consuming ? '…' : 'Confirmer' }}
        </button>
        <button type="button" class="btn btn-outline" :disabled="consuming" @click="confirmGroup = null">Annuler</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import {
  getUserToken, fetchUserMe, getInventory, activateInventoryItem,
  resolveAvatarUrl, authEvents,
} from '../api.js'
import { useEnergy } from '../composables/useEnergy.js'
import { useGeolocation } from '../composables/useGeolocation.js'
import { groupInventoryByType, ITEM_META } from '../utils/inventoryItems.js'

const CITY = import.meta.env.VITE_CITY_SLUG

const profile = ref(null)
const groups = ref([])
const confirmGroup = ref(null)
const consuming = ref(false)
const consumeError = ref('')

const { energy, current: energyCurrent, setEnergy } = useEnergy()
const { refresh: refreshPosition, position } = useGeolocation()

const circumference = 2 * Math.PI * 35
const xpPercent = computed(() => {
  if (!profile.value?.xp_needed) return 0
  return Math.min(100, (profile.value.xp / profile.value.xp_needed) * 100)
})
const offset = computed(() => circumference * (1 - xpPercent.value / 100))
const avatarUrl = computed(() => resolveAvatarUrl(profile.value?.avatar_url) || '/avatar/player-512.png')
const energyPercent = computed(() =>
  energy.value ? Math.min(100, (energyCurrent.value / energy.value.max) * 100) : 0)
const regenLabel = computed(() => {
  if (!energy.value?.next_energy_at || energyCurrent.value >= energy.value.max) return ''
  const mins = Math.max(1, Math.ceil((new Date(energy.value.next_energy_at).getTime() - Date.now()) / 60000))
  return `+5 dans ${mins} min`
})

const confirmText = computed(() => {
  if (!confirmGroup.value) return ''
  if (confirmGroup.value.type === 'energy_store') return "Consommer 1 Réserve d'énergie ? +30 ⚡"
  if (confirmGroup.value.type === 'boss_spawner') return 'Invoquer Affamer de Gaffe ici ?'
  return `Utiliser 1 ${confirmGroup.value.label} ?`
})

let isMounted = true
async function load() {
  const token = getUserToken()
  if (!token) return
  const [me, inv] = await Promise.all([fetchUserMe(token), getInventory()])
  if (!isMounted) return
  if (me.success) profile.value = me.data
  if (inv.success) groups.value = groupInventoryByType(inv.data || [])
}

function askConfirm(g) {
  consumeError.value = ''
  confirmGroup.value = g
}

async function consume() {
  const g = confirmGroup.value
  if (!g || consuming.value) return
  consuming.value = true
  consumeError.value = ''
  try {
    await refreshPosition()
    const pos = position.value
    if (!pos) {
      consumeError.value = 'Position GPS indisponible.'
      return
    }
    const res = await activateInventoryItem(g.firstId, { lat: pos.latitude, lng: pos.longitude, city: CITY })
    if (!res.success) {
      consumeError.value = res.error || "Échec de l'activation."
      return
    }
    if (res.data?.activated === 'energy_store' && res.data?.energy) setEnergy(res.data.energy)
    confirmGroup.value = null
    await load()
  } catch (e) {
    consumeError.value = 'Position GPS indisponible.'
  } finally {
    consuming.value = false
  }
}

onMounted(() => {
  load()
  authEvents.addEventListener('change', load)
})
onBeforeUnmount(() => {
  isMounted = false
  authEvents.removeEventListener('change', load)
})
</script>

<style scoped>
.hero-widget {
  position: absolute;
  top: 12px;
  left: 12px;
  z-index: 20;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 8px 10px;
  border-radius: 16px;
}
.hero-ring { position: relative; width: 60px; height: 60px; }
.hero-ring-svg { position: absolute; inset: 0; width: 60px; height: 60px; }
.hero-avatar {
  position: absolute; top: 5px; left: 5px;
  width: 50px; height: 50px; border-radius: 50%; object-fit: cover;
  background: var(--c-cream-2);
}
.ring-bg { fill: none; stroke: var(--c-border, #e5e2d8); stroke-width: 5; }
.ring-fill {
  fill: none; stroke: var(--c-gold, #C07A2E); stroke-width: 5;
  stroke-linecap: round; transition: stroke-dashoffset 0.6s ease;
}
.hero-level { font-size: 0.78rem; font-weight: 700; color: var(--c-text); }
.hero-energy { display: flex; align-items: center; gap: 4px; font-size: 0.72rem; }
.hero-energy-track { width: 52px; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; }
.hero-energy-fill { height: 100%; background: #f59e0b; transition: width 0.4s; }
.hero-energy-num { font-weight: 600; color: var(--c-text-2); }
.hero-items { display: flex; gap: 4px; flex-wrap: wrap; justify-content: center; }
.hero-item-chip {
  border: 1px solid var(--c-border);
  background: var(--c-cream-2);
  border-radius: 999px;
  padding: 2px 8px;
  font-size: 0.78rem;
  cursor: pointer;
}
.hero-item-chip:hover { background: var(--c-cream); }
.hero-confirm {
  position: absolute; top: 0; left: calc(100% + 8px);
  width: 200px; padding: 10px 12px; border-radius: 12px;
}
.hero-confirm p { margin: 0 0 8px; font-size: 0.85rem; }
.hero-error { color: #b91c1c; }
.hero-confirm-actions { display: flex; gap: 6px; }
.hero-confirm-actions .btn { padding: 4px 10px; font-size: 0.8rem; }
</style>
```

- [ ] **Step 2: Vérifier le build** — `npm run build` (livry) OK ; `npx vitest run` OK.

- [ ] **Step 3: Commit** — `feat(map): widget héros (avatar, XP, niveau, énergie, inventaire)`

---

### Task 4: Intégration MapView.vue (HeroWidget, EnergyBar retirée, défaut fp, bouton masqué)

**Files:**
- Modify: `sites/artisans-shared/src/views/MapView.vue`

**Interfaces:**
- Consumes: `<HeroWidget />` (Task 3)

- [ ] **Step 1: Remplacer EnergyBar par HeroWidget** — template : remplacer `<EnergyBar v-if="authenticated" />` par `<HeroWidget v-if="authenticated" />` ; script : remplacer l'import `EnergyBar` par `import HeroWidget from '../components/HeroWidget.vue'`.

- [ ] **Step 2: Défaut fp** — remplacer l'init de `mapMode` :

```js
const mapMode = ref(
  localStorage.getItem('map_mode')
  || (localStorage.getItem('map_3d') === '1' ? '3d' : 'fp')
)
```

- [ ] **Step 3: Bouton de changement de vue masqué par défaut** — ajouter :

```js
// Bouton 2D/3D/FP : masqué par défaut, réactivable dans /profil > Paramètres.
const showMapModeSwitch = ref(localStorage.getItem('map_mode_switch') === '1')
```

et sur le bouton `.map3d-fab` : `v-if="can3D && showMapModeSwitch"`.

- [ ] **Step 4: Build + tests** — `npm run build` OK, `npx vitest run` OK.

- [ ] **Step 5: Commit** — `feat(map): HUD héros intégré, vue FPS par défaut, bouton de vue masqué`

---

### Task 5: Section Paramètres dans UserProfile.vue

**Files:**
- Modify: `sites/artisans-shared/src/views/UserProfile.vue`

**Interfaces:**
- Produces: `localStorage.map_mode_switch` (`'1'` | absent), lu par MapView au montage

- [ ] **Step 1: Template** — ajouter avant la section « 🚪 Session » :

```html
<section class="profile-section card">
  <h2>⚙️ Paramètres</h2>
  <label class="setting-toggle">
    <input type="checkbox" v-model="showMapModeSwitch" @change="saveMapModeSwitch" />
    <span>Afficher le bouton de changement de vue (2D / 3D / 1ère pers.)</span>
  </label>
</section>
```

- [ ] **Step 2: Script** — ajouter :

```js
const showMapModeSwitch = ref(localStorage.getItem('map_mode_switch') === '1')
function saveMapModeSwitch() {
  if (showMapModeSwitch.value) localStorage.setItem('map_mode_switch', '1')
  else localStorage.removeItem('map_mode_switch')
}
```

- [ ] **Step 3: Style** — ajouter :

```css
.setting-toggle { display: flex; align-items: center; gap: 10px; cursor: pointer; }
```

- [ ] **Step 4: Build + tests** — OK.

- [ ] **Step 5: Commit** — `feat(profil): option pour réafficher le bouton de changement de vue`

---

### Task 6: Vérification finale + déploiement

- [ ] **Step 1** — `cd sites/artisans-shared && npm test` : tous les tests passent.
- [ ] **Step 2** — Vérifier le montage : `mount | grep /mnt/gandi` (le point réel est `/mnt/gandi`, pas `/home/tof/mnt/gandi`).
- [ ] **Step 3** — `for s in webiartisan-livry webiartisan-combs webiartisan-vert-saint-denis webiartisan-lieusaint; do make -C sites/$s push; done`
- [ ] **Step 4** — Pour chaque ville, comparer md5 du `index-*.js` local (`dist/assets/`) vs live (`https://artisans-<ville>.prigent.tech/` avec cache-buster) : OK partout sinon remonter + repousser.
- [ ] **Step 5** — Push git (GitHub + GitLab).

## Self-Review

- Couverture spec : A (Task 2), B (Tasks 1, 3, 4 step 1), C (Task 4 steps 2-3, Task 5), déploiement (Task 6) ✓
- Pas de placeholder ; commandes explicites ✓
- Cohérence : `groupInventoryByType` produit `{ type, emoji, label, count, firstId }`, consommé tel quel par HeroWidget ✓ ; `firstId` = l'item le plus ancien (ordre serveur) ✓
