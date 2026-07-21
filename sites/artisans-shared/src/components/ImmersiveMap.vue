<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { Map, NavigationControl, GeolocateControl, Marker, Popup } from 'maplibre-gl'
import 'maplibre-gl/dist/maplibre-gl.css'
import { useMapStyle, isMapTilerKey, terrainTilesUrl } from '../composables/useMapStyle.js'
import { escapeHtml } from '@/utils/escapeHtml.js'

const props = defineProps({
  center: { type: Array, default: () => [-0.7658, 49.1081] },
  zoom: { type: Number, default: 14 },
  artisans: { type: Array, default: () => [] },
  pois: { type: Array, default: () => [] },
  objects: { type: Array, default: () => [] },
  userPosition: { type: Object, default: null },
  halo: { type: Boolean, default: false }
})

const emit = defineEmits(['select', 'map-click', 'ready'])
const mapEl = ref(null)
const map = ref(null)
const markers = []
const has3D = ref(false)
const is3D = ref(false)

onMounted(async () => {
  const key = import.meta.env.VITE_MAPTILER_KEY
  map.value = new Map({
    container: mapEl.value,
    style: useMapStyle(key),
    center: props.center,
    zoom: props.zoom,
    maxPitch: 70,
    attributionControl: false
  })

  map.value.addControl(new NavigationControl({ visualizePitch: true }), 'bottom-right')
  map.value.addControl(
    new GeolocateControl({ positionOptions: { enableHighAccuracy: true }, trackUserLocation: true }),
    'bottom-right'
  )

  map.value.on('click', (e) => {
    emit('map-click', { latitude: e.lngLat.lat, longitude: e.lngLat.lng })
  })

  map.value.on('load', () => {
    setup3D(key)
    renderMarkers()
    upsertUserPosition()
    emit('ready')
  })
})

/**
 * Prépare la 3D (bâtiments + terrain) si le style est vectoriel (clé MapTiler).
 * Les couches sont ajoutées masquées : c'est set3D(true) qui les active.
 */
function setup3D(key) {
  if (!isMapTilerKey(key) || !map.value) return
  const style = map.value.getStyle()
  const vectorSourceId = Object.keys(style.sources).find(id => style.sources[id].type === 'vector')
  if (!vectorSourceId) return

  map.value.addLayer({
    id: 'buildings-3d',
    type: 'fill-extrusion',
    source: vectorSourceId,
    'source-layer': 'building',
    minzoom: 14,
    layout: { visibility: 'none' },
    paint: {
      'fill-extrusion-color': '#d6cfc4',
      'fill-extrusion-height': ['coalesce', ['get', 'render_height'], ['get', 'height'], 8],
      'fill-extrusion-base': ['coalesce', ['get', 'render_min_height'], 0],
      'fill-extrusion-opacity': 0.85
    }
  })
  map.value.addSource('terrain-dem', { type: 'raster-dem', url: terrainTilesUrl(key) })
  has3D.value = true
}

/** Bascule 2D/3D : bâtiments extrudés, terrain, caméra inclinée à 55°. */
function set3D(enabled) {
  if (!map.value || !has3D.value || !map.value.getLayer('buildings-3d')) return
  is3D.value = enabled
  map.value.setLayoutProperty('buildings-3d', 'visibility', enabled ? 'visible' : 'none')
  map.value.setTerrain(enabled ? { source: 'terrain-dem', exaggeration: 1.3 } : null)
  map.value.easeTo({ pitch: enabled ? 55 : 0, duration: 800 })
}

defineExpose({ set3D, has3D, is3D })

let userMarker = null
const USER_POS_SOURCE = 'user-pos'

function upsertUserPosition() {
  if (!map.value?.isStyleLoaded?.()) return
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
watch(() => props.objects, renderMarkers, { deep: true })

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

  props.objects.forEach(o => {
    if (!o.lat || !o.lng) return
    const el = document.createElement('div')
    el.className = `object-marker object-marker--${o.type}`
    el.innerHTML = `<span>${objectIcon(o.type)}</span>`

    const popup = new Popup({ offset: 12 }).setHTML(
      `<div class="map-popup object-popup">
        <strong>${objectIcon(o.type)} ${escapeHtml(o.label)}</strong>
        <span class="popup-type">+${o.xp} XP${o.energy_cost > 0 ? ` · ⚡${o.energy_cost}` : ' · gratuit'}</span>
        <span class="popup-address">${o.distance_m} m${o.distance_m <= 50 ? ' — à portée !' : ''}</span>
      </div>`
    )

    const marker = new Marker({ element: el, anchor: 'center' })
      .setLngLat([parseFloat(o.lng), parseFloat(o.lat)])
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

function objectIcon(type) {
  const icons = {
    dechet: '🗑️',
    canette: '🍾',
    papier: '📰',
    tresor: '💎',
    cadeau_artisan: '🎁'
  }
  return icons[type] || '❓'
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
:deep(.object-marker) {
  width: 30px;
  height: 30px;
  background: rgba(255,255,255,0.92);
  border: 2px solid #d4a017;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(0,0,0,0.25);
  cursor: pointer;
}
:deep(.object-marker span) { font-size: 1rem; }
:deep(.object-marker--tresor) {
  border-color: #7c3aed;
  animation: treasure-pulse 1.6s ease-in-out infinite;
}
:deep(.object-marker--cadeau_artisan) { border-color: #e11d48; }
@keyframes treasure-pulse {
  0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(124,58,237,0.5); }
  50% { transform: scale(1.15); box-shadow: 0 0 0 8px rgba(124,58,237,0); }
}
:deep(.user-location-marker) {
  width: 16px;
  height: 16px;
  background: #3b82f6;
  border: 3px solid #fff;
  border-radius: 50%;
  box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

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
