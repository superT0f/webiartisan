import { ref, onUnmounted, getCurrentInstance } from 'vue'
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

// ------------------------------------------------------------------
// Position partagée (singleton) + cache localStorage.
// Le GPS lâche souvent quelques secondes (intérieur, tunnel, changement
// d'écran) : on conserve la dernière position connue plutôt que de
// déclarer « Position indisponible » alors qu'on en avait une à l'instant.
// ------------------------------------------------------------------
const CACHE_KEY = 'geo_last_position'
const CACHE_TTL_MS = 5 * 60 * 1000 // 5 min

export function readPositionCache(storage = globalThis.localStorage) {
  try {
    const raw = storage?.getItem(CACHE_KEY)
    if (!raw) return null
    const { latitude, longitude, accuracy, at } = JSON.parse(raw)
    if (typeof latitude !== 'number' || typeof longitude !== 'number') return null
    if (Date.now() - at > CACHE_TTL_MS) return null
    return { latitude, longitude, accuracy }
  } catch {
    return null
  }
}

export function writePositionCache(p, storage = globalThis.localStorage) {
  try {
    storage?.setItem(CACHE_KEY, JSON.stringify({
      latitude: p.latitude,
      longitude: p.longitude,
      accuracy: p.accuracy ?? null,
      at: Date.now(),
    }))
  } catch { /* quota / mode privé : le cache est un bonus, jamais bloquant */ }
}

// Singleton : MapView, Inventaire, etc. partagent la même dernière position.
const position = ref(readPositionCache()) // { latitude, longitude, accuracy } | null
const error = ref('')

function setPosition(p) {
  position.value = p
  writePositionCache(p)
}

/**
 * Watches the user's position via the Flutter bridge (app) or
 * navigator.geolocation (web). position est amorcée avec le cache local
 * (si frais) puis mise à jour dès le premier fix réel.
 */
export function useGeolocation() {
  let webWatchId = null
  let flutterWatchId = null

  async function start() {
    error.value = ''
    try {
      if (isFlutter()) {
        setPosition(await getPosition({ accuracy: 'best', timeout: 15000, maxAccuracy: 50 }))
        flutterWatchId = watchPosition((err, pos) => {
          if (!err && pos) setPosition(pos)
        }, { accuracy: 'best', distanceFilter: 10 })
      } else if (navigator.geolocation) {
        setPosition(await new Promise((resolve, reject) => {
          navigator.geolocation.getCurrentPosition(
            p => resolve({ latitude: p.coords.latitude, longitude: p.coords.longitude, accuracy: p.coords.accuracy }),
            reject,
            { enableHighAccuracy: true, timeout: 15000 }
          )
        }))
        webWatchId = navigator.geolocation.watchPosition(
          p => setPosition({ latitude: p.coords.latitude, longitude: p.coords.longitude, accuracy: p.coords.accuracy }),
          () => {},
          { enableHighAccuracy: true }
        )
      } else {
        error.value = 'Géolocalisation indisponible'
      }
    } catch (e) {
      // Position précédente encore utilisable → on garde le silence, la
      // carte et les actions continuent sur la dernière position connue.
      if (position.value) return
      // Permission refusée (app : code string, web : code 1) → message actionnable
      if (e?.code === 'permission_denied' || e?.code === 1) {
        error.value = 'Permission GPS refusée — active-la dans les réglages du téléphone'
      } else if (e?.code === 'service_disabled') {
        error.value = 'Localisation désactivée — active le GPS du téléphone'
      } else {
        error.value = 'Position indisponible'
      }
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

  /**
   * Force une lecture fraîche de la position (one-shot, timeout court).
   * À appeler avant une action sensible (check-in). Si la lecture échoue,
   * on retourne la dernière position connue (cache) plutôt que null.
   */
  async function refresh() {
    try {
      if (isFlutter()) {
        setPosition(await getPosition({ accuracy: 'best', timeout: 8000, maxAccuracy: 100 }))
      } else if (navigator.geolocation) {
        setPosition(await new Promise((resolve, reject) => {
          navigator.geolocation.getCurrentPosition(
            p => resolve({ latitude: p.coords.latitude, longitude: p.coords.longitude, accuracy: p.coords.accuracy }),
            reject,
            { enableHighAccuracy: true, timeout: 8000 }
          )
        }))
      }
    } catch (e) {
      // Garde la dernière position connue si la lecture échoue
    }
    return position.value
  }

  if (getCurrentInstance()) onUnmounted(stop)

  return { position, error, start, stop, refresh }
}
