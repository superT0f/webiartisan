<template>
  <div class="admin-dashboard-view section">
    <div class="container">
      <div class="section-header flex-between">
        <div>
          <h1>Administration</h1>
          <p class="text-muted">Gérez les artisans et leurs abonnements.</p>
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
                <td>{{ a.subscription_status || '—' }}</td>
                <td>
                  <div class="actions">
                    <RouterLink :to="`/espace/admin/artisans/${a.id}`" class="btn btn-outline btn-sm">
                      Modifier
                    </RouterLink>
                    <button
                      v-if="a.status !== 'active'"
                      class="btn btn-outline btn-sm"
                      :disabled="acting === a.id"
                      @click="activate(a)"
                    >
                      Activer
                    </button>
                    <button
                      v-if="a.status !== 'suspended'"
                      class="btn btn-outline btn-sm"
                      :disabled="acting === a.id"
                      @click="suspend(a)"
                    >
                      Suspendre
                    </button>
                    <button
                      class="btn btn-sm"
                      :class="a.plan === 'premium' ? 'btn-outline' : 'btn-gold'"
                      :disabled="acting === a.id"
                      @click="togglePlan(a)"
                    >
                      {{ a.plan === 'premium' ? 'Passer Gratuit' : 'Passer Premium' }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { getArtisanToken, fetchAdminArtisans, activateArtisan, suspendArtisan, setArtisanPlan } from '../api.js'

const token = ref(getArtisanToken())
const artisans = ref([])
const loading = ref(true)
const error = ref('')
const search = ref('')
const acting = ref(null)

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

async function activate(a) {
  acting.value = a.id
  try {
    const res = await activateArtisan(token.value, a.id)
    if (res.success) {
      a.status = 'active'
    } else {
      alert(res.error || 'Erreur')
    }
  } catch (e) {
    alert('Erreur réseau')
  } finally {
    acting.value = null
  }
}

async function suspend(a) {
  if (!confirm(`Suspendre « ${a.company_name || a.email} » ?`)) return
  acting.value = a.id
  try {
    const res = await suspendArtisan(token.value, a.id)
    if (res.success) {
      a.status = 'suspended'
    } else {
      alert(res.error || 'Erreur')
    }
  } catch (e) {
    alert('Erreur réseau')
  } finally {
    acting.value = null
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

onMounted(() => {
  load()
})
</script>

<style scoped>
.admin-dashboard-view { min-height: 60vh; }
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

@media (max-width: 900px) {
  .artisan-table th,
  .artisan-table td { padding: 12px; }
}
</style>
