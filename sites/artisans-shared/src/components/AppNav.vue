<template>
  <nav class="nav" :class="{ scrolled }">
    <div class="container nav-inner">
      <RouterLink to="/" class="nav-logo">
        <span class="logo-icon">🏠</span>
        <span class="logo-text">
          <span class="logo-title">Artisans</span>
          <span class="logo-city">{{ CITY_NAME }}</span>
        </span>
      </RouterLink>

      <button class="nav-burger" @click="menuOpen = !menuOpen" :aria-label="menuOpen ? 'Fermer' : 'Menu'" :aria-expanded="menuOpen">
        <span :class="{ open: menuOpen }"></span>
        <span :class="{ open: menuOpen }"></span>
        <span :class="{ open: menuOpen }"></span>
      </button>
    </div>

    <!-- Menu déroulant — toutes tailles d'écran -->
    <Transition name="slide-down">
      <div v-if="menuOpen" class="nav-mobile" @click="menuOpen = false">
        <RouterLink v-if="user" to="/profil" class="nav-profile-hero">
          <span class="hero-ring">
            <svg class="hero-ring-svg" viewBox="0 0 76 76" aria-hidden="true">
              <circle class="ring-bg" cx="38" cy="38" r="35" />
              <circle class="ring-fill" cx="38" cy="38" r="35"
                :stroke-dasharray="heroCircumference" :stroke-dashoffset="heroOffset"
                transform="rotate(-90 38 38)" />
            </svg>
            <img v-if="avatarUrl" :src="avatarUrl" class="hero-avatar" alt="" />
            <span v-else class="hero-avatar hero-avatar-ph">🙂</span>
          </span>
          <span class="hero-info">
            <strong>{{ user.display_name || 'Mon profil' }}</strong>
            <small>Nv.{{ user.level }} · {{ user.xp }}/{{ user.xp_needed }} XP</small>
          </span>
        </RouterLink>
        <RouterLink v-if="weather" to="/annuaire#meteo" class="nav-weather" :title="`Météo à ${CITY_NAME}`">
          <span class="nav-weather-icon">{{ weatherIcon(weather.weathercode) }}</span>
          <span class="nav-weather-temp">{{ Math.round(weather.temperature) }}°</span>
          <span class="nav-weather-city">à {{ CITY_NAME }}</span>
        </RouterLink>
        <RouterLink to="/carte" class="nav-mobile-link nav-link-featured">🗺️ Carte</RouterLink>
        <RouterLink to="/annuaire" class="nav-mobile-link">🏠 Annuaire des artisans</RouterLink>
        <RouterLink to="/temoignages" class="nav-mobile-link">💬 Avis locaux</RouterLink>
        <RouterLink to="/recettes" class="nav-mobile-link">🍳 Recettes locales</RouterLink>
        <RouterLink to="/annuaire#services-locaux" class="nav-mobile-link">🏙️ Services locaux</RouterLink>
        <div class="nav-separator" role="separator"></div>
        <RouterLink v-if="!user" to="/profil" class="nav-mobile-link">👤 Se connecter / Mon compte</RouterLink>
        <RouterLink v-if="user" to="/profil" class="nav-mobile-link">👤 Mon profil (Lv.{{ user.level }})</RouterLink>
        <RouterLink to="/espace" class="nav-mobile-link">🔐 Mon espace</RouterLink>
        <RouterLink v-if="isAdmin" to="/espace/admin" class="nav-mobile-link nav-link-featured">🛡️ Administration</RouterLink>
        <RouterLink to="/inscrire" class="btn btn-primary" style="margin: 12px 20px;">
          + Inscrire mon entreprise
        </RouterLink>
      </div>
    </Transition>
  </nav>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { CITY_NAME, CITY_LAT, CITY_LNG, getUserToken, getArtisanToken, fetchUserMe, fetchMe, removeUserToken, resolveAvatarUrl, authEvents } from '../api.js'
import { useWeather } from '../composables/useWeather.js'

const scrolled   = ref(false)
const menuOpen   = ref(false)
const user = ref(null)
const artisan = ref(null)

const isAdmin = computed(() => artisan.value?.is_admin === 1 || artisan.value?.is_admin === true)
const avatarUrl = computed(() => resolveAvatarUrl(user.value?.avatar_url))
const heroXpPercent = computed(() => {
  if (!user.value?.xp_needed) return 0
  return Math.min(100, (user.value.xp / user.value.xp_needed) * 100)
})
const heroCircumference = 2 * Math.PI * 35
const heroOffset = computed(() => heroCircumference * (1 - heroXpPercent.value / 100))

// Météo affichée en petit en tête du menu (cache partagé 15 min)
const { weather, load: loadWeather } = useWeather(CITY_LAT, CITY_LNG)
function weatherIcon(code) {
  if (code == null) return '🌤️'
  if (code <= 3) return '☀️'
  if (code <= 48) return '☁️'
  if (code <= 67) return '🌧️'
  if (code <= 77) return '🌨️'
  if (code <= 82) return '🌦️'
  if (code <= 86) return '🌨️'
  return '⛈️'
}

function onScroll() { scrolled.value = window.scrollY > 20 }

let isMounted = true
let abortController = null

