<template>
  <article class="game-card" :class="{ premium: game.is_premium }">
    <div class="game-card__badge">
      {{ game.is_premium ? 'Premium' : 'Gratuit' }}
    </div>
    <h3 class="game-card__title">{{ game.title }}</h3>
    <p class="game-card__type">{{ game.game_type_label }}</p>
    <p v-if="game.description" class="game-card__desc">{{ game.description }}</p>
    <p class="game-card__artisan">par {{ game.artisan_name }}</p>
    <RouterLink to="/carte" class="game-card__cta">
      {{ game.is_premium ? 'Voir' : 'Jouer' }}
    </RouterLink>
  </article>
</template>

<script setup>
defineProps({
  game: { type: Object, required: true },
})
</script>

<style scoped>
.game-card {
  border: 1px solid #eee;
  border-radius: 0.75rem;
  padding: 1rem;
  background: #fff;
  display: flex;
  flex-direction: column;
}
.game-card.premium {
  border-color: var(--c-gold);
  background: #fffbf2;
}
.game-card__badge {
  align-self: flex-start;
  font-size: 0.7rem;
  font-weight: bold;
  padding: 0.15rem 0.4rem;
  border-radius: 0.25rem;
  background: var(--c-green);
  color: #fff;
  margin-bottom: 0.5rem;
}
.game-card.premium .game-card__badge {
  background: var(--c-gold);
}
.game-card__title {
  margin: 0 0 0.25rem;
  font-size: 1.1rem;
}
.game-card__type {
  color: #666;
  font-size: 0.85rem;
  margin: 0 0 0.5rem;
}
.game-card__desc {
  flex: 1;
  font-size: 0.9rem;
}
.game-card__artisan {
  font-size: 0.8rem;
  color: #888;
  margin: 0.5rem 0;
}
.game-card__cta {
  display: inline-block;
  text-align: center;
  padding: 0.5rem;
  background: var(--c-green);
  color: #fff;
  border-radius: 0.5rem;
  text-decoration: none;
}
</style>
