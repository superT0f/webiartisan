<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { Map, NavigationControl, GeolocateControl, Marker, Popup } from 'maplibre-gl'
import 'maplibre-gl/dist/maplibre-gl.css'
import { useMapStyle } from '../composables/useMapStyle.js'
import { getPosition } from '../utils/flutterBridge.js'
import { escapeHtml } from '@/utils/escapeHtml.js'

const props = defineProps({
  center: { type: Array, default: () => [49.1081, -0.7658] },
  zoom: { type: Number, default: 14 },
  artisans: { type: Array, default: () => [] },
  pois: { type: Array, default: () => [] },
  userPosition: { type: Object, default: null },
  halo: { type: Boolean, default: false }
})

const emit = defineEmits(['select', 'map-click'])
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

  map.value.on('click', (e) => {
    emit('map-click', { latitude: e.lngLat.lat, longitude: e.lngLat.lng })
  })

  map.value.on('load', () => {
    renderMarkers()
    centerOnUser()
    upsertUserPosition()
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
  } catch (err) {
    console.warn('[ImmersiveMap] Impossible d\'obtenir la position', err)
  }
}

let userMarker = null
const USER_POS_SOURCE = 'user-pos'

function upsertUserPosition() {
  if (!map.value?.isStyleLoaded?.()) return
  if (!map.value) return
  const pos = props.userPosition
  if (!pos || !pos.latitude || !pos.longitude) {
    if (userMarker) { userMarker.remove(); userMarker = null }
    if (map.value.getSource(USER_POS_SOURCE)) {
      map.value.getSource(USER_POS_SOURCE).setData({ type: 'FeatureCollection', features: [] })
    }
    return
  }
  const lngLat = [pos.longitude, pos.latitude]
  if (!userMarker) {
    const el = document.createElement('div')
    el.className = 'user-location-marker'
    userMarker = new Marker({ element: el, anchor: 'center' }).setLngLat(lngLat).addTo(map.value)
  } else {
    userMarker.setLngLat(lngLat)
  }
  const data = { type: 'Feature', geometry: { type: 'Point', coordinates: lngLat } }
  if (map.value.getSource(USER_POS_SOURCE)) {
    map.value.getSource(USER_POS_SOURCE).setData(data)
  } else {
    map.value.addSource(USER_POS_SOURCE, { type: 'geojson', data })
    map.value.addLayer({
      id: 'user-accuracy-circle',
      type: 'circle',
      source: USER_POS_SOURCE,
      paint: {
        'circle-radius': { stops: [[10, (pos.accuracy || 20) / 30], [16, (pos.accuracy || 20) / 5], [20, (pos.accuracy || 20) / 2]], base: 2 },
        'circle-color': '#3b82f6',
        'circle-opacity': 0.15,
        'circle-stroke-width': 1,
        'circle-stroke-color': '#3b82f6'
      }
    })
    map.value.addLayer({
      id: 'admin-halo',
      type: 'circle',
      source: USER_POS_SOURCE,
      layout: { visibility: props.halo ? 'visible' : 'none' },
      paint: {
        'circle-radius': { stops: [[10, 200 / 30], [16, 200 / 5], [20, 200 / 2]], base: 2 },
        'circle-color': '#C07A2E',
        'circle-opacity': 0.12,
        'circle-stroke-width': 2,
        'circle-stroke-color': '#C07A2E'
      }
    })
  }
}

function syncHalo() {
  if (!map.value || !map.value.getLayer('admin-halo')) return
  map.value.setLayoutProperty('admin-halo', 'visibility', props.halo ? 'visible' : 'none')
}

