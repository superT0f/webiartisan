<template>
  <div class="home-view">
    <!-- HERO -->
    <section class="hero">
    <div class="hero-bg">
      <div class="hero-blob blob-1"></div>
      <div class="hero-blob blob-2"></div>
    </div>
    <div class="container hero-content">
      <div class="hero-tag"><span>📍</span> {{ CITY_NAME }} · {{ CITY_CP }}</div>
      <h1>
        Les artisans de<br />
        <span class="text-green">votre ville</span>, enfin réunis
      </h1>
      <p class="hero-sub">
        Trouvez un plombier, électricien, peintre ou menuisier de confiance à {{ CITY_NAME }}.
        <strong>Gratuit · Local · Sans intermédiaire.</strong>
      </p>

      <!-- Barre de recherche -->
      <div class="search-bar">
        <span class="search-icon">🔍</span>
        <input
          v-model="searchQuery"
          type="text"
          class="search-input"
          placeholder="Plombier, électricien, peintre…"
          @input="onSearch"
        />
        <button v-if="searchQuery" class="search-clear" @click="searchQuery = ''; onSearch()">✕</button>
      </div>

      <!-- Stats rapides -->
      <div class="hero-stats">
        <div class="hero-stat">
          <strong>{{ stats.artisans }}</strong>
          <span>artisans locaux</span>
        </div>
        <div class="hero-divider"></div>
        <div class="hero-stat">
          <strong>{{ stats.categories }}</strong>
          <span>catégories de métiers</span>
        </div>
        <div class="hero-divider"></div>
        <div class="hero-stat">
          <strong>100%</strong>
          <span>gratuit & local</span>
        </div>
      </div>
    </div>

    <!-- Widget météo flottant -->
    <div class="weather-widget" v-if="weather">
      <div class="weather-emoji">{{ weatherInfo(weather.weathercode).emoji }}</div>
      <div class="weather-info">
        <span class="weather-temp">{{ Math.round(weather.temperature) }}°C</span>
        <span class="weather-label">{{ weatherInfo(weather.weathercode).label }}</span>
      </div>
      <div class="weather-extras">
        <span>🌬️ {{ Math.round(weather.windspeed) }} km/h</span>
      </div>
    </div>
  </section>

  <!-- CARTE OpenStreetMap -->
  <section class="section" id="carte">
    <div class="container">
      <div class="section-header text-center">
        <div class="section-eyebrow">🗺️ {{ CITY_NAME }}</div>
        <h2>Carte de la ville</h2>
        <p class="text-muted">Localisez-vous et explorez les alentours</p>
      </div>
      <div id="osm-map" style="height: 400px; border-radius: var(--r-lg); overflow: hidden; margin-top: 16px;"></div>
    </div>
  </section>

  <!-- CATÉGORIES -->
  <section class="section-sm" id="categories">
    <div class="container">
      <div class="section-header">
        <h2>Trouver par métier</h2>
        <p class="text-muted">{{ categories.length }} catégories disponibles à {{ CITY_NAME }}</p>
      </div>
      <div class="categories-scroll">
        <button
          class="tag"
          :class="{ active: selectedCategory === '' }"
          @click="filterByCategory('')"
        >Tous</button>
        <button
          v-for="cat in categories"
          :key="cat.slug"
          class="tag"
          :class="{ active: selectedCategory === cat.slug }"
          @click="filterByCategory(cat.slug)"
        >
          <span>{{ cat.icon }}</span>
          <span>{{ cat.name }}</span>
          <span class="tag-count" v-if="cat.count">{{ cat.count }}</span>
        </button>
      </div>
    </div>
  </section>

  <!-- LISTE DES ARTISANS -->
  <section class="section" id="artisans">
    <div class="container">
      <div class="section-header flex-between">
        <div>
          <h2>{{ selectedCategory ? categories.find(c=>c.slug===selectedCategory)?.name || 'Artisans' : 'Tous les artisans' }}</h2>
          <p class="text-muted" v-if="!loadingArtisans">{{ filteredArtisans.length }} résultat{{ filteredArtisans.length > 1 ? 's' : '' }}</p>
        </div>
        <div class="sort-select" v-if="!loadingArtisans && filteredArtisans.length">
          <select v-model="sortBy" class="form-select" style="width:auto;">
            <option value="featured">Mis en avant</option>
            <option value="rating">Mieux notés</option>
            <option value="name">A → Z</option>
          </select>
        </div>
      </div>

      <!-- Skeleton loaders -->
      <div v-if="loadingArtisans" class="grid-auto">
        <div v-for="n in 6" :key="n" class="card artisan-skeleton">
          <div class="skeleton" style="height:140px;"></div>
          <div class="card-body">
            <div class="skeleton" style="height:20px;width:60%;margin-bottom:8px;"></div>
            <div class="skeleton" style="height:14px;margin-bottom:6px;"></div>
            <div class="skeleton" style="height:14px;width:80%;"></div>
          </div>
        </div>
      </div>

      <!-- Résultats -->
      <Transition name="fade">
        <div v-if="!loadingArtisans" class="grid-auto">
          <RouterLink
            v-for="artisan in sortedArtisans"
            :key="artisan.id"
            :to="`/artisan/${artisan.id}`"
            class="card artisan-card"
          >
            <!-- Cover / Logo -->
            <div class="artisan-cover" :style="{ background: artisan.category_color || '#2D6A4F' }">
              <span class="artisan-cover-icon">{{ artisan.category_icon || '🔧' }}</span>
              <div class="artisan-badges-top">
                <span v-if="artisan.is_featured" class="badge badge-gold">⭐ En vedette</span>
                <span v-if="artisan.is_verified" class="badge badge-green">✓ Vérifié</span>
              </div>
            </div>

            <div class="card-body">
              <div class="artisan-category">{{ artisan.category_name || 'Artisan' }}</div>
              <h3 class="artisan-name">{{ artisan.company_name }}</h3>

              <p v-if="artisan.description" class="artisan-desc">{{ truncate(artisan.description, 80) }}</p>

              <!-- Note -->
              <div class="artisan-rating" v-if="artisan.rating_count > 0">
                <div class="stars">
                  <span v-for="i in 5" :key="i" class="star" :class="{ filled: i <= Math.round(artisan.rating_avg) }">★</span>
                </div>
                <span class="rating-text">{{ artisan.rating_avg }} ({{ artisan.rating_count }})</span>
              </div>

              <div class="artisan-footer">
                <span class="artisan-phone" v-if="artisan.phone">
                  📞 {{ artisan.phone }}
                </span>
                <span class="artisan-more">Voir →</span>
              </div>
            </div>
          </RouterLink>

          <!-- Vide -->
          <div v-if="!sortedArtisans.length" class="empty-state">
            <div class="empty-icon">🔍</div>
            <h3>Aucun artisan trouvé</h3>
            <p>Pas encore d'artisan dans cette catégorie.<br />Soyez le premier à vous inscrire !</p>
            <RouterLink to="/inscrire" class="btn btn-primary" style="margin-top:16px;">
              + Inscrire mon entreprise
            </RouterLink>
          </div>
        </div>
      </Transition>
    </div>
  </section>

  <!-- SERVICES LOCAUX -->
  <section class="section services-locaux-section" id="services-locaux">
    <div class="container">
      <div class="section-header text-center">
        <div class="section-eyebrow">📍 {{ CITY_NAME }}</div>
        <h2>Services & Infos de votre ville</h2>
        <p class="text-muted">Horaires en temps réel, météo, transports… tout en un lieu.</p>
      </div>

      <div class="services-grid">
        <!-- POI horaires -->
        <template v-if="pois.length">
          <div
            v-for="poi in pois"
            :key="poi.id"
            class="service-card"
          >
            <div class="service-header">
              <span class="service-icon">{{ poiIcon(poi.type) }}</span>
              <div>
                <h3>{{ poi.name }}</h3>
                <span class="badge" :class="poi.is_open_now ? 'badge-open' : 'badge-closed'">
                  {{ poi.is_open_now ? '● Ouvert' : '● Fermé' }}
                </span>
              </div>
            </div>
            <div v-if="poi.schedules?.length" class="poi-schedule">
              <div
                v-for="s in todaySchedule(poi.schedules)"
                :key="s.day"
                class="schedule-today"
              >
                <template v-if="s.is_closed">Fermé aujourd'hui</template>
                <template v-else-if="s.open_time">
                  {{ formatTime(s.open_time) }} – {{ formatTime(s.close_time) }}
                  <span v-if="s.break_start" class="break-info">
                    (pause {{ formatTime(s.break_start) }}–{{ formatTime(s.break_end) }})
                  </span>
                </template>
              </div>
            </div>
            <a v-if="poi.website" :href="poi.website" target="_blank" rel="noopener" class="poi-link">
              Voir le site ↗
            </a>
            <a v-if="poi.phone" :href="`tel:${poi.phone}`" class="poi-link">
              📞 {{ poi.phone }}
            </a>
          </div>
        </template>

        <!-- Skeletons POI -->
        <template v-else-if="loadingPois">
          <div v-for="n in 5" :key="n" class="service-card">
            <div class="skeleton" style="height:24px;width:60%;margin-bottom:10px;"></div>
            <div class="skeleton" style="height:16px;margin-bottom:6px;"></div>
            <div class="skeleton" style="height:16px;width:70%;"></div>
          </div>
        </template>

        <!-- Bus -->
        <div class="service-card bus-card">
          <div class="service-header">
            <span class="service-icon">🚌</span>
            <div>
              <h3>Transports en commun</h3>
              <p class="text-muted">Île-de-France Mobilités</p>
            </div>
          </div>
          <div class="bus-links">
            <a href="https://www.transdev-idf.com" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
              Horaires Transdev ↗
            </a>
            <a href="https://www.vianavigo.com" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
              Vianavigo ↗
            </a>
          </div>
          <p class="text-muted" style="font-size:0.8rem;margin-top:10px;">
            Lignes : 19, 218, 320, Brie Transports...
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA ARTISAN -->
  <section class="cta-section">
    <div class="container">
      <div class="cta-box">
        <div class="cta-content">
          <div class="cta-eyebrow">👷 Vous êtes artisan à {{ CITY_NAME }} ?</div>
          <h2>Inscrivez votre entreprise gratuitement</h2>
          <p>Rejoignez l'annuaire local, présentez vos services et touchez de nouveaux clients dans votre ville. <strong>100% gratuit, 100% local, toujours.</strong></p>
          <div class="cta-features">
            <span>✅ Inscription en 2 minutes</span>
            <span>✅ Profil personnalisable</span>
            <span>✅ Visible sur Google</span>
            <span>✅ Aucun abonnement</span>
          </div>
          <RouterLink to="/inscrire" class="btn btn-gold btn-lg">
            🚀 Créer mon profil gratuit
          </RouterLink>
        </div>
        <div class="cta-deco" aria-hidden="true">
          <div class="cta-emoji-cloud">
            <span>🔧</span><span>⚡</span><span>🎨</span><span>🪚</span>
            <span>🔥</span><span>🌿</span><span>🏠</span><span>🔑</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- PHILOSOPHIE -->
  <section class="section">
    <div class="container">
      <div class="section-header text-center">
        <h2>Notre engagement</h2>
        <p class="text-muted">Une plateforme au service du territoire, pas des actionnaires.</p>
      </div>
      <div class="grid-4 values-grid">
        <div class="value-card" v-for="v in values" :key="v.label">
          <div class="value-icon">{{ v.icon }}</div>
          <h3>{{ v.label }}</h3>
          <p>{{ v.text }}</p>
        </div>
      </div>
    </div>
  </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { fetchArtisans, fetchCategories, fetchCityPois, fetchWeather,
  weatherInfo, DAYS, formatTime, todayIndex,
  CITY_NAME, CITY_CP, CITY_SLUG, CITY_LAT, CITY_LNG
} from '../api.js'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

