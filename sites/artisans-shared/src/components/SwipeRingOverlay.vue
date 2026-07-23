<template>
  <Transition name="ring-fade">
    <div v-if="open && target" class="ring-overlay" @pointerdown.self="onCancel">
      <div
        ref="stageEl"
        class="ring-stage"
        :class="{ 'ring-stage--success': success }"
        @pointerdown="onPointerDown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUp"
        @pointercancel="onPointerUp"
      >
        <svg class="ring-svg" viewBox="0 0 300 300">
          <circle class="ring-track" cx="150" cy="150" :r="R" />
          <circle
            class="ring-progress"
            cx="150" cy="150" :r="R"
            :stroke-dasharray="circumference"
            :stroke-dashoffset="dashOffset"
          />
        </svg>
        <div
          class="ring-disc"
          :style="target.imageUrl ? { backgroundImage: `url(${target.imageUrl})`, backgroundSize: 'cover', backgroundPosition: 'center' } : {}"
        >
          <div v-if="target.imageUrl" class="ring-disc-veil"></div>
          <span class="ring-emoji">{{ target.emoji }}</span>
          <strong class="ring-name">{{ target.name }}</strong>
          <span class="ring-reward">{{ target.rewardLabel }}</span>
          <span v-if="errorMessage" class="ring-error">{{ errorMessage }}</span>
          <span v-else-if="!success" class="ring-hint">Balaye le long de l'anneau</span>
        </div>
        <div v-if="success" class="ring-burst" aria-hidden="true">{{ target.emoji }}</div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { angleFromTop, angularDeltaCW, progressFromAccum } from '../utils/swipeRingGeometry.js'
import { playSound } from '../utils/sounds.js'

const R = 130

const props = defineProps({
  target: { type: Object, default: null },
  open: { type: Boolean, default: false },
})
const emit = defineEmits(['complete', 'cancel'])

const stageEl = ref(null)
const progress = ref(0)
const success = ref(false)
const errorMessage = ref('')

const circumference = 2 * Math.PI * R
const dashOffset = computed(() => circumference * (1 - progress.value))

let dragging = false
let lastAngle = null
let accum = 0

watch(() => props.open, (v) => {
  if (v) reset()
})

function reset() {
  progress.value = 0
  accum = 0
  dragging = false
  lastAngle = null
  success.value = false
  errorMessage.value = ''
}

function angleFromEvent(e) {
  const rect = stageEl.value.getBoundingClientRect()
  const cx = rect.left + rect.width / 2
  const cy = rect.top + rect.height / 2
  return angleFromTop(e.clientX - cx, e.clientY - cy)
}

function onPointerDown(e) {
  if (success.value) return
  dragging = true
  lastAngle = angleFromEvent(e)
  stageEl.value.setPointerCapture(e.pointerId)
}

function onPointerMove(e) {
  if (!dragging || success.value) return
  const a = angleFromEvent(e)
  accum += angularDeltaCW(lastAngle, a)
  lastAngle = a
  progress.value = progressFromAccum(accum)
  if (progress.value >= 1) {
    dragging = false
    emit('complete')
  }
}

function onPointerUp() {
  if (!dragging) return
  dragging = false
  // Relâché avant la fin : l'anneau se vide doucement (transition CSS)
  progress.value = 0
  accum = 0
}

function onCancel() {
  if (!success.value) emit('cancel')
}

/** Succès API : particules + son, le parent ferme l'overlay après ~1,3 s. */
function succeed() {
  success.value = true
  playSound(props.target?.kind === 'pickup' ? 'xp-boost' : 'success')
}

/** Échec API : message + reset de l'anneau. */
function fail(message) {
  errorMessage.value = message || 'Action impossible'
  progress.value = 0
  accum = 0
}

defineExpose({ succeed, fail })
</script>

<style scoped>
.ring-overlay {
  position: fixed;
  inset: 0;
  z-index: 60;
  background: rgba(20, 16, 12, 0.72);
  display: flex;
  align-items: center;
  justify-content: center;
  touch-action: none;
}
.ring-stage {
  position: relative;
  width: min(78vw, 340px);
  aspect-ratio: 1;
  user-select: none;
  touch-action: none;
}
.ring-svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.ring-track {
  fill: none;
  stroke: rgba(255, 255, 255, 0.25);
  stroke-width: 14;
}
.ring-progress {
  fill: none;
  stroke: #f59e0b;
  stroke-width: 14;
  stroke-linecap: round;
  transition: stroke-dashoffset 0.08s linear;
}
.ring-disc {
  position: absolute;
  inset: 12%;
  background: var(--c-surface, #fff);
  border-radius: 50%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  text-align: center;
  padding: 16px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.35);
}
.ring-emoji { font-size: 2.6rem; }
.ring-disc-veil {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.72);
}
.ring-disc > *:not(.ring-disc-veil) { position: relative; z-index: 1; }
.ring-name { font-size: 1rem; }
.ring-reward { color: #b45309; font-weight: 700; }
.ring-hint { font-size: 0.75rem; color: #64748b; }
.ring-error { font-size: 0.85rem; color: #dc2626; font-weight: 600; }
.ring-burst {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
  pointer-events: none;
  animation: burst 0.9s ease-out forwards;
}
@keyframes burst {
  0% { transform: scale(0.6); opacity: 0; filter: drop-shadow(0 0 0 rgba(245, 158, 11, 0.9)); }
  40% { transform: scale(1.6); opacity: 1; filter: drop-shadow(0 0 24px rgba(245, 158, 11, 0.9)); }
  100% { transform: scale(2.6) translateY(-30px); opacity: 0; filter: drop-shadow(0 0 48px rgba(245, 158, 11, 0)); }
}
.ring-stage--success .ring-progress { stroke: #10b981; }
.ring-fade-enter-active, .ring-fade-leave-active { transition: opacity 0.25s ease; }
.ring-fade-enter-from, .ring-fade-leave-to { opacity: 0; }
</style>
