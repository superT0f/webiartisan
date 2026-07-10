<script setup>
import { ref, onMounted } from 'vue'
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

function openSheet(artisan) { selected.value = artisan }
function closeSheet() { selected.value = null }

function navigate(artisan) {
  const url = `https://www.google.com/maps/dir/?api=1&destination=${artisan.latitude},${artisan.longitude}`
  window.open(url, '_blank')
}
</script>

<template>
  <div class="map-view">
    <ImmersiveMap :artisans="artisans" :pois="pois" @select="openSheet" />
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
</style>