async function loadUser() {
  const token = getUserToken()
  if (!token) {
    user.value = null
  } else {
    abortController?.abort()
    abortController = new AbortController()

    try {
      const res = await fetchUserMe(token, { signal: abortController.signal })
      if (!isMounted) return
      if (res.success) {
        user.value = res.data
      } else if (res.error === 'AbortError') {
        return
      } else if (res.status === 401) {
        console.warn('User token invalid or expired')
        removeUserToken()
        user.value = null
      } else {
        console.warn('Failed to load user profile', res.error || res)
        user.value = null
      }
    } catch (e) {
      if (!isMounted || e.name === 'AbortError') return
      console.warn('Failed to load user profile', e)
      user.value = null
    }
  }

  // Charger le profil artisan (pour badge admin)
  const artisanToken = getArtisanToken()
  if (artisanToken) {
    try {
      const res = await fetchMe(artisanToken)
      if (isMounted && res.success) {
        artisan.value = res.data
      }
    } catch (e) {
      console.warn('Failed to load artisan profile for nav', e)
    }
  } else {
    artisan.value = null
  }
}

onMounted(() => {
  window.addEventListener('scroll', onScroll)
  authEvents.addEventListener('change', loadUser)
  loadUser()
  loadWeather()
})

onUnmounted(() => {
  window.removeEventListener('scroll', onScroll)
  authEvents.removeEventListener('change', loadUser)
  isMounted = false
  abortController?.abort()
})
</script>

<style scoped>
.nav {
  position: sticky;
  top: 0;
  z-index: 100;
  background: rgba(254, 250, 224, 0.88);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-bottom: 1px solid transparent;
  transition: all 0.3s;
}
.nav.scrolled {
  background: rgba(254, 250, 224, 0.97);
  border-bottom-color: var(--c-border);
  box-shadow: 0 2px 16px rgba(0,0,0,0.06);
}
.nav-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: var(--nav-h);
}
.nav-logo {
  display: flex;
  align-items: center;
  gap: 10px;
}
.logo-icon { font-size: 1.6rem; }
.logo-text { display: flex; flex-direction: column; line-height: 1.1; }
.logo-title { font-family: var(--font-title); font-weight: 800; font-size: 1.05rem; color: var(--c-green); }
.logo-city  { font-size: 0.72rem; color: var(--c-text-3); font-weight: 500; }

.nav-link-featured {
  background: rgba(45, 106, 79, 0.1);
  color: var(--c-green) !important;
  padding: 6px 12px;
  border-radius: 999px;
  border: 1px solid rgba(45, 106, 79, 0.2);
}
.nav-link-featured:hover {
  background: rgba(45, 106, 79, 0.18);
}

/* Burger — toujours visible, à droite */
.nav-burger {
  display: flex;
  flex-direction: column;
  gap: 5px;
  padding: 8px;
}
.nav-burger span {
  display: block;
  width: 22px;
  height: 2px;
  background: var(--c-green);
  border-radius: 2px;
  transition: all 0.3s;
}
.nav-burger span.open:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.nav-burger span.open:nth-child(2) { opacity: 0; }
.nav-burger span.open:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* Panneau déroulant */
.nav-mobile {
  background: var(--c-cream);
  border-top: 1px solid var(--c-border);
  padding: 12px 0;
  display: flex;
  flex-direction: column;
}
.nav-mobile-link {
  padding: 14px 24px;
  font-weight: 500;
  color: var(--c-text);
  border-bottom: 1px solid var(--c-border);
  transition: background 0.2s;
}
.nav-mobile-link:last-of-type { border-bottom: none; }
.nav-mobile-link:hover { background: var(--c-cream-2); }

/* Météo inline en tête de menu */
.nav-weather {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 24px;
  font-size: 0.9rem;
  color: var(--c-text-2);
  border-bottom: 1px solid var(--c-border);
  transition: background 0.2s;
}
.nav-weather:hover { background: var(--c-cream-2); }
.nav-weather-icon { font-size: 1.1rem; }
.nav-weather-temp { font-weight: 700; color: var(--c-text); }
.nav-weather-city { color: var(--c-text-3); }

/* Séparateur avant la section compte */
.nav-separator {
  height: 8px;
  background: var(--c-cream-2);
  border-bottom: 1px solid var(--c-border);
}

/* Bloc profil en tête de menu (avatar + anneau XP) */
.nav-profile-hero {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 24px;
  border-bottom: 1px solid var(--c-border);
  transition: background 0.2s;
}
.nav-profile-hero:hover { background: var(--c-cream-2); }
.hero-ring { position: relative; width: 76px; height: 76px; flex-shrink: 0; }
.hero-ring-svg { position: absolute; inset: 0; width: 76px; height: 76px; }
.hero-avatar {
  position: absolute; top: 6px; left: 6px;
  width: 64px; height: 64px; border-radius: 50%; object-fit: cover;
}
.hero-avatar-ph {
  display: flex; align-items: center; justify-content: center;
  font-size: 1.8rem; background: var(--c-cream-2);
}
.hero-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.hero-info strong { font-size: 0.95rem; color: var(--c-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hero-info small { font-size: 0.78rem; color: var(--c-text-3); font-weight: 600; }
.hero-ring .ring-bg { fill: none; stroke: var(--c-border, #e5e2d8); stroke-width: 4; }
.hero-ring .ring-fill {
  fill: none; stroke: var(--c-gold, #C07A2E); stroke-width: 4;
  stroke-linecap: round; transition: stroke-dashoffset 0.6s ease;
}

.slide-down-enter-active, .slide-down-leave-active { transition: all 0.3s; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-10px); }
</style>
