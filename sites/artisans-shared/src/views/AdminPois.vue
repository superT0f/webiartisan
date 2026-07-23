<template>
  <div class="admin-pois-view section">
    <div class="container">
      <div class="section-header flex-between">
        <div>
          <h1>Gestion des points d'intérêt</h1>
          <p class="text-muted">Services publics et lieux utiles de votre ville.</p>
        </div>
        <RouterLink to="/espace" class="btn btn-outline btn-sm">Retour à mon espace</RouterLink>
      </div>

      <div v-if="!token" class="auth-card card">
        <div class="empty-icon">🔐</div>
        <h3>Connexion requise</h3>
        <p>Vous devez être connecté avec un compte administrateur.</p>
        <RouterLink to="/espace" class="btn btn-primary" style="margin-top: 16px;">Me connecter</RouterLink>
      </div>

      <template v-else>
        <section class="claims-section card" v-if="poiClaims.length">
          <h2>Revendications à valider ({{ poiClaims.length }})</h2>
          <ul class="claims-list">
            <li v-for="c in poiClaims" :key="c.id" class="claims-item">
              <span><strong>{{ c.poi_name }}</strong> ({{ c.city }}) — {{ c.artisan_name }}</span>
              <span class="claims-actions">
                <button type="button" class="btn btn-primary btn-sm" @click="onReviewClaim(c, true)">Approuver</button>
                <button type="button" class="btn btn-outline btn-sm" @click="onReviewClaim(c, false)">Rejeter</button>
              </span>
            </li>
          </ul>
        </section>

        <div class="filters">
          <input
            v-model="search"
            type="text"
            class="form-input"
            placeholder="Rechercher un POI…"
          />
          <button class="btn btn-primary" @click="startCreate">
            + Ajouter un POI
          </button>
          <button class="btn btn-outline btn-sm" @click="load">🔄 Actualiser</button>
        </div>

        <div v-if="loading" class="skeleton" style="height: 300px; border-radius: 12px;"></div>

        <div v-else-if="error" class="alert alert-error">
          {{ error }}
        </div>

        <div v-else-if="!filteredPois.length" class="empty-state card">
          <div class="empty-icon">📍</div>
          <h3>Aucun POI trouvé</h3>
        </div>

        <div v-else class="poi-list">
          <div v-for="poi in filteredPois" :key="poi.id" class="poi-card card">
            <div class="poi-header">
              <div>
                <h3>{{ poi.name }}</h3>
                <span class="badge" :class="poi.is_active ? 'badge-green' : 'badge-grey'">
                  {{ poi.is_active ? 'Actif' : 'Inactif' }}
                </span>
                <span class="badge badge-grey">{{ poi.type }}</span>
              </div>
              <div class="poi-actions">
                <button class="btn btn-outline btn-sm" @click="edit(poi)">Modifier</button>
                <button class="btn btn-sm btn-danger" @click="remove(poi)">Supprimer</button>
              </div>
            </div>
            <p v-if="poi.address" class="poi-address">{{ poi.address }}</p>
            <p v-if="poi.phone" class="poi-meta">📞 {{ poi.phone }}</p>
            <p v-if="poi.schedules?.length" class="poi-schedules">
              <strong>Horaires :</strong>
              {{ formatSchedules(poi.schedules) }}
            </p>
          </div>
        </div>
      </template>

      <!-- Modal édition / création -->
      <div v-if="showForm" class="modal-overlay" @click.self="closeForm">
        <div class="modal">
          <div class="modal-header">
            <h2>{{ editingId ? 'Modifier' : 'Ajouter' }} un POI</h2>
            <button class="btn-close" @click="closeForm">✕</button>
          </div>

          <form @submit.prevent="save">
            <div class="form-row">
              <div class="form-group">
                <label for="poi-name">Nom *</label>
                <input id="poi-name" v-model="form.name" class="form-input" required />
              </div>
              <div class="form-group">
                <label for="poi-type">Type *</label>
                <select id="poi-type" v-model="form.type" class="form-input" required>
                  <option v-for="t in poiTypes" :key="t" :value="t">{{ t }}</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label for="poi-address">Adresse</label>
              <input id="poi-address" v-model="form.address" class="form-input" />
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="poi-phone">Téléphone</label>
                <input id="poi-phone" v-model="form.phone" class="form-input" />
              </div>
              <div class="form-group">
                <label for="poi-website">Site web</label>
                <input id="poi-website" v-model="form.website" class="form-input" />
              </div>
            </div>

            <div class="form-group">
              <label for="poi-email">Email</label>
              <input id="poi-email" v-model="form.email" class="form-input" type="email" />
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="poi-lat">Latitude</label>
                <input id="poi-lat" v-model="form.latitude" class="form-input" type="number" step="any" />
              </div>
              <div class="form-group">
                <label for="poi-lng">Longitude</label>
                <input id="poi-lng" v-model="form.longitude" class="form-input" type="number" step="any" />
              </div>
            </div>

            <div class="form-group">
              <label for="poi-description">Description</label>
              <textarea id="poi-description" v-model="form.description" class="form-input" rows="3"></textarea>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="poi-order">Ordre d'affichage</label>
                <input id="poi-order" v-model.number="form.sort_order" class="form-input" type="number" />
              </div>
              <div class="form-group form-checkbox-group">
                <label>
                  <input v-model="form.is_active" type="checkbox" />
                  POI actif
                </label>
              </div>
            </div>

            <fieldset class="schedules-fieldset">
              <legend>Horaires</legend>
              <div v-for="(sched, idx) in form.schedules" :key="idx" class="schedule-row">
                <select v-model="sched.day_of_week" class="form-input">
                  <option v-for="(day, i) in DAYS" :key="i" :value="i">{{ day }}</option>
                </select>
                <input v-model="sched.open_time" type="time" class="form-input" />
                <input v-model="sched.close_time" type="time" class="form-input" />
                <label class="schedule-closed">
                  <input v-model="sched.is_closed" type="checkbox" />
                  Fermé
                </label>
                <button type="button" class="btn btn-sm btn-danger" @click="removeScheduleField(idx)">✕</button>
              </div>
              <button type="button" class="btn btn-outline btn-sm" @click="addScheduleField">
                + Ajouter un horaire
              </button>
            </fieldset>

            <div class="form-actions">
              <button type="submit" class="btn btn-primary" :disabled="saving">
                {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
              </button>
              <button type="button" class="btn btn-outline" @click="closeForm">Annuler</button>
            </div>

            <div v-if="formMessage" class="auth-message" :class="formMessageType">
              {{ formMessage }}
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { getArtisanToken, fetchAdminPois, createAdminPoi, updateAdminPoi, deleteAdminPoi, createAdminSchedule, deleteAdminSchedule, fetchAdminPoiClaims, reviewPoiClaim, DAYS } from '../api.js'

const token = ref(getArtisanToken())
const pois = ref([])
const poiClaims = ref([])
const loading = ref(true)
const error = ref('')
const search = ref('')
const saving = ref(false)
const showForm = ref(false)
const editingId = ref(null)
const formMessage = ref('')
const formMessageType = ref('')

const poiTypes = ['mairie', 'piscine', 'bibliotheque', 'mediatheque', 'cinema', 'dechetterie', 'poste', 'supermarche', 'transport', 'ecole', 'hopital', 'pharmacie', 'parc', 'eglise', 'autre']

const emptyForm = () => ({
  name: '',
  type: 'autre',
  address: '',
  phone: '',
  website: '',
  email: '',
  latitude: '',
  longitude: '',
  description: '',
  is_active: true,
  sort_order: 0,
  schedules: [],
})

const form = ref(emptyForm())

async function onReviewClaim(c, approve) {
  const res = await reviewPoiClaim(token.value, c.id, approve)
  if (res.success) {
    const claimsRes = await fetchAdminPoiClaims(token.value, 'pending')
    if (claimsRes.success) poiClaims.value = claimsRes.data || []
  }
}

const filteredPois = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return pois.value
  return pois.value.filter(p =>
    (p.name || '').toLowerCase().includes(q) ||
    (p.type || '').toLowerCase().includes(q) ||
    (p.address || '').toLowerCase().includes(q)
  )
})

