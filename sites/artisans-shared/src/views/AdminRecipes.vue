<template>
  <div class="admin-recipes-view section">
    <div class="container">
      <div class="section-header flex-between">
        <div>
          <h1>Modération des recettes</h1>
          <p class="text-muted">Gérez et archivez les recettes proposées.</p>
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
          <select v-model="statusFilter" class="form-select">
            <option value="">Tous les statuts</option>
            <option value="published">Publiées</option>
            <option value="reported">Signalées</option>
            <option value="archived">Archivées</option>
          </select>
          <button class="btn btn-outline btn-sm" @click="load">🔄 Actualiser</button>
        </div>

        <div v-if="loading" class="skeleton" style="height: 300px; border-radius: 12px;"></div>

        <div v-else-if="error" class="alert alert-error">
          {{ error }}
        </div>

        <div v-else-if="!recipes.length" class="empty-state card">
          <div class="empty-icon">🍳</div>
          <h3>Aucune recette à modérer</h3>
        </div>

        <div v-else class="recipe-list">
          <div v-for="r in recipes" :key="r.id" class="recipe-row card">
            <div class="recipe-info">
              <div class="recipe-main">
                <img v-if="r.image_url" :src="r.image_url" :alt="r.title" class="recipe-thumb" />
                <div v-else class="recipe-thumb-placeholder">🍽️</div>
                <div>
                  <h3>{{ r.title }}</h3>
                  <p class="text-muted small">Par {{ r.submitted_by || 'Anonyme' }} — {{ r.city_name }}</p>
                  <div class="recipe-meta">
                    <span class="badge" :class="statusClass(r.status)">{{ statusLabel(r.status) }}</span>
                    <span v-if="r.is_incomplete" class="badge badge-gold">Incomplète</span>
                    <span v-if="r.is_premium" class="badge badge-green">Premium</span>
                  </div>
                </div>
              </div>
              <div class="recipe-actions">
                <RouterLink :to="`/recette/${r.slug}`" target="_blank" class="btn btn-outline btn-sm">Voir</RouterLink>
                <button
                  v-if="r.status !== 'archived'"
                  class="btn btn-outline btn-sm"
                  :disabled="archiving === r.id"
                  @click="archive(r)"
                >
                  {{ archiving === r.id ? 'Archivage…' : 'Archiver' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { getAdminRecipes, archiveRecipe } from '../api.js'
const STORAGE_KEY = 'artisan_token'
const token = ref(localStorage.getItem(STORAGE_KEY) || '')
const recipes = ref([])
const loading = ref(true)
const error = ref('')
const statusFilter = ref('')
const archiving = ref(null)

const filters = computed(() => {
  const f = {}
  if (statusFilter.value) f.status = statusFilter.value
  return f
})

function statusLabel(status) {
  const map = { published: 'Publiée', reported: 'Signalée', archived: 'Archivée' }
  return map[status] || status
}

function statusClass(status) {
  if (status === 'published') return 'badge-green'
  if (status === 'reported') return 'badge-gold'
  if (status === 'archived') return 'badge-grey'
  return 'badge-grey'
}

async function load() {
  if (!token.value) {
    loading.value = false
    return
  }
  loading.value = true
  error.value = ''
  try {
    const res = await getAdminRecipes(token.value, filters.value)
    if (res.success) {
      recipes.value = res.data || []
    } else {
      error.value = res.error || 'Erreur lors du chargement'
      if (res.error?.includes('administrateur')) {
        localStorage.removeItem(STORAGE_KEY)
        token.value = ''
      }
    }
  } catch (e) {
    error.value = 'Erreur réseau'
  } finally {
    loading.value = false
  }
}

async function archive(r) {
  if (!confirm(`Archiver la recette « ${r.title} » ?`)) return
  archiving.value = r.id
  try {
    const res = await archiveRecipe(token.value, r.id)
    if (res.success) {
      r.status = 'archived'
    } else {
      alert(res.error || 'Erreur')
    }
  } catch (e) {
    alert('Erreur réseau')
  } finally {
    archiving.value = null
  }
}

watch(statusFilter, load)

onMounted(() => {
  load()
})
</script>

<style scoped>
.admin-recipes-view { min-height: 60vh; }
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
}
.filters select { width: auto; min-width: 180px; }

.recipe-list { display: flex; flex-direction: column; gap: 16px; }
.recipe-row { padding: 20px; }
.recipe-row:hover { transform: none; box-shadow: 0 4px 16px var(--c-shadow); }

.recipe-info {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.recipe-main {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: 1 1 300px;
}
.recipe-thumb {
  width: 72px;
  height: 72px;
  object-fit: cover;
  border-radius: var(--r-md);
  flex-shrink: 0;
}
.recipe-thumb-placeholder {
  width: 72px;
  height: 72px;
  border-radius: var(--r-md);
  background: var(--c-cream-2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.6rem;
  flex-shrink: 0;
}
.recipe-main h3 { margin-bottom: 4px; font-size: 1.1rem; }
.recipe-meta { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
.recipe-actions { display: flex; gap: 10px; }

.empty-state { text-align: center; padding: 60px 20px; }
.empty-icon { font-size: 3rem; margin-bottom: 16px; }

@media (max-width: 600px) {
  .recipe-info { flex-direction: column; align-items: flex-start; }
  .recipe-actions { width: 100%; }
}
</style>
