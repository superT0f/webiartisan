<template>
  <div class="spin-view section">
    <div class="container narrow">
      <h1>🎰 La Roue des Artisans</h1>
      <p class="text-muted">Tournez la roue une fois par jour et gagnez une offre locale.</p>

      <!-- Auth -->
      <div v-if="!token" class="auth-card card">
        <h2>Connexion</h2>
        <div class="auth-tabs" role="tablist" aria-label="Méthode de connexion" @keydown="onTabKeydown">
          <button
            v-for="tab in tabList"
            :key="tab.key"
            :id="`tab-${tab.key}`"
            ref="tabRefs"
            type="button"
            role="tab"
            :tabindex="authTab === tab.key ? 0 : -1"
            :aria-selected="authTab === tab.key"
            :aria-controls="`panel-${tab.key}`"
            :class="{ active: authTab === tab.key }"
            @click="setAuthTab(tab.key)"
          >{{ tab.label }}</button>
        </div>

        <div v-show="authTab === 'magic'" role="tabpanel" id="panel-magic" aria-labelledby="tab-magic">
          <form @submit.prevent="sendMagicLink" class="auth-form">
            <p class="text-muted">Recevez un lien magique par email pour participer.</p>
            <label class="form-label" for="auth-email">Email</label>
            <input
              id="auth-email"
              v-model="email"
              type="email"
              class="form-input"
              placeholder="votre@email.fr"
              autocomplete="email"
              required
              :disabled="sending"
            />
            <label class="form-checkbox">
              <input v-model="rememberMe" type="checkbox" :disabled="sending" />
              Rester connecté sur cet appareil
            </label>
            <button type="submit" class="btn btn-primary" :disabled="sending || !email">
              {{ sending ? 'Envoi…' : 'Recevoir mon lien' }}
            </button>
          </form>
        </div>

        <div v-show="authTab === 'login'" role="tabpanel" id="panel-login" aria-labelledby="tab-login">
          <form @submit.prevent="submitLogin" class="auth-form">
            <p class="text-muted">Connectez-vous avec votre email et mot de passe.</p>
            <label class="form-label" for="login-email">Email</label>
            <input id="login-email" ref="loginEmailInput" v-model="email" type="email" class="form-input" placeholder="votre@email.fr" autocomplete="email" required />
            <label class="form-label" for="login-password">Mot de passe</label>
            <input id="login-password" v-model="password" type="password" class="form-input" placeholder="Mot de passe" autocomplete="current-password" required />
            <label class="form-checkbox">
              <input v-model="rememberMe" type="checkbox" />
              Rester connecté sur cet appareil
            </label>
            <button type="submit" class="btn btn-primary" :disabled="sending">
              {{ sending ? 'Connexion…' : 'Se connecter' }}
            </button>
            <button type="button" class="btn btn-link" @click="openForgot">Mot de passe oublié ?</button>
          </form>
        </div>

        <div v-show="authTab === 'register'" role="tabpanel" id="panel-register" aria-labelledby="tab-register">
          <form @submit.prevent="submitRegister" class="auth-form">
            <p class="text-muted">Créez un compte pour sauvegarder votre progression.</p>
            <label class="form-label" for="register-email">Email</label>
            <input id="register-email" v-model="email" type="email" class="form-input" placeholder="votre@email.fr" autocomplete="email" required />
            <label class="form-label" for="register-display-name">Pseudo (optionnel)</label>
            <input id="register-display-name" v-model="displayName" type="text" class="form-input" placeholder="Pseudo (optionnel)" autocomplete="nickname" maxlength="80" />
            <label class="form-label" for="register-password">Mot de passe</label>
            <input id="register-password" v-model="password" type="password" class="form-input" placeholder="Mot de passe (min 8 caractères)" autocomplete="new-password" minlength="8" required />
            <label class="form-checkbox">
              <input v-model="rememberMe" type="checkbox" />
              Rester connecté sur cet appareil
            </label>
            <button type="submit" class="btn btn-primary" :disabled="sending">
              {{ sending ? 'Création…' : 'Créer mon compte' }}
            </button>
          </form>
        </div>

        <div v-show="authTab === 'forgot'">
          <form @submit.prevent="submitForgot" class="auth-form">
            <p class="text-muted">Recevez un lien de réinitialisation par email.</p>
            <label class="form-label" for="forgot-email">Email</label>
            <input id="forgot-email" ref="forgotEmailInput" v-model="email" type="email" class="form-input" placeholder="votre@email.fr" autocomplete="email" required />
            <button type="submit" class="btn btn-primary" :disabled="sending">
              {{ sending ? 'Envoi…' : 'Envoyer' }}
            </button>
            <button type="button" class="btn btn-link" @click="backToLogin">Retour</button>
          </form>
        </div>
      </div>

      <div class="auth-message" :class="[messageType, { 'is-empty': !message }]" role="status" aria-live="polite">{{ message }}</div>

      <!-- Connected -->
      <template v-if="token">
        <div class="auth-actions">
          <button class="btn btn-outline" @click="logout">Déconnexion</button>
        </div>

        <div class="sr-only" role="status" aria-live="polite">{{ winAnnouncement }}</div>

        <div v-if="loading" class="skeleton skeleton-wheel"></div>

        <template v-else>
          <div v-if="alreadySpun" class="card result-card">
            <h2>Vous avez déjà tourné aujourd'hui 🎉</h2>
            <p>Revenez demain pour une nouvelle chance.</p>
            <RouterLink to="/" class="btn btn-outline">Retour à l'annuaire</RouterLink>
          </div>

          <div v-else class="wheel-wrap">
            <div class="wheel-container" :class="{ spinning: spinning }">
              <canvas ref="wheelCanvas" width="360" height="360" role="img" aria-label="Roue des offres à gagner" :style="{ transform: `rotate(${finalAngle}deg)` }">Roue des offres à gagner</canvas>
              <div class="wheel-pointer"></div>
            </div>
            <button class="btn btn-primary btn-lg" @click="spin" :disabled="spinning || fetchingSpin || offers.length < 2">
              {{ fetchingSpin ? 'Chargement…' : (spinning ? 'La roue tourne…' : 'Tourner la roue') }}
            </button>
            <p v-if="offers.length < 2" class="text-muted small">Offres insuffisantes pour tourner.</p>
          </div>

          <div v-if="showResult" class="card result-card">
            <h2 ref="resultHeading" tabindex="-1">🎁 Vous avez gagné</h2>
            <div class="offer-label">{{ result.label }}</div>
            <p class="text-muted">{{ result.description }}</p>
            <p><strong>Artisan :</strong> {{ result.artisan_name }}</p>
            <div class="qr-wrap">
              <canvas ref="qrCanvas" aria-hidden="true"></canvas>
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
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import QRCode from 'qrcode'
import {
  requestUserMagicLink,
  authUser,
  getUserToken,
  setUserToken,
  removeUserToken,
  registerUser,
  loginUser,
  logoutUser,
  requestPasswordReset,
  getSpinOffers,
  postSpin,
  getSpinWins,
} from '../api.js'

