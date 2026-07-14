<template>
  <Transition name="fade">
    <div class="game-overlay" @click.self="$emit('close')">
      <div class="game-panel">
        <header class="game-panel__header">
          <h2>{{ title }}</h2>
          <button type="button" class="game-panel__close" aria-label="Fermer" @click="$emit('close')">✕</button>
        </header>
        <div class="game-panel__body">
          <slot />
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
defineProps({
  title: { type: String, default: '' },
})
defineEmits(['close'])
</script>

<style scoped>
.game-overlay {
  position: fixed;
  inset: 0;
  z-index: 60;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: flex-end;
  justify-content: center;
}
.game-panel {
  width: 100%;
  max-width: 480px;
  max-height: 92vh;
  overflow-y: auto;
  background: #fff;
  border-radius: 20px 20px 0 0;
  padding: 20px;
}
.game-panel__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}
.game-panel__header h2 { margin: 0; font-size: 1.2rem; }
.game-panel__close {
  background: none;
  border: none;
  font-size: 1.2rem;
  cursor: pointer;
  padding: 4px 8px;
}
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
