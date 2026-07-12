<template>
  <div class="admin-accounts-view section">
    <div class="container">
      <div class="section-header flex-between">
        <div>
          <h1>Gestion des comptes artisans</h1>
          <p class="text-muted">Réinitialisation, mot de passe et abonnements.</p>
        </div>
        <div class="header-actions">
          <RouterLink to="/espace/admin" class="btn btn-outline btn-sm">← Administration</RouterLink>
          <RouterLink to="/espace" class="btn btn-outline btn-sm">Mon espace</RouterLink>
        </div>
      </div>

      <div v-if="!token" class="auth-card card">
        <div class="empty-icon">🔐</div>
        <h3>Connexion requise</h3>
        <p>Vous devez être connecté avec un compte administrateur.</p>
        <RouterLink to="/espace" class="btn btn-primary" style="margin-top: 16px;">Me connecter</RouterLink>
      </div>

      <template v-else>
        <div class="filters">
          <input
            v-model="search"
            type="text"
            class="form-input"
            placeholder="Rechercher un artisan…"
          />
          <button class="btn btn-outline btn-sm" @click="load">🔄 Actualiser</button>
        </div>

        <div v-if="loading" class="skeleton" style="height: 300px; border-radius: 12px;"></div>

        <div v-else-if="error" class="alert alert-error">
          {{ error }}
        </div>

        <div v-else-if="!filteredArtisans.length" class="empty-state card">
          <div class="empty-icon">🏪</div>
          <h3>Aucun artisan trouvé</h3>
        </div>

        <div v-else class="table-wrapper card">
          <table class="artisan-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Entreprise</th>
                <th>Email</th>
                <th>Ville</th>
                <th>Statut</th>
                <th>Plan</th>
                <th>Admin</th>
                <th>Abonnement</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="a in filteredArtisans" :key="a.id">
                <td>{{ a.id }}</td>
                <td>{{ a.company_name || '—' }}</td>
                <td>{{ a.email || '—' }}</td>
                <td>{{ a.city_name || '—' }}</td>
                <td>
                  <span class="badge" :class="statusClass(a.status)">
                    {{ statusLabel(a.status) }}
                  </span>
                </td>
                <td>
                  <span class="badge" :class="planClass(a.plan)">
                    {{ a.plan === 'premium' ? 'Premium' : 'Gratuit' }}
                  </span>
                </td>
                <td>
                  <span class="badge" :class="a.is_admin ? 'badge-admin' : 'badge-grey'">
                    {{ a.is_admin ? 'Admin' : 'Non' }}
                  </span>
                </td>
                <td>{{ subscriptionLabel(a.subscription_status) }}</td>
                <td>
                  <div class="actions">
                    <button
                      class="btn btn-outline btn-sm"
                      :disabled="acting === a.id"
                      @click="openForcePassword(a)"
                    >
                      Forcer MDP
                    </button>
                    <button
                      class="btn btn-outline btn-sm"
                      :disabled="acting === a.id || a.status !== 'active'"
                      @click="resetPassword(a)"
                    >
                      Lien magique
                    </button>
                    <button
                      class="btn btn-sm"
                      :class="a.plan === 'premium' ? 'btn-outline' : 'btn-gold'"
                      :disabled="acting === a.id"
                      @click="togglePlan(a)"
                    >
                      {{ a.plan === 'premium' ? 'Passer Gratuit' : 'Passer Premium' }}
                    </button>
                    <button
                      class="btn btn-outline btn-sm"
                      :disabled="acting === a.id"
                      @click="openSubscription(a)"
                    >
                      Abonnement
                    </button>
                    <button
                      class="btn btn-sm"
                      :class="a.is_admin ? 'btn-danger' : 'btn-primary'"
                      :disabled="acting === a.id"
                      @click="toggleAdmin(a)"
                    >
                      {{ a.is_admin ? 'Retirer admin' : 'Promouvoir admin' }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- Modal forcer mot de passe -->
      <div v-if="showForceModal" class="modal-overlay" @click.self="closeForcePassword">
        <div class="modal">
          <div class="modal-header">
            <h2>Forcer le mot de passe</h2>
            <button class="btn-close" @click="closeForcePassword">✕</button>
          </div>
          <p class="modal-subtitle">{{ selectedArtisan?.company_name || selectedArtisan?.email }}</p>
          <form @submit.prevent="confirmForcePassword">
            <div class="form-group">
              <label for="force-password">Nouveau mot de passe</label>
              <input
                id="force-password"
                v-model="forcePassword"
                type="password"
                class="form-input"
                minlength="8"
                required
                placeholder="Min. 8 caractères"
              />
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary" :disabled="forceSaving">
                {{ forceSaving ? 'Enregistrement…' : 'Forcer le mot de passe' }}
              </button>
              <button type="button" class="btn btn-outline" @click="closeForcePassword">Annuler</button>
            </div>
            <div v-if="forceMessage" class="auth-message" :class="forceMessageType">
              {{ forceMessage }}
            </div>
          </form>
        </div>
      </div>

      <!-- Modal abonnement -->
      <div v-if="showSubscriptionModal" class="modal-overlay" @click.self="closeSubscription">
        <div class="modal">
          <div class="modal-header">
            <h2>Gérer l'abonnement</h2>
            <button class="btn-close" @click="closeSubscription">✕</button>
          </div>
          <p class="modal-subtitle">{{ selectedArtisan?.company_name || selectedArtisan?.email }}</p>
          <form @submit.prevent="confirmSubscription">
            <div class="form-group">
              <label for="sub-status">Statut d'abonnement</label>
              <select id="sub-status" v-model="subscriptionStatus" class="form-input">
                <option value="">— Aucun / non renseigné —</option>
                <option value="trialing">Période d'essai</option>
                <option value="active">Actif</option>
                <option value="past_due">En retard</option>
                <option value="unpaid">Impayé</option>
                <option value="canceled">Annulé</option>
                <option value="incomplete">Incomplet</option>
              </select>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary" :disabled="subSaving">
                {{ subSaving ? 'Enregistrement…' : 'Mettre à jour' }}
              </button>
              <button type="button" class="btn btn-outline" @click="closeSubscription">Annuler</button>
            </div>
            <div v-if="subMessage" class="auth-message" :class="subMessageType">
              {{ subMessage }}
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import {
  getArtisanToken,
  fetchAdminArtisans,
  setArtisanPlan,
  setArtisanAdmin,
  resetArtisanPassword,
  forceArtisanPassword,
  setArtisanSubscriptionStatus,
} from '../api.js'

const token = ref(getArtisanToken())
const artisans = ref([])
const loading = ref(true)
const error = ref('')
const search = ref('')
const acting = ref(null)

const showForceModal = ref(false)
const selectedArtisan = ref(null)
const forcePassword = ref('')
const forceSaving = ref(false)
const forceMessage = ref('')
const forceMessageType = ref('')

const showSubscriptionModal = ref(false)
const subscriptionStatus = ref('')
const subSaving = ref(false)
const subMessage = ref('')
const subMessageType = ref('')

const filteredArtisans = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return artisans.value
  return artisans.value.filter(a =>
    (a.company_name || '').toLowerCase().includes(q) ||
    (a.email || '').toLowerCase().includes(q) ||
    (a.city_name || '').toLowerCase().includes(q)
  )
})

