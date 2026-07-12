export const API_BASE  = import.meta.env.VITE_API_URL   || 'https://api.prigent.tech'
export const CITY_SLUG  = import.meta.env.VITE_CITY_SLUG  || 'livry'
export const CITY_NAME  = import.meta.env.VITE_CITY_NAME  || 'Livry'
export const CITY_LAT   = parseFloat(import.meta.env.VITE_CITY_LAT   || '49.1081')
export const CITY_LNG   = parseFloat(import.meta.env.VITE_CITY_LNG   || '-0.7658')
export const CITY_CP    = import.meta.env.VITE_CITY_CP    || '14240'
export const APP_VERSION = import.meta.env.VITE_APP_VERSION || '1.0.0'

export async function fetchArtisans(params = {}) {
  const qs = new URLSearchParams({ city: CITY_SLUG, ...params }).toString()
  const res = await fetch(`${API_BASE}/artisans?${qs}`)
  if (!res.ok) throw new Error('Erreur chargement artisans')
  return res.json()
}

export async function fetchArtisan(id) {
  const res = await fetch(`${API_BASE}/artisans/${id}`)
  if (!res.ok) throw new Error('Artisan non trouvé')
  return res.json()
}

export async function fetchCategories() {
  // Retourne les catégories avec artisans pour la ville actuelle
  const res = await fetchArtisans({ limit: 200 })
  const map = {}
  for (const a of res.data || []) {
    if (a.category_slug && !map[a.category_slug]) {
      map[a.category_slug] = {
        slug: a.category_slug,
        name: a.category_name,
        icon: a.category_icon,
        color: a.category_color,
        count: 0,
      }
    }
    if (a.category_slug) map[a.category_slug].count++
  }
  return Object.values(map).sort((a, b) => b.count - a.count)
}

export async function fetchCityPois() {
  const res = await fetch(`${API_BASE}/cities/${CITY_SLUG}/pois`)
  if (!res.ok) throw new Error('Erreur chargement POI')
  return res.json()
}

export async function fetchWeather(lat, lng) {
  const res = await fetch(`${API_BASE}/weather?lat=${lat}&lng=${lng}`)
  return res.json()
}

export async function registerArtisan(data) {
  const res = await fetch(`${API_BASE}/artisans/register`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ...data, city_slug: CITY_SLUG }),
  })
  return res.json()
}

export async function postReview(artisanId, data) {
  const res = await fetch(`${API_BASE}/artisans/${artisanId}/review`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function contactArtisan(artisanId, data) {
  const res = await fetch(`${API_BASE}/artisans/${artisanId}/contact`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  })
  return res.json()
}

// --- Authentification artisan ------------------------------------

const ARTISAN_TOKEN_KEY = 'artisan_token'
const ARTISAN_TOKEN_COOKIE = 'artisan_token'

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

export async function logoutArtisan(token, options = {}) {
  return requestJson(`${API_BASE}/artisans/logout`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    signal: options.signal,
  }, 'Erreur lors de la déconnexion.')
}

export async function createSubscriptionCheckout(returnUrl, options = {}) {
  return requestJson(`${API_BASE}/subscription/checkout`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': getArtisanToken(),
    },
    body: JSON.stringify({ return_url: returnUrl }),
    signal: options.signal,
  }, 'Erreur lors de la création du paiement.')
}

export async function getSubscriptionStatus(options = {}) {
  return requestJson(`${API_BASE}/subscription/status`, {
    headers: { 'X-Artisan-Token': getArtisanToken() },
    signal: options.signal,
  }, 'Impossible de charger votre abonnement.')
}

export async function createSubscriptionPortal(returnUrl, options = {}) {
  return requestJson(`${API_BASE}/subscription/portal`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': getArtisanToken(),
    },
    body: JSON.stringify({ return_url: returnUrl }),
    signal: options.signal,
  }, 'Erreur lors de l\'ouverture du portail.')
}

