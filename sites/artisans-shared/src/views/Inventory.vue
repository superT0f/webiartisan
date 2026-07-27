<script setup>
import { ref, onMounted } from 'vue'
import { getUserToken, getInventory, activateInventoryItem, CITY_SLUG } from '../api.js'
import { useGeolocation } from '../composables/useGeolocation.js'
import { useEnergy } from '../composables/useEnergy.js'
import { useGamification } from '../composables/useGamification.js'
import AuthForm from '../components/AuthForm.vue'

const authenticated = ref(!!getUserToken())
const items = ref([])
const loading = ref(true)
const activatingId = ref(null)

const { refresh: refreshPosition } = useGeolocation()
const { setEnergy } = useEnergy()
const { showToast } = useGamification()

const ITEM_META = {
  boss_spawner: { icon: '🎯', desc: 'Invoque Affamer de Gaffe près de ta position (il reste 2 h).' },
  energy_store: { icon: '🔋', desc: '+30 ⚡ d\'énergie immédiatement.' },
}

onMounted(async () => {
  if (!authenticated.value) { loading.value = false; return }
  await loadItems()
})

async function loadItems() {
  loading.value = true
  const res = await getInventory()
  if (res.success) items.value = res.data || []
  loading.value = false
}

async function onAuthenticated() {
  authenticated.value = true
  await loadItems()
}

async function activate(item) {
  if (activatingId.value) return
  activatingId.value = item.id
  try {
    const pos = await refreshPosition()
    if (!pos) {
      showToast('Position indisponible — active le GPS pour utiliser un objet')
      return
    }
    const res = await activateInventoryItem(item.id, {
      lat: pos.latitude,
      lng: pos.longitude,
      city: CITY_SLUG,
    })
    if (res.success) {
      items.value = items.value.filter(i => i.id !== item.id)
      if (res.data.activated === 'boss_spawner') {
        showToast('🎩 Affamer de Gaffe surgit près de toi — ouvre la carte !')
      } else if (res.data.activated === 'energy_store') {
        setEnergy(res.data.energy)
        showToast(`+${res.data.amount} ⚡ énergie`)
      }
    } else {
      showToast(res.error || 'Activation impossible')
    }
  } finally {
    activatingId.value = null
  }
}
</script>

<template>
  <div class="inventory-page page">
    <h1>🎒 Inventaire</h1>

    <template v-if="!authenticated">
      <p class="hint">Connecte-toi pour voir ton inventaire.</p>
      <AuthForm @authenticated="onAuthenticated" />
    </template>

    <template v-else>
      <p class="hint">Les objets spéciaux ramassés sur la carte se rangent ici. Active-les quand tu en as besoin.</p>

      <p v-if="loading" class="hint">Chargement…</p>

      <div v-else-if="items.length" class="inventory-list">
        <div v-for="item in items" :key="item.id" class="inventory-item card">
          <span class="item-icon">{{ ITEM_META[item.type]?.icon || '❓' }}</span>
          <div class="item-body">
            <strong>{{ item.label }}</strong>
            <small>{{ ITEM_META[item.type]?.desc || '' }}</small>
          </div>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="activatingId === item.id"
            @click="activate(item)"
          >{{ activatingId === item.id ? 'Activation…' : 'Activer' }}</button>
        </div>
      </div>

      <p v-else class="hint">Inventaire vide. Ramasse des objets spéciaux (🎯 🔋) sur la carte !</p>

      <RouterLink to="/carte" class="btn btn-outline back-link">🗺️ Retour à la carte</RouterLink>
    </template>
  </div>
</template>

<style scoped>
.inventory-page {
  max-width: 640px;
  margin: 0 auto;
  padding: 24px 16px 48px;
}
.hint { color: var(--c-text-2); font-size: 0.9rem; }
.inventory-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin: 20px 0;
}
.inventory-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
}
.item-icon { font-size: 1.8rem; }
.item-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.item-body small { color: var(--c-text-2); }
.back-link { display: inline-block; margin-top: 8px; }
</style>
