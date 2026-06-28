<template>
  <div class="recipes-view section">
    <div class="container">
      <div class="section-header flex-between">
        <div>
          <h1>Recettes locales</h1>
          <p class="text-muted">Cuisinez avec les produits des artisans de {{ CITY_NAME }}.</p>
        </div>
        <button class="btn btn-primary btn-sm" @click="router.push('/recette/nouvelle')">
          Proposer une recette
        </button>
      </div>

      <div class="filters">
        <div class="search-bar filter-search">
          <span class="search-icon">🔍</span>
          <input
            v-model="search"
            type="text"
            class="search-input"
            placeholder="Rechercher une recette..."
          />
        </div>
        <select v-model="difficulty" class="form-select">
          <option value="">Difficulté</option>
          <option v-for="d in difficulties" :key="d.value" :value="d.value">{{ d.label }}</option>
        </select>
        <select v-model="season" class="form-select">
          <option value="">Saison</option>
          <option v-for="s in seasons" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
      </div>

      <div v-if="loading" class="skeleton" style="height: 300px; border-radius: 12px;"></div>

      <div v-else class="recipe-grid">
        <RouterLink
          v-for="r in filtered"
          :key="r.id"
          :to="`/recette/${r.slug}`"
          class="card recipe-card"
        >
          <img v-if="r.image_url" :src="r.image_url" :alt="r.title" />
          <div v-else class="recipe-no-image">🍽️</div>
          <div class="card-body">
            <h3>{{ r.title }}</h3>
            <p class="text-muted recipe-desc">{{ r.description }}</p>
            <div class="recipe-meta">
              <span class="badge badge-grey">{{ r.prep_time_minutes + r.cook_time_minutes }} min</span>
              <span class="badge badge-grey">{{ difficultyLabel(r.difficulty) }}</span>
              <span v-if="r.is_incomplete" class="badge badge-gold">Incomplète</span>
              <span v-if="r.is_premium" class="badge badge-green">Premium</span>
            </div>
          </div>
        </RouterLink>

        <div v-if="!filtered.length" class="empty-state">
          <div class="empty-icon">🍳</div>
          <h3>Aucune recette trouvée</h3>
          <p>Essayez d'élargir vos critères ou proposez la vôtre.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getRecipes, CITY_NAME } from '../api.js'

const recipes = ref([])
const search = ref('')
const difficulty = ref('')
const season = ref('')
const loading = ref(true)
const router = useRouter()

const difficulties = [
  { value: 'very_easy', label: 'Très facile' },
  { value: 'easy', label: 'Facile' },
  { value: 'medium', label: 'Moyen' },
  { value: 'hard', label: 'Difficile' },
]

const seasons = [
  { value: 'spring', label: 'Printemps' },
  { value: 'summer', label: 'Été' },
  { value: 'autumn', label: 'Automne' },
  { value: 'winter', label: 'Hiver' },
  { value: 'all', label: 'Toute saison' },
]

function difficultyLabel(value) {
  return difficulties.find(d => d.value === value)?.label || value
}

const filtered = computed(() => {
  const q = search.value.toLowerCase().trim()
  return recipes.value.filter(r => {
    const matchesSearch = !q ||
      (r.title || '').toLowerCase().includes(q) ||
      (r.description || '').toLowerCase().includes(q)
    const matchesDifficulty = !difficulty.value || r.difficulty === difficulty.value
    const matchesSeason = !season.value || r.season === season.value
    return matchesSearch && matchesDifficulty && matchesSeason
  })
})

onMounted(async () => {
  try {
    const res = await getRecipes()
    recipes.value = res.data || []
  } catch (e) {
    console.error('Erreur chargement recettes', e)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.recipes-view { min-height: 60vh; }
.section-header { align-items: flex-start; gap: 16px; }

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 28px;
  align-items: center;
}
.filter-search { flex: 1 1 260px; max-width: 360px; margin-bottom: 0; }
.filters select { width: auto; min-width: 160px; }

.recipe-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
}
.recipe-card {
  display: flex;
  flex-direction: column;
  text-decoration: none;
  color: inherit;
}
.recipe-card img {
  width: 100%;
  height: 160px;
  object-fit: cover;
}
.recipe-no-image {
  height: 160px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
  background: var(--c-cream-2);
}
.recipe-card h3 { margin-bottom: 8px; }
.recipe-desc {
  font-size: 0.9rem;
  margin-bottom: 12px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.recipe-meta { display: flex; gap: 8px; flex-wrap: wrap; }

.empty-state { text-align: center; padding: 60px 20px; grid-column: 1 / -1; }
.empty-icon { font-size: 3rem; margin-bottom: 16px; }
</style>
