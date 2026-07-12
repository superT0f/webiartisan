<script setup>
import { ref, computed, onMounted } from 'vue'
import ImmersiveMap from '../components/ImmersiveMap.vue'
import ArtisanSheet from '../components/ArtisanSheet.vue'
import MapWeatherBadge from '../components/MapWeatherBadge.vue'
import { fetchArtisans, fetchCityPois, CITY_LAT, CITY_LNG } from '../api.js'
import { useWeather } from '../composables/useWeather.js'

const artisans = ref([])
const pois = ref([])
const selected = ref(null)
const loading = ref(true)
const { weather, load: loadWeather } = useWeather(CITY_LAT, CITY_LNG)

const categoryFilter = ref('')
const poiTypeFilter = ref('')
const showArtisans = ref(true)
const showPois = ref(true)

onMounted(async () => {
  await loadWeather()
  const [artRes, poiRes] = await Promise.all([
    fetchArtisans({ limit: 200 }),
    fetchCityPois(),
  ])
  artisans.value = artRes.data || []
  pois.value = poiRes.data || []
  loading.value = false
})

const categories = computed(() => {
  const map = {}
  artisans.value.forEach(a => {
    if (!a.category_slug) return
    if (!map[a.category_slug]) {
      map[a.category_slug] = { slug: a.category_slug, name: a.category_name || a.category_slug }
    }
  })
  return Object.values(map).sort((a, b) => a.name.localeCompare(b.name, 'fr'))
})

const poiTypes = computed(() => {
  const map = {}
  pois.value.forEach(p => {
    if (!p.type) return
    if (!map[p.type]) map[p.type] = { type: p.type, name: p.type }
  })
  return Object.values(map).sort((a, b) => a.name.localeCompare(b.name, 'fr'))
})

const filteredArtisans = computed(() => {
  if (!showArtisans.value) return []
  if (!categoryFilter.value) return artisans.value
  return artisans.value.filter(a => a.category_slug === categoryFilter.value)
})

const filteredPois = computed(() => {
  if (!showPois.value) return []
  if (!poiTypeFilter.value) return pois.value
  return pois.value.filter(p => p.type === poiTypeFilter.value)
})

function openSheet(artisan) { selected.value = artisan }
function closeSheet() { selected.value = null }

function navigate(artisan) {
  const url = `https://www.google.com/maps/dir/?api=1&destination=${artisan.latitude},${artisan.longitude}`
  window.open(url, '_blank')
}
</script>

<template>
  <div class="map-view">
    <ImmersiveMap :artisans="filteredArtisans" :pois="filteredPois" @select="openSheet" />

    <div class="map-controls card">
      <div v-if="loading" class="loading-bar">Chargement de la carte…</div>
      <template v-else>
        <div class="control-row">
          <label class="toggle">
            <input v-model="showArtisans" type="checkbox" />
            <span>Artisans</span>
          </label>
          <select v-model="categoryFilter" :disabled="!showArtisans" class="form-select">
            <option value="">Toutes les catégories</option>
            <option v-for="cat in categories" :key="cat.slug" :value="cat.slug">{{ cat.name }}</option>
          </select>
        </div>
        <div class="control-row">
          <label class="toggle">
            <input v-model="showPois" type="checkbox" />
            <span>Services publics</span>
          </label>
          <select v-model="poiTypeFilter" :disabled="!showPois" class="form-select">
            <option value="">Tous les types</option>
            <option v-for="t in poiTypes" :key="t.type" :value="t.type">{{ t.name }}</option>
          </select>
        </div>
      </template>
    </div>

    <MapWeatherBadge :weather="weather" />
    <ArtisanSheet :artisan="selected" @close="closeSheet" @navigate="navigate" />
  </div>
</template>

<style scoped>
.map-view {
  position: fixed;
  inset: 0;
  z-index: 1;
}
.map-controls {
  position: absolute;
  top: 84px;
  left: 24px;
  z-index: 10;
  padding: 16px;
  min-width: 260px;
  max-width: 320px;
  box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}
.control-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}
.control-row:last-child { margin-bottom: 0; }
.toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}
.toggle input { width: 18px; height: 18px; cursor: pointer; }
.form-select {
  flex: 1;
  padding: 8px 10px;
  border: 1px solid var(--c-border);
  border-radius: 8px;
  font-size: 0.9rem;
  background: #fff;
}
.loading-bar { font-size: 0.9rem; color: var(--c-text-2); }

@media (max-width: 600px) {
  .map-controls {
    top: 72px;
    left: 12px;
    right: 12px;
    max-width: none;
    min-width: auto;
    padding: 12px;
  }
  .control-row { flex-direction: column; align-items: flex-start; gap: 6px; }
  .form-select { width: 100%; }
}
</style>