async function load() {
  if (!token.value) {
    loading.value = false
    return
  }
  loading.value = true
  error.value = ''
  try {
    const res = await fetchAdminPois(token.value)
    if (res.success) {
      pois.value = res.data || []
    } else {
      error.value = res.error || 'Erreur de chargement'
    }
    const claimsRes = await fetchAdminPoiClaims(token.value, 'pending')
    if (claimsRes.success) poiClaims.value = claimsRes.data || []
  } catch (e) {
    error.value = 'Erreur réseau'
  } finally {
    loading.value = false
  }
}

function startCreate() {
  editingId.value = null
  form.value = emptyForm()
  showForm.value = true
}

function edit(poi) {
  editingId.value = poi.id
  form.value = {
    name: poi.name || '',
    type: poi.type || 'autre',
    address: poi.address || '',
    phone: poi.phone || '',
    website: poi.website || '',
    email: poi.email || '',
    latitude: poi.latitude ?? '',
    longitude: poi.longitude ?? '',
    description: poi.description || '',
    is_active: !!poi.is_active,
    sort_order: poi.sort_order || 0,
    schedules: (poi.schedules || []).map(s => ({ ...s })),
  }
  showForm.value = true
}

function closeForm() {
  showForm.value = false
  editingId.value = null
  form.value = emptyForm()
  formMessage.value = ''
}

