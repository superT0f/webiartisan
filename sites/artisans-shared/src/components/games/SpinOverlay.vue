<template>
  <GameOverlay title="🎁 Tournez pour gagner" @close="$emit('close')">
    <div class="sr-only" role="status" aria-live="polite">{{ winAnnouncement }}</div>

    <AuthForm v-if="!token" />

    <div v-else-if="loading" class="spin-loading">Chargement…</div>

    <template v-else>
      <div v-if="alreadySpun" class="spin-message">
        <h3>Vous avez déjà tourné aujourd'hui 🎉</h3>
        <p>Revenez demain pour une nouvelle chance.</p>
      </div>

      <div v-else class="pokestop-wrap">
        <div class="pokestop" :class="{ spinning: spinning }">
          <img v-if="avatarUrl" :src="avatarUrl" :alt="avatarAlt" class="pokestop-avatar" />
          <div v-else class="pokestop-fallback" aria-hidden="true">🛍️</div>
        </div>
        <p v-if="artisan?.company_name" class="pokestop-name">{{ artisan.company_name }}</p>
        <button class="btn btn-primary btn-lg" @click="spin" :disabled="spinning || fetchingSpin">
          {{ fetchingSpin ? 'Chargement…' : (spinning ? 'Ça tourne…' : '🌀 Tourner') }}
        </button>
      </div>

      <div v-if="showResult" class="result-card">
        <h3 ref="resultHeading" tabindex="-1">🎁 Vous avez gagné</h3>
        <div class="offer-label">{{ result.label }}</div>
        <p v-if="result.description" class="text-muted">{{ result.description }}</p>
        <p><strong>Artisan :</strong> {{ result.artisan_name }}</p>
        <div class="qr-wrap">
          <canvas ref="qrCanvas" aria-hidden="true"></canvas>
          <div class="code">{{ result.code }}</div>
        </div>
        <p class="text-muted small">Valide jusqu'au {{ formatDate(result.expires_at) }}</p>
      </div>

      <div v-if="wins.length" class="wins-card">
        <h3>Mes gains</h3>
        <div v-for="w in wins" :key="w.id" class="win-row">
          <div>
            <strong>{{ w.label }}</strong>
            <span class="badge" :class="'status-' + w.status">{{ statusLabel[w.status] }}</span>
          </div>
          <div class="code">{{ w.code }}</div>
          <div class="text-muted small">Expire le {{ formatDate(w.expires_at) }}</div>
        </div>
      </div>

      <p v-if="message" class="auth-message" :class="messageType" role="status">{{ message }}</p>
    </template>
  </GameOverlay>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import QRCode from 'qrcode'
import GameOverlay from '../GameOverlay.vue'
import AuthForm from '../AuthForm.vue'
import {
  getUserToken, removeUserToken, authEvents,
  postSpin, getSpinWins,
} from '../../api.js'

const props = defineProps({
  artisan: { type: Object, default: null },
})
defineEmits(['close'])

const avatarUrl = computed(() => props.artisan?.logo_url || '')
const avatarAlt = computed(() =>
  props.artisan?.company_name ? `Boutique ${props.artisan.company_name}` : 'Boutique'
)

function parisTodayIso() {
  const paris = new Intl.DateTimeFormat('fr-FR', {
    timeZone: 'Europe/Paris',
    year: 'numeric', month: '2-digit', day: '2-digit',
  }).formatToParts(new Date())
  const part = (type) => paris.find(p => p.type === type).value
  return `${part('year')}-${part('month')}-${part('day')}`
}

function toParisIso(iso) {
  const [datePart] = String(iso).split('T')
  const [y, m, d] = datePart.split('-').map(Number)
  if (!y || !m || !d) return null
  const dt = new Date(Date.UTC(y, m - 1, d, 12, 0, 0))
  const parts = new Intl.DateTimeFormat('fr-FR', {
    timeZone: 'Europe/Paris',
    year: 'numeric', month: '2-digit', day: '2-digit',
  }).formatToParts(dt)
  const part = (type) => parts.find(p => p.type === type).value
  return `${part('year')}-${part('month')}-${part('day')}`
}

function formatDate(iso) {
  if (!iso) return '—'
  const parts = toParisIso(iso)
  if (!parts) return '—'
  const [y, m, d] = parts.split('-')
  return `${d}/${m}/${y}`
}

const token = ref(getUserToken() || '')
const wins = ref([])
const loadingWins = ref(false)
const loading = computed(() => loadingWins.value)
const spinning = ref(false)
const fetchingSpin = ref(false)
const result = ref(null)
const showResult = ref(false)
const message = ref('')
const messageType = ref('')
const qrCanvas = ref(null)
const resultHeading = ref(null)
const winAnnouncement = ref('')
let spinTimeout = null

