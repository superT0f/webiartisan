<template>
  <div v-if="current !== null" class="energy-bar card">
    <span class="energy-bar__icon">⚡</span>
    <div class="energy-bar__track">
      <div class="energy-bar__fill" :style="{ width: percent + '%' }"></div>
    </div>
    <span class="energy-bar__label">{{ current }}/{{ energy.max }}</span>
    <small v-if="regenLabel" class="energy-bar__regen">{{ regenLabel }}</small>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useEnergy } from '../composables/useEnergy.js'

const { energy, current } = useEnergy()

const percent = computed(() => energy.value ? Math.min(100, (current.value / energy.value.max) * 100) : 0)

const regenLabel = computed(() => {
  if (!energy.value?.next_energy_at || current.value >= energy.value.max) return ''
  const mins = Math.max(1, Math.ceil((new Date(energy.value.next_energy_at).getTime() - Date.now()) / 60000))
  return `+5 dans ${mins} min`
})
</script>

<style scoped>
.energy-bar {
  position: absolute;
  top: 12px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 20;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  border-radius: 999px;
}
.energy-bar__track { width: 90px; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
.energy-bar__fill { height: 100%; background: #f59e0b; transition: width 0.3s; }
.energy-bar__label { font-size: 0.8rem; font-weight: 600; }
.energy-bar__regen { font-size: 0.7rem; color: #64748b; }
</style>