function parisTodayIso() {
  const d = new Date()
  const paris = new Intl.DateTimeFormat('fr-FR', {
    timeZone: 'Europe/Paris',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(d)
  const part = (type) => paris.find(p => p.type === type).value
  return `${part('year')}-${part('month')}-${part('day')}`
}

function toParisDateParts(iso) {
  const [datePart] = String(iso).split('T')
  const [y, m, d] = datePart.split('-').map(Number)
  if (!y || !m || !d) return null
  const dt = new Date(Date.UTC(y, m - 1, d, 12, 0, 0))
  return new Intl.DateTimeFormat('fr-FR', {
    timeZone: 'Europe/Paris',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(dt)
}

function toParisIso(iso) {
  const parts = toParisDateParts(iso)
  if (!parts) return null
  const part = (type) => parts.find(p => p.type === type).value
  return `${part('year')}-${part('month')}-${part('day')}`
}

function formatLocalDate(iso) {
  if (!iso) return '—'
  const parts = toParisDateParts(iso)
  if (!parts) return '—'
  const part = (type) => parts.find(p => p.type === type).value
  return `${part('day')}/${part('month')}/${part('year')}`
}

const route = useRoute()
const router = useRouter()

const tabList = [
  { key: 'magic', label: 'Lien magique' },
  { key: 'login', label: 'Mot de passe' },
  { key: 'register', label: 'Créer un compte' },
]
const tabRefs = ref([])

const email = ref('')
const rememberMe = ref(true)
const token = ref(getUserToken() || '')
const authTab = ref('magic') // 'magic' | 'login' | 'register' | 'forgot'
const password = ref('')
const displayName = ref('')
const offers = ref([])
const wins = ref([])
const loadingOffers = ref(false)
const loadingWins = ref(false)
const loading = computed(() => loadingOffers.value || loadingWins.value)
const sending = ref(false)
const spinning = ref(false)
const fetchingSpin = ref(false)
const result = ref(null)
const message = ref('')
const messageType = ref('')
const wheelCanvas = ref(null)
const qrCanvas = ref(null)
const finalAngle = ref(0)
const winAnnouncement = ref('')
const showResult = ref(false)
const loginEmailInput = ref(null)
const forgotEmailInput = ref(null)
const resultHeading = ref(null)
let spinTimeout = null

watch(authTab, () => {
  message.value = ''
  messageType.value = ''
})

function setAuthTab(key) {
  authTab.value = key
  const idx = tabList.findIndex(t => t.key === key)
  const el = tabRefs.value[idx]
  if (el) el.focus()
}

function openForgot() {
  authTab.value = 'forgot'
  nextTick(() => {
    forgotEmailInput.value?.focus()
  })
}

function backToLogin() {
  authTab.value = 'login'
  nextTick(() => {
    loginEmailInput.value?.focus()
  })
}

function onTabKeydown(e) {
  if (e.target.getAttribute('role') !== 'tab') return
  const keys = tabList.map(t => t.key)
  let idx = keys.indexOf(authTab.value)
  if (idx < 0) return
  switch (e.key) {
    case 'ArrowLeft':
      idx = (idx - 1 + keys.length) % keys.length
      break
    case 'ArrowRight':
      idx = (idx + 1) % keys.length
      break
    case 'Home':
      idx = 0
      break
    case 'End':
      idx = keys.length - 1
      break
    default:
      return
  }
  e.preventDefault()
  setAuthTab(keys[idx])
}

const alreadySpun = computed(() => {
  const today = parisTodayIso()
  return wins.value.some(w => {
    const spinDate = toParisIso(w.spin_date)
    return spinDate === today && w.status !== 'expired'
  })
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

async function exchangeMagicLink() {
  if (!route.query.token) return true
  try {
    const res = await authUser(route.query.token, rememberMe.value)
    if (res.success && res.token) {
      setUserToken(res.token, rememberMe.value)
      token.value = res.token
      await router.replace('/roue')
      return true
    } else {
      setMessage(res.error || 'Lien invalide', 'error')
      return false
    }
  } catch (e) {
    setMessage('Erreur réseau.', 'error')
    return false
  }
}

async function sendMagicLink() {
  sending.value = true
  message.value = ''
  try {
    const res = await requestUserMagicLink(email.value, rememberMe.value, '/roue')
    setMessage(res.message || 'Si votre email est valide, vous recevrez un lien.', 'success')
  } catch (e) {
    setMessage('Erreur lors de l\'envoi.', 'error')
  } finally {
    sending.value = false
  }
}

async function submitLogin() {
  sending.value = true
  message.value = ''
  try {
    const res = await loginUser({ email: email.value, password: password.value, rememberMe: rememberMe.value })
    if (res.success && res.token) {
      setUserToken(res.token, rememberMe.value)
      token.value = res.token
      await router.replace('/roue')
      await loadOffers()
      await loadWins()
    } else {
      setMessage(res.error || 'Erreur de connexion.', 'error')
    }
  } catch (e) {
    setMessage('Erreur réseau.', 'error')
  } finally {
    sending.value = false
  }
}

async function submitRegister() {
  sending.value = true
  message.value = ''
  try {
    const res = await registerUser({
      email: email.value,
      password: password.value,
      display_name: displayName.value,
    })
    if (res.success) {
      try {
        const loginRes = await loginUser({ email: email.value, password: password.value, rememberMe: rememberMe.value })
        if (loginRes.success && loginRes.token) {
          setUserToken(loginRes.token, rememberMe.value)
          token.value = loginRes.token
          await router.replace('/roue')
          await loadOffers()
          await loadWins()
          return
        }
      } catch (loginErr) {
        // ignore, fall through to manual login prompt
      }
      setMessage('Compte créé. Veuillez vous connecter.', 'success')
      authTab.value = 'login'
    } else {
      setMessage(res.error || 'Erreur lors de l\'inscription.', 'error')
    }
  } catch (e) {
    setMessage('Erreur réseau.', 'error')
  } finally {
    sending.value = false
  }
}

async function submitForgot() {
  sending.value = true
  message.value = ''
  try {
    const res = await requestPasswordReset(email.value)
    setMessage(res.message || 'Si votre email est valide, vous recevrez un lien.', 'success')
  } catch (e) {
    setMessage('Erreur réseau.', 'error')
  } finally {
    sending.value = false
  }
}

async function loadOffers() {
  if (!token.value) return
  loadingOffers.value = true
  try {
    const res = await getSpinOffers()
    if (!res.success) {
      if (res.status === 401) {
        await logout()
      }
      setMessage(res.error || 'Impossible de charger les offres.', 'error')
      return
    }
    offers.value = res.data || []
    await nextTick()
    drawWheel()
  } catch (e) {
    console.error('Erreur chargement offres', e)
    setMessage('Impossible de charger les offres.', 'error')
  } finally {
    loadingOffers.value = false
  }
}

async function loadWins() {
  if (!token.value) return
  loadingWins.value = true
  try {
    const res = await getSpinWins(token.value)
    if (!res.success) {
      if (res.status === 401) {
        await logout()
      }
      setMessage(res.error || 'Impossible de charger vos gains.', 'error')
      return
    }
    wins.value = res.data || []
  } catch (e) {
    console.error('Erreur chargement gains', e)
    setMessage('Impossible de charger vos gains.', 'error')
  } finally {
    loadingWins.value = false
  }
}

async function spin() {
  if (!token.value || fetchingSpin.value || spinning.value || alreadySpun.value || offers.value.length < 2) return
  fetchingSpin.value = true
  result.value = null
  showResult.value = false
  winAnnouncement.value = ''
  try {
    const res = await postSpin(token.value, {})
    if (!res.success && res.status === 401) {
      await logout()
      setMessage('Session expirée. Veuillez vous reconnecter.', 'error')
      return
    }
    if (res.success && res.data && res.data.offer_id != null) {
      const idx = offers.value.findIndex(o => o.id === res.data.offer_id)
      if (idx < 0) {
        setMessage('Résultat inconnu.', 'error')
        return
      }
      const offer = offers.value[idx]
      result.value = { ...offer, ...res.data }
      const sliceDeg = 360 / offers.value.length
      finalAngle.value = 1800 - (idx * sliceDeg + sliceDeg / 2)
      if (wheelCanvas.value) {
        wheelCanvas.value.style.setProperty('--final-angle', `${finalAngle.value}deg`)
      }
      const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
      const spinDuration = reducedMotion ? 50 : 3000
      spinTimeout = setTimeout(async () => {
        try {
          await loadWins()
          if (!token.value || !result.value) return
          showResult.value = true
          await nextTick()
          resultHeading.value?.focus()
          await drawQr()
          winAnnouncement.value = `Vous avez gagné : ${result.value.label}`
        } catch (qrErr) {
          console.error('Erreur génération QR', qrErr)
        } finally {
          spinning.value = false
        }
      }, spinDuration)
      spinning.value = true
    } else {
      setMessage(res.error || 'Erreur lors du spin', 'error')
    }
  } catch (e) {
    setMessage('Erreur réseau.', 'error')
  } finally {
    fetchingSpin.value = false
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
    ctx.shadowColor = 'rgba(0,0,0,0.45)'
    ctx.shadowBlur = 3
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
  return formatLocalDate(iso)
}

async function logout() {
  const currentToken = token.value
  token.value = ''
  if (currentToken) {
    try {
      await logoutUser(currentToken)
    } catch (e) {
      console.error('Erreur lors de la déconnexion', e)
    }
  }
  removeUserToken()
  offers.value = []
  wins.value = []
  result.value = null
  showResult.value = false
  winAnnouncement.value = ''
  finalAngle.value = 0
  email.value = ''
  password.value = ''
  displayName.value = ''
  message.value = ''
  messageType.value = ''
}

onMounted(async () => {
  await exchangeMagicLink()
  if (token.value) {
    await loadOffers()
    await loadWins()
  }
})

onBeforeUnmount(() => {
  if (spinTimeout) {
    clearTimeout(spinTimeout)
    spinTimeout = null
  }
})
</script>

<style scoped>
.spin-view { min-height: 60vh; }
.narrow { max-width: 720px; }
.auth-card, .result-card, .wins-card { padding: 28px; margin-top: 24px; }
.auth-form { display: flex; flex-direction: column; gap: 14px; margin-top: 16px; }
.form-label { font-weight: 500; margin-bottom: 4px; display: block; }
.auth-tabs {
  display: flex;
  gap: 4px;
  margin: 16px 0;
  border-bottom: 1px solid var(--c-border);
  padding-bottom: 8px;
  overflow-x: auto;
}
.auth-tabs button {
  flex: 1 0 auto;
  min-width: 80px;
  padding: 10px 8px;
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  font-weight: 500;
  font-size: 0.9rem;
  color: var(--c-text-muted);
  white-space: nowrap;
}
.auth-tabs button.active {
  color: var(--c-primary);
  border-bottom-color: var(--c-primary);
}
.btn-link {
  background: transparent;
  border: none;
  color: var(--c-primary);
  text-decoration: underline;
  cursor: pointer;
  padding: 0;
  margin-top: 8px;
}
.form-checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  cursor: pointer;
}
.form-checkbox input {
  width: 18px;
  height: 18px;
  cursor: pointer;
}
.auth-actions { text-align: right; margin-bottom: 12px; }
.skeleton-wheel { height: 360px; border-radius: 12px; }
.wheel-wrap { text-align: center; margin: 32px 0; }
.wheel-container { position: relative; display: inline-block; margin-bottom: 24px; }
.wheel-container canvas {
  max-width: 100%;
  height: auto;
}
.wheel-container.spinning canvas {
  animation: spin-anim 3s cubic-bezier(0.25, 0.1, 0.25, 1) forwards;
  --final-angle: 1800deg;
}
@keyframes spin-anim {
  from { transform: rotate(0deg); }
  to { transform: rotate(var(--final-angle)); }
}
@media (prefers-reduced-motion: reduce) {
  .wheel-container.spinning canvas {
    animation: none;
    transform: rotate(var(--final-angle));
  }
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
.auth-message { margin-top: 16px; padding: 12px; border-radius: 8px; }
.auth-message.is-empty { opacity: 0; height: 0; overflow: hidden; padding: 0; margin: 0; }
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
