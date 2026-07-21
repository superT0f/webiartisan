<template>
  <Transition name="rise">
    <button v-if="action" class="checkin-fab" :class="{ 'checkin-fab--pickup': action.kind === 'pickup' }" :disabled="loading" @click="$emit('act', action)">
      <template v-if="action.kind === 'pickup'">
        <span class="checkin-fab__icon">{{ objectEmoji(action.object.type) }}</span>
        <span class="checkin-fab__text">
          <strong>{{ loading ? 'Ramassage…' : 'Ramasser' }}</strong>
          <small>{{ action.object.label }} · +{{ action.object.xp }} XP · {{ action.object.distance_m }} m</small>
        </span>
      </template>
      <template v-else>
        <span class="checkin-fab__icon">📍</span>
        <span class="checkin-fab__text">
          <strong>{{ loading ? 'Check-in…' : 'Check-in' }}</strong>
          <small>{{ action.target.name }} · {{ action.target.distance_m }} m</small>
        </span>
      </template>
    </button>
  </Transition>
</template>

<script setup>
defineProps({
  action: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})
defineEmits(['act'])

function objectEmoji(type) {
  const icons = { dechet: '🗑️', canette: '🍾', papier: '📰', tresor: '💎', cadeau_artisan: '🎁' }
  return icons[type] || '❓'
}
</script>

<style scoped>
.checkin-fab {
  position: absolute;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 20;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 20px;
  background: var(--c-green);
  color: #fff;
  border: none;
  border-radius: 999px;
  box-shadow: 0 6px 20px rgba(0,0,0,0.25);
  cursor: pointer;
  font-size: 1rem;
}
.checkin-fab--pickup { background: #b45309; }
.checkin-fab:disabled { opacity: 0.75; cursor: wait; }
.checkin-fab__icon { font-size: 1.4rem; }
.checkin-fab__text { display: flex; flex-direction: column; align-items: flex-start; line-height: 1.2; }
.checkin-fab__text small { opacity: 0.85; font-size: 0.75rem; }
.rise-enter-active, .rise-leave-active { transition: all 0.25s ease; }
.rise-enter-from, .rise-leave-to { opacity: 0; transform: translate(-50%, 20px); }
</style>
