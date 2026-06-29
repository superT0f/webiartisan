<template>
  <div class="spin-view section">
    <div class="container narrow">
      <h1>🎰 La Roue des Artisans</h1>
      <p class="text-muted">Tournez la roue une fois par jour et gagnez une offre locale.</p>

      <!-- Auth -->
      <div v-if="!token" class="auth-card card">
        <h2>Connexion</h2>
        <p class="text-muted">Recevez un lien magique par email pour participer.</p>
        <form @submit.prevent="sendMagicLink" class="auth-form">
          <input
            v-model="email"
            type="email"
            class="form-input"
            placeholder="votre@email.fr"
            required
            :disabled="sending"
          />
          <button type="submit" class="btn btn-primary" :disabled="sending || !email">
            {{ sending ? 'Envoi…' : 'Recevoir mon lien' }}
          </button>
        </form>
        <div v-if="message" class="auth-message" :class="messageType">{{ message }}</div>
      </div>

      <!-- Connected -->
      <template v-else>
        <div v-if="loading" class="skeleton" style="height: 360px; border-radius: 12px;"></div>

        <template v-else>
          <div v-if="alreadySpun" class="card result-card">
            <h2>Vous avez déjà tourné aujourd'hui 🎉</h2>
            <p>Revenez demain pour une nouvelle chance.</p>
            <RouterLink to="/" class="btn btn-outline">Retour à l'annuaire</RouterLink>
          </div>

          <div v-else class="wheel-wrap">
            <div class="wheel-container" :class="{ spinning: spinning }">
              <canvas ref="wheelCanvas" width="360" height="360"></canvas>
              <div class="wheel-pointer"></div>
            </div>
            <button class="btn btn-primary btn-lg" @click="spin" :disabled="spinning || offers.length < 2">
              {{ spinning ? 'La roue tourne…' : 'Tourner la roue' }}
            </button>
            <p v-if="offers.length < 2" class="text-muted small">Offres insuffisantes pour tourner.</p>
          </div>

          <div v-if="result" class="card result-card">
            <h2>🎁 Vous avez gagné</h2>
            <div class="offer-label">{{ result.label }}</div>
            <p class="text-muted">{{ result.description }}</p>
            <p><strong>Artisan :</strong> {{ result.artisan_name }}</p>
            <div class="qr-wrap">
              <canvas ref="qrCanvas"></canvas>
              <div class="code">{{ result.code }}</div>
            </div>
            <p class="text-muted small">Valide jusqu'au {{ formatDate(result.expires_at) }}</p>
          </div>

          <div v-if="wins.length" class="card wins-card">
            <h2>Mes gains</h2>
            <div v-for="w in wins" :key="w.id" class="win-row">
              <div>
                <strong>{{ w.label }}</strong>
                <span class="badge" :class="'status-' + w.status">{{ statusLabel[w.status] }}</span>
              </div>
              <div class="code">{{ w.code }}</div>
              <div class="text-muted small">Expire le {{ formatDate(w.expires_at) }}</div>
            </div>
          </div>
        </template>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import QRCode from 'qrcode'
import { useGamification } from '../composables/useGamification.js'
import {
  requestUserMagicLink,
  authUser,
  fetchUserMe,
  getUserToken,
  setUserToken,
  removeUserToken,
  getSpinOffers,
  postSpin,
  getSpinWins,
} from '../api.js'

const route = useRoute()
const router = useRouter()
const { recordAction } = useGamification()

const email = ref('')
const token = ref(getUserToken() || '')
const user = ref(null)
const offers = ref([])
const wins = ref([])
const loading = ref(false)
const sending = ref(false)
const spinning = ref(false)
const result = ref(null)
const message = ref('')
const messageType = ref('')
const wheelCanvas = ref(null)
const qrCanvas = ref(null)

const alreadySpun = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  return wins.value.some(w => w.spin_date === today && w.status !== 'expired')
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

if (route.query.token) {
  authUser(route.query.token).then(res => {
    if (res.success && res.token) {
      setUserToken(res.token)
      token.value = res.token
      router.replace('/roue')
    } else {
      setMessage(res.error || 'Lien invalide', 'error')
    }
  })
}

async function sendMagicLink() {
  sending.value = true
  message.value = ''
  try {
    const res = await requestUserMagicLink(email.value)
    setMessage(res.message || 'Si votre email est valide, vous recevrez un lien.', 'success')
  } catch (e) {
    setMessage('Erreur lors de l\'envoi.', 'error')
  } finally {
    sending.value = false
  }
}

