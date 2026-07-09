<template>
  <div class="freemium-banner" role="status">
    <span class="freemium-banner__badge">Gratuit</span>
    <span class="freemium-banner__message">{{ message }}</span>
    <button type="button" class="freemium-banner__cta" @click="$emit('upgrade')">
      {{ upgradeLabel }}
    </button>
  </div>
</template>

<script setup>
defineProps({
  message: { type: String, default: 'Passez à la version premium pour débloquer plus de fonctionnalités.' },
  upgradeLabel: { type: String, default: 'Passer premium' },
})
defineEmits(['upgrade'])
</script>

<style scoped>
.freemium-banner {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  background: #e8f5e9;
  border: 1px solid #c8e6c9;
  border-radius: 0.5rem;
  font-size: 0.9rem;
}
.freemium-banner__badge {
  background: #2d6a4f;
  color: #fff;
  padding: 0.2rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: bold;
}
.freemium-banner__cta {
  margin-left: auto;
  background: #f5a623;
  color: #fff;
  border: none;
  padding: 0.4rem 0.75rem;
  border-radius: 0.5rem;
  cursor: pointer;
}
</style>
