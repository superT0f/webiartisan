<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { Map, NavigationControl, GeolocateControl, Marker, Popup } from 'maplibre-gl'
import 'maplibre-gl/dist/maplibre-gl.css'
import { useMapStyle } from '../composables/useMapStyle.js'
import { getPosition } from '../utils/flutterBridge.js'

const props = defineProps({
  center: { type: Array, default: () => [49.1081, -0.7658] },
  zoom: { type: Number, default: 14 },
  artisans: { type: Array, default: () => [] },
  pois: { type: Array, default: () => [] }
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

  map.value.on('load', () => {
    renderMarkers()
    centerOnUser()
  })
})

async function centerOnUser() {
  try {
    const isFlutter = typeof FlutterBridge !== 'undefined' && FlutterBridge.postMessage
    let position

    if (isFlutter) {
      position = await getPosition({ accuracy: 'best', timeout: 15000, maxAccuracy: 20 })
    } else if (navigator.geolocation) {
      position = await new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(
          pos => resolve({
            latitude: pos.coords.latitude,
            longitude: pos.coords.longitude,
            accuracy: pos.coords.accuracy
          }),
          reject,
          { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        )
      })
    } else {
      return
    }

    const { latitude, longitude, accuracy } = position
    if (!map.value || !latitude || !longitude) return

    map.value.flyTo({ center: [longitude, latitude], zoom: 16 })

    const el = document.createElement('div')
    el.className = 'user-location-marker'

    new Marker({ element: el, anchor: 'center' })
      .setLngLat([longitude, latitude])
      .addTo(map.value)

    map.value.addSource('user-accuracy', {
      type: 'geojson',
      data: {
        type: 'Feature',
        geometry: {
          type: 'Point',
          coordinates: [longitude, latitude]
        }
      }
    })

    map.value.addLayer({
      id: 'user-accuracy-circle',
      type: 'circle',
      source: 'user-accuracy',
      paint: {
        'circle-radius': {
          stops: [[10, accuracy / 30], [16, accuracy / 5], [20, accuracy / 2]],
          base: 2
        },
        'circle-color': '#3b82f6',
        'circle-opacity': 0.15,
        'circle-stroke-width': 1,
        'circle-stroke-color': '#3b82f6'
      }
    })
  } catch (err) {
    console.warn('[ImmersiveMap] Impossible d\'obtenir la position', err)
  }
}

onUnmounted(() => {
  markers.forEach(m => m.remove())
  map.value?.remove()
})

watch(() => props.artisans, renderMarkers, { deep: true })
watch(() => props.pois, renderMarkers, { deep: true })

function renderMarkers() {
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

  props.pois.forEach(p => {
    if (!p.latitude || !p.longitude) return
    const el = document.createElement('div')
    el.className = 'poi-marker'
    el.innerHTML = `<span>${poiIcon(p.type)}</span>`

    const popup = new Popup({ offset: 16 }).setHTML(
      `<strong>${p.name}</strong><br><span style="color:#666">${p.address || ''}</span>`
    )

    const marker = new Marker({ element: el, anchor: 'bottom' })
      .setLngLat([parseFloat(p.longitude), parseFloat(p.latitude)])
      .setPopup(popup)
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

function poiIcon(type) {
  const icons = {
    mairie: '🏛️', piscine: '🏊', bibliotheque: '📚', mediatheque: '📚',
    cinema: '🎬', dechetterie: '♻️', poste: '📬', supermarche: '🛒',
    transport: '🚌', ecole: '🏫', hopital: '🏥', pharmacie: '💊',
    parc: '🌳', eglise: '⛪', autre: '📍'
  }
  return icons[type] || '📍'
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
:deep(.poi-marker) {
  width: 34px;
  height: 34px;
  background: #1a73e8;
  border: 3px solid #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  cursor: pointer;
}
:deep(.poi-marker span) {
  font-size: 1rem;
}
:deep(.user-location-marker) {
  width: 16px;
  height: 16px;
  background: #3b82f6;
  border: 3px solid #fff;
  border-radius: 50%;
  box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
</style>
