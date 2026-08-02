<template>
  <!-- Teleport body : .map-view crée un stacking context (z-index 1) qui
       piégerait le widget sous le header AppNav (z-index 100). -->
  <Teleport to="body">
  <div class="hero-widget card">
    <div class="hero-ring">
      <svg class="hero-ring-svg" viewBox="0 0 76 76" aria-hidden="true">
        <circle class="ring-bg" cx="38" cy="38" r="35" />
        <circle class="ring-fill" cx="38" cy="38" r="35"
          :stroke-dasharray="circumference" :stroke-dashoffset="offset"
          transform="rotate(-90 38 38)" />
      </svg>
      <img :src="avatarUrl" class="hero-avatar" alt="" />
    </div>
    <div class="hero-level">Nv.{{ profile?.level ?? '–' }}</div>
    <div v-if="energyCurrent !== null && energy" class="hero-energy" :title="regenLabel">
      <span>⚡</span>
      <div class="hero-energy-track">
        <div class="hero-energy-fill" :style="{ width: energyPercent + '%' }"></div>
      </div>
      <span class="hero-energy-num">{{ energyCurrent }}/{{ energy.max }}</span>
    </div>
    <div v-if="groups.length" class="hero-items">
      <button v-for="g in groups" :key="g.type" type="button" class="hero-item-chip"
        @click="askConfirm(g)">{{ g.emoji }}×{{ g.count }}</button>
    </div>

    <div v-if="confirmGroup" class="hero-confirm card">
      <p>{{ confirmText }}</p>
      <p v-if="consumeError" class="hero-error">{{ consumeError }}</p>
      <div class="hero-confirm-actions">
        <button type="button" class="btn btn-primary" :disabled="consuming" @click="consume">
          {{ consuming ? '…' : 'Confirmer' }}
        </button>
        <button type="button" class="btn btn-outline" :disabled="consuming" @click="confirmGroup = null">Annuler</button>
      </div>
    </div>
  </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import {
  getUserToken, fetchUserMe, getInventory, activateInventoryItem,
  resolveAvatarUrl, authEvents, CITY_SLUG,
} from '../api.js'
import { useEnergy } from '../composables/useEnergy.js'
import { useGeolocation } from '../composables/useGeolocation.js'
import { groupInventoryByType } from '../utils/inventoryItems.js'

const profile = ref(null)
const groups = ref([])
const confirmGroup = ref(null)
const consuming = ref(false)
const consumeError = ref('')

const { energy, current: energyCurrent, setEnergy } = useEnergy()
const { refresh: refreshPosition } = useGeolocation()

const circumference = 2 * Math.PI * 35
const xpPercent = computed(() => {
  if (!profile.value?.xp_needed) return 0
  return Math.min(100, (profile.value.xp / profile.value.xp_needed) * 100)
})
const offset = computed(() => circumference * (1 - xpPercent.value / 100))
const avatarUrl = computed(() => resolveAvatarUrl(profile.value?.avatar_url) || '/avatar/player-512.png')
const energyPercent = computed(() =>
  energy.value ? Math.min(100, (energyCurrent.value / energy.value.max) * 100) : 0)
const regenLabel = computed(() => {
  if (!energy.value?.next_energy_at || energyCurrent.value >= energy.value.max) return ''
  const mins = Math.max(1, Math.ceil((new Date(energy.value.next_energy_at).getTime() - Date.now()) / 60000))
  return `+5 dans ${mins} min`
})

const confirmText = computed(() => {
  if (!confirmGroup.value) return ''
  if (confirmGroup.value.type === 'energy_store') return "Consommer 1 Réserve d'énergie ? +30 ⚡"
  if (confirmGroup.value.type === 'boss_spawner') return 'Invoquer Affamer de Gaffe ici ?'
  return `Utiliser 1 ${confirmGroup.value.label} ?`
})

let isMounted = true
async function load() {
  const token = getUserToken()
  if (!token) return
  const [me, inv] = await Promise.all([fetchUserMe(token), getInventory()])
  if (!isMounted) return
  if (me.success) profile.value = me.data
  if (inv.success) groups.value = groupInventoryByType(inv.data || [])
}

function askConfirm(g) {
  consumeError.value = ''
  confirmGroup.value = g
}

async function consume() {
  const g = confirmGroup.value
  if (!g || consuming.value) return
  consuming.value = true
  consumeError.value = ''
  try {
    const pos = await refreshPosition()
    if (!pos) {
      consumeError.value = 'Position GPS indisponible.'
      return
    }
    const res = await activateInventoryItem(g.firstId, { lat: pos.latitude, lng: pos.longitude, city: CITY_SLUG })
    if (!res.success) {
      consumeError.value = res.error || "Échec de l'activation."
      return
    }
    if (res.data?.activated === 'energy_store' && res.data?.energy) setEnergy(res.data.energy)
    confirmGroup.value = null
    await load()
  } catch (e) {
    consumeError.value = 'Position GPS indisponible.'
  } finally {
    consuming.value = false
  }
}

onMounted(() => {
  load()
  authEvents.addEventListener('change', load)
})
onBeforeUnmount(() => {
  isMounted = false
  authEvents.removeEventListener('change', load)
})
</script>

<style scoped>
.hero-widget {
  position: fixed;
  top: 12px;
  left: 12px;
  z-index: 110;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 8px 10px;
  border-radius: 16px;
}
.hero-ring { position: relative; width: 60px; height: 60px; }
.hero-ring-svg { position: absolute; inset: 0; width: 60px; height: 60px; }
.hero-avatar {
  position: absolute; top: 5px; left: 5px;
  width: 50px; height: 50px; border-radius: 50%; object-fit: cover;
  background: var(--c-cream-2);
}
.ring-bg { fill: none; stroke: var(--c-border, #e5e2d8); stroke-width: 5; }
.ring-fill {
  fill: none; stroke: var(--c-gold, #C07A2E); stroke-width: 5;
  stroke-linecap: round; transition: stroke-dashoffset 0.6s ease;
}
.hero-level { font-size: 0.78rem; font-weight: 700; color: var(--c-text); }
.hero-energy { display: flex; align-items: center; gap: 4px; font-size: 0.72rem; }
.hero-energy-track { width: 52px; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; }
.hero-energy-fill { height: 100%; background: #f59e0b; transition: width 0.4s; }
.hero-energy-num { font-weight: 600; color: var(--c-text-2); }
.hero-items { display: flex; gap: 4px; flex-wrap: wrap; justify-content: center; }
.hero-item-chip {
  border: 1px solid var(--c-border);
  background: var(--c-cream-2);
  border-radius: 999px;
  padding: 2px 8px;
  font-size: 0.78rem;
  cursor: pointer;
}
.hero-item-chip:hover { background: var(--c-cream); }
.hero-confirm {
  position: absolute; top: 0; left: calc(100% + 8px);
  width: 200px; padding: 10px 12px; border-radius: 12px;
}
.hero-confirm p { margin: 0 0 8px; font-size: 0.85rem; }
.hero-error { color: #b91c1c; }
.hero-confirm-actions { display: flex; gap: 6px; }
.hero-confirm-actions .btn { padding: 4px 10px; font-size: 0.8rem; }
</style>
