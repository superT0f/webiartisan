<template>
  <div class="admin-artisan-edit section">
    <div class="container">
      <div class="section-header flex-between">
        <div>
          <h1>Modifier la fiche artisan</h1>
          <p class="text-muted">{{ artisan?.company_name || 'Chargement…' }}</p>
        </div>
        <RouterLink to="/espace/admin" class="btn btn-outline btn-sm">← Retour</RouterLink>
      </div>

      <div v-if="loading" class="skeleton" style="height: 300px; border-radius: 12px;"></div>

      <div v-else-if="error" class="alert alert-error">
        {{ error }}
      </div>

      <form v-else @submit.prevent="save" class="card edit-form">
        <div class="form-group">
          <label for="company_name">Nom de l'entreprise</label>
          <input id="company_name" v-model="form.company_name" class="form-input" required />
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="phone">Téléphone</label>
            <input id="phone" v-model="form.phone" class="form-input" />
          </div>
          <div class="form-group">
            <label for="website">Site web</label>
            <input id="website" v-model="form.website" class="form-input" placeholder="https://..." />
          </div>
        </div>

        <div class="form-group">
          <label for="address">Adresse</label>
          <input id="address" v-model="form.address" class="form-input" />
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="logo_url">URL du logo</label>
            <input id="logo_url" v-model="form.logo_url" class="form-input" placeholder="https://..." />
          </div>
          <div class="form-group">
            <label for="cover_url">URL de l'image de couverture</label>
            <input id="cover_url" v-model="form.cover_url" class="form-input" placeholder="https://..." />
          </div>
        </div>

        <div class="form-group">
          <label for="description">Description</label>
          <textarea id="description" v-model="form.description" class="form-input" rows="6"></textarea>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ saving ? 'Enregistrement…' : 'Enregistrer les modifications' }}
          </button>
          <RouterLink to="/espace/admin" class="btn btn-outline">Annuler</RouterLink>
        </div>

        <div v-if="message" class="auth-message" :class="messageType">
          {{ message }}
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getArtisanToken, fetchAdminArtisans, updateAdminArtisan } from '../api.js'

const route = useRoute()
const router = useRouter()
const token = ref(getArtisanToken())
const artisanId = parseInt(route.params.id)
const artisan = ref(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const message = ref('')
const messageType = ref('')

const form = ref({
  company_name: '',
  phone: '',
  website: '',
  address: '',
  description: '',
  logo_url: '',
  cover_url: '',
})

async function load() {
  if (!token.value || !artisanId) {
    error.value = 'Accès non autorisé'
    loading.value = false
    return
  }
  try {
    const res = await fetchAdminArtisans(token.value)
    if (res.success) {
      const found = (res.data || []).find(a => a.id === artisanId)
      if (found) {
        artisan.value = found
        form.value = {
          company_name: found.company_name || '',
          phone: found.phone || '',
          website: found.website || '',
          address: found.address || '',
          description: found.description || '',
          logo_url: found.logo_url || '',
          cover_url: found.cover_url || '',
        }
      } else {
        error.value = 'Artisan non trouvé'
      }
    } else {
      error.value = res.error || 'Erreur de chargement'
    }
  } catch (e) {
    error.value = 'Erreur réseau'
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  message.value = ''
  try {
    const payload = {}
    for (const key of Object.keys(form.value)) {
      payload[key] = form.value[key].trim()
    }
    const res = await updateAdminArtisan(token.value, artisanId, payload)
    if (res.success) {
      message.value = res.message || 'Fiche artisan mise à jour.'
      messageType.value = 'success'
      setTimeout(() => router.push('/espace/admin'), 1000)
    } else {
      message.value = res.error || 'Erreur lors de la mise à jour.'
      messageType.value = 'error'
    }
  } catch (e) {
    message.value = 'Erreur réseau'
    messageType.value = 'error'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.admin-artisan-edit { min-height: 60vh; }
.section-header { align-items: flex-start; gap: 16px; margin-bottom: 24px; }
.edit-form { padding: 28px; }
.form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
.form-group label { font-size: 0.85rem; font-weight: 600; color: var(--c-text-2); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px; }
.auth-message { margin-top: 16px; padding: 12px 16px; border-radius: var(--r-md); font-size: 0.9rem; }
.auth-message.success { background: rgba(45, 106, 79, 0.1); color: var(--c-green-dark); }
.auth-message.error { background: rgba(183, 28, 28, 0.08); color: #b71c1c; }
@media (max-width: 600px) {
  .form-row { grid-template-columns: 1fr; }
}
</style>