// --- State ---
const artisans         = ref([])
const categories       = ref([])
const pois             = ref([])
const weather          = ref(null)
const loadingArtisans  = ref(true)
const loadingPois      = ref(true)
const selectedCategory = ref('')
const searchQuery      = ref('')
const sortBy           = ref('featured')

const stats = computed(() => ({
  artisans:   artisans.value.length,
  categories: categories.value.length,
}))

// --- Filtres ---
const filteredArtisans = computed(() => {
  let list = artisans.value
  if (selectedCategory.value) {
    list = list.filter(a => a.category_slug === selectedCategory.value)
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(a =>
      a.company_name.toLowerCase().includes(q) ||
      (a.category_name || '').toLowerCase().includes(q) ||
      (a.description || '').toLowerCase().includes(q)
    )
  }
  return list
})

const sortedArtisans = computed(() => {
  const list = [...filteredArtisans.value]
  if (sortBy.value === 'rating') return list.sort((a, b) => b.rating_avg - a.rating_avg)
  if (sortBy.value === 'name')   return list.sort((a, b) => a.company_name.localeCompare(b.company_name, 'fr'))
  // featured: vedette d'abord, puis note
  return list.sort((a, b) => (b.is_featured - a.is_featured) || (b.rating_avg - a.rating_avg))
})

