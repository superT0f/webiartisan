<template>
  <div class="section">
    <div class="container narrow">
      <h1>Validation des gains</h1>
      <RouterLink to="/espace" class="btn btn-outline btn-sm">← Retour à l'espace</RouterLink>

      <form @submit.prevent="validate" class="card form-card">
        <h2>Saisir un code</h2>
        <div class="form-group">
          <label>Code du gain</label>
          <input v-model="code" class="form-input" placeholder="LIV-XXXXXX" required />
        </div>
        <button type="submit" class="btn btn-primary" :disabled="validating">
          {{ validating ? 'Validation…' : 'Valider' }}
        </button>
        <div v-if="message" class="auth-message" :class="messageType">{{ message }}</div>
      </form>

      <div v-if="loading" class="skeleton" style="height: 120px;"></div>
      <div v-else-if="wins.length" class="card wins-card">
        <h2>Gains en attente</h2>
        <div v-for="w in wins" :key="w.id" class="win-row">
          <div>
            <strong>{{ w.label }}</strong>
            <span class="code">{{ w.code }}</span>
          </div>
          <div class="text-muted small">{{ w.user_email }} — expire le {{ formatDate(w.expires_at) }}</div>
          <button class="btn btn-primary btn-sm" @click="validateCode(w.code)">Valider</button>
        </div>
      </div>
      <div v-else class="empty-state">
        <p>Aucun gain en attente.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getArtisanSpinWins, validateArtisanSpinWin } from '../api.js'

const STORAGE_KEY = 'artisan_token'
const token = ref(localStorage.getItem(STORAGE_KEY) || '')

const code = ref('')
const wins = ref([])
const loading = ref(false)
const validating = ref(false)
const message = ref('')
const messageType = ref('')

function setMessage(text, type = 'info') {
  message.value = text
  messageType.value = type
}

async function load() {
  if (!token.value) return
  loading.value = true
  try {
    const res = await getArtisanSpinWins(token.value, 'pending')
    wins.value = res.data || []
  } catch (e) {
    setMessage('Erreur chargement.', 'error')
  } finally {
    loading.value = false
  }
}

async function validate() {
  await validateCode(code.value)
}

async function validateCode(c) {
  validating.value = true
  message.value = ''
  try {
    const res = await validateArtisanSpinWin(token.value, c)
    if (res.success) {
      setMessage('Gain validé avec succès.', 'success')
      code.value = ''
      await load()
    } else {
      setMessage(res.error || 'Erreur de validation.', 'error')
    }
  } catch (e) {
    setMessage('Erreur réseau.', 'error')
  } finally {
    validating.value = false
  }
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('fr-FR')
}

onMounted(load)
</script>

<style scoped>
.narrow { max-width: 760px; }
.form-card, .wins-card { padding: 28px; margin-top: 24px; }
.win-row { padding: 14px 0; border-bottom: 1px solid var(--c-border); }
.win-row:last-child { border-bottom: none; }
.code { font-family: monospace; margin-left: 12px; }
</style>
