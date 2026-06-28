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