// --- Actions ---
function filterByCategory(slug) { selectedCategory.value = slug; searchQuery.value = '' }
function onSearch() { selectedCategory.value = '' }
function truncate(str, n) { return str?.length > n ? str.slice(0, n) + '…' : str }

// --- POI ---
function poiIcon(type) {
  const icons = {
    mairie: '🏛️', piscine: '🏊', bibliotheque: '📚', mediatheque: '📚',
    cinema: '🎬', dechetterie: '♻️', poste: '📬', supermarche: '🛒',
    transport: '🚌', ecole: '🏫', hopital: '🏥', pharmacie: '💊',
    parc: '🌳', eglise: '⛪', autre: '📍',
  }
  return icons[type] || '📍'
}

function todaySchedule(schedules) {
  const today = todayIndex()
  return schedules.filter(s => {
    const day = s.day_of_week !== undefined ? s.day_of_week : s.day
    return parseInt(day) === today
  })
}

// --- Valeurs ---
const values = [
  { icon: '📍', label: '100% Local',     text: 'Uniquement des artisans de ' + CITY_NAME + ' et ses alentours. Pas de résultats nationaux ou payants.' },
  { icon: '🇫🇷', label: '100% Français', text: 'Hébergé en France, données en France. Aucune dépendance aux GAFAM.' },
  { icon: '🆓', label: '100% Gratuit',   text: 'Gratuit pour toujours, pour les habitants comme pour les artisans. Zéro commission.' },
  { icon: '🔓', label: 'Open Source',    text: 'Le code est public. Vous pouvez le vérifier, l\'améliorer et le réutiliser.' },
]

