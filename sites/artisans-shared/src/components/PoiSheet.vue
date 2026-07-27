<script setup>
import { computed } from 'vue'

const props = defineProps({
  poi: { type: Object, default: null },
  checkinState: { type: Object, default: null },
  authenticated: { type: Boolean, default: false },
})
defineEmits(['close', 'navigate', 'checkin'])

const TYPE_LABELS = {
  mairie: 'Mairie', piscine: 'Piscine', bibliotheque: 'Bibliothèque', mediatheque: 'Médiathèque',
  cinema: 'Cinéma', dechetterie: 'Déchèterie', poste: 'Poste', supermarche: 'Supermarché',
  transport: 'Transport', ecole: 'École', hopital: 'Hôpital', pharmacie: 'Pharmacie',
  parc: 'Parc', eglise: 'Église', autre: 'Lieu',
}
const typeLabel = computed(() => (TYPE_LABELS[props.poi?.type] || props.poi?.type || 'Lieu').toUpperCase())

const scheduleLine = computed(() => {
  const days = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']
  return (props.poi?.schedules || [])
    .filter(s => !s.is_closed)
    .map(s => `${days[s.day_of_week] || '?'} ${s.open_time?.slice(0, 5)}–${s.close_time?.slice(0, 5)}`)
    .join(' · ')
})

// Même logique que ArtisanSheet : cooldown grisé + compte à rebours
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
    <div v-if="poi" class="sheet-overlay" @click.self="$emit('close')">
      <div class="sheet">
        <button class="sheet-close" @click="$emit('close')">✕</button>
        <img v-if="poi.image_url" :src="poi.image_url" alt="" class="sheet-photo" />
        <div class="sheet-header">
          <h3>{{ poi.name }}</h3>
          <span class="category">{{ typeLabel }}</span>
        </div>
        <p v-if="poi.address" class="address">{{ poi.address }}</p>
        <p v-if="scheduleLine" class="schedules">🕐 {{ scheduleLine }}</p>
        <p v-if="poi.phone" class="address">📞 {{ poi.phone }}</p>

        <div class="play-section">
          <button class="btn btn-primary play-btn" :disabled="checkinDisabled" @click="$emit('checkin')">
            {{ checkinLabel }}
          </button>
          <p v-if="!authenticated" class="play-hint">Connexion par email requise pour jouer.</p>
        </div>

        <div class="actions">
          <button class="btn btn-primary" @click="$emit('navigate', poi)">Itinéraire</button>
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
  max-height: 70vh;
  overflow-y: auto;
}
.sheet-close {
  position: absolute;
  top: 12px;
  right: 16px;
  background: none;
  border: none;
  font-size: 1.2rem;
  cursor: pointer;
  z-index: 1;
}
.sheet-photo {
  width: 100%;
  max-height: 140px;
  object-fit: cover;
  border-radius: 12px;
  margin-bottom: 12px;
}
.sheet-header h3 { margin: 0 0 4px; }
.category { color: #1a73e8; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.04em; }
.address { color: #666; margin: 6px 0 0; }
.schedules { color: #666; margin: 6px 0 0; font-size: 0.9rem; }

.play-section {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--c-border);
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.play-btn { width: 100%; text-align: center; }
.play-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.play-hint { margin: 0; font-size: 0.8rem; color: var(--c-text-2); }

.actions { display: flex; gap: 10px; margin-top: 16px; }

.slide-up-enter-active, .slide-up-leave-active { transition: transform 0.3s ease; }
.slide-up-enter-from, .slide-up-leave-to { transform: translateY(100%); }
</style>
