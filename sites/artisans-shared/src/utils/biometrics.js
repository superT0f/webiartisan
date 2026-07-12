/**
 * Pont biométrique Flutter.
 * N'est fonctionnel que lorsque l'application s'exécute dans la WebView Flutter
 * (Android / iOS) qui expose `window.FlutterBiometrics`.
 */

const callbacks = new Map()
let idCounter = 0

function generateId() {
  return `bio-${++idCounter}-${Date.now()}-${Math.random().toString(36).slice(2)}`
}

function isAvailable() {
  return typeof window !== 'undefined'
    && typeof window.FlutterBiometrics !== 'undefined'
    && typeof window.FlutterBiometrics.postMessage === 'function'
}

function send(action, payload = {}) {
  return new Promise((resolve, reject) => {
    if (!isAvailable()) {
      reject(new Error('Biométrie non disponible'))
      return
    }

    const callbackId = generateId()
    const timer = setTimeout(() => {
      callbacks.delete(callbackId)
      reject(new Error('Timeout biométrie'))
    }, 30000)

    callbacks.set(callbackId, (response) => {
      clearTimeout(timer)
      callbacks.delete(callbackId)
      if (response && response.authenticated === false) {
        reject(new Error('Authentification biométrique refusée'))
      } else if (response && response.error) {
        reject(new Error(response.error))
      } else {
        resolve(response)
      }
    })

    try {
      window.FlutterBiometrics.postMessage(JSON.stringify({ action, callbackId, payload }))
    } catch (e) {
      clearTimeout(timer)
      callbacks.delete(callbackId)
      reject(e)
    }
  })
}

/**
 * Handler global appelé par le code natif Flutter.
 */
window.onBiometricResponse = function (callbackId, response) {
  const cb = callbacks.get(callbackId)
  if (cb) {
    cb(response)
  }
}

export const biometrics = {
  isAvailable,

  /**
   * Vérifie si le capteur biométrique est disponible sur l'appareil.
   * @returns {Promise<boolean>}
   */
  async checkAvailable() {
    if (!isAvailable()) return false
    try {
      const res = await send('isAvailable')
      return !!res.available
    } catch {
      return false
    }
  },

  /**
   * Déclenche une authentification biométrique.
   * @param {string} reason
   */
  authenticate(reason = 'Authentification requise') {
    return send('authenticate', { reason })
  },

  /**
   * Active la biométrie sur l'appareil et retourne { secret, deviceId }.
   * @param {string} reason
   */
  enable(reason = 'Activer la biométrie sur cet appareil') {
    return send('enable', { reason })
  },

  /**
   * Récupère le secret biométrique après authentification.
   * @param {string} reason
   */
  getSecret(reason = 'Connexion rapide') {
    return send('getSecret', { reason })
  },

  /**
   * Retourne l'identifiant unique de l'appareil.
   */
  getDeviceId() {
    return send('getDeviceId')
  },

  /**
   * Supprime les données biométriques locales.
   */
  clear() {
    return send('clear')
  },
}
