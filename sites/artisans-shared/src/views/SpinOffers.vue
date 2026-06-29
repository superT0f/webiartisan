<template>
  <div class="section">
    <div class="container narrow">
      <h1>Mes offres roue</h1>
      <RouterLink to="/espace" class="btn btn-outline btn-sm">← Retour à l'espace</RouterLink>

      <form @submit.prevent="save" class="card form-card">
        <h2>{{ editingId ? 'Modifier' : 'Nouvelle' }} offre</h2>
        <div class="form-group">
          <label>Libellé (affiché sur la roue)</label>
          <input v-model="form.label" class="form-input" required maxlength="200" />
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea v-model="form.description" class="form-textarea" rows="3"></textarea>
        </div>
        <div class="form-row grid-2">
          <div class="form-group">
            <label>Stock total</label>
            <input type="number" min="1" v-model.number="form.stock_total" class="form-input" required />
          </div>
          <div class="form-group">
            <label class="checkbox-label">
              <input type="checkbox" v-model="form.is_active" />
              Visible sur la roue
            </label>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ saving ? 'Enregistrement…' : (editingId ? 'Mettre à jour' : 'Créer') }}
          </button>
          <button type="button" class="btn btn-outline" @click="reset" v-if="editingId">Annuler</button>
        </div>
        <div v-if="message" class="auth-message" :class="messageType">{{ message }}</div>
      </form>

      <div v-if="loading" class="skeleton" style="height: 120px;"></div>
      <div v-else-if="offers.length" class="card offers-card">
        <div v-for="o in offers" :key="o.id" class="offer-row">
          <div>
            <strong>{{ o.label }}</strong>
            <span class="badge" :class="o.is_active ? 'badge-green' : 'badge-grey'">
              {{ o.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          <div class="text-muted small">Stock : {{ o.stock_remaining }} / {{ o.stock_total }}</div>
          <div class="row-actions">
            <button class="btn btn-outline btn-sm" @click="edit(o)">Modifier</button>
            <button class="btn btn-danger btn-sm" @click="remove(o.id)">Supprimer</button>
          </div>
        </div>
      </div>
      <div v-else class="empty-state">
        <p>Aucune offre créée.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import {
  getArtisanSpinOffers,
  createArtisanSpinOffer,
  updateArtisanSpinOffer,
  deleteArtisanSpinOffer,
} from '../api.js'

const STORAGE_KEY = 'artisan_token'
const token = ref(localStorage.getItem(STORAGE_KEY) || '')

const offers = ref([])
const loading = ref(false)
const saving = ref(false)
const editingId = ref(null)
const message = ref('')
const messageType = ref('')

const form = reactive({
  label: '',
  description: '',
  stock_total: 10,
  is_active: true,
})

function setMessage(text, type = 'info') {
  message.value = text
  messageType.value = type
}

async function load() {
  if (!token.value) return
  loading.value = true
  try {
    const res = await getArtisanSpinOffers(token.value)
    offers.value = res.data || []
  } catch (e) {
    setMessage('Erreur chargement offres.', 'error')
  } finally {
    loading.value = false
  }
}

function edit(offer) {
  editingId.value = offer.id
  Object.assign(form, {
    label: offer.label,
    description: offer.description,
    stock_total: offer.stock_total,
    is_active: offer.is_active,
  })
}

function reset() {
  editingId.value = null
  Object.assign(form, { label: '', description: '', stock_total: 10, is_active: true })
}

async function save() {
  saving.value = true
  message.value = ''
  try {
    const res = editingId.value
      ? await updateArtisanSpinOffer(token.value, editingId.value, form)
      : await createArtisanSpinOffer(token.value, form)
    if (res.success) {
      setMessage(editingId.value ? 'Offre mise à jour.' : 'Offre créée.', 'success')
      reset()
      await load()
    } else {
      setMessage(res.error || 'Erreur.', 'error')
    }
  } catch (e) {
    setMessage('Erreur réseau.', 'error')
  } finally {
    saving.value = false
  }
}

async function remove(id) {
  if (!confirm('Supprimer cette offre ?')) return
  try {
    await deleteArtisanSpinOffer(token.value, id)
    await load()
  } catch (e) {
    setMessage('Erreur suppression.', 'error')
  }
}

onMounted(load)
</script>

<style scoped>
.narrow { max-width: 760px; }
.form-card, .offers-card { padding: 28px; margin-top: 24px; }
.offer-row { padding: 16px 0; border-bottom: 1px solid var(--c-border); }
.offer-row:last-child { border-bottom: none; }
.row-actions { display: flex; gap: 8px; margin-top: 12px; }
.checkbox-label { display: flex; align-items: center; gap: 8px; margin-top: 8px; cursor: pointer; }
.checkbox-label input { width: auto; }
</style>