// --- Init ---
onMounted(async () => {
  await Promise.allSettled([
    (async () => {
      try {
        const [artRes, catRes] = await Promise.all([
          fetchArtisans({ limit: 200 }),
          fetchCategories(),
        ])
        artisans.value  = artRes.data || []
        categories.value = catRes
      } catch (e) {
        console.warn('API artisans indisponible, mode démo activé')
        artisans.value  = demoArtisans
        categories.value = demoCategories
      } finally {
        loadingArtisans.value = false
      }
    })(),
    (async () => {
      try {
        const res = await fetchCityPois()
        pois.value = res.data || []
      } catch { pois.value = [] }
      finally { loadingPois.value = false }
    })(),
    (async () => {
      try {
        const res = await fetchWeather(CITY_LAT, CITY_LNG)
        weather.value = res.data || null
      } catch { weather.value = null }
    })(),
  ])

  // Init OpenStreetMap
  const map = L.map('osm-map').setView([CITY_LAT, CITY_LNG], 14)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map)
  L.marker([CITY_LAT, CITY_LNG]).addTo(map)
    .bindPopup(`<b>${CITY_NAME}</b><br/>Centre ville`)
    .openPopup()

  // Marqueurs artisans
  artisans.value.forEach(a => {
    if (a.latitude == null || a.longitude == null) return
    const color = a.category_color || '#2D6A4F'
    L.circleMarker([parseFloat(a.latitude), parseFloat(a.longitude)], {
      radius: 8,
      fillColor: color,
      color: '#fff',
      weight: 2,
      opacity: 1,
      fillOpacity: 0.9,
    })
      .addTo(map)
      .bindPopup(`<a href="#/artisan/${a.id}" style="color:${color};font-weight:600;">${a.company_name}</a>`)
  })
})

