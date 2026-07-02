<template>
  <main class="game-play">
    <BetaBanner message="Les jeux sont en version bêta." />

    <div v-if="loading" class="game-play__state">
      <div class="loading-spinner"></div>
      <p>Chargement du jeu…</p>
    </div>

    <div v-else-if="error" class="game-play__state game-play__error">
      <p>{{ error }}</p>
      <RouterLink to="/jeux" class="btn btn-primary">Retour aux jeux</RouterLink>
    </div>

    <template v-else-if="game">
      <h1>{{ game.title }}</h1>
      <p v-if="game.description">{{ game.description }}</p>

      <div v-if="!token" class="game-play__blocked">
        <p>Connectez-vous gratuitement pour jouer.</p>
        <form @submit.prevent="sendMagicLink" class="game-play__auth-form">
          <input
            v-model="email"
            type="email"
            class="form-input"
            placeholder="votre@email.fr"
            required
            :disabled="sending"
          />
          <button type="submit" class="btn btn-primary" :disabled="sending || !email">
            {{ sending ? 'Envoi…' : 'Recevoir mon lien' }}
          </button>
        </form>
        <div v-if="message" class="auth-message" :class="messageType">{{ message }}</div>
      </div>

      <div v-else-if="!game.can_play" class="game-play__blocked">
        <p>Limite de participations atteinte ou jeu inactif.</p>
      </div>

      <GameRenderer
        v-else
        :instance-id="game.id"
        :game-type="game.game_type_key"
        :config="game.config"
        @played="onPlayed"
      />
    </template>
  </main>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  fetchGame,
  requestUserMagicLink,
  authUser,
  getUserToken,
  setUserToken,
} from '../api.js'
import GameRenderer from '../components/GameRenderer.vue'
import BetaBanner from '../components/BetaBanner.vue'

const route = useRoute()
const router = useRouter()

const game = ref(null)
const loading = ref(true)
const error = ref('')
const token = ref(getUserToken() || '')
const email = ref('')
const sending = ref(false)
const message = ref('')
const messageType = ref('')

function setMessage(text, type = 'info') {
  message.value = text
  messageType.value = type
}

if (route.query.token) {
  authUser(route.query.token).then(res => {
    if (res.success && res.token) {
      setUserToken(res.token)
      token.value = res.token
      router.replace({ path: route.path, query: {} })
      load()
    } else {
      setMessage(res.error || 'Lien invalide', 'error')
    }
  })
}

async function sendMagicLink() {
  sending.value = true
  message.value = ''
  try {
    const res = await requestUserMagicLink(email.value)
    setMessage(res.message || 'Si votre email est valide, vous recevrez un lien.', 'success')
  } catch (e) {
    setMessage('Erreur lors de l\'envoi.', 'error')
  } finally {
    sending.value = false
  }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await fetchGame(route.params.id)
    if (res.success) {
      game.value = res.data
    } else {
      error.value = res.error || 'Jeu introuvable.'
    }
  } catch (e) {
    console.error('Erreur chargement jeu', e)
    error.value = 'Impossible de charger le jeu.'
  } finally {
    loading.value = false
  }
}

function onPlayed(data) {
  console.log('played', data)
}

onMounted(() => {
  if (!route.query.token) load()
})
</script>

<style scoped>
.game-play {
  padding: 1rem;
  max-width: 600px;
  margin: 0 auto;
}
.game-play__blocked {
  padding: 1rem;
  background: #f5f5f5;
  border-radius: 0.5rem;
  text-align: center;
}
.game-play__auth-form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-width: 320px;
  margin: 1rem auto 0;
}
.game-play__state {
  text-align: center;
  padding: 2rem 1rem;
}
.game-play__error {
  color: #b71c1c;
}
.game-play__error p {
  margin-bottom: 1rem;
}
.loading-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid var(--c-border);
  border-top-color: var(--c-green);
  border-radius: 50%;
  margin: 0 auto 1rem;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.auth-message {
  margin-top: 0.75rem;
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
  font-size: 0.9rem;
}
.auth-message.success {
  background: rgba(45, 106, 79, 0.1);
  color: var(--c-green-dark);
}
.auth-message.error {
  background: rgba(183, 28, 28, 0.08);
  color: #b71c1c;
}
</style>