const alreadySpun = computed(() => {
  const today = parisTodayIso()
  return wins.value.some(w => toParisIso(w.spin_date) === today && w.status !== 'expired')
})

const statusLabel = {
  pending: 'En attente',
  claimed: 'Utilisé',
  expired: 'Expiré',
}

function setMessage(text, type = 'info') {
  message.value = text
  messageType.value = type
}

function handleUnauthorized() {
  removeUserToken()
  token.value = ''
  wins.value = []
}

async function loadWins() {
  if (!token.value) return
  loadingWins.value = true
  const res = await getSpinWins(token.value)
  loadingWins.value = false
  if (!res.success) {
    if (res.status === 401) return handleUnauthorized()
    setMessage(res.error || 'Impossible de charger vos gains.', 'error')
    return
  }
  wins.value = res.data || []
}

async function spin() {
  if (!token.value || fetchingSpin.value || spinning.value || alreadySpun.value) return
  fetchingSpin.value = true
  result.value = null
  showResult.value = false
  winAnnouncement.value = ''
  const res = await postSpin(token.value)
  fetchingSpin.value = false

  if (!res.success && res.status === 401) {
    handleUnauthorized()
    setMessage('Session expirée. Veuillez vous reconnecter.', 'error')
    return
  }
  if (!res.success && res.status === 429) {
    await loadWins()
    setMessage('Vous avez déjà tourné aujourd\'hui. Revenez demain !', 'info')
    return
  }
  if (!res.success || !res.data || res.data.offer_id == null) {
    setMessage(res.error || 'Erreur lors du tirage', 'error')
    return
  }

  result.value = res.data
  spinning.value = true
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  const spinDuration = reducedMotion ? 50 : 2000
  spinTimeout = setTimeout(async () => {
    try {
      await loadWins()
      if (!token.value || !result.value) return
      showResult.value = true
      await nextTick()
      resultHeading.value?.focus()
      await drawQr()
      winAnnouncement.value = `Vous avez gagné : ${result.value.label}`
    } finally {
      spinning.value = false
    }
  }, spinDuration)
}

async function drawQr() {
  if (!qrCanvas.value || !result.value) return
  await QRCode.toCanvas(qrCanvas.value, result.value.code, { width: 180, margin: 2 })
}

function onAuthChange() {
  const newToken = getUserToken()
  if (newToken && newToken !== token.value) {
    token.value = newToken
    message.value = ''
    loadWins()
  } else if (!newToken) {
    token.value = ''
  }
}

onMounted(() => {
  authEvents.addEventListener('change', onAuthChange)
  if (token.value) {
    loadWins()
  }
})

onBeforeUnmount(() => {
  authEvents.removeEventListener('change', onAuthChange)
  if (spinTimeout) {
    clearTimeout(spinTimeout)
    spinTimeout = null
  }
})
</script>

<style scoped>
.spin-loading { text-align: center; padding: 40px 0; color: var(--c-text-2); }
.spin-message, .result-card, .wins-card { text-align: center; padding: 16px 0; }
.pokestop-wrap { text-align: center; margin: 16px 0; }
.pokestop {
  width: 180px;
  height: 180px;
  margin: 0 auto 12px;
  border-radius: 50%;
  overflow: hidden;
  border: 4px solid var(--c-green-dark);
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
.pokestop.spinning {
  animation: avatar-spin 2s cubic-bezier(0.25, 0.1, 0.25, 1) forwards;
}
@keyframes avatar-spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(1080deg); }
}
@media (prefers-reduced-motion: reduce) {
  .pokestop.spinning { animation: none; }
}
.pokestop-avatar { width: 100%; height: 100%; object-fit: cover; }
.pokestop-fallback { font-size: 4rem; line-height: 1; }
.pokestop-name { font-weight: 600; margin-bottom: 16px; }
.offer-label { font-size: 1.2rem; font-weight: 700; color: var(--c-green-dark); margin: 10px 0; }
.qr-wrap { margin: 16px 0; }
.code { font-family: monospace; font-size: 1.1rem; letter-spacing: 1px; margin-top: 8px; }
.win-row { padding: 12px 0; border-bottom: 1px solid var(--c-border); text-align: left; }
.win-row:last-child { border-bottom: none; }
.status-pending { background: #FFF3E0; color: #E65100; }
.status-claimed { background: #E8F5E9; color: #2E7D32; }
.status-expired { background: #FFEBEE; color: #C62828; }
.auth-message { margin-top: 12px; padding: 10px; border-radius: 8px; font-size: 0.9rem; }
.auth-message.success { background: #e6f4ea; color: #1e7e34; }
.auth-message.error { background: #fdecea; color: #c5221f; }
.auth-message.info { background: #e8f0fe; color: #1967d2; }
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
