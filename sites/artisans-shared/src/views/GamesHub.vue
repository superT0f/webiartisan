<template>
  <main class="games-hub">
    <BetaBanner message="Le hub de jeux est en version bêta. De nouveaux jeux arrivent bientôt." />
    <h1>Jeux et bons plans à {{ CITY_NAME }}</h1>

    <div v-if="loading" class="games-hub__state">
      <div class="loading-spinner"></div>
      <p>Chargement des jeux…</p>
    </div>

    <div v-else-if="error" class="games-hub__state games-hub__error">
      <p>{{ error }}</p>
    </div>

    <template v-else>
      <FreemiumLimitBanner
        v-if="premiumGames.length"
        message="Passez premium pour débloquer la roue, les quiz, le bingo et les rébus."
      />

      <h2>Jeux gratuits</h2>
      <GameCardGrid :games="freeGames" />

      <h2>Jeux premium</h2>
      <GameCardGrid :games="premiumGames" />
    </template>
  </main>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { fetchGames, CITY_NAME } from '../api.js'
import GameCardGrid from '../components/GameCardGrid.vue'
import BetaBanner from '../components/BetaBanner.vue'
import FreemiumLimitBanner from '../components/FreemiumLimitBanner.vue'

const games = ref([])
const loading = ref(true)
const error = ref('')

const freeGames = computed(() => games.value.filter(g => !g.is_premium))
const premiumGames = computed(() => games.value.filter(g => g.is_premium))

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await fetchGames()
    games.value = res.data || []
  } catch (e) {
    console.error('Erreur chargement jeux', e)
    error.value = 'Impossible de charger les jeux.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.games-hub {
  padding: 1rem;
  max-width: 960px;
  margin: 0 auto;
}
.games-hub h2 {
  margin-top: 1.5rem;
}
.games-hub__state {
  text-align: center;
  padding: 2rem 1rem;
}
.games-hub__error {
  color: #b71c1c;
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
