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

      <div class="nav-links">
        <RouterLink to="/" class="nav-link">Annuaire</RouterLink>
        <RouterLink to="/carte" class="nav-link nav-link-featured">🗺️ Carte des artisans</RouterLink>
        <RouterLink to="/temoignages" class="nav-link">Avis</RouterLink>
        <RouterLink to="/prospection" class="nav-link">Prospection</RouterLink>
        <RouterLink to="/#services-locaux" class="nav-link" @click.prevent="scrollTo('services-locaux')">Services locaux</RouterLink>
        <RouterLink v-if="!user" to="/profil" class="nav-link">Se connecter / Mon compte</RouterLink>
        <RouterLink to="/espace" class="nav-link">Mon espace</RouterLink>
        <RouterLink v-if="isAdmin" to="/espace/admin" class="nav-admin-badge">
          🛡️ Admin
        </RouterLink>

        <RouterLink to="/inscrire" class="btn btn-primary btn-sm">
          <span>+ Inscrire mon entreprise</span>
        </RouterLink>

        <button v-if="user" type="button" class="nav-profile" :aria-label="`Mon profil, niveau ${user.level}`" @click="goToProfile">
          <img v-if="avatarUrl" :src="avatarUrl" class="nav-avatar" alt="" />
          <span v-else class="nav-avatar-placeholder">🙂</span>
          <span class="nav-level">Lv.{{ user.level }}</span>
        </button>
      </div>

      <button class="nav-burger" @click="menuOpen = !menuOpen" :aria-label="menuOpen ? 'Fermer' : 'Menu'">
        <span :class="{ open: menuOpen }"></span>
        <span :class="{ open: menuOpen }"></span>
        <span :class="{ open: menuOpen }"></span>
      </button>
    </div>

    <!-- Mobile menu -->
    <Transition name="slide-down">
      <div v-if="menuOpen" class="nav-mobile" @click="menuOpen = false">
        <RouterLink to="/" class="nav-mobile-link">🏠 Annuaire des artisans</RouterLink>
        <RouterLink to="/carte" class="nav-mobile-link nav-link-featured">🗺️ Carte des artisans</RouterLink>
        <RouterLink to="/temoignages" class="nav-mobile-link">💬 Avis locaux</RouterLink>
        <RouterLink to="/prospection" class="nav-mobile-link">🎯 Prospection</RouterLink>
        <a href="/#meteo" class="nav-mobile-link">🌤️ Météo locale</a>
        <a href="/#services-locaux" class="nav-mobile-link">🏙️ Services locaux</a>
        <RouterLink v-if="!user" to="/profil" class="nav-mobile-link">👤 Se connecter / Mon compte</RouterLink>
        <RouterLink to="/espace" class="nav-mobile-link">🔐 Mon espace</RouterLink>
        <RouterLink v-if="isAdmin" to="/espace/admin" class="nav-mobile-link nav-link-featured">🛡️ Administration</RouterLink>
        <RouterLink v-if="user" to="/profil" class="nav-mobile-link">👤 Mon profil (Lv.{{ user.level }})</RouterLink>
        <RouterLink to="/inscrire" class="btn btn-primary" style="margin: 12px 20px;">
          + Inscrire mon entreprise
        </RouterLink>
      </div>
    </Transition>
  </nav>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { CITY_NAME, getUserToken, getArtisanToken, fetchUserMe, fetchMe, removeUserToken, resolveAvatarUrl, authEvents } from '../api.js'

const router = useRouter()
const scrolled   = ref(false)
const menuOpen   = ref(false)
const user = ref(null)
const artisan = ref(null)

const avatarUrl = computed(() => resolveAvatarUrl(user.value?.avatar_url))
const isAdmin = computed(() => artisan.value?.is_admin === 1 || artisan.value?.is_admin === true)

function onScroll() { scrolled.value = window.scrollY > 20 }
function scrollTo(id) {
  menuOpen.value = false
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' })
}

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

  // Charger le profil artisan (pour badge admin et accès rapide)
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
})

onUnmounted(() => {
  window.removeEventListener('scroll', onScroll)
  authEvents.removeEventListener('change', loadUser)
  isMounted = false
  abortController?.abort()
})

function goToProfile() {
  router.push('/profil')
}
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

.nav-links {
  display: flex;
  align-items: center;
  gap: 24px;
}
.nav-link {
  font-weight: 500;
  font-size: 0.9rem;
  color: var(--c-text-2);
  transition: color 0.2s;
}
.nav-link:hover { color: var(--c-green); }

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

.nav-admin-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #B71C1C;
  color: #fff;
  font-size: 0.78rem;
  font-weight: 700;
  padding: 5px 10px;
  border-radius: 999px;
  text-decoration: none;
}
.nav-admin-badge:hover {
  background: #9b1515;
  color: #fff;
}

.nav-profile {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  background: none;
  border: none;
  padding: 0;
  margin: 0;
  font: inherit;
  color: inherit;
  border-radius: 999px;
}
.nav-profile:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px var(--c-green);
}
.nav-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--c-green);
}
.nav-avatar-placeholder {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  border: 2px solid var(--c-green);
  font-size: 1rem;
  background: var(--c-cream-2);
}
.nav-level {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--c-green);
}

/* Burger */
.nav-burger {
  display: none;
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

/* Mobile menu */
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

.slide-down-enter-active, .slide-down-leave-active { transition: all 0.3s; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-10px); }

@media (max-width: 768px) {
  .nav-links { display: none; }
  .nav-burger { display: flex; }
}
</style>