async function loadUser() {
  if (!token.value) return
  loading.value = true
  try {
    const res = await fetchUserMe(token.value)
    if (res.success) {
      user.value = res.data
    } else if (res.status === 401) {
      logout()
      setMessage('Session expirée.', 'error')
    } else {
      setMessage(res.error || 'Impossible de charger le profil.', 'error')
    }
  } finally {
    loading.value = false
  }
}

async function loadOffers() {
  try {
    const res = await getSpinOffers()
    offers.value = res.data || []
    drawWheel()
  } catch (e) {
    console.error('Erreur chargement offres', e)
  }
}

async function loadWins() {
  if (!token.value) return
  try {
    const res = await getSpinWins(token.value)
    wins.value = res.data || []
  } catch (e) {
    console.error('Erreur chargement gains', e)
  }
}

async function spin() {
  if (spinning.value || alreadySpun.value || offers.value.length < 2) return
  spinning.value = true
  result.value = null
  let success = false
  try {
    const res = await postSpin(token.value, {})
    if (res.success) {
      result.value = res.data
      await loadWins()
      await nextTick()
      drawQr()
      success = true
    } else {
      setMessage(res.error || 'Erreur lors du spin', 'error')
    }
  } catch (e) {
    setMessage('Erreur réseau.', 'error')
  } finally {
    spinning.value = false
  }
  if (success) {
    await recordAction('spin_play')
  }
}

function drawWheel() {
  if (!wheelCanvas.value || offers.value.length < 2) return
  const ctx = wheelCanvas.value.getContext('2d')
  const w = wheelCanvas.value.width
  const h = wheelCanvas.value.height
  const cx = w / 2
  const cy = h / 2
  const r = Math.min(w, h) / 2 - 8
  const slice = (Math.PI * 2) / offers.value.length
  const colors = ['#2D6A4F', '#40916C', '#52B788', '#74C69D', '#95D5B2', '#B7E4C7']

  ctx.clearRect(0, 0, w, h)
  offers.value.forEach((offer, i) => {
    const start = i * slice - Math.PI / 2
    const end = start + slice
    ctx.beginPath()
    ctx.moveTo(cx, cy)
    ctx.arc(cx, cy, r, start, end)
    ctx.fillStyle = colors[i % colors.length]
    ctx.fill()
    ctx.stroke()
    ctx.save()
    ctx.translate(cx, cy)
    ctx.rotate(start + slice / 2)
    ctx.fillStyle = '#fff'
    ctx.font = 'bold 13px sans-serif'
    ctx.textAlign = 'right'
    const text = offer.label.length > 18 ? offer.label.slice(0, 18) + '…' : offer.label
    ctx.fillText(text, r - 16, 5)
    ctx.restore()
  })
}

async function drawQr() {
  if (!qrCanvas.value || !result.value) return
  await QRCode.toCanvas(qrCanvas.value, result.value.code, { width: 180, margin: 2 })
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('fr-FR')
}

function logout() {
  token.value = ''
  user.value = null
  removeUserToken()
}

onMounted(() => {
  loadUser()
  loadOffers()
  loadWins()
})
</script>

<style scoped>
.spin-view { min-height: 60vh; }
.narrow { max-width: 720px; }
.auth-card, .result-card, .wins-card { padding: 28px; margin-top: 24px; }
.auth-form { display: flex; flex-direction: column; gap: 14px; margin-top: 16px; }
.wheel-wrap { text-align: center; margin: 32px 0; }
.wheel-container { position: relative; display: inline-block; margin-bottom: 24px; }
.wheel-container.spinning canvas {
  animation: spin-anim 3s cubic-bezier(0.25, 0.1, 0.25, 1) forwards;
}
@keyframes spin-anim {
  from { transform: rotate(0deg); }
  to { transform: rotate(1800deg); }
}
.wheel-pointer {
  position: absolute;
  top: -10px;
  left: 50%;
  transform: translateX(-50%);
  width: 0;
  height: 0;
  border-left: 14px solid transparent;
  border-right: 14px solid transparent;
  border-top: 22px solid #B71C1C;
}
.offer-label { font-size: 1.3rem; font-weight: 700; color: var(--c-green-dark); margin: 12px 0; }
.qr-wrap { margin: 20px 0; }
.code { font-family: monospace; font-size: 1.1rem; letter-spacing: 1px; margin-top: 8px; }
.win-row { padding: 14px 0; border-bottom: 1px solid var(--c-border); }
.win-row:last-child { border-bottom: none; }
.status-pending { background: #FFF3E0; color: #E65100; }
.status-claimed { background: #E8F5E9; color: #2E7D32; }
.status-expired { background: #FFEBEE; color: #C62828; }
</style>
