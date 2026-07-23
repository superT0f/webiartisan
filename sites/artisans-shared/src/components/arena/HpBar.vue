<template>
  <div class="hp-bar">
    <span class="hp-bar__label">{{ label }}</span>
    <span class="hp-bar__hearts">
      <span v-for="i in max" :key="i" class="hp-heart" :class="{ 'hp-heart--off': i > hp }">❤️</span>
    </span>
  </div>
</template>

<script setup>
defineProps({
  hp: { type: Number, required: true },
  max: { type: Number, default: 3 },
  label: { type: String, default: '' },
})
</script>

<style scoped>
.hp-bar { display: flex; align-items: center; gap: 8px; }
.hp-bar__label { font-size: 0.85rem; font-weight: 700; color: #fff; }
.hp-heart { font-size: 1.2rem; transition: opacity 0.3s, transform 0.3s; }
.hp-heart--off { opacity: 0.2; transform: scale(0.85); filter: grayscale(1); }
</style>
