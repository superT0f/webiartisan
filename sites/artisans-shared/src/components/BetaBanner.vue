<template>
  <div class="beta-banner" role="status">
    <span class="beta-banner__badge">Bêta</span>
    <span class="beta-banner__message">{{ message }}</span>
  </div>
</template>

<script setup>
defineProps({
  message: { type: String, default: 'Cette fonctionnalité est en version bêta.' },
})
</script>

<style scoped>
.beta-banner {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  margin-bottom: 1rem;
  background: #fff8e1;
  border: 1px solid #ffecb3;
  border-radius: 0.5rem;
  font-size: 0.85rem;
}
.beta-banner__badge {
  background: #f5a623;
  color: #fff;
  padding: 0.15rem 0.4rem;
  border-radius: 0.25rem;
  font-weight: bold;
  font-size: 0.75rem;
}
</style>
