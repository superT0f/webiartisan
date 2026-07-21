<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import ImmersiveMap from '../components/ImmersiveMap.vue'
import ArtisanSheet from '../components/ArtisanSheet.vue'
import MapWeatherBadge from '../components/MapWeatherBadge.vue'
import ActionButton from '../components/ActionButton.vue'
import GameOverlay from '../components/GameOverlay.vue'
import AuthForm from '../components/AuthForm.vue'
import GameRenderer from '../components/GameRenderer.vue'
import SpinOverlay from '../components/games/SpinOverlay.vue'
import {
  fetchArtisans, fetchCityPois, fetchGames,
  getUserToken, setUserToken, removeUserToken, authEvents,
  getArtisanToken, fetchMe, fetchUserMe, fetchArtisanConsumerToken,
  postCheckin, getCheckinStatus, postMessageToFlutter,
  haptic, shareText,
  CITY_LAT, CITY_LNG, CITY_NAME,
  pickupObject,
} from '../api.js'
import { useWeather } from '../composables/useWeather.js'
import { useGeolocation, haversineM } from '../composables/useGeolocation.js'
import { useGamification } from '../composables/useGamification.js'
import { useWorldObjects } from '../composables/useWorldObjects.js'
import { useEnergy } from '../composables/useEnergy.js'
import { pickMapAction } from '../utils/pickMapAction.js'

const artisans = ref([])
const pois = ref([])
const games = ref([])
const selected = ref(null)
const loading = ref(true)
const { weather, load: loadWeather } = useWeather(CITY_LAT, CITY_LNG)
const { position, start: startGeolocation, stop: stopGeolocation, refresh: refreshPosition } = useGeolocation()
const { showToast } = useGamification()

const userToken = ref(getUserToken())
const isAdmin = ref(false)
const adminHalo = ref(true)
const mockPosition = ref(null)
const teleportArmed = ref(false)

const effectivePosition = computed(() => mockPosition.value || position.value)
const statusTargets = ref([])
const checkinLoading = ref(false)
const overlay = ref(null) // null | 'coupon' | 'spin' | 'auth'
const { objects: worldObjects, cityCleanliness, fetchNearby, removeObject } = useWorldObjects()
const { setEnergy } = useEnergy()
const pickupLoading = ref(false)

const authenticated = computed(() => !!userToken.value)

const gameByArtisan = computed(() => {
  const map = {}
  for (const g of games.value) {
    if (g.game_type_key !== 'coupon') continue
    map[Number(g.artisan_id)] = g
  }
  return map
})

const selectedGame = computed(() => {
  if (!selected.value) return null
  return gameByArtisan.value[Number(selected.value.id)] || null
})

// Nearest in-range point (server sorts by distance) — drives the floating button
const nearestTarget = computed(() => statusTargets.value[0] || null)
const fabAction = computed(() => pickMapAction(worldObjects.value, nearestTarget.value))

