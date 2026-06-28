<template>
  <div class="recipe-detail section">
    <div class="container">
      <div v-if="loading" class="skeleton" style="height: 300px; border-radius: 12px;"></div>

      <template v-else-if="recipe">
        <div class="detail-header">
          <RouterLink to="/recettes" class="back-link">← Retour aux recettes</RouterLink>
          <img v-if="recipe.image_url" :src="recipe.image_url" :alt="recipe.title" class="hero" />
          <h1>{{ recipe.title }}</h1>
          <div class="detail-meta">
            <span class="badge badge-grey">Par {{ recipe.submitted_by || 'Anonyme' }}</span>
            <span class="badge badge-grey">{{ totalTime }} min</span>
            <span class="badge badge-grey">{{ difficultyLabel(recipe.difficulty) }}</span>
            <span v-if="recipe.is_incomplete" class="badge badge-gold">Incomplète</span>
            <span v-if="recipe.is_premium" class="badge badge-green">Premium</span>
          </div>
        </div>

        <div v-if="recipe.is_incomplete" class="alert alert-info">
          Cette recette est incomplète : contribuez en proposant un complément !
        </div>

        <div class="servings card">
          <label class="form-label">Portions :</label>
          <input type="number" min="1" v-model.number="servings" class="form-input servings-input" />
        </div>

        <div class="card info-card">
          <h2>Ingrédients</h2>
          <ul class="ingredient-list">
            <li v-for="ing in recipe.ingredients" :key="ing.id">
              <span v-if="ing.is_local" class="badge badge-green local-badge">local</span>
              <strong>{{ scaledQuantity(ing.quantity) }}</strong> {{ ing.unit }} {{ ing.name }}
              <span v-if="ing.is_optional" class="text-muted">(optionnel)</span>
            </li>
          </ul>
        </div>

        <div class="card info-card">
          <h2>Préparation</h2>
          <ol class="step-list">
            <li v-for="step in recipe.steps" :key="step.id">
              <strong>Étape {{ step.step_number }}</strong>
              <p>{{ step.instruction }}</p>
            </li>
          </ol>
        </div>

        <div v-if="recipe.artisans?.length" class="card info-card">
          <h2>Artisans associés</h2>
          <div v-for="a in recipe.artisans" :key="a.id" class="artisan-row">
            <strong>{{ a.company_name }}</strong>
            <p v-if="a.phone" class="text-muted small">{{ a.phone }}</p>
          </div>
        </div>

        <div v-if="recipe.variants?.length" class="card info-card">
          <h2>Variantes / compléments</h2>
          <div
            v-for="v in recipe.variants"
            :key="v.id"
            class="variant-row"
            @click="router.push(`/recette/${v.slug}`)"
          >
            <strong>{{ v.title }}</strong>
            <p class="text-muted small">{{ v.description }}</p>
          </div>
        </div>

        <div class="actions">
          <button class="btn btn-primary" @click="router.push(`/recette/${recipe.id}/suggérer`)">
            Proposer un complément
          </button>
          <button class="btn btn-outline" @click="reporting = true">Signaler</button>
        </div>

        <div v-if="reporting" class="card report-card">
          <label class="form-label">Motif du signalement</label>
          <textarea v-model="reportReason" class="form-textarea" placeholder="Décrivez le problème"></textarea>
          <div class="form-actions">
            <button class="btn btn-primary" @click="report" :disabled="!reportReason">Envoyer</button>
            <button class="btn btn-outline btn-sm" @click="reporting = false">Annuler</button>
          </div>
        </div>
      </template>

      <div v-else class="empty-state">
        <div class="empty-icon">🍳</div>
        <h3>Recette introuvable</h3>
        <RouterLink to="/recettes" class="btn btn-primary" style="margin-top: 16px;">Retour à la liste</RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getRecipe, reportRecipe } from '../api.js'

const route = useRoute()
const router = useRouter()

const recipe = ref(null)
const loading = ref(true)
const servings = ref(1)
const reportReason = ref('')
const reporting = ref(false)

const difficulties = [
  { value: 'very_easy', label: 'Très facile' },
  { value: 'easy', label: 'Facile' },
  { value: 'medium', label: 'Moyen' },
  { value: 'hard', label: 'Difficile' },
]

function difficultyLabel(value) {
  return difficulties.find(d => d.value === value)?.label || value
}

const totalTime = computed(() => (recipe.value?.prep_time_minutes || 0) + (recipe.value?.cook_time_minutes || 0))

function scaledQuantity(qty) {
  if (!qty || !recipe.value?.servings) return qty
  return Math.round((qty * servings.value / recipe.value.servings) * 100) / 100
}

async function report() {
  if (!reportReason.value || !recipe.value) return
  try {
    await reportRecipe(recipe.value.id, reportReason.value)
    reporting.value = false
    reportReason.value = ''
    alert('Signalement envoyé')
  } catch (e) {
    console.error('Erreur signalement', e)
  }
}

onMounted(async () => {
  try {
    const res = await getRecipe(route.params.slug)
    if (res.success && res.data) {
      recipe.value = res.data
      servings.value = res.data.servings || 1
    }
  } catch (e) {
    console.error('Erreur chargement recette', e)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.recipe-detail { min-height: 60vh; }
.back-link { display: inline-block; margin-bottom: 12px; color: var(--c-text-2); }
.back-link:hover { color: var(--c-green); }
.detail-header { margin-bottom: 28px; }
.detail-header h1 { margin: 16px 0 12px; }
.detail-meta { display: flex; gap: 8px; flex-wrap: wrap; }

.hero {
  width: 100%;
  max-height: 320px;
  object-fit: cover;
  border-radius: var(--r-lg);
  margin-bottom: 16px;
}

.servings {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 20px;
  margin-bottom: 20px;
}
.servings-input { width: 80px; margin: 0; }

.info-card { padding: 24px; margin-bottom: 20px; }
.info-card h2 { font-size: 1.1rem; margin-bottom: 16px; color: var(--c-green-dark); }

.ingredient-list { display: flex; flex-direction: column; gap: 10px; }
.ingredient-list li { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.local-badge { font-size: 0.7rem; padding: 2px 8px; }

.step-list { display: flex; flex-direction: column; gap: 16px; }
.step-list li { padding-left: 8px; }
.step-list p { margin-top: 4px; color: var(--c-text-2); }

.artisan-row { padding: 10px 0; border-bottom: 1px solid var(--c-border); }
.artisan-row:last-child { border-bottom: none; }

.variant-row {
  padding: 12px 0;
  border-bottom: 1px solid var(--c-border);
  cursor: pointer;
}
.variant-row:last-child { border-bottom: none; }
.variant-row:hover strong { color: var(--c-green); }

.actions { display: flex; gap: 12px; margin: 24px 0; flex-wrap: wrap; }

.report-card { padding: 24px; margin-top: 16px; }
.report-card .form-actions { display: flex; gap: 12px; margin-top: 16px; }

.empty-state { text-align: center; padding: 80px 20px; }
.empty-icon { font-size: 3rem; margin-bottom: 16px; }

@media (max-width: 600px) {
  .servings { flex-direction: column; align-items: flex-start; }
}
</style>
