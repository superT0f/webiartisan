<template>
  <div class="container section dashboard">
    <div class="dashboard-header flex-between">
      <div>
        <h1>Mes services</h1>
        <p class="text-muted">Gérez les services affichés sur votre fiche artisan.</p>
      </div>
      <RouterLink to="/espace" class="btn btn-outline">Retour à l'espace</RouterLink>
    </div>

    <div v-if="error" class="error-banner">{{ error }}</div>

    <section class="dashboard-section card">
      <p v-if="activeServicesCount >= 5" class="limit-warning">
        Limite gratuite atteinte (5 services actifs).
      </p>

      <form @submit.prevent="addService" class="service-form">
        <div class="form-group">
          <label for="catalog_id">Ajouter depuis le catalogue</label>
          <select id="catalog_id" v-model="newService.catalog_id" class="form-input">
            <option :value="null">— Personnalisé —</option>
            <option v-for="c in catalog" :key="c.id" :value="c.id">{{ c.icon }} {{ c.label }}</option>
          </select>
        </div>

        <div class="form-group">
          <label for="service_name">Nom du service *</label>
          <input id="service_name" v-model="newService.name" type="text" class="form-input" required />
        </div>

        <div class="form-group">
          <label for="service_description">Description</label>
          <textarea id="service_description" v-model="newService.description" class="form-input" rows="2"></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="service_price">Fourchette de prix</label>
            <input id="service_price" v-model="newService.price_range" type="text" class="form-input" placeholder="Ex: 20€-50€" />
          </div>
          <div class="form-group">
            <label for="service_duration">Durée</label>
            <input id="service_duration" v-model="newService.duration" type="text" class="form-input" placeholder="Ex: 1h" />
          </div>
        </div>

        <button type="submit" class="btn btn-primary" :disabled="activeServicesCount >= 5 || adding">
          {{ adding ? 'Ajout…' : 'Ajouter' }}
        </button>
      </form>
    </section>

    <section class="dashboard-section card">
      <div class="section-title">
        <h2>Services actifs</h2>
        <span class="badge badge-grey">{{ activeServicesCount }} / 5</span>
      </div>

      <ul v-if="services.length" class="service-list">
        <li v-for="s in services" :key="s.id" class="service-item">
          <div>
            <strong>{{ s.catalog_icon || s.icon }} {{ s.name }}</strong>
            <p v-if="s.description" class="text-muted small">{{ s.description }}</p>
            <p v-if="s.price_range || s.duration" class="text-muted small">
              <span v-if="s.price_range">{{ s.price_range }}</span>
              <span v-if="s.price_range && s.duration"> · </span>
              <span v-if="s.duration">{{ s.duration }}</span>
            </p>
          </div>
          <div class="service-actions">
            <button class="btn btn-outline btn-sm" @click="toggleActive(s)">
              {{ s.is_active ? 'Désactiver' : 'Activer' }}
            </button>
            <button class="btn btn-outline btn-sm" @click="removeService(s.id)">Supprimer</button>
          </div>
        </li>
      </ul>
      <p v-else class="text-muted">Aucun service configuré.</p>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import {
  fetchMyServices,
  fetchServiceCatalog,
  createArtisanService,
  updateArtisanService,
  deleteArtisanService,
} from '../../api.js'

const props = defineProps({
  token: { type: String, default: '' },
})

const artisanToken = computed(() => props.token || localStorage.getItem('artisan_token') || '')

const services = ref([])
const catalog = ref([])
const adding = ref(false)
const error = ref('')
const activeServicesCount = computed(() => services.value.filter(s => s.is_active).length)
const newService = ref({
  catalog_id: null,
  name: '',
  description: '',
  price_range: '',
  duration: '',
})

async function load() {
  if (!artisanToken.value) return
  error.value = ''
  try {
    const [svcRes, catRes] = await Promise.all([
      fetchMyServices(artisanToken.value),
      fetchServiceCatalog(),
    ])
    services.value = svcRes.data || []
    catalog.value = catRes.data || []
  } catch (e) {
    console.error('Erreur chargement services', e)
    error.value = 'Impossible de charger vos services.'
  }
}

async function addService() {
  if (activeServicesCount.value >= 5) return
  error.value = ''
  adding.value = true
  try {
    const res = await createArtisanService(artisanToken.value, {
      service_catalog_id: newService.value.catalog_id,
      name: newService.value.name,
      description: newService.value.description,
      price_range: newService.value.price_range,
      duration: newService.value.duration,
      is_custom: !newService.value.catalog_id,
    })
    if (!res.success) {
      error.value = res.error || 'Erreur lors de l\'ajout'
      return
    }
    newService.value = { catalog_id: null, name: '', description: '', price_range: '', duration: '' }
    await load()
  } catch (e) {
    console.error('Erreur ajout service', e)
    error.value = 'Impossible d\'ajouter le service.'
  } finally {
    adding.value = false
  }
}

async function toggleActive(s) {
  if (!s.is_active && activeServicesCount.value >= 5) {
    error.value = 'Vous ne pouvez pas activer plus de 5 services.'
    return
  }
  error.value = ''
  try {
    await updateArtisanService(artisanToken.value, s.id, { is_active: !s.is_active })
    await load()
  } catch (e) {
    console.error('Erreur mise à jour service', e)
    error.value = 'Impossible de mettre à jour le service.'
  }
}

async function removeService(id) {
  if (!confirm('Supprimer ce service ?')) return
  error.value = ''
  try {
    await deleteArtisanService(artisanToken.value, id)
    await load()
  } catch (e) {
    console.error('Erreur suppression service', e)
    error.value = 'Impossible de supprimer le service.'
  }
}

onMounted(load)
</script>

<style scoped>
.dashboard { max-width: 720px; }
.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 24px;
}
.dashboard-header h1 { margin-bottom: 4px; }

.dashboard-section { padding: 24px; margin-bottom: 24px; }
.section-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}
.section-title h2 { font-size: 1.2rem; margin-bottom: 0; }

.service-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.limit-warning {
  color: #b71c1c;
  margin-bottom: 16px;
  font-weight: 600;
}
.error-banner {
  background: #ffebee;
  color: #b71c1c;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  margin-bottom: 24px;
}

.service-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.service-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  padding: 14px;
  background: var(--c-cream);
  border-radius: var(--r-md);
}
.service-item strong { display: block; margin-bottom: 4px; }
.service-actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.form-group label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--c-text-2);
}
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

@media (max-width: 600px) {
  .form-row { grid-template-columns: 1fr; }
  .service-actions { flex-direction: column; }
  .dashboard-header { flex-direction: column; }
}
</style>
