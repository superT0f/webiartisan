<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ImmersiveMap from '../components/ImmersiveMap.vue'
import ArtisanSheet from '../components/ArtisanSheet.vue'
import MapWeatherBadge from '../components/MapWeatherBadge.vue'
import CheckinButton from '../components/CheckinButton.vue'
import GameOverlay from '../components/GameOverlay.vue'
import AuthForm from '../components/AuthForm.vue'
import GameRenderer from '../components/GameRenderer.vue'
import {
  fetchArtisans, fetchCityPois, fetchGames,
  getUserToken, setUserToken, authUser, authEvents,
  postCheckin, getCheckinStatus,
  CITY_LAT, CITY_LNG,
} from '../api.js'
import { useWeather } from '../composables/useWeather.js'
import { useGeolocation, haversineM } from '../composables/useGeolocation.js'
import { useGamification } from '../composables/useGamification.js'

const route = useRoute()
const router = useRouter()

const artisans = ref([])
const pois = ref([])
const games = ref([])
const selected = ref(null)
const loading = ref(true)
const { weather, load: loadWeather } = useWeather(CITY_LAT, CITY_LNG)
const { position, start: startGeolocation, stop: stopGeolocation } = useGeolocation()
const { showToast } = useGamification()

const userToken = ref(getUserToken())
const statusTargets = ref([])
const checkinLoading = ref(false)
const overlay = ref(null) // null | 'coupon' | 'auth'

const categoryFilter = ref('')
const poiTypeFilter = ref('')
const showArtisans = ref(true)
const showPois = ref(true)

const authenticated = computed(() => !!userToken.value)

const categories = computed(() => {
  const map = {}
  artisans.value.forEach(a => {
    if (!a.category_slug) return
    if (!map[a.category_slug]) {
      map[a.category_slug] = { slug: a.category_slug, name: a.category_name || a.category_slug }
    }
  })
  return Object.values(map).sort((a, b) => a.name.localeCompare(b.name, 'fr'))
})

const poiTypes = computed(() => {
  const map = {}
  pois.value.forEach(p => {
    if (!p.type) return
    if (!map[p.type]) map[p.type] = { type: p.type, name: p.type }
  })
  return Object.values(map).sort((a, b) => a.name.localeCompare(b.name, 'fr'))
})

const filteredArtisans = computed(() => {
  if (!showArtisans.value) return []
  if (!categoryFilter.value) return artisans.value
  return artisans.value.filter(a => a.category_slug === categoryFilter.value)
})

const filteredPois = computed(() => {
  if (!showPois.value) return []
  if (!poiTypeFilter.value) return pois.value
  return pois.value.filter(p => p.type === poiTypeFilter.value)
})

const gameByArtisan = computed(() => {
  const map = {}
  for (const g of games.value) map[Number(g.artisan_id)] = g
  return map
})

const selectedGame = computed(() => {
  if (!selected.value) return null
  return gameByArtisan.value[Number(selected.value.id)] || null
})

// Nearest in-range point (server sorts by distance) — drives the floating button
const nearestTarget = computed(() => statusTargets.value[0] || null)

// Check-in state for the artisan shown in the sheet
const selectedCheckin = computed(() => {
  if (!selected.value || !position.value) return null
  const distanceM = Math.round(haversineM(
    position.value.latitude, position.value.longitude,
    Number(selected.value.latitude), Number(selected.value.longitude)
  ))
  const st = statusTargets.value.find(
    t => t.target_type === 'artisan' && t.target_id === Number(selected.value.id)
  )
  return {
    inRange: distanceM <= 200,
    distanceM,
    dailyAvailable: st ? st.daily_available : null,
    nextSpinAt: st ? st.next_spin_at : null,
  }
})

let lastStatusAt = 0
watch(position, () => {
  const now = Date.now()
  if (now - lastStatusAt < 5000) return
  lastStatusAt = now
  refreshStatus()
})

async function refreshStatus() {
  if (!position.value) return
  const res = await getCheckinStatus(position.value.latitude, position.value.longitude)
  if (res.success) statusTargets.value = res.data || []
}

async function doCheckin(targetType, targetId) {
  if (!authenticated.value) {
    overlay.value = 'auth'
    return
  }
  if (!position.value) {
    showToast('Position indisponible')
    return
  }
  checkinLoading.value = true
  const res = await postCheckin({
    target_type: targetType,
    target_id: targetId,
    lat: position.value.latitude,
    lng: position.value.longitude,
  })
  checkinLoading.value = false

  if (res.success) {
    showToast(`+${res.data.xp_awarded} XP`)
    if (res.data.level_up) showToast('Niveau supérieur !')
    for (const b of res.data.new_badges || []) showToast(`Badge débloqué : ${b.name}`)
    await refreshStatus()
  } else if (res.status === 401) {
    overlay.value = 'auth'
  } else if (res.status === 429) {
    showToast('Point en recharge, réessayez dans quelques minutes')
  } else if (res.status === 422) {
    showToast(`Trop loin du point (${res.data?.distance_m ?? '?'} m, 200 m max)`)
  } else {
    showToast(res.error || 'Check-in impossible')
  }
}

