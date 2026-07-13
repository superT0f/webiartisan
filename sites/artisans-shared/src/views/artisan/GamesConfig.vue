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
      <div v-if="success" class="success-banner">{{ success }}</div>

      <section class="dashboard-section card">
        <FreemiumLimitBanner
          v-if="activeCount >= 1"
          message="Limite de 1 jeu actif atteinte en version gratuite."
          @upgrade="startCheckout"
        />

        <div v-if="!isPremium && !loadingSubscription" class="upgrade-cta">
          <p class="text-muted">
            Passez Premium pour proposer le jeu « Tournez l'avatar » à vos clients (offres gérées dans « Mes offres roue »).
          </p>
          <button type="button" class="btn btn-gold" @click="startCheckout" :disabled="subscribing">
            {{ subscribing ? 'Redirection…' : 'Passer Premium' }}
          </button>
        </div>

        <form @submit.prevent="createGame" class="game-form">
          <div class="form-group">
            <label for="game_type">Type de jeu</label>
            <select id="game_type" v-model="newGame.game_type_key" class="form-input" required>
              <option v-for="t in availableTypes" :key="t.key" :value="t.key">{{ t.label_fr }}</option>
            </select>
            <span v-if="!isPremium" class="premium-note">
              Version gratuite : types de jeux limités.
            </span>
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

          <button type="submit" class="btn btn-primary" :disabled="activeCount >= 1 || saving">
            {{ saving ? 'Création…' : 'Créer le jeu' }}
          </button>
        </form>
      </section>

      <section class="dashboard-section card">
        <div class="section-title">
          <h2>Jeux créés</h2>
          <span class="badge badge-grey">{{ activeCount }} actif / 1</span>
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
import { fetchGameTypes, fetchMyGames, createArtisanGame, updateArtisanGame, deleteArtisanGame, getSubscriptionStatus, createSubscriptionCheckout } from '../../api.js'
import FreemiumLimitBanner from '../../components/FreemiumLimitBanner.vue'

const props = defineProps({
  token: { type: String, default: '' },
})

const artisanToken = computed(() => props.token || localStorage.getItem('artisan_token') || '')

const types = ref([])
const games = ref([])
const saving = ref(false)
const error = ref('')
const success = ref('')
const subscribing = ref(false)
const subscriptionStatus = ref(null)
const loadingSubscription = ref(false)
const newGame = ref({
  game_type_key: 'coupon',
  title: '',
  description: '',
  config: {},
})

const isPremium = computed(() => subscriptionStatus.value?.plan === 'premium')
// Only the coupon is created here; the wheel (premium) is managed via /espace/spin-offers
const availableTypes = computed(() => types.value.filter(t => t.key === 'coupon'))
const activeCount = computed(() => games.value.filter(g => g.is_active).length)

async function load() {
  if (!artisanToken.value) return
  error.value = ''
  loadingSubscription.value = true
  try {
    const [tRes, gRes, sRes] = await Promise.all([
      fetchGameTypes(),
      fetchMyGames(artisanToken.value),
      getSubscriptionStatus(),
    ])
    types.value = tRes.data || []
    games.value = gRes.data || []
    if (sRes.success && sRes.data) {
      subscriptionStatus.value = sRes.data
    }
    if (availableTypes.value.length && !availableTypes.value.find(t => t.key === newGame.value.game_type_key)) {
      newGame.value.game_type_key = availableTypes.value[0].key
    }
  } catch (e) {
    console.error('Erreur chargement jeux', e)
    error.value = 'Impossible de charger vos jeux.'
  } finally {
    loadingSubscription.value = false
  }
}

async function startCheckout() {
  subscribing.value = true
  error.value = ''
  success.value = ''
  try {
    const res = await createSubscriptionCheckout(window.location.origin + '/artisan/jeux')
    if (res.success && res.data?.url) {
      window.location.href = res.data.url
    } else {
      error.value = res.error || 'Impossible de démarrer le paiement.'
      subscribing.value = false
    }
  } catch (e) {
    console.error('Erreur checkout', e)
    error.value = 'Erreur lors du paiement.'
    subscribing.value = false
  }
}

async function createGame() {
  if (activeCount.value >= 1) return
  error.value = ''
  success.value = ''
  saving.value = true
  try {
    const config = { ...newGame.value.config }
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
    success.value = 'Jeu créé.'
    await load()
  } catch (e) {
    console.error('Erreur création jeu', e)
    error.value = 'Impossible de créer le jeu.'
  } finally {
    saving.value = false
  }
}

async function toggleActive(g) {
  if (!g.is_active && activeCount.value >= 1) {
    error.value = 'Limite de 1 jeu actif atteinte.'
    return
  }
  error.value = ''
  success.value = ''
  saving.value = true
  try {
    const res = await updateArtisanGame(artisanToken.value, g.id, { is_active: !g.is_active })
    if (!res.success) {
      error.value = res.error || 'Erreur lors de la mise à jour.'
      return
    }
    success.value = 'Jeu mis à jour.'
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
  success.value = ''
  saving.value = true
  try {
    const res = await deleteArtisanGame(artisanToken.value, id)
    if (!res.success) {
      error.value = res.error || 'Erreur lors de la suppression.'
      return
    }
    success.value = 'Jeu supprimé.'
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
.success-banner {
  background: #e8f5e9;
  color: #1b5e20;
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

.premium-note {
  font-size: 0.8rem;
  color: var(--c-text-3);
}

.upgrade-cta {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  margin-bottom: 20px;
  background: #FFF8E1;
  border: 1px solid #FFE082;
  border-radius: var(--r-md);
}
.upgrade-cta p { margin: 0; }

@media (max-width: 600px) {
  .game-actions { flex-direction: column; }
  .dashboard-header { flex-direction: column; }
}
</style>