export async function requestMagicLink(email, rememberMe = true, options = {}) {
  return requestJson(`${API_BASE}/artisans/magic-link`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, rememberMe }),
    signal: options.signal,
  }, 'Erreur lors de l\'envoi.')
}

export async function loginArtisan(data) {
  const res = await fetch(`${API_BASE}/artisans/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function fetchMe(token, options = {}) {
  return requestJson(`${API_BASE}/artisans/me`, {
    headers: { 'X-Artisan-Token': token },
    signal: options.signal,
  }, 'Impossible de charger votre profil.')
}

export async function changeArtisanPassword(token, currentPassword, newPassword, confirmPassword) {
  return requestJson(`${API_BASE}/artisans/me/change-password`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify({ current_password: currentPassword, new_password: newPassword, confirm_password: confirmPassword }),
  }, 'Erreur lors du changement de mot de passe.')
}

export async function updateMe(token, data, options = {}) {
  return requestJson(`${API_BASE}/artisans/me`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
    signal: options.signal,
  }, 'Erreur lors de la sauvegarde.')
}

// --- Prospection B2B ---------------------------------------------

export async function getProspects(filters = {}) {
  const qs = new URLSearchParams({ city: CITY_SLUG, ...filters }).toString()
  const res = await fetch(`${API_BASE}/prospects?${qs}`)
  if (!res.ok) throw new Error('Erreur chargement prospects')
  return res.json()
}

export async function getProspect(id) {
  const res = await fetch(`${API_BASE}/prospects/${id}`)
  if (!res.ok) throw new Error('Prospect non trouvé')
  return res.json()
}

export async function getMyProspects(token, options = {}) {
  return requestJson(`${API_BASE}/artisans/me/prospects`, {
    headers: { 'X-Artisan-Token': token },
    signal: options.signal,
  }, 'Impossible de charger vos prospects.')
}

export async function followProspect(token, prospectId, data) {
  const res = await fetch(`${API_BASE}/artisans/me/prospects/${prospectId}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function unfollowProspect(token, prospectId) {
  const res = await fetch(`${API_BASE}/artisans/me/prospects/${prospectId}`, {
    method: 'DELETE',
    headers: { 'X-Artisan-Token': token },
  })
  return res.json()
}

// --- Recettes locales ----------------------------------------------

export async function getRecipes(filters = {}) {
  const qs = new URLSearchParams({ city: CITY_SLUG, ...filters }).toString()
  const res = await fetch(`${API_BASE}/recipes?${qs}`)
  if (!res.ok) throw new Error('Erreur chargement recettes')
  return res.json()
}

export async function getRecipe(slug) {
  const res = await fetch(`${API_BASE}/recipes/${slug}`)
  if (!res.ok) throw new Error('Recette non trouvée')
  return res.json()
}

export async function createRecipe(data) {
  const res = await fetch(`${API_BASE}/recipes`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ city_slug: CITY_SLUG, ...data }),
  })
  return res.json()
}

export async function reportRecipe(id, reason) {
  const res = await fetch(`${API_BASE}/recipes/${id}/report`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ reason }),
  })
  return res.json()
}

export async function suggestRecipe(id, data) {
  const res = await fetch(`${API_BASE}/recipes/${id}/suggest`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ city_slug: CITY_SLUG, ...data }),
  })
  return res.json()
}

// --- Administration recettes ---------------------------------------

export async function getAdminRecipes(token, filters = {}) {
  const qs = new URLSearchParams(filters).toString()
  const res = await fetch(`${API_BASE}/artisans/me/admin-recipes${qs ? '?' + qs : ''}`, {
    headers: { 'X-Artisan-Token': token },
  })
  return res.json()
}

export async function archiveRecipe(token, recipeId) {
  const res = await fetch(`${API_BASE}/artisans/me/admin-recipes/${recipeId}/archive`, {
    method: 'PUT',
    headers: { 'X-Artisan-Token': token },
  })
  return res.json()
}

