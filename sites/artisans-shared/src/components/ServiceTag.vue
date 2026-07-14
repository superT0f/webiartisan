<template>
  <button
    type="button"
    class="service-tag"
    :class="{ active: isActive }"
    @click="$emit('toggle', serviceKey)"
  >
    <span v-if="icon" class="service-tag__icon">{{ icon }}</span>
    <span class="service-tag__label">{{ label }}</span>
  </button>
</template>

<script setup>
defineProps({
  serviceKey: { type: String, default: '' },
  label: { type: String, required: true },
  icon: { type: String, default: '' },
  isActive: { type: Boolean, default: false },
})
defineEmits(['toggle'])
</script>

<style scoped>
.service-tag {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.35rem 0.75rem;
  border-radius: 999px;
  border: 1px solid #ddd;
  background: #fff;
  cursor: pointer;
  font-size: 0.85rem;
}
.service-tag.active {
  background: var(--c-green);
  color: #fff;
  border-color: var(--c-green);
}
.service-tag__icon {
  font-size: 0.9rem;
}
</style>
