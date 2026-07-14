import { ref, onUnmounted } from 'vue'
import { getPosition, watchPosition, clearWatch } from '../utils/flutterBridge.js'

export function haversineM(lat1, lng1, lat2, lng2) {
  const r = 6371000
  const dLat = (lat2 - lat1) * Math.PI / 180
  const dLng = (lng2 - lng1) * Math.PI / 180
  const a = Math.sin(dLat / 2) ** 2
    + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2
  return 2 * r * Math.asin(Math.min(1, Math.sqrt(a)))
}

function isFlutter() {
  return typeof FlutterBridge !== 'undefined' && FlutterBridge.postMessage
}

/**
 * Watches the user's position via the Flutter bridge (app) or
 * navigator.geolocation (web). position is null until the first fix.
 */
export function useGeolocation() {
  const position = ref(null) // { latitude, longitude, accuracy }
  const error = ref('')
  let webWatchId = null
  let flutterWatchId = null

  async function start() {
    error.value = ''
    try {
      if (isFlutter()) {
        position.value = await getPosition({ accuracy: 'best', timeout: 15000, maxAccuracy: 50 })
        flutterWatchId = watchPosition((err, pos) => {
          if (!err && pos) position.value = pos
        }, { accuracy: 'best', distanceFilter: 10 })
      } else if (navigator.geolocation) {
        position.value = await new Promise((resolve, reject) => {
          navigator.geolocation.getCurrentPosition(
            p => resolve({ latitude: p.coords.latitude, longitude: p.coords.longitude, accuracy: p.coords.accuracy }),
            reject,
            { enableHighAccuracy: true, timeout: 15000 }
          )
        })
        webWatchId = navigator.geolocation.watchPosition(
          p => { position.value = { latitude: p.coords.latitude, longitude: p.coords.longitude, accuracy: p.coords.accuracy } },
          () => {},
          { enableHighAccuracy: true }
        )
      } else {
        error.value = 'Géolocalisation indisponible'
      }
    } catch (e) {
      error.value = 'Position indisponible'
    }
  }

  function stop() {
    if (webWatchId !== null && navigator.geolocation) {
      navigator.geolocation.clearWatch(webWatchId)
      webWatchId = null
    }
    if (flutterWatchId !== null) {
      clearWatch(flutterWatchId)
      flutterWatchId = null
    }
  }

  onUnmounted(stop)

  return { position, error, start, stop }
}
