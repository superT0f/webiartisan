<template>
  <div v-if="games.length" class="game-card-grid">
    <GameCard v-for="g in games" :key="g.id" :game="g" />
  </div>
  <p v-else>Aucun jeu disponible.</p>
</template>

<script setup>
import GameCard from './GameCard.vue'

defineProps({
  games: { type: Array, required: true },
})
</script>

<style scoped>
.game-card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 1rem;
}
</style>
