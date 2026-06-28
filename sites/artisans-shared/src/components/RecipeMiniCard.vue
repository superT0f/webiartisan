<script setup>
defineProps({
  recipe: { type: Object, required: true }
});
</script>

<template>
  <RouterLink :to="`/recette/${recipe.slug}`" class="recipe-mini-card">
    <img v-if="recipe.image_url" :src="recipe.image_url" :alt="recipe.title" />
    <div class="content">
      <h4>{{ recipe.title }}</h4>
      <p>{{ recipe.description }}</p>
      <span v-if="recipe.is_product_recipe" class="badge">Avec ses produits</span>
    </div>
  </RouterLink>
</template>

<style scoped>
.recipe-mini-card {
  display: flex;
  gap: 0.75rem;
  border: 1px solid #eee;
  border-radius: 8px;
  padding: 0.75rem;
  text-decoration: none;
  color: inherit;
}
.recipe-mini-card img {
  width: 80px;
  height: 80px;
  object-fit: cover;
  border-radius: 6px;
}
.recipe-mini-card .content {
  flex: 1;
}
.recipe-mini-card h4 {
  margin: 0 0 0.25rem;
}
.recipe-mini-card p {
  margin: 0;
  font-size: 0.85rem;
  color: #555;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.badge {
  display: inline-block;
  margin-top: 0.4rem;
  font-size: 0.75rem;
  background: #22c55e;
  color: #fff;
  padding: 2px 8px;
  border-radius: 12px;
}
</style>
