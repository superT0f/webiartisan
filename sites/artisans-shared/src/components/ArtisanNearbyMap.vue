<script setup>
import { ref, onMounted, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { escapeHtml } from '@/utils/escapeHtml.js';

const props = defineProps({
  artisan: { type: Object, required: true },
  nearby: { type: Array, default: () => [] }
});

const mapEl = ref(null);
const map = ref(null);
const nearbyLayer = ref(null);

onMounted(() => {
  const lat = parseFloat(props.artisan.latitude);
  const lng = parseFloat(props.artisan.longitude);
  map.value = L.map(mapEl.value).setView([lat, lng], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map.value);

  nearbyLayer.value = L.layerGroup().addTo(map.value);

  L.circleMarker([lat, lng], {
    radius: 9, color: '#16a34a', fillColor: '#16a34a', fillOpacity: 0.9
  })
    .addTo(map.value)
    .bindPopup(`<b>${escapeHtml(props.artisan.company_name)}</b>`);

  renderNearby();
});

watch(() => props.nearby, renderNearby, { deep: true });

function renderNearby() {
  if (!map.value || !nearbyLayer.value) return;
  nearbyLayer.value.clearLayers();
  props.nearby.forEach(place => {
    const color = place.kind === 'prospect' ? '#f97316' : '#3b82f6';
    const marker = L.circleMarker(
      [parseFloat(place.latitude), parseFloat(place.longitude)],
      { radius: 7, color, fillColor: color, fillOpacity: 0.7 }
    );
    marker.bindPopup(`<b>${escapeHtml(place.name)}</b><br>${escapeHtml(place.type)}`);
    nearbyLayer.value.addLayer(marker);
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
