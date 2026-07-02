import { ref } from 'vue'
import { getUserToken, recordXpEvent } from '../api.js'

const toasts = ref([])
const toastTimers = []
let toastIdCounter = 0

function generateToastId() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  return `${Date.now()}-${++toastIdCounter}`
}

export function useGamification() {
  /**
   * Records a gamified action via the /gamification/xp endpoint.
   *
   * Returns the normalized envelope from recordXpEvent. Callers should
   * check `result.success` before using `result.data`.
   *
   * @param {string} action
   * @param {string|null} [resourceKey]
   * @param {any} [metadata]
   * @returns {Promise<{ success: boolean, data: any, status: number, error: string|undefined }>}
   */
  async function recordAction(action, resourceKey = null, metadata = null) {
    const token = getUserToken()
    if (!token) return { success: false, status: 0, error: 'Non authentifié' }

    const result = await recordXpEvent(action, resourceKey, metadata)

    if (result.success && result.data && result.data.xp_gained > 0) {
      showToast(`+${result.data.xp_gained} XP`)
      if (result.data.level_up) showToast('Niveau supérieur !')
      if (result.data.new_badges?.length) {
        for (const b of result.data.new_badges) showToast(`Badge débloqué : ${b.name}`)
      }
    }
    return result
  }

  function showToast(message, duration = 3000) {
    const id = generateToastId()
    toasts.value.push({ id, message })
    const timer = setTimeout(() => {
      toasts.value = toasts.value.filter(t => t.id !== id)
      const index = toastTimers.indexOf(timer)
      if (index > -1) toastTimers.splice(index, 1)
    }, duration)
    toastTimers.push(timer)
  }

  function clearToasts() {
    for (const timer of toastTimers) {
      clearTimeout(timer)
    }
    toastTimers.length = 0
    toasts.value = []
  }

  return { toasts, recordAction, showToast, clearToasts }
}