function addScheduleField() {
  form.value.schedules.push({
    day_of_week: 0,
    open_time: '09:00',
    close_time: '18:00',
    break_start: '',
    break_end: '',
    is_closed: false,
  })
}

function removeScheduleField(idx) {
  form.value.schedules.splice(idx, 1)
}

async function save() {
  saving.value = true
  formMessage.value = ''
  try {
    const payload = {
      name: form.value.name.trim(),
      type: form.value.type,
      address: form.value.address.trim() || null,
      phone: form.value.phone.trim() || null,
      website: form.value.website.trim() || null,
      email: form.value.email.trim() || null,
      latitude: form.value.latitude === '' ? null : parseFloat(form.value.latitude),
      longitude: form.value.longitude === '' ? null : parseFloat(form.value.longitude),
      description: form.value.description.trim() || null,
      is_active: form.value.is_active ? 1 : 0,
      sort_order: Number(form.value.sort_order) || 0,
    }

    let res
    if (editingId.value) {
      res = await updateAdminPoi(token.value, editingId.value, payload)
    } else {
      res = await createAdminPoi(token.value, payload)
    }

    if (!res.success) {
      formMessage.value = res.error || 'Erreur lors de l\'enregistrement.'
      formMessageType.value = 'error'
      return
    }

    const poiId = editingId.value || res.data?.id

    // Synchroniser les horaires
    if (poiId) {
      const existingPoi = pois.value.find(p => p.id === poiId)
      const existingSchedules = existingPoi?.schedules || []

      // Supprimer les horaires existants et recréer (approche simple et sûre)
      for (const s of existingSchedules) {
        if (s.id) await deleteAdminSchedule(token.value, s.id)
      }
      for (const s of form.value.schedules) {
        await createAdminSchedule(token.value, {
          poi_id: poiId,
          day_of_week: Number(s.day_of_week),
          open_time: s.is_closed ? null : s.open_time,
          close_time: s.is_closed ? null : s.close_time,
          break_start: s.break_start || null,
          break_end: s.break_end || null,
          is_closed: s.is_closed ? 1 : 0,
        })
      }
    }

    formMessage.value = editingId.value ? 'POI mis à jour.' : 'POI créé.'
    formMessageType.value = 'success'
    await load()
    setTimeout(() => closeForm(), 800)
  } catch (e) {
    formMessage.value = 'Erreur réseau.'
    formMessageType.value = 'error'
  } finally {
    saving.value = false
  }
}

