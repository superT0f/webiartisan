<template>
  <Transition name="slide-up">
    <div v-if="open" class="quests-panel card">
      <div class="quests-panel__header">
        <h3>Quêtes du jour</h3>
        <button type="button" class="quests-panel__close" @click="$emit('close')">✕</button>
      </div>
      <div v-for="q in quests" :key="q.quest_code" class="quest">
        <div class="quest__info">
          <strong>{{ q.label }}</strong>
          <div class="quest__track">
            <div class="quest__fill" :style="{ width: Math.min(100, (q.progress / q.target_count) * 100) + '%' }"></div>
          </div>
          <small>{{ q.progress }}/{{ q.target_count }}</small>
        </div>
        <button
          v-if="q.completed && !q.claimed"
          type="button"
          class="btn btn-sm quest__claim"
          :disabled="claiming"
          @click="$emit('claim', q)"
        >+{{ q.reward_xp }} XP</button>
        <span v-else-if="q.claimed" class="quest__done">✅</span>
      </div>
      <p v-if="!quests.length" class="quests-panel__empty">Connecte-toi pour découvrir tes quêtes.</p>
    </div>
  </Transition>
</template>

<script setup>
defineProps({
  quests: { type: Array, default: () => [] },
  open: { type: Boolean, default: false },
  claiming: { type: Boolean, default: false },
})
defineEmits(['close', 'claim'])
</script>

<style scoped>
.quests-panel {
  position: absolute;
  bottom: 90px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 25;
  width: min(92vw, 360px);
  padding: 16px;
}
.quests-panel__header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.quests-panel__header h3 { margin: 0; font-size: 1rem; }
.quests-panel__close { background: none; border: none; font-size: 1rem; cursor: pointer; }
.quest { display: flex; align-items: center; gap: 12px; padding: 8px 0; border-top: 1px solid #e2e8f0; }
.quest__info { flex: 1; }
.quest__track { height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; margin: 4px 0; }
.quest__fill { height: 100%; background: #10b981; transition: width 0.3s; }
.quest__claim { background: #f59e0b; color: #fff; border: none; border-radius: 999px; padding: 6px 12px; cursor: pointer; }
.quest__done { font-size: 1.1rem; }
.quests-panel__empty { color: #64748b; font-size: 0.85rem; }
.slide-up-enter-active, .slide-up-leave-active { transition: all 0.25s ease; }
.slide-up-enter-from, .slide-up-leave-to { opacity: 0; transform: translate(-50%, 20px); }
</style>