watch(() => props.userPosition, upsertUserPosition, { deep: true })
watch(() => props.halo, syncHalo)

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
    el.innerHTML = `<span>${categoryIcon(a.category_slug)}</span>${a.has_active_game ? '<i class="marker-gift">🎁</i>' : ''}`

    const popupHtml = `
      <div class="map-popup artisan-popup">
        <strong>${escapeHtml(a.company_name)}</strong>
        <span class="popup-category">${escapeHtml(a.category_name || 'Artisan')}</span>
        ${a.address ? `<span class="popup-address">${escapeHtml(a.address)}</span>` : ''}
        ${a.phone ? `<span class="popup-phone">📞 ${escapeHtml(a.phone)}</span>` : ''}
        <div class="popup-actions">
          <a href="#/artisan/${a.id}" class="popup-link">Voir la fiche</a>
          <a href="https://www.google.com/maps/dir/?api=1&destination=${a.latitude},${a.longitude}" target="_blank" rel="noopener" class="popup-link">Itinéraire</a>
        </div>
      </div>
    `
    const popup = new Popup({ offset: 16 }).setHTML(popupHtml)

    el.addEventListener('click', () => emit('select', a))

    const marker = new Marker({ element: el, anchor: 'bottom' })
      .setLngLat([parseFloat(a.longitude), parseFloat(a.latitude)])
      .setPopup(popup)
      .addTo(map.value)
    markers.push(marker)
  })

  props.pois.forEach(p => {
    if (!p.latitude || !p.longitude) return
    const el = document.createElement('div')
    el.className = 'poi-marker'
    el.innerHTML = `<span>${poiIcon(p.type)}</span>`

    const scheduleInfo = p.schedules?.length
      ? `<br><small>${escapeHtml(formatSchedules(p.schedules))}</small>`
      : ''

    const popup = new Popup({ offset: 16 }).setHTML(
      `<div class="map-popup poi-popup">
        <strong>${escapeHtml(p.name)}</strong>
        <span class="popup-type">${escapeHtml(p.type)}</span>
        ${p.address ? `<span class="popup-address">${escapeHtml(p.address)}</span>` : ''}
        ${p.phone ? `<span class="popup-phone">📞 ${escapeHtml(p.phone)}</span>` : ''}
        ${scheduleInfo}
      </div>`
    )

    const marker = new Marker({ element: el, anchor: 'bottom' })
      .setLngLat([parseFloat(p.longitude), parseFloat(p.latitude)])
      .setPopup(popup)
      .addTo(map.value)
    markers.push(marker)
  })
}

function formatSchedules(schedules) {
  const days = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']
  return schedules
    .filter(s => !s.is_closed)
    .map(s => `${days[s.day_of_week] || '?'} ${s.open_time?.slice(0, 5)}–${s.close_time?.slice(0, 5)}`)
    .join(' · ')
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
  <div class="map-legend">
    <div class="legend-title">Légende</div>
    <div class="legend-item"><span class="legend-dot artisan"></span> Artisans</div>
    <div class="legend-item"><span class="legend-dot poi"></span> Services publics</div>
    <div class="legend-item"><span class="legend-dot user"></span> Votre position</div>
  </div>
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
:deep(.marker-gift) {
  position: absolute;
  top: -10px;
  right: -12px;
  transform: rotate(45deg);
  font-size: 0.95rem;
  font-style: normal;
  filter: drop-shadow(0 1px 2px rgba(0,0,0,0.4));
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

.map-legend {
  position: absolute;
  bottom: 24px;
  left: 24px;
  z-index: 10;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 12px;
  padding: 14px 16px;
  box-shadow: 0 4px 16px rgba(0,0,0,0.12);
  font-size: 0.85rem;
  min-width: 140px;
}
.legend-title {
  font-weight: 700;
  margin-bottom: 8px;
  color: var(--c-text);
}
.legend-item {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
  color: var(--c-text-2);
}
.legend-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}
.legend-dot.artisan { background: #16a34a; }
.legend-dot.poi { background: #1a73e8; }
.legend-dot.user { background: #3b82f6; }

:deep(.map-popup) {
  font-family: inherit;
  min-width: 180px;
}
:deep(.map-popup strong) {
  display: block;
  font-size: 1rem;
  margin-bottom: 4px;
  color: var(--c-text);
}
:deep(.map-popup .popup-category),
:deep(.map-popup .popup-type) {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--c-green);
  margin-bottom: 6px;
}
:deep(.map-popup .popup-type) { color: #1a73e8; }
:deep(.map-popup .popup-address),
:deep(.map-popup .popup-phone) {
  display: block;
  font-size: 0.85rem;
  color: var(--c-text-2);
  margin-bottom: 2px;
}
:deep(.map-popup .popup-actions) {
  display: flex;
  gap: 8px;
  margin-top: 10px;
}
:deep(.map-popup .popup-link) {
  font-size: 0.8rem;
  font-weight: 600;
  color: #fff;
  background: var(--c-green);
  padding: 6px 10px;
  border-radius: 6px;
  text-decoration: none;
}
:deep(.map-popup .popup-link:last-child) {
  background: #1a73e8;
}
</style>
