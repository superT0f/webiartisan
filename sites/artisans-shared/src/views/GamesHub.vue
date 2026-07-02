<template>
  <main class="games-hub">
    <BetaBanner message="Le hub de jeux est en version bêta. De nouveaux jeux arrivent bientôt." />
    <h1>Jeux et bons plans à {{ CITY_NAME }}</h1>

    <FreemiumLimitBanner
      v-if="premiumGames.length"
      message="Passez premium pour débloquer la roue, les quiz, le bingo et les rébus."
    />

    <h2>Jeux gratuits</h2>
    <GameCardGrid :games="freeGames" />

    <h2>Jeux premium</h2>
    <GameCardGrid :games="premiumGames" />
  </main>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { fetchGames, CITY_NAME } from '../api.js'
import GameCardGrid from '../components/GameCardGrid.vue'
import BetaBanner from '../components/BetaBanner.vue'
import FreemiumLimitBanner from '../components/FreemiumLimitBanner.vue'

const games = ref([])

const freeGames = computed(() => games.value.filter(g => !g.is_premium))
const premiumGames = computed(() => games.value.filter(g => g.is_premium))

async function load() {
  const res = await fetchGames()
  games.value = res.data || []
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
</style>