// --- Authentification consommateur ------------------------------

const USER_TOKEN_KEY = 'spin_user_token'
const USER_TOKEN_COOKIE = 'user_token'

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

function userHeaders() {
  const token = getUserToken()
  return token ? { Authorization: `Bearer ${token}` } : {}
}

export async function requestUserMagicLink(email, rememberMe = true, redirect = '/roue') {
  const res = await fetch(`${API_BASE}/users/magic-link`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, rememberMe, redirect }),
  })
  return res.json()
}

export async function fetchArtisanConsumerToken(artisanToken, options = {}) {
  return requestJson(`${API_BASE}/artisans/me/consumer-token`, {
    method: 'POST',
    headers: { 'X-Artisan-Token': artisanToken },
    signal: options.signal,
  }, 'Impossible de créer le compte joueur.')
}

export async function authUser(token, rememberMe = true) {
  const res = await fetch(`${API_BASE}/users/auth?token=${encodeURIComponent(token)}&rememberMe=${rememberMe ? 1 : 0}`, {
    method: 'POST',
  })
  return res.json()
}

export async function registerUser(data) {
  const res = await fetch(`${API_BASE}/users/register`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function loginUser(data) {
  const res = await fetch(`${API_BASE}/users/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function logoutUser(token) {
  const res = await fetch(`${API_BASE}/users/logout`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${token}` },
  })
  removeUserToken()
  postMessageToFlutter('logout')
  return res.json()
}

export async function requestPasswordReset(email) {
  const res = await fetch(`${API_BASE}/users/forgot-password`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email }),
  })
  return res.json()
}

export async function resetPassword(token, password) {
  const res = await fetch(`${API_BASE}/users/reset-password`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ token, password }),
  })
  return res.json()
}

export async function enableBiometric(token, deviceId, secret, deviceName = 'Mon appareil') {
  return requestJson(`${API_BASE}/auth/biometric-enable`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify({ device_id: deviceId, secret, device_name: deviceName }),
  }, 'Erreur lors de l\'activation biométrique.')
}

export async function disableBiometric(token, deviceId) {
  return requestJson(`${API_BASE}/auth/biometric-disable`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify({ device_id: deviceId }),
  }, 'Erreur lors de la désactivation biométrique.')
}

