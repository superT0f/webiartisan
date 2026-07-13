<template>
  <div class="prospects-view section">
    <div class="container">
      <div class="section-header">
        <div>
          <h1>Prospection locale</h1>
          <p class="text-muted">Commerces et établissements à contacter autour de {{ CITY_NAME }}.</p>
        </div>
        <div class="view-toggle">
          <button
            class="btn btn-outline btn-sm"
            :class="{ active: viewMode === 'list' }"
            @click="viewMode = 'list'"
          >
            📋 Liste
          </button>
          <button
            class="btn btn-outline btn-sm"
            :class="{ active: viewMode === 'map' }"
            @click="switchToMap"
          >
            🗺️ Carte
          </button>
        </div>
      </div>

      <div class="filters">
        <div class="search-bar filter-search">
          <span class="search-icon">🔍</span>
          <input
            v-model="search"
            type="text"
            class="search-input"
            placeholder="Rechercher un prospect..."
          />
        </div>
        <select v-model="selectedZone" class="form-select">
          <option value="">Toutes les zones</option>
          <option v-for="z in zones" :key="z" :value="z">{{ z }}</option>
        </select>
        <select v-model="selectedType" class="form-select">
          <option value="">Tous les types</option>
          <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
        </select>
      </div>

      <div v-if="loading" class="skeleton" style="height: 300px; border-radius: 12px;"></div>

      <template v-else>
        <div v-show="viewMode === 'list'" class="prospect-list">
          <RouterLink
            v-for="p in filtered"
            :key="p.id"
            :to="`/prospect/${p.id}`"
            class="card prospect-card"
          >
            <div class="prospect-body">
              <h3>{{ p.name }}</h3>
              <div class="prospect-meta">
                <span class="badge badge-grey">{{ p.type }}</span>
                <span v-if="p.zone" class="badge badge-green">{{ p.zone }}</span>
              </div>
              <p v-if="p.pitch" class="prospect-pitch">{{ p.pitch }}</p>
              <p v-if="p.address" class="text-muted small">{{ p.address }}</p>
            </div>
          </RouterLink>

          <div v-if="!filtered.length" class="empty-state">
            <div class="empty-icon">🔍</div>
            <h3>Aucun prospect trouvé</h3>
            <p>Essayez d'élargir vos critères de recherche.</p>
          </div>
        </div>

        <div v-show="viewMode === 'map'" class="map-wrap">
          <div ref="mapEl" class="map-container"></div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { escapeHtml } from '@/utils/escapeHtml.js'
import { getProspects, CITY_NAME, CITY_LAT, CITY_LNG } from '../api.js'

const prospects = ref([])
const viewMode = ref('list')
const search = ref('')
const selectedZone = ref('')
const selectedType = ref('')
const loading = ref(true)
const mapEl = ref(null)
const map = ref(null)
const markers = ref([])
const router = useRouter()

const zones = computed(() => [...new Set(prospects.value.map(p => p.zone).filter(Boolean))].sort())
const types = computed(() => [...new Set(prospects.value.map(p => p.type).filter(Boolean))].sort())

const filtered = computed(() => {
  const q = search.value.toLowerCase().trim()
  return prospects.value.filter(p => {
    const matchesSearch = !q ||
      (p.name || '').toLowerCase().includes(q) ||
      (p.type || '').toLowerCase().includes(q) ||
      (p.zone || '').toLowerCase().includes(q)
    const matchesZone = !selectedZone.value || p.zone === selectedZone.value
    const matchesType = !selectedType.value || p.type === selectedType.value
    return matchesSearch && matchesZone && matchesType
  })
})

async function switchToMap() {
  viewMode.value = 'map'
  await nextTick()
  if (!map.value) initMap()
  updateMarkers()
}

function initMap() {
  if (!mapEl.value) return
  map.value = L.map(mapEl.value).setView([CITY_LAT, CITY_LNG], 14)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map.value)
  updateMarkers()
}

function updateMarkers() {
  if (!map.value) return
  markers.value.forEach(m => map.value.removeLayer(m))
  markers.value = []
  filtered.value.forEach(p => {
    const lat = parseFloat(p.latitude)
    const lng = parseFloat(p.longitude)
    if (isNaN(lat) || isNaN(lng)) return
    const marker = L.marker([lat, lng]).addTo(map.value)
    marker.bindPopup(`<b>${escapeHtml(p.name)}</b><br>${escapeHtml(p.type)}`)
    marker.on('click', () => router.push(`/prospect/${p.id}`))
    markers.value.push(marker)
  })
}

watch(filtered, () => {
  if (viewMode.value === 'map') updateMarkers()
})

onMounted(async () => {
  try {
    const res = await getProspects()
    prospects.value = res.data || []
  } catch (e) {
    console.error('Erreur chargement prospects', e)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.prospects-view { min-height: 60vh; }
.section-header { margin-bottom: 24px; align-items: flex-start; gap: 16px; }
.view-toggle { display: flex; gap: 8px; }
.view-toggle .active { background: var(--c-green); color: var(--c-white); border-color: var(--c-green); }

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 28px;
  align-items: center;
}
.filter-search { flex: 1 1 260px; max-width: 360px; margin-bottom: 0; }
.filters select { width: auto; min-width: 160px; }

.prospect-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
.prospect-card { display: flex; cursor: pointer; text-decoration: none; color: inherit; }
.prospect-card:hover { transform: translateY(-4px); }
.prospect-body { padding: 20px; }
.prospect-body h3 { margin-bottom: 10px; }
.prospect-meta { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
.prospect-pitch { color: var(--c-text-2); font-size: 0.95rem; margin-bottom: 10px; }
.small { font-size: 0.85rem; }

.map-wrap { border-radius: var(--r-lg); overflow: hidden; border: 1px solid var(--c-border); }
.map-container { height: 520px; width: 100%; }

.empty-state { text-align: center; padding: 60px 20px; grid-column: 1 / -1; }
.empty-icon { font-size: 3rem; margin-bottom: 16px; }

@media (max-width: 768px) {
  .section-header { flex-direction: column; }
  .filters select { width: 100%; }
  .map-container { height: 360px; }
}
</style>