async function remove(poi) {
  if (!confirm(`Supprimer « ${poi.name} » ?`)) return
  try {
    const res = await deleteAdminPoi(token.value, poi.id)
    if (res.success) {
      await load()
    } else {
      alert(res.error || 'Erreur')
    }
  } catch (e) {
    alert('Erreur réseau')
  }
}

function formatSchedules(schedules) {
  return schedules
    .filter(s => !s.is_closed)
    .map(s => `${DAYS[s.day_of_week] || '?'} : ${s.open_time?.slice(0, 5) || ''}–${s.close_time?.slice(0, 5) || ''}`)
    .join(' — ')
}

onMounted(load)
</script>

<style scoped>
.admin-pois-view { min-height: 60vh; }
.section-header { align-items: flex-start; gap: 16px; margin-bottom: 24px; }

.auth-card {
  max-width: 420px;
  margin: 40px auto;
  text-align: center;
  padding: 40px 24px;
}

.filters {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
  align-items: center;
  flex-wrap: wrap;
}
.filters input { flex: 1 1 240px; min-width: 200px; }

.poi-list { display: flex; flex-direction: column; gap: 16px; }
.poi-card { padding: 20px; }
.poi-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 12px; }
.poi-header h3 { margin-bottom: 8px; }
.poi-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.poi-address { color: var(--c-text-2); font-size: 0.9rem; margin-bottom: 8px; }
.poi-meta { color: var(--c-text-2); font-size: 0.9rem; margin-bottom: 4px; }
.poi-schedules { font-size: 0.85rem; color: var(--c-text-3); }

.empty-state { text-align: center; padding: 60px 20px; }
.empty-icon { font-size: 3rem; margin-bottom: 16px; }

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.modal {
  background: var(--c-white, #fff);
  border-radius: var(--r-lg, 16px);
  width: 100%;
  max-width: 680px;
  max-height: 90vh;
  overflow-y: auto;
  padding: 28px;
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}
.btn-close {
  background: none;
  border: none;
  font-size: 1.4rem;
  cursor: pointer;
}

.form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
.form-group label { font-size: 0.85rem; font-weight: 600; color: var(--c-text-2); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-checkbox-group { display: flex; align-items: flex-end; padding-bottom: 8px; }
.form-checkbox-group label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500; }

.schedules-fieldset { border: 1px solid var(--c-border); border-radius: var(--r-md); padding: 16px; margin: 16px 0; }
.schedules-fieldset legend { padding: 0 8px; font-weight: 600; }
.schedule-row { display: grid; grid-template-columns: 1.2fr 0.9fr 0.9fr auto auto; gap: 8px; align-items: center; margin-bottom: 8px; }
.schedule-closed { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; white-space: nowrap; }

.form-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px; }
.auth-message { margin-top: 16px; padding: 12px 16px; border-radius: var(--r-md); font-size: 0.9rem; }
.auth-message.success { background: rgba(45, 106, 79, 0.1); color: var(--c-green-dark); }
.auth-message.error { background: rgba(183, 28, 28, 0.08); color: #b71c1c; }

.btn-danger { background: #b71c1c; color: #fff; border-color: #b71c1c; }
.btn-danger:hover { background: #9b1515; }

@media (max-width: 600px) {
  .form-row { grid-template-columns: 1fr; }
  .schedule-row { grid-template-columns: 1fr 1fr; }
  .poi-header { flex-direction: column; }
}
.claims-section { padding: 16px 20px; margin-bottom: 16px; }
.claims-section h2 { font-size: 1.05rem; margin-bottom: 8px; }
.claims-list { list-style: none; padding: 0; margin: 0; }
.claims-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 0; border-top: 1px solid #e2e8f0; flex-wrap: wrap; }
.claims-actions { display: flex; gap: 8px; }
</style>
