/**
 * Authentification centrale des frontends artisans.
 *
 * Deux types de tokens cohabitent :
 * - token « artisan » (header X-Artisan-Token) pour l'espace artisan ;
 * - token « user » (header Authorization Bearer) pour les fonctions joueur/consommateur.
 *
 * Chaque token vit soit en localStorage (session, « rester connecté » décoché),
 * soit dans un cookie longue durée (365 j). Le transport cookie est requis par
 * la WebView Flutter, et admin-login.html écrit localStorage['artisan_token']
 * directement : NE PAS renommer les clés de stockage.
 */

const ARTISAN_TOKEN_KEY = 'artisan_token'
const ARTISAN_TOKEN_COOKIE = 'artisan_token'
const USER_TOKEN_KEY = 'spin_user_token'
const USER_TOKEN_COOKIE = 'user_token'

function setCookie(name, value, days) {
  const date = new Date()
  date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000)
  const secure = location.protocol === 'https:' ? '; Secure' : ''
  document.cookie = `${name}=${encodeURIComponent(value)}; expires=${date.toUTCString()}; path=/; SameSite=Lax${secure}`
}

function getCookie(name) {
  const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'))
  return match ? decodeURIComponent(match[2]) : null
}

function deleteCookie(name) {
  document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax`
}

export const authEvents = new EventTarget()
export function notifyAuthChanged() {
  authEvents.dispatchEvent(new Event('change'))
}

/**
 * Envoie un message au conteneur Flutter via le JavaScriptChannel FlutterBridge.
 * Nécessite que l'application Flutter ait enregistré le channel `FlutterBridge`.
 */
export function postMessageToFlutter(action, payload = null) {
  if (typeof window !== 'undefined' && window.FlutterBridge && typeof window.FlutterBridge.postMessage === 'function') {
    try {
      window.FlutterBridge.postMessage(JSON.stringify({ action, payload }))
    } catch (e) {
      // Ignorer silencieusement si le bridge n'est pas disponible (navigateur web classique)
    }
  }
}

// --- Token artisan ---------------------------------------------------

export function getArtisanToken() {
  return localStorage.getItem(ARTISAN_TOKEN_KEY) || getCookie(ARTISAN_TOKEN_COOKIE) || ''
}

export function setArtisanToken(token, remember = false) {
  if (remember) {
    setCookie(ARTISAN_TOKEN_COOKIE, token, 365)
    localStorage.removeItem(ARTISAN_TOKEN_KEY)
  } else {
    localStorage.setItem(ARTISAN_TOKEN_KEY, token)
    deleteCookie(ARTISAN_TOKEN_COOKIE)
  }
}

export function removeArtisanToken() {
  localStorage.removeItem(ARTISAN_TOKEN_KEY)
  deleteCookie(ARTISAN_TOKEN_COOKIE)
}

// --- Token user ------------------------------------------------------

export function getUserToken() {
  return localStorage.getItem(USER_TOKEN_KEY) || getCookie(USER_TOKEN_COOKIE) || ''
}

export function setUserToken(token, remember = false) {
  if (remember) {
    setCookie(USER_TOKEN_COOKIE, token, 365)
    localStorage.removeItem(USER_TOKEN_KEY)
  } else {
    localStorage.setItem(USER_TOKEN_KEY, token)
    deleteCookie(USER_TOKEN_COOKIE)
  }
  notifyAuthChanged()
}

export function removeUserToken() {
  localStorage.removeItem(USER_TOKEN_KEY)
  deleteCookie(USER_TOKEN_COOKIE)
  notifyAuthChanged()
}

// --- Liens magiques (?token= dans l'URL) ------------------------------

/**
 * Extrait un token de lien magique d'une cible de route.
 * - chemins `/espace*` → token artisan (définitif, à stocker tel quel) ;
 *   rememberMe vrai seulement si `rememberMe=1|true` dans l'URL.
 * - autres chemins → token user temporaire (à échanger via l'API) ;
 *   rememberMe vrai par défaut, faux si `rememberMe=0|false`.
 *
 * @param {{path: string, query: Record<string, any>}} to
 * @returns {null | {token: string, rememberMe: boolean, type: 'artisan'|'user'}}
 */
export function extractLinkToken(to) {
  const raw = Array.isArray(to.query?.token) ? to.query.token[0] : to.query?.token
  if (!raw) return null
  const isArtisan = to.path.startsWith('/espace')
  const rememberMe = isArtisan
    ? to.query.rememberMe === '1' || to.query.rememberMe === 'true'
    : to.query.rememberMe !== '0' && to.query.rememberMe !== 'false'
  return { token: raw, rememberMe, type: isArtisan ? 'artisan' : 'user' }
}

/**
 * Consomme un `?token=` d'URL (connexion automatique par lien magique).
 * `exchangeUserToken(token, rememberMe)` est injecté par l'appelant
 * (en pratique `authUser` de api.js) pour éviter un import circulaire.
 *
 * @param {{path: string, query: Record<string, any>}} to
 * @param {(token: string, rememberMe: boolean) => Promise<any>} exchangeUserToken
 * @returns {Promise<null | {type: 'artisan'|'user', success: boolean, error?: string}>}
 */
export async function consumeTokenFromQuery(to, exchangeUserToken) {
  const link = extractLinkToken(to)
  if (!link) return null
  if (link.type === 'artisan') {
    setArtisanToken(link.token, link.rememberMe)
    return { type: 'artisan', success: true }
  }
  try {
    const res = await exchangeUserToken(link.token, link.rememberMe)
    if (res?.success && res.token) {
      setUserToken(res.token, link.rememberMe)
      return { type: 'user', success: true }
    }
    return { type: 'user', success: false, error: res?.error || 'Lien invalide' }
  } catch {
    return { type: 'user', success: false, error: 'Erreur réseau.' }
  }
}