function onFabCheckin(target) {
  doCheckin(target.target_type, target.target_id)
}

function onSheetCheckin() {
  if (!selected.value) return
  doCheckin('artisan', Number(selected.value.id))
}

function openSheet(artisan) { selected.value = artisan }
function closeSheet() { selected.value = null }

function navigate(artisan) {
  const url = `https://www.google.com/maps/dir/?api=1&destination=${artisan.latitude},${artisan.longitude}`
  window.open(url, '_blank')
}

async function exchangeMagicToken() {
  if (!route.query.token) return
  try {
    const res = await authUser(route.query.token, true)
    if (res.success && res.token) {
      setUserToken(res.token, true)
      userToken.value = res.token
      showToast('Connexion réussie !')
    } else {
      showToast(res.error || 'Lien invalide')
    }
  } catch (e) {
    showToast('Erreur réseau.')
  } finally {
    router.replace({ path: '/carte' })
  }
}

function onAuthChange() {
  userToken.value = getUserToken()
}

onMounted(async () => {
  authEvents.addEventListener('change', onAuthChange)
  await exchangeMagicToken()

  await loadWeather()
  const [artRes, poiRes] = await Promise.all([
    fetchArtisans({ limit: 200 }),
    fetchCityPois(),
  ])
  artisans.value = artRes.data || []
  pois.value = poiRes.data || []
  try {
    const gamesRes = await fetchGames()
    games.value = gamesRes.data || []
  } catch (e) {
    games.value = []
  }
  loading.value = false

  startGeolocation()
})

onUnmounted(() => {
  authEvents.removeEventListener('change', onAuthChange)
  stopGeolocation()
})
</script>

<template>
  <div class="map-view">
    <ImmersiveMap :artisans="filteredArtisans" :pois="filteredPois" @select="openSheet" />

    <div class="map-controls card">
      <div v-if="loading" class="loading-bar">Chargement de la carte…</div>
      <template v-else>
        <div class="control-row">
          <label class="toggle">
            <input v-model="showArtisans" type="checkbox" />
            <span>Artisans</span>
          </label>
          <select v-model="categoryFilter" :disabled="!showArtisans" class="form-select">
            <option value="">Toutes les catégories</option>
            <option v-for="cat in categories" :key="cat.slug" :value="cat.slug">{{ cat.name }}</option>
          </select>
        </div>
        <div class="control-row">
          <label class="toggle">
            <input v-model="showPois" type="checkbox" />
            <span>Services publics</span>
          </label>
          <select v-model="poiTypeFilter" :disabled="!showPois" class="form-select">
            <option value="">Tous les types</option>
            <option v-for="t in poiTypes" :key="t.type" :value="t.type">{{ t.name }}</option>
          </select>
        </div>
      </template>
    </div>

    <MapWeatherBadge :weather="weather" />

    <CheckinButton :target="nearestTarget" :loading="checkinLoading" @checkin="onFabCheckin" />

    <ArtisanSheet
      :artisan="selected"
      :game="selectedGame"
      :checkin-state="selectedCheckin"
      :authenticated="authenticated"
      @close="closeSheet"
      @navigate="navigate"
      @checkin="onSheetCheckin"
      @play-coupon="overlay = 'coupon'"
    />

    <GameOverlay v-if="overlay === 'coupon'" :title="selectedGame?.title || 'Coupon'" @close="overlay = null">
      <AuthForm v-if="!authenticated" />
      <GameRenderer
        v-else-if="selectedGame"
        :instance-id="selectedGame.id"
        :game-type="selectedGame.game_type_key"
        :config="selectedGame.config"
        @played="showToast('🎁 Coupon débloqué !')"
      />
    </GameOverlay>

    <GameOverlay v-if="overlay === 'auth'" title="Connexion" @close="overlay = null">
      <AuthForm />
    </GameOverlay>
  </div>
</template>

<style scoped>
.map-view {
  position: fixed;
  inset: 0;
  z-index: 1;
}
.map-controls {
  position: absolute;
  top: 84px;
  left: 24px;
  z-index: 10;
  padding: 16px;
  min-width: 260px;
  max-width: 320px;
  box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}
.control-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}
.control-row:last-child { margin-bottom: 0; }
.toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}
.toggle input { width: 18px; height: 18px; cursor: pointer; }
.form-select {
  flex: 1;
  padding: 8px 10px;
  border: 1px solid var(--c-border);
  border-radius: 8px;
  font-size: 0.9rem;
  background: #fff;
}
.loading-bar { font-size: 0.9rem; color: var(--c-text-2); }

@media (max-width: 600px) {
  .map-controls {
    top: 72px;
    left: 12px;
    right: 12px;
    max-width: none;
    min-width: auto;
    padding: 12px;
  }
  .control-row { flex-direction: column; align-items: flex-start; gap: 6px; }
  .form-select { width: 100%; }
}
</style>
