import { ref } from 'vue'
import { getWorldObjects } from '../api.js'
import { useEnergy } from './useEnergy.js'

// Singleton module (pattern useGamification)
const objects = ref([])
const cityCleanliness = ref(null)

export function useWorldObjects() {
  const { setEnergy } = useEnergy()

  async function fetchNearby(lat, lng) {
    const res = await getWorldObjects(lat, lng)
    if (res.success && res.data) {
      objects.value = res.data.objects || []
      cityCleanliness.value = res.data.city_cleanliness ?? null
      setEnergy(res.data.energy)
    }
    return res
  }

  function removeObject(id) {
    objects.value = objects.value.filter(o => o.id !== id)
  }

  return { objects, cityCleanliness, fetchNearby, removeObject }
}
