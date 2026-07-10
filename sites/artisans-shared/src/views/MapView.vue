<script setup>
import { ref, onMounted } from 'vue'
import ImmersiveMap from '../components/ImmersiveMap.vue'
import ArtisanSheet from '../components/ArtisanSheet.vue'
import { fetchArtisans } from '../api.js'

const artisans = ref([])
const selected = ref(null)
const loading = ref(true)

onMounted(async () => {
  const res = await fetchArtisans({ limit: 200 })
  artisans.value = res.data || []
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
    <ImmersiveMap :artisans="artisans" @select="openSheet" />
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
