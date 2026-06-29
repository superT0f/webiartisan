const API_BASE  = import.meta.env.VITE_API_URL   || 'https://api.prigent.tech'
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

export async function fetchWeather() {
  // Open-Meteo — coordonnées configurées par ville
  const url = `https://api.open-meteo.com/v1/forecast?latitude=${CITY_LAT}&longitude=${CITY_LNG}` +
              '&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m' +
              '&daily=temperature_2m_max,temperature_2m_min,weather_code' +
              '&timezone=Europe%2FParis&forecast_days=4'
  const res = await fetch(url)
  if (!res.ok) throw new Error('Météo indisponible')
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

export async function requestMagicLink(email) {
  const res = await fetch(`${API_BASE}/artisans/magic-link`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email }),
  })
  return res.json()
}

export async function fetchMe(token) {
  const res = await fetch(`${API_BASE}/artisans/me`, {
    headers: { 'X-Artisan-Token': token },
  })
  return res.json()
}

export async function updateMe(token, data) {
  const res = await fetch(`${API_BASE}/artisans/me`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
  })
  return res.json()
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

export async function getMyProspects(token) {
  const res = await fetch(`${API_BASE}/artisans/me/prospects`, {
    headers: { 'X-Artisan-Token': token },
  })
  return res.json()
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

export function getUserToken() {
  return localStorage.getItem(USER_TOKEN_KEY)
}

export function setUserToken(token) {
  localStorage.setItem(USER_TOKEN_KEY, token)
}

export function removeUserToken() {
  localStorage.removeItem(USER_TOKEN_KEY)
}

export async function requestUserMagicLink(email) {
  const res = await fetch(`${API_BASE}/users/magic-link`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email }),
  })
  return res.json()
}

export async function authUser(token) {
  const res = await fetch(`${API_BASE}/users/auth?token=${encodeURIComponent(token)}`, {
    method: 'POST',
  })
  return res.json()
}

export async function fetchUserMe(token) {
  const res = await fetch(`${API_BASE}/users/me`, {
    headers: { 'Authorization': `Bearer ${token}` },
  })
  return res.json()
}

// --- Spin wheel -------------------------------------------------

export async function getSpinOffers() {
  const res = await fetch(`${API_BASE}/spin/offers?city=${CITY_SLUG}`)
  if (!res.ok) throw new Error('Erreur chargement offres')
  return res.json()
}

export async function postSpin(token, payload = {}) {
  const res = await fetch(`${API_BASE}/spin`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify({ city_slug: CITY_SLUG, ...payload }),
  })
  return res.json()
}

export async function getSpinWins(token) {
  const res = await fetch(`${API_BASE}/spin/wins`, {
    headers: { 'Authorization': `Bearer ${token}` },
  })
  if (!res.ok) throw new Error('Erreur chargement gains')
  return res.json()
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