function statusLabel(status) {
  const map = { pending: 'En attente', active: 'Actif', suspended: 'Suspendu' }
  return map[status] || status
}

function statusClass(status) {
  if (status === 'active') return 'badge-green'
  if (status === 'suspended') return 'badge-red'
  return 'badge-gold'
}

function planClass(plan) {
  return plan === 'premium' ? 'badge-gold' : 'badge-grey'
}

function subscriptionLabel(status) {
  if (!status) return '—'
  const map = {
    trialing: 'Essai',
    active: 'Actif',
    past_due: 'En retard',
    unpaid: 'Impayé',
    canceled: 'Annulé',
    incomplete: 'Incomplet',
  }
  return map[status] || status
}

async function load() {
  if (!token.value) {
    loading.value = false
    return
  }
  loading.value = true
  error.value = ''
  try {
    const res = await fetchAdminArtisans(token.value)
    if (res.success) {
      artisans.value = res.data || []
    } else {
      error.value = res.error || 'Erreur lors du chargement'
      if (res.status === 401 || res.status === 403) {
        artisans.value = []
      }
    }
  } catch (e) {
    error.value = 'Erreur réseau'
  } finally {
    loading.value = false
  }
}

async function resetPassword(a) {
  if (!confirm(`Envoyer un lien magique à « ${a.company_name || a.email} » ?`)) return
  acting.value = a.id
  try {
    const res = await resetArtisanPassword(token.value, a.id)
    alert(res.success ? res.message : (res.error || 'Erreur'))
  } catch (e) {
    alert('Erreur réseau')
  } finally {
    acting.value = null
  }
}

function openForcePassword(a) {
  selectedArtisan.value = a
  forcePassword.value = ''
  forceMessage.value = ''
  showForceModal.value = true
}

function closeForcePassword() {
  showForceModal.value = false
  selectedArtisan.value = null
  forcePassword.value = ''
  forceMessage.value = ''
}

async function confirmForcePassword() {
  if (!selectedArtisan.value) return
  const password = forcePassword.value.trim()
  if (password.length < 8) {
    forceMessage.value = 'Le mot de passe doit faire au moins 8 caractères.'
    forceMessageType.value = 'error'
    return
  }
  forceSaving.value = true
  forceMessage.value = ''
  try {
    const res = await forceArtisanPassword(token.value, selectedArtisan.value.id, password)
    if (res.success) {
      forceMessage.value = res.message || 'Mot de passe mis à jour.'
      forceMessageType.value = 'success'
      setTimeout(() => closeForcePassword(), 1000)
    } else {
      forceMessage.value = res.error || 'Erreur'
      forceMessageType.value = 'error'
    }
  } catch (e) {
    forceMessage.value = 'Erreur réseau'
    forceMessageType.value = 'error'
  } finally {
    forceSaving.value = false
  }
}

