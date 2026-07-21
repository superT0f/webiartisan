import { ref, computed } from 'vue'

const REGEN_STEP = 5
const REGEN_TICK_MS = 600_000 // 10 min

// Singleton module (pattern useGamification) : l'énergie vient du serveur,
// la projection locale évite de poller entre deux réponses.
const energy = ref(null)
const now = ref(Date.now())
let timer = null

function ensureTimer() {
  if (timer) return
  timer = setInterval(() => { now.value = Date.now() }, 30_000)
}

/**
 * Projection pure du solde : +5 par tranche de 10 min après next_energy_at,
 * plafonné à max. `at` injectable pour les tests.
 */
export function projectEnergy(e, at = Date.now()) {
  if (!e) return null
  if (e.current >= e.max || !e.next_energy_at) return e.current
  const nextAt = new Date(e.next_energy_at).getTime()
  const ticks = Math.max(0, Math.floor((at - nextAt + REGEN_TICK_MS) / REGEN_TICK_MS))
  return Math.min(e.max, e.current + ticks * REGEN_STEP)
}

export function useEnergy() {
  const current = computed(() => projectEnergy(energy.value, now.value))

  function setEnergy(e) {
    if (!e) return
    energy.value = e
    ensureTimer()
  }

  return { energy, current, setEnergy }
}