export async function biometricLogin(deviceId, secret) {
  const res = await fetch(`${API_BASE}/auth/biometric-login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ device_id: deviceId, secret }),
  })
  return res.json()
}

export async function changeUserPassword(token, currentPassword, newPassword) {
  const res = await fetch(`${API_BASE}/users/change-password`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify({ current_password: currentPassword, new_password: newPassword, confirm_password: newPassword }),
  })
  return res.json()
}

export function resolveAvatarUrl(avatarUrl) {
  if (!avatarUrl) return null
  try {
    const base = new URL(API_BASE)
    const url = new URL(avatarUrl, API_BASE)
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return null
    if (url.host !== base.host) return null
    const allowedPaths = ['/avatars/', '/uploads/avatars/']
    if (!allowedPaths.some((p) => url.pathname.startsWith(p))) return null
    return url.href
  } catch {
    return null
  }
}

// ------------------------------------------------------------------
// API helper convention
// ------------------------------------------------------------------
// The helpers below (fetchUserMe, fetchAvatars, updateUserProfile,
// updateUserAvatar) return a normalized envelope:
//   { success, data, status, error }
// They do NOT throw on HTTP or network errors. Callers should always
// check `res.success` before using `res.data`.
//
// `status` is the HTTP status code (0 for network/abort errors).
// `error` is a user-facing French message or 'AbortError' when the
// request was intentionally cancelled.
//
// Older helpers in this file are gradually being migrated to this
// pattern; new helpers should follow it.
// ------------------------------------------------------------------

/**
 * Fetch JSON from the API and return a normalized envelope.
 *
 * @param {string} url
 * @param {RequestInit} [options]
 * @param {string|null} [networkError] - Custom user-facing message for network failures.
 * @returns {Promise<{success: boolean, data: any, status: number, error: string|undefined}>}
 */
export async function requestJson(url, options = {}, networkError = null) {
  let res
  try {
    res = await fetch(url, options)
  } catch (e) {
    if (e.name === 'AbortError') {
      return { success: false, error: 'AbortError', status: 0 }
    }
    return { success: false, status: 0, error: networkError || 'Erreur réseau' }
  }

  let json = {}
  try {
    const text = await res.text()
    if (text) json = JSON.parse(text)
  } catch {
    return { success: false, status: res.status, error: 'Réponse invalide' }
  }

  return {
    success: res.ok && json.success !== false,
    data: json.data,
    status: res.status,
    error: json.error,
  }
}

/**
 * Fetch the current consumer user profile.
 * Returns a normalized object: { success, data, status, error }.
 * The HTTP status is always exposed in `status`, even when the body is empty or invalid.
 */
export async function fetchUserMe(token, options = {}) {
  const res = await requestJson(`${API_BASE}/users/me`, {
    headers: { 'Authorization': `Bearer ${token}` },
    signal: options.signal,
  }, 'Impossible de charger le profil.')
  if (!res.success && res.status === 401) {
    return { ...res, error: 'Session expirée' }
  }
  return res
}

export async function fetchAvatars(gender = 'neutral', options = {}) {
  const res = await requestJson(`${API_BASE}/avatars?gender=${encodeURIComponent(gender)}`, {
    signal: options.signal,
  }, 'Impossible de charger les avatars.')
  return res
}

export async function updateUserProfile(token, data, options = {}) {
  const res = await requestJson(`${API_BASE}/users/me`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify(data),
    signal: options.signal,
  }, 'Erreur mise à jour profil')
  if (!res.success && res.status === 401) {
    return { ...res, error: 'Session expirée' }
  }
  if (!res.success && !res.error) {
    return { ...res, error: 'Erreur mise à jour profil' }
  }
  return res
}

export async function updateUserAvatar(token, data, options = {}) {
  const res = await requestJson(`${API_BASE}/users/me/avatar`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify(data),
    signal: options.signal,
  }, 'Erreur mise à jour avatar')
  if (!res.success && res.status === 401) {
    return { ...res, error: 'Session expirée' }
  }
  if (!res.success && !res.error) {
    return { ...res, error: 'Erreur mise à jour avatar' }
  }
  return res
}

// --- Spin wheel -------------------------------------------------

export async function getSpinOffers(options = {}) {
  return requestJson(`${API_BASE}/spin/offers?city=${CITY_SLUG}`, {
    signal: options.signal,
  }, 'Impossible de charger les offres.')
}

export async function postSpin(token, payload = {}, options = {}) {
  return requestJson(`${API_BASE}/spin`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify({ city_slug: CITY_SLUG, ...payload }),
    signal: options.signal,
  }, 'Erreur réseau.')
}

export async function getSpinWins(token, options = {}) {
  return requestJson(`${API_BASE}/spin/wins`, {
    headers: { 'Authorization': `Bearer ${token}` },
    signal: options.signal,
  }, 'Impossible de charger vos gains.')
}

// --- Gestion artisan spin ---------------------------------------

export async function getArtisanSpinOffers(token) {
  const res = await fetch(`${API_BASE}/artisans/me/spin-offers`, {
    headers: { 'X-Artisan-Token': token },
  })
  return res.json()
}

export async function createArtisanSpinOffer(token, data) {
  const res = await fetch(`${API_BASE}/artisans/me/spin-offers`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function updateArtisanSpinOffer(token, id, data) {
  const res = await fetch(`${API_BASE}/artisans/me/spin-offers/${id}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function deleteArtisanSpinOffer(token, id) {
  const res = await fetch(`${API_BASE}/artisans/me/spin-offers/${id}`, {
    method: 'DELETE',
    headers: { 'X-Artisan-Token': token },
  })
  return res.json()
}

export async function getArtisanSpinWins(token, status = '') {
  const qs = status ? `?status=${encodeURIComponent(status)}` : ''
  const res = await fetch(`${API_BASE}/artisans/me/spin-wins${qs}`, {
    headers: { 'X-Artisan-Token': token },
  })
  return res.json()
}

export async function validateArtisanSpinWin(token, code) {
  const res = await fetch(`${API_BASE}/artisans/me/spin-wins/${encodeURIComponent(code)}/validate`, {
    method: 'POST',
    headers: { 'X-Artisan-Token': token },
  })
  return res.json()
}

// Codes météo WMO → description + emoji
export function weatherInfo(code) {
  const map = {
    0:  { label: 'Ciel dégagé',      emoji: '☀️' },
    1:  { label: 'Principalement dégagé', emoji: '🌤️' },
    2:  { label: 'Partiellement nuageux', emoji: '⛅' },
    3:  { label: 'Couvert',           emoji: '☁️' },
    45: { label: 'Brouillard',        emoji: '🌫️' },
    51: { label: 'Bruine légère',     emoji: '🌦️' },
    61: { label: 'Pluie légère',      emoji: '🌧️' },
    63: { label: 'Pluie modérée',     emoji: '🌧️' },
    65: { label: 'Pluie forte',       emoji: '⛈️' },
    71: { label: 'Neige légère',      emoji: '🌨️' },
    80: { label: 'Averses',           emoji: '🌦️' },
    95: { label: 'Orage',             emoji: '⛈️' },
  }
  return map[code] || { label: 'Variable', emoji: '🌤️' }
}

export const DAYS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche']

export function formatTime(t) {
  if (!t) return '—'
  return t.slice(0, 5)
}

export function todayIndex() {
  // 0=Lundi, 6=Dimanche (comme la BDD)
  return (new Date().getDay() + 6) % 7
}

// --- Administration ------------------------------------------------

export async function fetchAdminArtisans(token, options = {}) {
  return requestJson(`${API_BASE}/admin/artisans`, {
    headers: { 'X-Artisan-Token': token },
    signal: options.signal,
  }, 'Impossible de charger les artisans.')
}

export async function activateArtisan(token, id, options = {}) {
  return requestJson(`${API_BASE}/admin/artisans/${id}/activate`, {
    method: 'POST',
    headers: { 'X-Artisan-Token': token },
    signal: options.signal,
  }, 'Erreur lors de l\'activation.')
}

export async function suspendArtisan(token, id, options = {}) {
  return requestJson(`${API_BASE}/admin/artisans/${id}/suspend`, {
    method: 'POST',
    headers: { 'X-Artisan-Token': token },
    signal: options.signal,
  }, 'Erreur lors de la suspension.')
}

export async function setArtisanPlan(token, id, plan, options = {}) {
  return requestJson(`${API_BASE}/admin/artisans/${id}/set-plan`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify({ plan }),
    signal: options.signal,
  }, 'Erreur lors du changement de plan.')
}

export async function resetArtisanPassword(token, id, options = {}) {
  return requestJson(`${API_BASE}/admin/artisans/${id}/reset-password`, {
    method: 'POST',
    headers: { 'X-Artisan-Token': token },
    signal: options.signal,
  }, 'Erreur lors de l\'envoi du lien.')
}

export async function forceArtisanPassword(token, id, password, options = {}) {
  return requestJson(`${API_BASE}/admin/artisans/${id}/force-password`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify({ password }),
    signal: options.signal,
  }, 'Erreur lors du forçage du mot de passe.')
}

