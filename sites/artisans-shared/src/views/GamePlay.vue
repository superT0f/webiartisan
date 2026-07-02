<template>
  <main v-if="game" class="game-play">
    <BetaBanner message="Les jeux sont en version bêta." />
    <h1>{{ game.title }}</h1>
    <p>{{ game.description }}</p>

    <div v-if="!game.can_play" class="game-play__blocked">
      <p v-if="!userToken">
        Connectez-vous gratuitement pour jouer.
        <router-link to="/inscription">Créer un compte</router-link>
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
  </main>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { fetchGame } from '../api.js'
import GameRenderer from '../components/GameRenderer.vue'
import BetaBanner from '../components/BetaBanner.vue'

const route = useRoute()
const game = ref(null)
const userToken = ref(localStorage.getItem('user_session_token'))

async function load() {
  const res = await fetchGame(route.params.id)
  if (res.success) game.value = res.data
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
</style>
