<template>
  <Transition name="slide-up">
    <div v-if="open" class="clean-panel card">
      <div class="clean-panel__header">
        <h3>🌿 Ville propre</h3>
        <button type="button" class="clean-panel__close" @click="$emit('close')">✕</button>
      </div>
      <div class="clean-panel__score">
        <div class="clean-panel__track">
          <div class="clean-panel__fill" :style="{ width: cleanliness + '%' }"></div>
        </div>
        <strong>{{ cleanliness }} %</strong>
      </div>
      <p class="clean-panel__total">🗑️ <strong>{{ total }}</strong> déchet{{ total > 1 ? 's' : '' }} ramassé{{ total > 1 ? 's' : '' }} dans la ville</p>
      <div v-if="topCleaners.length" class="clean-panel__podium">
        <h4>Meilleurs nettoyeurs</h4>
        <div v-for="(c, i) in topCleaners" :key="i" class="clean-panel__row">
          <span>{{ medals[i] || '🎖️' }}</span>
          <span class="clean-panel__name">{{ c.display_name }}</span>
          <strong>×{{ c.count }}</strong>
        </div>
      </div>
      <p v-else class="clean-panel__empty">Sois le premier à nettoyer la ville !</p>
    </div>
  </Transition>
</template>

<script setup>
defineProps({
  cleanliness: { type: Number, default: 100 },
  total: { type: Number, default: 0 },
  topCleaners: { type: Array, default: () => [] },
  open: { type: Boolean, default: false },
})
defineEmits(['close'])

const medals = ['🥇', '🥈', '🥉']
</script>

<style scoped>
.clean-panel {
  position: absolute;
  bottom: 90px;
  left: 12px;
  z-index: 25;
  width: min(80vw, 300px);
  padding: 16px;
}
.clean-panel__header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.clean-panel__header h3 { margin: 0; font-size: 1rem; }
.clean-panel__close { background: none; border: none; font-size: 1rem; cursor: pointer; }
.clean-panel__score { display: flex; align-items: center; gap: 10px; }
.clean-panel__track { flex: 1; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
.clean-panel__fill { height: 100%; background: #10b981; transition: width 0.3s; }
.clean-panel__total { margin: 12px 0 0; font-size: 0.9rem; }
.clean-panel__podium { margin-top: 12px; border-top: 1px solid #e2e8f0; padding-top: 8px; }
.clean-panel__podium h4 { margin: 0 0 6px; font-size: 0.85rem; color: #64748b; }
.clean-panel__row { display: flex; align-items: center; gap: 8px; padding: 4px 0; font-size: 0.9rem; }
.clean-panel__name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.clean-panel__empty { color: #64748b; font-size: 0.85rem; margin-top: 8px; }
.slide-up-enter-active, .slide-up-leave-active { transition: all 0.25s ease; }
.slide-up-enter-from, .slide-up-leave-to { opacity: 0; transform: translateY(20px); }
</style>