export async function setArtisanSubscriptionStatus(token, id, status, options = {}) {
  return requestJson(`${API_BASE}/admin/artisans/${id}/set-subscription-status`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify({ status }),
    signal: options.signal,
  }, 'Erreur lors du changement de statut.')
}

export async function updateAdminArtisan(token, id, data, options = {}) {
  return requestJson(`${API_BASE}/admin/artisans/${id}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
    signal: options.signal,
  }, 'Erreur lors de la mise à jour de l\'artisan.')
}

// --- Administration POI ------------------------------------------------

export async function fetchAdminPois(token, options = {}) {
  return requestJson(`${API_BASE}/admin/pois`, {
    headers: { 'X-Artisan-Token': token },
    signal: options.signal,
  }, 'Impossible de charger les POI.')
}

export async function fetchAdminPoi(token, id, options = {}) {
  return requestJson(`${API_BASE}/admin/pois/${id}`, {
    headers: { 'X-Artisan-Token': token },
    signal: options.signal,
  }, 'Impossible de charger le POI.')
}

export async function createAdminPoi(token, data, options = {}) {
  return requestJson(`${API_BASE}/admin/pois`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
    signal: options.signal,
  }, 'Erreur lors de la création du POI.')
}

export async function updateAdminPoi(token, id, data, options = {}) {
  return requestJson(`${API_BASE}/admin/pois/${id}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
    signal: options.signal,
  }, 'Erreur lors de la mise à jour du POI.')
}

