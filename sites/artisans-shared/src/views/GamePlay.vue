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

      <div v-if="!userToken || !game.can_play" class="game-play__blocked">
        <p v-if="!userToken">
          Connectez-vous gratuitement pour jouer.
          <RouterLink to="/inscrire">Créer un compte</RouterLink>
        </p>
        <p v-else>Limite de participations atteinte ou jeu inactif.</p>
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
import { useRoute } from 'vue-router'
import { fetchGame, getUserToken } from '../api.js'
import GameRenderer from '../components/GameRenderer.vue'
import BetaBanner from '../components/BetaBanner.vue'

const route = useRoute()
const game = ref(null)
const loading = ref(true)
const error = ref('')
const userToken = ref(getUserToken())

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

onMounted(load)
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
</style>