// Check-in state for the artisan shown in the sheet
const selectedCheckin = computed(() => {
  if (!selected.value || !effectivePosition.value) return null
  const distanceM = Math.round(haversineM(
    effectivePosition.value.latitude, effectivePosition.value.longitude,
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
let statusTimer = null
watch(effectivePosition, () => {
  const now = Date.now()
  const elapsed = now - lastStatusAt
  if (elapsed >= 5000) {
    lastStatusAt = now
    refreshStatus()
  } else if (!statusTimer) {
    // Trailing : ne pas perdre le dernier déplacement pendant la fenêtre de throttle
    statusTimer = setTimeout(() => {
      statusTimer = null
      lastStatusAt = Date.now()
      refreshStatus()
    }, 5000 - elapsed)
  }
})

async function refreshStatus() {
  if (!effectivePosition.value) return
  const res = await getCheckinStatus(effectivePosition.value.latitude, effectivePosition.value.longitude)
  if (res.success) statusTargets.value = res.data || []
  if (authenticated.value) {
    await fetchNearby(effectivePosition.value.latitude, effectivePosition.value.longitude)
  }
}

async function doCheckin(targetType, targetId, retried = false) {
  if (!authenticated.value) {
    // Pont artisan → joueur avant d'ouvrir la popin de connexion
    const artisanToken = getArtisanToken()
    if (!retried && artisanToken) {
      await bridgeConsumerIfNeeded(artisanToken)
      if (authenticated.value) return doCheckin(targetType, targetId, true)
    }
    overlay.value = 'auth'
    return
  }
  if (!effectivePosition.value) {
    showToast('Position indisponible')
    return
  }
  checkinLoading.value = true
  // Rafraîchir la position avant d'agir : la watch peut être périmée et
  // fausser la cible et la distance (sauf si position fictive admin active)
  if (!mockPosition.value) {
    await refreshPosition()
    await refreshStatus()
  }
  const res = await postCheckin({
    target_type: targetType,
    target_id: targetId,
    lat: effectivePosition.value.latitude,
    lng: effectivePosition.value.longitude,
  })
  checkinLoading.value = false

  if (res.success) {
    haptic('medium')
    showToast(`+${res.data.xp_awarded} XP`)
    if (res.data.level_up) {
      haptic('heavy')
      showToast('Niveau supérieur !')
    }
    for (const b of res.data.new_badges || []) showToast(`Badge débloqué : ${b.name}`)
    if (res.data.energy_bonus) showToast(`+${res.data.energy_bonus} ⚡ énergie`)
    if (res.data.energy) setEnergy(res.data.energy)
    for (const q of res.data.quests_completed || []) showToast(`Quête terminée : ${q.label}`)
    await refreshStatus()
  } else if (res.status === 401) {
    // Session consommateur invalide : tenter le pont artisan → joueur une fois
    const artisanToken = getArtisanToken()
    if (!retried && artisanToken) {
      await bridgeConsumerIfNeeded(artisanToken, true)
      if (getUserToken()) return doCheckin(targetType, targetId, true)
    }
    overlay.value = 'auth'
  } else if (res.status === 429) {
    showToast(res.code === 'rate_limited'
      ? 'Trop de requêtes, réessayez dans une minute'
      : 'Point en recharge, réessayez dans quelques minutes')
  } else if (res.status === 422) {
    showToast(`Trop loin du point (${res.data?.distance_m ?? '?'} m, 200 m max)`)
  } else {
    showToast(res.error || 'Check-in impossible')
  }
}

async function doPickup(object, retried = false) {
  if (!authenticated.value) {
    const artisanToken = getArtisanToken()
    if (!retried && artisanToken) {
      await bridgeConsumerIfNeeded(artisanToken)
      if (authenticated.value) return doPickup(object, true)
    }
    overlay.value = 'auth'
    return
  }
  if (!effectivePosition.value) {
    showToast('Position indisponible')
    return
  }
  pickupLoading.value = true
  if (!mockPosition.value) {
    await refreshPosition()
  }
  const res = await pickupObject(object.id, effectivePosition.value.latitude, effectivePosition.value.longitude)
  pickupLoading.value = false

  if (res.success) {
    haptic('medium')
    showToast(`+${res.data.xp_awarded} XP`)
    removeObject(object.id)
    setEnergy(res.data.energy)
    if (res.data.level_up) {
      haptic('heavy')
      showToast('Niveau supérieur !')
    }
    for (const b of res.data.new_badges || []) showToast(`Badge débloqué : ${b.name}`)
    for (const q of res.data.quests_completed || []) showToast(`Quête terminée : ${q.label}`)
    await refreshStatus()
  } else if (res.status === 401) {
    const artisanToken = getArtisanToken()
    if (!retried && artisanToken) {
      await bridgeConsumerIfNeeded(artisanToken, true)
      if (getUserToken()) return doPickup(object, true)
    }
    overlay.value = 'auth'
  } else if (res.status === 410) {
    showToast('Trop tard, un voisin l\'a eu !')
    removeObject(object.id)
  } else if (res.status === 422 && res.error === 'energy') {
    setEnergy(res.data?.energy)
    showToast('Plus d\'énergie — fais un check-in chez un artisan pour en récupérer')
  } else if (res.status === 422) {
    showToast(`Trop loin de l'objet (${res.data?.distance_m ?? '?'} m, 50 m max)`)
  } else {
    showToast(res.error || 'Ramassage impossible')
  }
}

function onFabAct(action) {
  if (action.kind === 'pickup') {
    doPickup(action.object)
  } else {
    doCheckin(action.target.target_type, action.target.target_id)
  }
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

function onAuthChange() {
  userToken.value = getUserToken()
}

async function loadAdminStatus() {
  const artisanToken = getArtisanToken()
  if (!artisanToken) return
  try {
    const res = await fetchMe(artisanToken)
    if (res.success) isAdmin.value = res.data.is_admin === 1 || res.data.is_admin === true
  } catch (e) {
    console.warn('Admin status check failed', e)
  }
  // Valider le token consommateur existant : s'il est périmé, le purger
  // pour déclencher le pont artisan → joueur immédiatement.
  const existing = getUserToken()
  if (existing) {
    try {
      const check = await fetchUserMe(existing)
      if (check.status === 401) removeUserToken()
    } catch (e) { /* tolérance réseau : on conserve le token */ }
  }
  await bridgeConsumerIfNeeded(artisanToken)
}

// Pont artisan → compte joueur : un artisan connecté ne doit pas voir la
// popin de connexion pour jouer/check-in si son token consommateur manque.
// Le token frais est aussi poussé vers l'app Flutter (remplace un token mort
// côté AuthService, sinon il serait ré-injecté au prochain chargement).
async function bridgeConsumerIfNeeded(artisanToken, force = false) {
  if (!force && getUserToken()) return
  try {
    const res = await fetchArtisanConsumerToken(artisanToken)
    if (res.success && res.data?.token) {
      setUserToken(res.data.token, true)
      userToken.value = res.data.token
      postMessageToFlutter('set-token', { token: res.data.token })
    } else {
      // DEBUG temporaire : remonter la cause exacte de l'échec du pont
      showToast(`Pont joueur KO: ${res.error || 'statut ' + res.status}`)
    }
  } catch (e) {
    showToast(`Pont joueur KO: ${e.message || 'réseau'}`)
    console.warn('Consumer bridge failed', e)
  }
}

function armTeleport() {
  teleportArmed.value = true
  showToast('Cliquez sur la carte pour définir votre position')
}

function onMapClick(pos) {
  if (!teleportArmed.value) return
  mockPosition.value = { latitude: pos.latitude, longitude: pos.longitude, accuracy: 5 }
  teleportArmed.value = false
  showToast('Position fictive définie')
}

function resetPosition() {
  mockPosition.value = null
  showToast('Position réelle restaurée')
}

function onCouponPlayed(data) {
  showToast(data?.reward ? '🎁 Coupon débloqué !' : '+10 XP, merci d\'avoir joué !')
}

function onShareArtisan(artisan) {
  const url = `${window.location.origin}/artisan/${artisan.id}`
  shareText(`Découvre ${artisan.company_name}, artisan à ${CITY_NAME} :\n${url}`, `Artisan à ${CITY_NAME}`)
}

function onCouponShare(reward) {
  const artisanName = selected.value?.company_name || 'un artisan local'
  const code = reward?.reward_value?.code ? ` (code ${reward.reward_value.code})` : ''
  shareText(`J'ai gagné : ${reward?.label || 'un coupon'} chez ${artisanName}${code} 🎁\n${window.location.origin}/carte`, 'Mon coupon WebiArtisan')
}

// Pull-to-refresh : tirer vers le bas depuis le haut de l'écran recharge la page
const PULL_ZONE_Y = 100
const PULL_TRIGGER = 90
let pullStartY = null

function onTouchStart(e) {
  const t = e.touches[0]
  pullStartY = (t.clientY < PULL_ZONE_Y && window.scrollY === 0) ? t.clientY : null
}
function onTouchMove(e) {
  if (pullStartY === null) return
  if (e.touches[0].clientY - pullStartY > PULL_TRIGGER) {
    pullStartY = null
    showToast('Actualisation…')
    setTimeout(() => window.location.reload(), 300)
  }
}
function onTouchEnd() { pullStartY = null }

onMounted(async () => {
  authEvents.addEventListener('change', onAuthChange)
  document.addEventListener('touchstart', onTouchStart, { passive: true })
  document.addEventListener('touchmove', onTouchMove, { passive: true })
  document.addEventListener('touchend', onTouchEnd, { passive: true })
  loadAdminStatus()

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
  document.removeEventListener('touchstart', onTouchStart)
  document.removeEventListener('touchmove', onTouchMove)
  document.removeEventListener('touchend', onTouchEnd)
  stopGeolocation()
})
</script>

<template>
  <div class="map-view">
    <ImmersiveMap
      :artisans="artisans"
      :pois="pois"
      :objects="worldObjects"
      :center="[CITY_LNG, CITY_LAT]"
      :user-position="effectivePosition"
      :halo="isAdmin && adminHalo"
      @select="openSheet"
      @map-click="onMapClick"
    />

    <div v-if="loading" class="loading-chip card">Chargement de la carte…</div>

    <div v-if="isAdmin" class="admin-controls card">
      <div class="control-row">
        <label class="toggle">
          <input v-model="adminHalo" type="checkbox" />
          <span>🛡️ Halo 200 m</span>
        </label>
        <button v-if="!mockPosition" type="button" class="btn btn-outline btn-sm" :disabled="teleportArmed" @click="armTeleport">
          {{ teleportArmed ? 'Cliquez sur la carte…' : '📍 Déplacer ma position' }}
        </button>
        <button v-else type="button" class="btn btn-outline btn-sm" @click="resetPosition">↩︎ Position réelle</button>
      </div>
    </div>

    <MapWeatherBadge :weather="weather" />

    <ActionButton :action="fabAction" :loading="checkinLoading || pickupLoading" @act="onFabAct" />

    <ArtisanSheet
      :artisan="selected"
      :game="selectedGame"
      :checkin-state="selectedCheckin"
      :authenticated="authenticated"
      @close="closeSheet"
      @navigate="navigate"
      @checkin="onSheetCheckin"
      @play-coupon="overlay = 'coupon'"
      @play-spin="overlay = 'spin'"
      @share="onShareArtisan"
    />

    <GameOverlay v-if="overlay === 'coupon'" :title="selectedGame?.title || 'Coupon'" @close="overlay = null">
      <AuthForm v-if="!authenticated" />
      <GameRenderer
        v-else-if="selectedGame"
        :instance-id="selectedGame.id"
        :game-type="selectedGame.game_type_key"
        :config="selectedGame.config"
        @played="onCouponPlayed"
        @share="onCouponShare"
      />
    </GameOverlay>

    <SpinOverlay v-if="overlay === 'spin'" :artisan="selected" @close="overlay = null" />

    <GameOverlay v-if="overlay === 'auth'" title="Connexion" @close="overlay = null">
      <AuthForm @authenticated="overlay = null" />
    </GameOverlay>
  </div>
</template>

<style scoped>
.map-view {
  position: fixed;
  inset: 0;
  z-index: 1;
}
.loading-chip {
  position: absolute;
  top: 84px;
  left: 24px;
  z-index: 10;
  padding: 10px 16px;
  font-size: 0.9rem;
  color: var(--c-text-2);
}
.admin-controls {
  position: absolute;
  top: 84px;
  left: 24px;
  z-index: 10;
  padding: 12px 16px;
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

@media (max-width: 600px) {
  .loading-chip, .admin-controls {
    top: 72px;
    left: 12px;
    right: 12px;
  }
  .control-row { flex-direction: column; align-items: flex-start; gap: 6px; }
}
</style>
