<script setup>
defineProps({ weather: { type: Object, default: null } })

function icon(code) {
  if (code <= 3) return '☀️'
  if (code <= 48) return '☁️'
  if (code <= 67) return '🌧️'
  if (code <= 77) return '🌨️'
  if (code <= 82) return '🌦️'
  if (code <= 86) return '🌨️'
  if (code <= 99) return '⛈️'
  return '🌤️'
}
</script>

<template>
  <div v-if="weather" class="weather-badge">
    <span class="icon">{{ icon(weather.weathercode) }}</span>
    <span class="temp">{{ Math.round(weather.temperature) }}°</span>
  </div>
</template>

<style scoped>
.weather-badge {
  position: fixed;
  top: calc(var(--nav-h) + 12px);
  left: 12px;
  z-index: 10;
  background: rgba(255,255,255,0.92);
  border-radius: 999px;
  padding: 8px 14px;
  display: flex;
  align-items: center;
  gap: 6px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  font-weight: 700;
}
.icon { font-size: 1.2rem; }
.temp { color: var(--c-text); }
</style>
