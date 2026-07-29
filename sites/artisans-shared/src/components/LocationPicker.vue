<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { Map, NavigationControl, Marker } from 'maplibre-gl'
import 'maplibre-gl/dist/maplibre-gl.css'
import { useMapStyle } from '../composables/useMapStyle.js'
import { useGeolocation } from '../composables/useGeolocation.js'
import { CITY_LAT, CITY_LNG } from '../api.js'

const props = defineProps({
  lat: { type: Number, default: null },
  lng: { type: Number, default: null },
})
const emit = defineEmits(['pick'])

const mapEl = ref(null)
const map = ref(null)
let marker = null

const { refresh: refreshPosition } = useGeolocation()

function upsertMarker(lat, lng, { fly = false } = {}) {
  if (!map.value || lat == null || lng == null) return
  const lngLat = [lng, lat]
  if (!marker) {
    const el = document.createElement('div')
    el.className = 'location-picker-marker'
    marker = new Marker({ element: el, anchor: 'bottom' }).setLngLat(lngLat).addTo(map.value)
  } else {
    marker.setLngLat(lngLat)
  }
  if (fly) map.value.easeTo({ center: lngLat, zoom: Math.max(map.value.getZoom(), 16), duration: 500 })
}

onMounted(() => {
  map.value = new Map({
    container: mapEl.value,
    style: useMapStyle(import.meta.env.VITE_MAPTILER_KEY),
    center: props.lng != null && props.lat != null ? [props.lng, props.lat] : [CITY_LNG, CITY_LAT],
    zoom: props.lat != null ? 16 : 14,
    attributionControl: false,
  })
  map.value.addControl(new NavigationControl(), 'bottom-right')
  if (props.lat != null && props.lng != null) upsertMarker(props.lat, props.lng)

  map.value.on('click', (e) => {
    const latitude = Math.round(e.lngLat.lat * 1e7) / 1e7
    const longitude = Math.round(e.lngLat.lng * 1e7) / 1e7
    upsertMarker(latitude, longitude)
    emit('pick', { latitude, longitude })
  })

  // La carte est dans un modal : forcer le recalcul de taille après affichage
  setTimeout(() => map.value?.resize(), 150)
})

onUnmounted(() => { marker = null; map.value?.remove() })

// Saisie manuelle dans les champs → le marqueur suit
watch(() => [props.lat, props.lng], ([lat, lng]) => {
  if (lat != null && lng != null) upsertMarker(lat, lng)
})

async function useMyPosition() {
  const pos = await refreshPosition()
  if (!pos) return
  upsertMarker(pos.latitude, pos.longitude, { fly: true })
  emit('pick', { latitude: pos.latitude, longitude: pos.longitude })
}
</script>

<template>
  <div class="location-picker">
    <div ref="mapEl" class="location-picker-map"></div>
    <button type="button" class="btn btn-outline btn-sm" @click="useMyPosition">
      📍 Définir sur ma position actuelle
    </button>
  </div>
</template>

<style scoped>
.location-picker { display: flex; flex-direction: column; gap: 8px; }
.location-picker-map { width: 100%; height: 240px; border-radius: 12px; overflow: hidden; border: 1px solid var(--c-border); }
:deep(.location-picker-marker) {
  width: 26px;
  height: 26px;
  background: var(--c-green);
  border: 3px solid #fff;
  border-radius: 50% 50% 50% 0;
  transform: rotate(-45deg);
  box-shadow: 0 2px 8px rgba(0,0,0,0.35);
}
</style>