async function togglePlan(a) {
  const newPlan = a.plan === 'premium' ? 'free' : 'premium'
  if (!confirm(`Passer « ${a.company_name || a.email} » au plan ${newPlan} ?`)) return
  acting.value = a.id
  try {
    const res = await setArtisanPlan(token.value, a.id, newPlan)
    if (res.success) {
      a.plan = newPlan
    } else {
      alert(res.error || 'Erreur')
    }
  } catch (e) {
    alert('Erreur réseau')
  } finally {
    acting.value = null
  }
}

async function toggleAdmin(a) {
  const newAdmin = !a.is_admin
  const actionLabel = newAdmin ? 'promouvoir admin' : 'retirer les droits admin'
  if (!confirm(`${actionLabel.charAt(0).toUpperCase() + actionLabel.slice(1)} « ${a.company_name || a.email} » ?`)) return
  acting.value = a.id
  try {
    const res = await setArtisanAdmin(token.value, a.id, newAdmin)
    if (res.success) {
      a.is_admin = newAdmin
    } else {
      alert(res.error || 'Erreur')
    }
  } catch (e) {
    alert('Erreur réseau')
  } finally {
    acting.value = null
  }
}

function openSubscription(a) {
  selectedArtisan.value = a
  subscriptionStatus.value = a.subscription_status || ''
  subMessage.value = ''
  showSubscriptionModal.value = true
}

function closeSubscription() {
  showSubscriptionModal.value = false
  selectedArtisan.value = null
  subscriptionStatus.value = ''
  subMessage.value = ''
}

async function confirmSubscription() {
  if (!selectedArtisan.value) return
  subSaving.value = true
  subMessage.value = ''
  try {
    const status = subscriptionStatus.value === '' ? null : subscriptionStatus.value
    const res = await setArtisanSubscriptionStatus(token.value, selectedArtisan.value.id, status)
    if (res.success) {
      selectedArtisan.value.subscription_status = status
      subMessage.value = res.message || 'Statut mis à jour.'
      subMessageType.value = 'success'
      setTimeout(() => closeSubscription(), 1000)
    } else {
      subMessage.value = res.error || 'Erreur'
      subMessageType.value = 'error'
    }
  } catch (e) {
    subMessage.value = 'Erreur réseau'
    subMessageType.value = 'error'
  } finally {
    subSaving.value = false
  }
}

onMounted(() => {
  load()
})
</script>

<style scoped>
.admin-accounts-view { min-height: 60vh; }
.section-header { align-items: flex-start; gap: 16px; margin-bottom: 24px; }
.header-actions { display: flex; gap: 8px; flex-wrap: wrap; }

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

.table-wrapper { overflow-x: auto; padding: 0; }
.artisan-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}
.artisan-table th,
.artisan-table td {
  padding: 14px 16px;
  text-align: left;
  border-bottom: 1px solid var(--c-border, #e8e8e8);
  white-space: nowrap;
}
.artisan-table th {
  font-weight: 600;
  color: var(--c-text-2);
  background: var(--c-cream, #faf9f6);
}
.artisan-table tbody tr:last-child td { border-bottom: none; }
.artisan-table tbody tr:hover { background: var(--c-cream-2, #f5f4f0); }

.actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.empty-state { text-align: center; padding: 60px 20px; }
.empty-icon { font-size: 3rem; margin-bottom: 16px; }

.badge-red { background: #FFEBEE; color: #B71C1C; }
.badge-admin { background: #E3F2FD; color: #0D47A1; }
.btn-danger { background: #b71c1c; color: #fff; border-color: #b71c1c; }
.btn-danger:hover { background: #9b1515; }

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
  max-width: 480px;
  max-height: 90vh;
  overflow-y: auto;
  padding: 28px;
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}
.modal-subtitle {
  color: var(--c-text-2);
  margin-bottom: 20px;
  font-size: 0.95rem;
}
.btn-close {
  background: none;
  border: none;
  font-size: 1.4rem;
  cursor: pointer;
}

.form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
.form-group label { font-size: 0.85rem; font-weight: 600; color: var(--c-text-2); }
.form-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px; }
.auth-message { margin-top: 16px; padding: 12px 16px; border-radius: var(--r-md); font-size: 0.9rem; }
.auth-message.success { background: rgba(45, 106, 79, 0.1); color: var(--c-green-dark); }
.auth-message.error { background: rgba(183, 28, 28, 0.08); color: #b71c1c; }

@media (max-width: 900px) {
  .artisan-table th,
  .artisan-table td { padding: 12px; }
  .section-header { flex-direction: column; }
}
</style>