// --- Données démo (affichées si l'API n'est pas encore disponible) ---
const demoCategories = [
  { slug: 'plombier',    name: 'Plombier',      icon: '🔧', color: '#1565C0', count: 3 },
  { slug: 'electricien', name: 'Électricien',   icon: '⚡', color: '#F57F17', count: 2 },
  { slug: 'peintre',     name: 'Peintre',       icon: '🎨', color: '#6A1B9A', count: 2 },
  { slug: 'jardinage',   name: 'Jardinage',     icon: '🌿', color: '#2E7D32', count: 1 },
]
const demoArtisans = [
  {
    id: 1, company_name: 'Plomberie Dupont', category_slug: 'plombier',
    category_name: 'Plombier', category_icon: '🔧', category_color: '#1565C0',
    description: 'Dépannage plomberie, installation sanitaire, chauffe-eau à ' + CITY_NAME + ' et alentours.',
    phone: '06 12 34 56 78', rating_avg: 4.8, rating_count: 12,
    is_featured: true, is_verified: true,
  },
  {
    id: 2, company_name: 'Élec Pro 77', category_slug: 'electricien',
    category_name: 'Électricien', category_icon: '⚡', category_color: '#F57F17',
    description: 'Mise aux normes, installation tableau électrique, domotique.',
    phone: '06 23 45 67 89', rating_avg: 4.5, rating_count: 8,
    is_featured: false, is_verified: true,
  },
  {
    id: 3, company_name: 'Martin Peinture', category_slug: 'peintre',
    category_name: 'Peintre', category_icon: '🎨', category_color: '#6A1B9A',
    description: 'Peinture intérieure et extérieure, ravalement de façade.',
    phone: '06 34 56 78 90', rating_avg: 4.9, rating_count: 21,
    is_featured: true, is_verified: false,
  },
  {
    id: 4, company_name: 'Jardins de ' + CITY_NAME, category_slug: 'jardinage',
    category_name: 'Jardinage', category_icon: '🌿', category_color: '#2E7D32',
    description: 'Entretien jardins, tonte, taille haies, création espaces verts.',
    phone: '06 45 67 89 01', rating_avg: 0, rating_count: 0,
    is_featured: false, is_verified: false,
  },
]
</script>

<style scoped>
/* --- Hero --------------------------------------------------- */
.hero {
  position: relative;
  overflow: hidden;
  padding: 80px 0 60px;
  background: var(--c-cream);
}
.hero-bg { position: absolute; inset: 0; pointer-events: none; }
.hero-blob {
  position: absolute;
  border-radius: 50%;
  opacity: 0.12;
  animation: drift 12s ease-in-out infinite alternate;
}
.blob-1 {
  width: 500px; height: 500px;
  background: var(--c-green);
  top: -150px; right: -100px;
}
.blob-2 {
  width: 300px; height: 300px;
  background: var(--c-gold);
  bottom: -80px; left: -60px;
  animation-delay: -6s;
}
@keyframes drift {
  from { transform: scale(1) rotate(0deg); }
  to   { transform: scale(1.15) rotate(8deg); }
}
.hero-content { position: relative; z-index: 1; max-width: 700px; }
.hero-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(45,106,79,0.12);
  color: var(--c-green);
  border: 1px solid rgba(45,106,79,0.25);
  border-radius: var(--r-full);
  padding: 6px 16px;
  font-size: 0.85rem;
  font-weight: 600;
  margin-bottom: 20px;
}
.hero-content h1 { margin-bottom: 18px; }
.hero-sub {
  font-size: 1.1rem;
  color: var(--c-text-2);
  margin-bottom: 32px;
  max-width: 540px;
}

/* Search bar */
.search-bar {
  display: flex;
  align-items: center;
  background: white;
  border: 2px solid var(--c-border);
  border-radius: var(--r-full);
  padding: 8px 8px 8px 20px;
  max-width: 500px;
  gap: 8px;
  box-shadow: 0 4px 20px var(--c-shadow);
  transition: border-color 0.2s;
}
.search-bar:focus-within { border-color: var(--c-green); }
.search-icon { font-size: 1.1rem; }
.search-input {
  flex: 1;
  border: none;
  outline: none;
  font-size: 1rem;
  font-family: var(--font-body);
  background: transparent;
  color: var(--c-text);
}
.search-clear {
  padding: 6px 10px;
  border-radius: var(--r-full);
  background: var(--c-cream-2);
  color: var(--c-text-3);
  font-size: 0.8rem;
  transition: background 0.2s;
}
.search-clear:hover { background: var(--c-border); }

