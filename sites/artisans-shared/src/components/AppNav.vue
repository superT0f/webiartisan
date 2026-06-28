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
        <RouterLink to="/#services-locaux" class="nav-link" @click.prevent="scrollTo('services-locaux')">Services locaux</RouterLink>
        <RouterLink to="/espace" class="nav-link">Mon espace</RouterLink>
        <RouterLink to="/inscrire" class="btn btn-primary btn-sm">
          <span>+ Inscrire mon entreprise</span>
        </RouterLink>
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
        <a href="/#meteo" class="nav-mobile-link">🌤️ Météo locale</a>
        <a href="/#services-locaux" class="nav-mobile-link">🏙️ Services locaux</a>
        <RouterLink to="/espace" class="nav-mobile-link">🔐 Mon espace</RouterLink>
        <RouterLink to="/inscrire" class="btn btn-primary" style="margin: 12px 20px;">
          + Inscrire mon entreprise
        </RouterLink>
      </div>
    </Transition>
  </nav>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { CITY_NAME } from '../api.js'

const scrolled   = ref(false)
const menuOpen   = ref(false)

function onScroll() { scrolled.value = window.scrollY > 20 }
function scrollTo(id) {
  menuOpen.value = false
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' })
}

onMounted(() => window.addEventListener('scroll', onScroll))
onUnmounted(() => window.removeEventListener('scroll', onScroll))
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