export async function deleteAdminPoi(token, id, options = {}) {
  return requestJson(`${API_BASE}/admin/pois/${id}`, {
    method: 'DELETE',
    headers: { 'X-Artisan-Token': token },
    signal: options.signal,
  }, 'Erreur lors de la suppression du POI.')
}

export async function createAdminSchedule(token, data, options = {}) {
  return requestJson(`${API_BASE}/admin/schedules`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
    signal: options.signal,
  }, 'Erreur lors de l\'ajout de l\'horaire.')
}

export async function updateAdminSchedule(token, id, data, options = {}) {
  return requestJson(`${API_BASE}/admin/schedules/${id}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
    signal: options.signal,
  }, 'Erreur lors de la mise à jour de l\'horaire.')
}

export async function deleteAdminSchedule(token, id, options = {}) {
  return requestJson(`${API_BASE}/admin/schedules/${id}`, {
    method: 'DELETE',
    headers: { 'X-Artisan-Token': token },
    signal: options.signal,
  }, 'Erreur lors de la suppression de l\'horaire.')
}

// --- Témoignages ---------------------------------------------------

export async function fetchTestimonials(filters = {}) {
  const qs = new URLSearchParams({ city: CITY_SLUG, ...filters }).toString()
  const res = await fetch(`${API_BASE}/testimonials?${qs}`)
  if (!res.ok) throw new Error('Erreur chargement témoignages')
  return res.json()
}

export async function fetchTestimonial(id) {
  const res = await fetch(`${API_BASE}/testimonials/${id}`)
  if (!res.ok) throw new Error('Témoignage non trouvé')
  return res.json()
}

export async function createTestimonial(data) {
  try {
    const res = await fetch(`${API_BASE}/testimonials`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...userHeaders() },
      body: JSON.stringify(data),
    })
    return await res.json()
  } catch (e) {
    if (e.name === 'AbortError') {
      throw e
    }
    return { success: false, error: 'Erreur réseau lors de la publication' }
  }
}

export async function reportTestimonial(id, reason, details = '') {
  const res = await fetch(`${API_BASE}/testimonials/${id}/report`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...userHeaders() },
    body: JSON.stringify({ reason, details }),
  })
  return res.json()
}

export async function markTestimonialHelpful(id) {
  const res = await fetch(`${API_BASE}/testimonials/${id}/helpful`, {
    method: 'POST',
    headers: { ...userHeaders() },
  })
  return res.json()
}

export async function fetchTestimonialTemplates(serviceKey = null) {
  const qs = serviceKey ? `?service=${encodeURIComponent(serviceKey)}` : ''
  const res = await fetch(`${API_BASE}/testimonials/templates${qs}`)
  if (!res.ok) throw new Error('Erreur chargement modèles')
  return res.json()
}

// --- Services artisan ----------------------------------------------

export async function fetchServiceCatalog() {
  const res = await fetch(`${API_BASE}/service-catalog`)
  if (!res.ok) throw new Error('Erreur chargement catalogue')
  return res.json()
}

export async function fetchArtisanServices(artisanId) {
  const res = await fetch(`${API_BASE}/artisans/${artisanId}/services`)
  if (!res.ok) throw new Error('Erreur chargement services')
  return res.json()
}

export async function createArtisanService(token, data) {
  const res = await fetch(`${API_BASE}/artisans/me/services`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function updateArtisanService(token, serviceId, data) {
  const res = await fetch(`${API_BASE}/artisans/me/services/${serviceId}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function deleteArtisanService(token, serviceId) {
  const res = await fetch(`${API_BASE}/artisans/me/services/${serviceId}`, {
    method: 'DELETE',
    headers: { 'X-Artisan-Token': token },
  })
  return res.json()
}

export async function fetchMyServices(token) {
  const res = await fetch(`${API_BASE}/artisans/me/services`, {
    headers: { 'X-Artisan-Token': token },
  })
  if (!res.ok) throw new Error('Erreur chargement de mes services')
  return res.json()
}


// --- Gamification --------------------------------------------------

export async function fetchGamificationEvents() {
  const res = await fetch(`${API_BASE}/gamification/events`)
  if (!res.ok) throw new Error('Erreur chargement événements XP')
  return res.json()
}

export async function fetchUserProfile(userId) {
  const res = await fetch(`${API_BASE}/gamification/${userId}/xp`, {
    headers: { ...userHeaders() },
  })
  if (!res.ok) throw new Error('Erreur chargement profil')
  return res.json()
}

export async function recordXpEvent(action, resourceKey = null, metadata = null) {
  return requestJson(`${API_BASE}/gamification/xp`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...userHeaders() },
    body: JSON.stringify({ action, resource_key: resourceKey, metadata }),
  }, 'Erreur lors de l\'enregistrement de l\'action')
}