/* Hero stats */
.hero-stats {
  display: flex;
  align-items: center;
  gap: 24px;
  margin-top: 40px;
}
.hero-stat { display: flex; flex-direction: column; }
.hero-stat strong { font-family: var(--font-title); font-size: 1.6rem; font-weight: 800; color: var(--c-green); }
.hero-stat span   { font-size: 0.82rem; color: var(--c-text-3); }
.hero-divider { width: 1px; height: 36px; background: var(--c-border); }

/* Weather widget */
.weather-widget {
  position: absolute;
  top: 32px;
  right: 32px;
  background: white;
  border: 1px solid var(--c-border);
  border-radius: var(--r-lg);
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: 0 4px 20px var(--c-shadow);
  z-index: 2;
}
.weather-emoji { font-size: 2rem; }
.weather-info  { display: flex; flex-direction: column; }
.weather-temp  { font-family: var(--font-title); font-size: 1.4rem; font-weight: 700; }
.weather-label { font-size: 0.78rem; color: var(--c-text-3); }
.weather-extras {
  display: flex;
  flex-direction: column;
  font-size: 0.78rem;
  color: var(--c-text-3);
  gap: 2px;
}

/* --- Categories scroll --- */
.categories-scroll {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 20px;
}
.tag-count {
  background: rgba(255,255,255,0.3);
  border-radius: var(--r-full);
  padding: 1px 7px;
  font-size: 0.75rem;
}
.tag.active .tag-count { background: rgba(255,255,255,0.25); }

/* --- Artisan card --- */
.artisan-card { display: flex; flex-direction: column; }
.artisan-cover {
  height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  flex-shrink: 0;
}
.artisan-cover-icon { font-size: 2.8rem; filter: drop-shadow(0 2px 8px rgba(0,0,0,0.2)); }
.artisan-badges-top { position: absolute; top: 10px; left: 10px; display: flex; gap: 6px; }
.artisan-category { font-size: 0.78rem; color: var(--c-text-3); font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 4px; }
.artisan-name { font-size: 1.05rem; margin-bottom: 6px; }
.artisan-desc { font-size: 0.85rem; color: var(--c-text-2); margin-bottom: 10px; line-height: 1.5; flex: 1; }
.artisan-rating { display: flex; align-items: center; gap: 6px; margin-bottom: 12px; }
.rating-text { font-size: 0.82rem; color: var(--c-text-3); }
.artisan-footer { display: flex; align-items: center; justify-content: space-between; margin-top: auto; padding-top: 12px; border-top: 1px solid var(--c-border); }
.artisan-phone { font-size: 0.85rem; color: var(--c-green); font-weight: 500; }
.artisan-more  { font-size: 0.85rem; color: var(--c-green); font-weight: 600; }

/* Sort select */
.sort-select select { padding: 8px 12px; border-radius: var(--r-md); }

/* Empty state */
.empty-state {
  grid-column: 1 / -1;
  text-align: center;
  padding: 60px 20px;
}
.empty-icon { font-size: 3rem; margin-bottom: 16px; }
.empty-state h3 { margin-bottom: 8px; }
.empty-state p  { color: var(--c-text-2); }

/* --- Services locaux --- */
.services-locaux-section { background: var(--c-cream-2); }
.section-eyebrow {
  display: inline-block;
  font-family: var(--font-title);
  font-weight: 700;
  font-size: 0.85rem;
  color: var(--c-green);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 12px;
}
.services-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  margin-top: 36px;
}
.service-card {
  background: var(--c-white);
  border-radius: var(--r-lg);
  border: 1px solid var(--c-border);
  padding: 20px;
  transition: box-shadow 0.3s;
}
.service-card:hover { box-shadow: 0 8px 30px var(--c-shadow); }
.service-card.wide { grid-column: span 2; }
.service-header {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  margin-bottom: 16px;
}
.service-icon { font-size: 2rem; flex-shrink: 0; }
.service-header h3 { font-size: 1rem; margin-bottom: 4px; }

