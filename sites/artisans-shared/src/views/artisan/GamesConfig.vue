<template>
  <div class="games-config container section dashboard">
    <div class="dashboard-header flex-between">
      <div>
        <h1>Mes mini-jeux</h1>
        <p class="text-muted">Créez et gérez vos jeux interactifs.</p>
      </div>
      <RouterLink to="/espace" class="btn btn-outline">Retour à l'espace</RouterLink>
    </div>

    <div v-if="!artisanToken" class="auth-card">
      <div class="empty-icon">🔐</div>
      <h2>Session requise</h2>
      <p class="text-muted">Veuillez vous connecter à votre espace artisan.</p>
      <RouterLink to="/espace" class="btn btn-primary" style="margin-top: 16px;">
        Se connecter
      </RouterLink>
    </div>

    <template v-else>
      <div v-if="error" class="error-banner">{{ error }}</div>

      <section class="dashboard-section card">
        <FreemiumLimitBanner
          v-if="activeCount >= 2"
          message="Limite de 2 jeux actifs atteinte en version gratuite."
        />

        <form @submit.prevent="createGame" class="game-form">
          <div class="form-group">
            <label for="game_type">Type de jeu</label>
            <select id="game_type" v-model="newGame.game_type_key" class="form-input" required>
              <option v-for="t in freeTypes" :key="t.key" :value="t.key">{{ t.label_fr }}</option>
            </select>
          </div>

          <div class="form-group">
            <label for="game_title">Titre</label>
            <input id="game_title" v-model="newGame.title" type="text" class="form-input" required />
          </div>

          <div class="form-group">
            <label for="game_description">Description</label>
            <textarea id="game_description" v-model="newGame.description" class="form-input" rows="2"></textarea>
          </div>

          <div v-if="newGame.game_type_key === 'coupon'" class="form-group">
            <label for="reveal_text">Texte de révélation</label>
            <input id="reveal_text" v-model="newGame.config.reveal_text" type="text" class="form-input" />
          </div>

          <template v-if="newGame.game_type_key === 'poll' || newGame.game_type_key === 'vote'">
            <div class="form-group">
              <label for="question">Question</label>
              <input id="question" v-model="newGame.config.question" type="text" class="form-input" />
            </div>
            <div class="form-group">
              <label for="options">Options (séparées par virgule)</label>
              <input id="options" v-model="optionsInput" type="text" class="form-input" />
            </div>
          </template>

          <button type="submit" class="btn btn-primary" :disabled="activeCount >= 2 || saving">
            {{ saving ? 'Création…' : 'Créer le jeu' }}
          </button>
        </form>
      </section>

      <section class="dashboard-section card">
        <div class="section-title">
          <h2>Jeux créés</h2>
          <span class="badge badge-grey">{{ activeCount }} actif{{ activeCount > 1 ? 's' : '' }} / 2</span>
        </div>

        <ul v-if="games.length" class="game-list">
          <li v-for="g in games" :key="g.id" class="game-item">
            <div>
              <strong>{{ g.title }}</strong>
              <span class="game-type">{{ g.game_type_label }}</span>
            </div>
            <div class="game-actions">
              <button class="btn btn-outline btn-sm" :disabled="saving" @click="toggleActive(g)">
                {{ g.is_active ? 'Désactiver' : 'Activer' }}
              </button>
              <button class="btn btn-outline btn-sm" :disabled="saving" @click="deleteGame(g.id)">
                Supprimer
              </button>
            </div>
          </li>
        </ul>
        <p v-else class="text-muted">Aucun jeu configuré.</p>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { fetchGameTypes, fetchMyGames, createArtisanGame, updateArtisanGame, deleteArtisanGame } from '../../api.js'
import FreemiumLimitBanner from '../../components/FreemiumLimitBanner.vue'

const props = defineProps({
  token: { type: String, default: '' },
})

const artisanToken = computed(() => props.token || localStorage.getItem('artisan_token') || '')

const types = ref([])
const games = ref([])
const optionsInput = ref('')
const saving = ref(false)
const error = ref('')
const newGame = ref({
  game_type_key: 'coupon',
  title: '',
  description: '',
  config: {},
})

const freeTypes = computed(() => types.value.filter(t => !t.is_premium))
const activeCount = computed(() => games.value.filter(g => g.is_active).length)

async function load() {
  if (!artisanToken.value) return
  error.value = ''
  try {
    const [tRes, gRes] = await Promise.all([
      fetchGameTypes(),
      fetchMyGames(artisanToken.value),
    ])
    types.value = tRes.data || []
    games.value = gRes.data || []
  } catch (e) {
    console.error('Erreur chargement jeux', e)
    error.value = 'Impossible de charger vos jeux.'
  }
}

async function createGame() {
  if (activeCount.value >= 2) return
  error.value = ''
  saving.value = true
  try {
    const config = { ...newGame.value.config }
    if (newGame.value.game_type_key === 'poll' || newGame.value.game_type_key === 'vote') {
      config.options = optionsInput.value.split(',').map(s => s.trim()).filter(Boolean)
    }
    const res = await createArtisanGame(artisanToken.value, {
      game_type_key: newGame.value.game_type_key,
      title: newGame.value.title,
      description: newGame.value.description,
      config,
    })
    if (!res.success) {
      error.value = res.error || 'Erreur lors de la création du jeu.'
      return
    }
    newGame.value = { game_type_key: 'coupon', title: '', description: '', config: {} }
    optionsInput.value = ''
    await load()
  } catch (e) {
    console.error('Erreur création jeu', e)
    error.value = 'Impossible de créer le jeu.'
  } finally {
    saving.value = false
  }
}

async function toggleActive(g) {
  error.value = ''
  saving.value = true
  try {
    const res = await updateArtisanGame(artisanToken.value, g.id, { is_active: !g.is_active })
    if (!res.success) {
      error.value = res.error || 'Erreur lors de la mise à jour.'
      return
    }
    await load()
  } catch (e) {
    console.error('Erreur mise à jour jeu', e)
    error.value = 'Impossible de mettre à jour le jeu.'
  } finally {
    saving.value = false
  }
}

async function deleteGame(id) {
  if (!confirm('Supprimer ce jeu ?')) return
  error.value = ''
  saving.value = true
  try {
    const res = await deleteArtisanGame(artisanToken.value, id)
    if (!res.success) {
      error.value = res.error || 'Erreur lors de la suppression.'
      return
    }
    await load()
  } catch (e) {
    console.error('Erreur suppression jeu', e)
    error.value = 'Impossible de supprimer le jeu.'
  } finally {
    saving.value = false
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

.game-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.error-banner {
  background: #ffebee;
  color: #b71c1c;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  margin-bottom: 24px;
}

.game-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.game-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  padding: 14px;
  background: var(--c-cream);
  border-radius: var(--r-md);
}
.game-item strong { display: block; margin-bottom: 4px; }
.game-type {
  display: block;
  font-size: 0.8rem;
  color: #666;
}
.game-actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
}

.auth-card {
  max-width: 420px;
  margin: 40px auto;
  text-align: center;
}
.empty-icon { font-size: 3rem; margin-bottom: 16px; }

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

@media (max-width: 600px) {
  .game-actions { flex-direction: column; }
  .dashboard-header { flex-direction: column; }
}
</style>
