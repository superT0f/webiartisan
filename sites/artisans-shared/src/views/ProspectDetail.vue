<template>
  <div class="prospect-detail section">
    <div class="container">
      <div v-if="loading" class="skeleton" style="height: 300px; border-radius: 12px;"></div>

      <template v-else-if="prospect">
        <div class="detail-header">
          <RouterLink to="/prospection" class="back-link">← Retour à la prospection</RouterLink>
          <h1>{{ prospect.name }}</h1>
          <div class="detail-meta">
            <span class="badge badge-grey">{{ prospect.type }}</span>
            <span v-if="prospect.zone" class="badge badge-green">{{ prospect.zone }}</span>
          </div>
        </div>

        <div class="detail-grid">
          <div class="detail-main">
            <div v-if="prospect.pitch" class="card info-card">
              <h2>Argumentaire</h2>
              <p>{{ prospect.pitch }}</p>
            </div>

            <div v-if="prospect.weakness" class="card info-card">
              <h2>Point de douleur</h2>
              <p>{{ prospect.weakness }}</p>
            </div>

            <div class="card info-card">
              <h2>Contact</h2>
              <p v-if="prospect.address"><strong>Adresse :</strong> {{ prospect.address }}</p>
              <p v-if="prospect.phone"><strong>Tél :</strong> <a :href="`tel:${prospect.phone}`">{{ prospect.phone }}</a></p>
              <p v-if="prospect.email"><strong>Email :</strong> <a :href="`mailto:${prospect.email}`">{{ prospect.email }}</a></p>
              <p v-if="prospect.website"><strong>Site :</strong> <a :href="prospect.website" target="_blank" rel="noopener">{{ prospect.website }}</a></p>
              <p v-if="prospect.instagram"><strong>Instagram :</strong> {{ prospect.instagram }}</p>
            </div>
          </div>

          <aside class="detail-side">
            <div v-if="!token" class="card follow-card">
              <p class="text-muted">Connectez-vous à votre espace artisan pour suivre ce prospect.</p>
              <RouterLink to="/espace" class="btn btn-primary btn-sm">Se connecter</RouterLink>
            </div>

            <div v-else class="card follow-card">
              <h2>Mon suivi</h2>
              <form @submit.prevent="saveFollow" class="follow-form">
                <div class="form-group">
                  <label for="status">Statut</label>
                  <select id="status" v-model="status" class="form-select">
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="notes">Notes</label>
                  <textarea id="notes" v-model="notes" class="form-textarea" rows="4" placeholder="Vos notes de suivi..."></textarea>
                </div>
                <div class="form-actions">
                  <button type="submit" class="btn btn-primary" :disabled="saving">
                    {{ saving ? 'Enregistrement…' : 'Enregistrer le suivi' }}
                  </button>
                  <button v-if="hasFollow" type="button" class="btn btn-outline btn-sm" @click="removeFollow" :disabled="saving">
                    Ne plus suivre
                  </button>
                </div>
                <div v-if="message" class="auth-message" :class="messageType">{{ message }}</div>
              </form>
            </div>
          </aside>
        </div>
      </template>

      <div v-else class="empty-state">
        <div class="empty-icon">📭</div>
        <h3>Prospect introuvable</h3>
        <RouterLink to="/prospection" class="btn btn-primary" style="margin-top: 16px;">Retour à la liste</RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { getProspect, getMyProspects, followProspect, unfollowProspect, getArtisanToken } from '../api.js'

const route = useRoute()

const prospect = ref(null)
const loading = ref(true)
const token = ref(getArtisanToken())
const status = ref('tocontact')
const notes = ref('')
const saving = ref(false)
const message = ref('')
const messageType = ref('')
const hasFollow = ref(false)

const statuses = [
  { value: 'tocontact', label: 'À contacter' },
  { value: 'contacted', label: 'Contacté' },
  { value: 'meeting', label: 'RDV pris' },
  { value: 'converted', label: 'Converti' },
  { value: 'declined', label: 'Refus' },
]

function setMessage(text, type = 'info') {
  message.value = text
  messageType.value = type
}

async function loadProspect() {
  try {
    const res = await getProspect(route.params.id)
    if (res.success && res.data) {
      prospect.value = res.data
    }
  } catch (e) {
    console.error('Erreur chargement prospect', e)
  }
}

async function loadMyFollow() {
  if (!token.value) return
  try {
    const my = await getMyProspects(token.value)
    const follow = (my.data || []).find(p => p.id === Number(route.params.id))
    if (follow && follow.follow_status) {
      hasFollow.value = true
      status.value = follow.follow_status
      notes.value = follow.follow_notes || ''
    }
  } catch (e) {
    console.error('Erreur chargement suivi', e)
  }
}

async function saveFollow() {
  if (!token.value || !prospect.value) return
  saving.value = true
  message.value = ''
  try {
    const res = await followProspect(token.value, prospect.value.id, { status: status.value, notes: notes.value })
    if (res.success) {
      hasFollow.value = true
      setMessage('Suivi enregistré', 'success')
    } else {
      setMessage(res.error || 'Erreur lors de l\'enregistrement', 'error')
    }
  } catch (e) {
    setMessage('Erreur lors de l\'enregistrement', 'error')
  } finally {
    saving.value = false
  }
}

async function removeFollow() {
  if (!token.value || !prospect.value) return
  saving.value = true
  message.value = ''
  try {
    const res = await unfollowProspect(token.value, prospect.value.id)
    if (res.success) {
      hasFollow.value = false
      status.value = 'tocontact'
      notes.value = ''
      setMessage('Suivi supprimé', 'success')
    } else {
      setMessage(res.error || 'Erreur lors de la suppression', 'error')
    }
  } catch (e) {
    setMessage('Erreur lors de la suppression', 'error')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await loadProspect()
  await loadMyFollow()
  loading.value = false
})
</script>

<style scoped>
.prospect-detail { min-height: 60vh; }
.back-link { display: inline-block; margin-bottom: 12px; color: var(--c-text-2); }
.back-link:hover { color: var(--c-green); }
.detail-header { margin-bottom: 28px; }
.detail-header h1 { margin-bottom: 12px; }
.detail-meta { display: flex; gap: 8px; flex-wrap: wrap; }

.detail-grid { display: grid; grid-template-columns: 1fr 360px; gap: 24px; align-items: start; }
.detail-main { display: flex; flex-direction: column; gap: 20px; }
.info-card { padding: 24px; }
.info-card h2 { font-size: 1.1rem; margin-bottom: 12px; color: var(--c-green-dark); }
.info-card p { margin-bottom: 8px; }
.info-card a { color: var(--c-green); text-decoration: underline; }

.follow-card { padding: 24px; position: sticky; top: 80px; }
.follow-card h2 { font-size: 1.2rem; margin-bottom: 16px; }
.follow-form { display: flex; flex-direction: column; gap: 16px; }
.form-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 4px; }

.auth-message { padding: 12px 16px; border-radius: var(--r-md); font-size: 0.9rem; }
.auth-message.success { background: rgba(45, 106, 79, 0.1); color: var(--c-green-dark); }
.auth-message.error { background: rgba(183, 28, 28, 0.08); color: #b71c1c; }

.empty-state { text-align: center; padding: 80px 20px; }
.empty-icon { font-size: 3rem; margin-bottom: 16px; }

@media (max-width: 900px) {
  .detail-grid { grid-template-columns: 1fr; }
  .follow-card { position: static; }
}
</style>
