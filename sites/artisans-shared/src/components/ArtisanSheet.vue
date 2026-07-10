<script setup>
defineProps({ artisan: { type: Object, default: null } })
defineEmits(['close', 'navigate'])
</script>

<template>
  <Transition name="slide-up">
    <div v-if="artisan" class="sheet-overlay" @click.self="$emit('close')">
      <div class="sheet">
        <button class="sheet-close" @click="$emit('close')">✕</button>
        <div class="sheet-header">
          <h3>{{ artisan.company_name }}</h3>
          <span class="category">{{ artisan.category_label || artisan.category_slug }}</span>
        </div>
        <p v-if="artisan.address" class="address">{{ artisan.address }}</p>
        <div class="actions">
          <button class="btn btn-primary" @click="$emit('navigate', artisan)">Itinéraire</button>
          <RouterLink :to="`/artisan/${artisan.id}`" class="btn btn-secondary">Voir la fiche</RouterLink>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.sheet-overlay {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: flex;
  align-items: flex-end;
  pointer-events: none;
}
.sheet {
  width: 100%;
  background: #fff;
  border-radius: 20px 20px 0 0;
  padding: 20px;
  box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
  pointer-events: auto;
  position: relative;
}
.sheet-close {
  position: absolute;
  top: 12px;
  right: 16px;
  background: none;
  border: none;
  font-size: 1.2rem;
  cursor: pointer;
}
.sheet-header h3 { margin: 0 0 4px; }
.category { color: #16a34a; font-weight: 600; font-size: 0.85rem; }
.address { color: #666; margin: 12px 0; }
.actions { display: flex; gap: 10px; margin-top: 16px; }

.slide-up-enter-active, .slide-up-leave-active { transition: transform 0.3s ease; }
.slide-up-enter-from, .slide-up-leave-to { transform: translateY(100%); }
</style>
