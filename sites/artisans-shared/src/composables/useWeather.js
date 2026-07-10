import { ref } from 'vue'
import { fetchWeather } from '../api.js'

const cache = ref(null)
let lastFetch = 0

export function useWeather(lat, lng) {
  const weather = ref(null)
  const loading = ref(false)

  async function load() {
    if (Date.now() - lastFetch < 15 * 60 * 1000 && cache.value) {
      weather.value = cache.value
      return
    }
    loading.value = true
    const res = await fetchWeather(lat, lng)
    if (res.success) {
      weather.value = res.data
      cache.value = res.data
      lastFetch = Date.now()
    }
    loading.value = false
  }

  return { weather, loading, load }
}
