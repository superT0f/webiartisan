<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { Map, NavigationControl, GeolocateControl, Marker } from 'maplibre-gl'
import 'maplibre-gl/dist/maplibre-gl.css'
import { useMapStyle } from '../composables/useMapStyle.js'

const props = defineProps({
  center: { type: Array, default: () => [49.1081, -0.7658] },
  zoom: { type: Number, default: 14 },
  artisans: { type: Array, default: () => [] }
})

const emit = defineEmits(['select'])
const mapEl = ref(null)
const map = ref(null)
const markers = []

onMounted(async () => {
  const key = import.meta.env.VITE_MAPTILER_KEY
  map.value = new Map({
    container: mapEl.value,
    style: useMapStyle(key),
    center: props.center,
    zoom: props.zoom,
    attributionControl: false
  })

  map.value.addControl(new NavigationControl(), 'bottom-right')
  map.value.addControl(
    new GeolocateControl({ positionOptions: { enableHighAccuracy: true }, trackUserLocation: true }),
    'bottom-right'
  )

  map.value.on('load', renderArtisans)
})

onUnmounted(() => {
  markers.forEach(m => m.remove())
  map.value?.remove()
})

watch(() => props.artisans, renderArtisans, { deep: true })

function renderArtisans() {
  if (!map.value) return
  markers.forEach(m => m.remove())
  markers.length = 0

  props.artisans.forEach(a => {
    if (!a.latitude || !a.longitude) return
    const el = document.createElement('div')
    el.className = 'artisan-marker'
    el.innerHTML = `<span>${categoryIcon(a.category_slug)}</span>`
    el.addEventListener('click', () => emit('select', a))

    const marker = new Marker({ element: el, anchor: 'bottom' })
      .setLngLat([parseFloat(a.longitude), parseFloat(a.latitude)])
      .addTo(map.value)
    markers.push(marker)
  })
}

function categoryIcon(slug) {
  const map = {
    boulangerie: '🥖',
    coiffure: '✂️',
    plombier: '🚿',
    couture: '🧵',
    restaurant: '🍽️',
    default: '🏪'
  }
  return map[slug] || map.default
}
</script>

<template>
  <div ref="mapEl" class="immersive-map"></div>
</template>

<style scoped>
.immersive-map {
  width: 100%;
  height: 100%;
  min-height: 100vh;
}
:deep(.artisan-marker) {
  width: 40px;
  height: 40px;
  background: #fff;
  border: 3px solid #16a34a;
  border-radius: 50% 50% 50% 0;
  transform: rotate(-45deg);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  cursor: pointer;
}
:deep(.artisan-marker span) {
  transform: rotate(45deg);
  font-size: 1.2rem;
}
</style>