/* Météo */
.weather-days { display: flex; gap: 8px; }
.weather-day {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  background: var(--c-cream-2);
  border-radius: var(--r-md);
  padding: 12px 8px;
  font-size: 0.82rem;
}
.weather-day.today { background: var(--c-green); color: white; }
.wday-label { font-weight: 700; font-size: 0.78rem; text-transform: uppercase; }
.wday-emoji { font-size: 1.4rem; }
.wday-max   { font-weight: 700; }
.wday-min   { opacity: 0.6; }

/* POI */
.poi-schedule { font-size: 0.88rem; color: var(--c-text-2); margin: 8px 0; }
.break-info   { color: var(--c-text-3); font-size: 0.8rem; }
.poi-link {
  display: inline-block;
  font-size: 0.83rem;
  color: var(--c-green);
  font-weight: 500;
  margin-top: 8px;
  margin-right: 12px;
}
.poi-link:hover { text-decoration: underline; }

/* Bus */
.bus-card { display: flex; flex-direction: column; }
.bus-links { display: flex; flex-wrap: wrap; gap: 8px; }

/* --- CTA --- */
.cta-section { padding: 64px 0; }
.cta-box {
  background: linear-gradient(135deg, var(--c-green-dark) 0%, var(--c-green) 60%, var(--c-green-light) 100%);
  border-radius: var(--r-xl);
  padding: 56px 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 40px;
  overflow: hidden;
  position: relative;
}
.cta-content { color: white; max-width: 560px; }
.cta-eyebrow { font-size: 0.85rem; font-weight: 700; opacity: 0.8; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.06em; }
.cta-content h2 { font-size: 2rem; margin-bottom: 16px; }
.cta-content p  { opacity: 0.9; margin-bottom: 24px; }
.cta-features { display: flex; flex-wrap: wrap; gap: 10px 24px; margin-bottom: 32px; font-size: 0.88rem; }
.cta-deco { flex-shrink: 0; }
.cta-emoji-cloud {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  font-size: 2rem;
  opacity: 0.25;
  animation: pulse 4s ease-in-out infinite;
}
@keyframes pulse {
  0%, 100% { opacity: 0.2; }
  50%       { opacity: 0.35; }
}

/* --- Valeurs --- */
.values-grid { margin-top: 40px; }
.value-card {
  background: var(--c-white);
  border: 1px solid var(--c-border);
  border-radius: var(--r-lg);
  padding: 28px 24px;
  text-align: center;
  transition: all 0.3s var(--ease-spring);
}
.value-card:hover { transform: translateY(-4px); box-shadow: 0 12px 36px var(--c-shadow); border-color: var(--c-green-light); }
.value-icon { font-size: 2.2rem; margin-bottom: 12px; }
.value-card h3 { margin-bottom: 8px; font-size: 1rem; }
.value-card p   { font-size: 0.85rem; color: var(--c-text-2); line-height: 1.6; }

/* --- Section header --- */
.section-header { margin-bottom: 8px; }
.section-header h2 { margin-bottom: 6px; }

/* --- Carte OSM --- */
#osm-map {
  width: 100%;
  height: 400px;
  border-radius: var(--r-lg);
  margin-top: 16px;
}
.leaflet-container {
  background-color: var(--c-cream-2);
}
.leaflet-control-attribution {
  font-size: 0.75rem !important;
  background-color: rgba(255, 255, 255, 0.7) !important;
}

/* --- Responsive --- */
@media (max-width: 900px) {
  .services-grid { grid-template-columns: 1fr 1fr; }
  .service-card.wide { grid-column: span 2; }
  .weather-widget { display: none; }
  #osm-map { height: 300px; }
}
@media (max-width: 600px) {
  #osm-map { height: 250px; }
  .hero { padding: 48px 0 40px; }
  .hero-stats { flex-wrap: wrap; gap: 16px; }
  .services-grid { grid-template-columns: 1fr; }
  .service-card.wide { grid-column: span 1; }
  .weather-days { gap: 4px; }
  .values-grid { grid-template-columns: 1fr 1fr; }
  .cta-box { flex-direction: column; padding: 40px 28px; }
  .cta-deco { display: none; }
}
@media (max-width: 400px) {
  .values-grid { grid-template-columns: 1fr; }
}
</style>
