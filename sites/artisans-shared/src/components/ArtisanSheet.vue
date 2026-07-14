<script setup>
import { computed } from 'vue'

const props = defineProps({
  artisan: { type: Object, default: null },
  game: { type: Object, default: null },
  checkinState: { type: Object, default: null },
  authenticated: { type: Boolean, default: false },
})
defineEmits(['close', 'navigate', 'checkin', 'play-coupon', 'play-spin'])

const checkinLabel = computed(() => {
  const s = props.checkinState
  if (!s) return '📍 Position indisponible'
  if (!s.inRange) return `📍 Trop loin (${s.distanceM} m)`
  if (s.dailyAvailable === false && s.nextSpinAt) {
    const ms = new Date(s.nextSpinAt).getTime() - Date.now()
    if (ms > 0) return `📍 Recharge (${Math.ceil(ms / 60000)} min)`
  }
  return s.dailyAvailable === false ? '📍 Check-in (+10 XP)' : '📍 Check-in (+100 XP)'
})

const checkinDisabled = computed(() => {
  const s = props.checkinState
  if (!s || !s.inRange) return true
  if (s.dailyAvailable === false && s.nextSpinAt && new Date(s.nextSpinAt).getTime() > Date.now()) return true
  return false
})
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

        <div class="play-section">
          <h4>🎮 Jouer</h4>
          <button class="btn btn-primary play-btn" :disabled="checkinDisabled" @click="$emit('checkin')">
            {{ checkinLabel }}
          </button>
          <button v-if="game" class="btn btn-secondary play-btn" @click="$emit('play-coupon')">
            🎁 {{ game.title }}
          </button>
          <button v-if="artisan.has_wheel" class="btn btn-secondary play-btn" @click="$emit('play-spin')">
            🌀 Tourner l'avatar
          </button>
          <p v-if="!authenticated" class="play-hint">Connexion par email requise pour jouer.</p>
        </div>

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

.play-section {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--c-border);
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.play-section h4 { margin: 0; font-size: 1rem; }
.play-btn { width: 100%; text-align: center; }
.play-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.play-hint { margin: 0; font-size: 0.8rem; color: var(--c-text-2); }

.slide-up-enter-active, .slide-up-leave-active { transition: transform 0.3s ease; }
.slide-up-enter-from, .slide-up-leave-to { transform: translateY(100%); }
</style>
