<template>
  <div class="recipe-form section">
    <div class="container narrow">
      <h1>{{ parentId ? 'Proposer un complément' : 'Proposer une recette' }}</h1>
      <p class="text-muted">Partagez une recette locale ou complétez une recette existante.</p>

      <form @submit.prevent="submit" class="form-card card">
        <div class="form-group">
          <label class="form-label">Titre de la recette *</label>
          <input v-model="recipe.title" type="text" class="form-input" placeholder="Tarte aux pommes normandes" required />
        </div>

        <div class="form-group">
          <label class="form-label">Description *</label>
          <textarea v-model="recipe.description" class="form-textarea" placeholder="Décrivez la recette en quelques phrases" required></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">URL d'image (optionnel)</label>
          <input v-model="recipe.image_url" type="url" class="form-input" placeholder="https://..." />
        </div>

        <div class="form-row grid-3">
          <div class="form-group">
            <label class="form-label">Prépa (min)</label>
            <input type="number" min="0" v-model.number="recipe.prep_time_minutes" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">Cuisson (min)</label>
            <input type="number" min="0" v-model.number="recipe.cook_time_minutes" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">Portions *</label>
            <input type="number" min="1" v-model.number="recipe.servings" class="form-input" required />
          </div>
        </div>

        <div class="form-row grid-2">
          <div class="form-group">
            <label class="form-label">Difficulté</label>
            <select v-model="recipe.difficulty" class="form-select">
              <option value="very_easy">Très facile</option>
              <option value="easy">Facile</option>
              <option value="medium">Moyen</option>
              <option value="hard">Difficile</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Saison</label>
            <select v-model="recipe.season" class="form-select">
              <option value="spring">Printemps</option>
              <option value="summer">Été</option>
              <option value="autumn">Automne</option>
              <option value="winter">Hiver</option>
              <option value="all">Toute saison</option>
            </select>
          </div>
        </div>

        <label class="checkbox-label">
          <input type="checkbox" v-model="recipe.is_incomplete" />
          Recette incomplète (ouverte aux compléments)
        </label>

        <h2 class="form-section-title">Ingrédients</h2>
        <div v-for="(ing, i) in ingredients" :key="i" class="dynamic-row">
          <input v-model="ing.name" placeholder="Nom" class="form-input" required />
          <input type="number" v-model.number="ing.quantity" placeholder="Qté" class="form-input" />
          <input v-model="ing.unit" placeholder="Unité" class="form-input" />
          <label class="checkbox-label compact">
            <input type="checkbox" v-model="ing.is_local" /> Local
          </label>
          <label class="checkbox-label compact">
            <input type="checkbox" v-model="ing.is_optional" /> Optionnel
          </label>
          <button type="button" class="btn btn-outline btn-sm" @click="removeIngredient(i)">×</button>
        </div>
        <button type="button" class="btn btn-outline btn-sm" @click="addIngredient">+ Ingrédient</button>

        <h2 class="form-section-title">Étapes</h2>
        <div v-for="(step, i) in steps" :key="i" class="dynamic-row step-row">
          <textarea v-model="step.instruction" placeholder="Description de l'étape" class="form-textarea" required></textarea>
          <button type="button" class="btn btn-outline btn-sm" @click="removeStep(i)">×</button>
        </div>
        <button type="button" class="btn btn-outline btn-sm" @click="addStep">+ Étape</button>

        <h2 class="form-section-title">Vos informations</h2>
        <div class="form-group">
          <label class="form-label">Votre nom</label>
          <input v-model="recipe.submitted_by" type="text" class="form-input" placeholder="Prénom Nom" />
        </div>
        <div class="form-group">
          <label class="form-label">Votre email (optionnel)</label>
          <input v-model="recipe.submitter_email" type="email" class="form-input" placeholder="email@exemple.fr" />
        </div>

        <div v-if="error" class="alert alert-error">{{ error }}</div>

        <button type="submit" class="btn btn-primary btn-lg" :disabled="submitting">
          {{ submitting ? 'Publication...' : 'Publier' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { createRecipe, suggestRecipe } from '../api.js'

const router = useRouter()
const route = useRoute()
const parentId = route.params.id || route.query.parent || null

const recipe = reactive({
  title: '',
  description: '',
  image_url: '',
  prep_time_minutes: 0,
  cook_time_minutes: 0,
  servings: 4,
  difficulty: 'easy',
  season: 'all',
  is_premium: false,
  is_incomplete: false,
  submitted_by: '',
  submitter_email: '',
})

const ingredients = ref([{ name: '', quantity: null, unit: '', is_local: false, is_optional: false }])
const steps = ref([{ instruction: '' }])
const submitting = ref(false)
const error = ref('')

function addIngredient() {
  ingredients.value.push({ name: '', quantity: null, unit: '', is_local: false, is_optional: false })
}
function removeIngredient(i) {
  if (ingredients.value.length > 1) ingredients.value.splice(i, 1)
}
function addStep() {
  steps.value.push({ instruction: '' })
}
function removeStep(i) {
  if (steps.value.length > 1) steps.value.splice(i, 1)
}

async function submit() {
  error.value = ''
  if (!recipe.title || !recipe.description || ingredients.value.some(i => !i.name) || steps.value.some(s => !s.instruction)) {
    error.value = 'Tous les champs obligatoires doivent être remplis.'
    return
  }
  submitting.value = true

  const payload = {
    ...recipe,
    ingredients: ingredients.value,
    steps: steps.value,
  }

  try {
    let res
    if (parentId) {
      res = await suggestRecipe(parentId, payload)
    } else {
      res = await createRecipe(payload)
    }

    if (res.success) {
      router.push(`/recette/${res.slug}`)
    } else {
      error.value = res.error || 'Erreur lors de la publication'
    }
  } catch (e) {
    error.value = 'Erreur lors de la publication'
    console.error(e)
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.recipe-form { min-height: 60vh; }
.narrow { max-width: 760px; }
.form-card { padding: 32px; margin-top: 24px; }
.form-section-title { font-size: 1.1rem; margin: 28px 0 16px; color: var(--c-green-dark); }

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 12px 0;
  cursor: pointer;
}
.checkbox-label input { width: auto; }
.checkbox-label.compact { font-size: 0.9rem; }

.dynamic-row {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr auto auto auto;
  gap: 10px;
  align-items: center;
  margin-bottom: 10px;
}
.step-row {
  grid-template-columns: 1fr auto;
  align-items: flex-start;
}

@media (max-width: 768px) {
  .dynamic-row {
    grid-template-columns: 1fr 1fr;
  }
  .dynamic-row .checkbox-label.compact { grid-column: span 1; }
  .step-row { grid-template-columns: 1fr auto; }
}
</style>