export async function fetchCityLeaderboard(cityId) {
  const res = await fetch(`${API_BASE}/gamification/leaderboards/city/${cityId}`)
  if (!res.ok) throw new Error('Erreur chargement classement')
  return res.json()
}

// --- Mini-jeux -----------------------------------------------------

export async function fetchGameTypes() {
  const res = await fetch(`${API_BASE}/games/types`)
  if (!res.ok) throw new Error('Erreur chargement types de jeux')
  return res.json()
}

export async function fetchGames(filters = {}) {
  const qs = new URLSearchParams({ city: CITY_SLUG, ...filters }).toString()
  const res = await fetch(`${API_BASE}/games?${qs}`)
  if (!res.ok) throw new Error('Erreur chargement jeux')
  return res.json()
}

export async function fetchGame(id) {
  const res = await fetch(`${API_BASE}/games/${id}`, {
    headers: { ...userHeaders() },
  })
  if (!res.ok) throw new Error('Jeu non trouvé')
  return res.json()
}

export async function playGame(id, data = {}) {
  const res = await fetch(`${API_BASE}/games/${id}/play`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...userHeaders() },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function fetchMyGames(token) {
  const res = await fetch(`${API_BASE}/artisans/me/games`, {
    headers: { 'X-Artisan-Token': token },
  })
  if (!res.ok) throw new Error('Erreur chargement de mes jeux')
  return res.json()
}

export async function createArtisanGame(token, data) {
  const res = await fetch(`${API_BASE}/artisans/me/games`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function updateArtisanGame(token, gameId, data) {
  const res = await fetch(`${API_BASE}/artisans/me/games/${gameId}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function deleteArtisanGame(token, gameId) {
  const res = await fetch(`${API_BASE}/artisans/me/games/${gameId}`, {
    method: 'DELETE',
    headers: { 'X-Artisan-Token': token },
  })
  return res.json()
}
