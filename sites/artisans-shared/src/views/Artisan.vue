<template>
  <div class="artisan-page">
    <!-- Loading -->
    <div v-if="loading" class="container" style="padding:80px 20px;text-align:center;">
      <div class="loading-spinner"></div>
      <p style="margin-top:16px;color:var(--c-text-3);">Chargement du profil…</p>
    </div>

    <!-- Erreur -->
    <div v-else-if="error" class="container" style="padding:80px 20px;text-align:center;">
      <div style="font-size:3rem;">😕</div>
      <h2>Artisan non trouvé</h2>
      <p class="text-muted">Ce profil n'existe pas ou n'est plus disponible.</p>
      <RouterLink to="/" class="btn btn-primary" style="margin-top:20px;">← Retour à l'annuaire</RouterLink>
    </div>

    <template v-else-if="artisan">
      <!-- HERO artisan -->
      <div class="artisan-hero" :style="{ background: artisan.category_color || '#C07A2E' }">
        <div class="container artisan-hero-content">
          <RouterLink to="/" class="back-link">← Retour à l'annuaire</RouterLink>
          <div class="artisan-hero-main">
            <div class="artisan-avatar">{{ artisan.category_icon || '🔧' }}</div>
            <div class="artisan-hero-info">
              <div class="artisan-category-badge">{{ artisan.category_name }}</div>
              <h1>{{ artisan.company_name }}</h1>
              <div class="hero-meta">
                <span v-if="artisan.is_verified" class="badge badge-green">✓ Vérifié</span>
                <span v-if="artisan.is_featured" class="badge badge-gold">⭐ En vedette</span>
                <span class="badge badge-grey">📍 {{ CITY_NAME }}</span>
              </div>
              <div class="hero-rating" v-if="artisan.rating_count > 0">
                <div class="stars">
                  <span v-for="i in 5" :key="i" class="star" :class="{ filled: i <= Math.round(artisan.rating_avg) }">★</span>
                </div>
                <span>{{ artisan.rating_avg }}/5 ({{ artisan.rating_count }} avis)</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- CONTENU -->
      <div class="container artisan-body">
        <div class="artisan-layout">

          <!-- Colonne principale -->
          <div class="artisan-main">

            <nav class="artisan-tabs" aria-label="Sections artisan">
              <button type="button" :class="{ active: activeTab === 'services' }" @click="activeTab = 'services'">Services</button>
              <button type="button" :class="{ active: activeTab === 'testimonials' }" @click="activeTab = 'testimonials'">Avis</button>
              <button type="button" :class="{ active: activeTab === 'games' }" @click="activeTab = 'games'">Jeux actifs</button>
              <button type="button" :class="{ active: activeTab === 'about' }" @click="activeTab = 'about'">À propos</button>
            </nav>

            <!-- Services -->
            <section v-if="activeTab === 'services'" class="info-card artisan-section">
              <h2>Services proposés</h2>
              <div v-if="services.length" class="services-list">
                <div v-for="s in services" :key="s.id" class="service-item">
                  <div class="service-item-main">
                    <h3>{{ s.catalog_icon || s.icon }} {{ s.name }}</h3>
                    <p v-if="s.description">{{ s.description }}</p>
                  </div>
                  <div class="service-item-meta">
                    <span v-if="s.price_range" class="price-tag">{{ s.price_range }}</span>
                    <span v-if="s.duration" class="duration-tag">⏱ {{ s.duration }}</span>
                  </div>
                </div>
              </div>
              <p v-else class="text-muted">Aucun service renseigné.</p>
            </section>

            <!-- Avis -->
            <section v-if="activeTab === 'testimonials'" class="info-card artisan-section">
              <h2>Avis et témoignages</h2>
              <TestimonialComposer :artisan-id="artisanId" :services="services" @posted="loadTestimonials" />
              <div v-if="testimonials.length" class="testimonials-list">
                <TestimonialCard
                  v-for="t in testimonials"
                  :key="t.id"
                  :testimonial="t"
                  :catalog-map="catalogMap"
                  @helpful="markHelpful"
                  @report="openReport"
                />
              </div>
              <p v-else class="text-muted">Pas encore d'avis. Soyez le premier !</p>
            </section>

            <!-- Jeux actifs -->
            <section v-if="activeTab === 'games'" class="info-card artisan-section">
              <h2>Jeux actifs</h2>
              <div v-if="artisanGames.length" class="artisan-games">
                <GameCard v-for="g in artisanGames" :key="g.id" :game="g" />
              </div>
              <p v-else class="text-muted">Aucun jeu actif pour le moment.</p>
            </section>

            <!-- À propos -->
            <section v-if="activeTab === 'about'" class="info-card artisan-section">
              <h2>À propos</h2>
              <p v-if="artisan.description">{{ artisan.description }}</p>
              <p v-else class="text-muted">Aucune description renseignée.</p>
            </section>
          </div>

          <!-- Sidebar contact -->
          <aside class="artisan-sidebar">
            <!-- Contacter -->
            <div class="contact-card">
              <h3>Contacter</h3>
              <a v-if="artisan.phone" :href="`tel:${artisan.phone}`" class="contact-btn contact-phone">
                📞 {{ artisan.phone }}
              </a>
              <a v-if="artisan.email" :href="`mailto:${artisan.email}`" class="contact-btn contact-email">
                ✉️ Envoyer un email
              </a>
              <a v-if="artisan.website" :href="artisan.website" target="_blank" rel="noopener" class="contact-btn contact-web">
                🌐 Voir le site web
              </a>

              <!-- Formulaire contact -->
              <button class="btn btn-primary" style="width:100%;margin-top:12px;" @click="showContactForm = !showContactForm">
                {{ showContactForm ? 'Annuler' : '💬 Envoyer un message' }}
              </button>
              <Transition name="slide-up">
                <form v-if="showContactForm" class="contact-form" @submit.prevent="submitContact">
                  <div class="form-group" style="margin-top:16px;">
                    <input v-model="contactForm.name" class="form-input" required placeholder="Votre nom *" />
                  </div>
                  <div class="form-group">
                    <input v-model="contactForm.email" class="form-input" type="email" required placeholder="Votre email *" />
                  </div>
                  <div class="form-group">
                    <textarea v-model="contactForm.message" class="form-textarea" required placeholder="Votre message *" style="min-height:80px;"></textarea>
                  </div>
                  <div v-if="contactMsg" class="alert" :class="contactSuccess ? 'alert-success' : 'alert-error'">{{ contactMsg }}</div>
                  <button type="submit" class="btn btn-primary btn-sm" style="width:100%;" :disabled="contactSending">
                    {{ contactSending ? 'Envoi…' : 'Envoyer' }}
                  </button>
                </form>
              </Transition>
            </div>

            <!-- Adresse -->
            <div class="info-card" v-if="artisan.address">
              <h3>📍 Adresse</h3>
              <p style="color:var(--c-text-2);font-size:0.9rem;">{{ artisan.address }}</p>
            </div>

            <!-- Stats -->
            <div class="info-card stats-card">
              <div class="stat-row">
                <span class="stat-label">Vues du profil</span>
                <span class="stat-val">{{ artisan.view_count }}</span>
              </div>
              <div class="stat-row" v-if="artisan.siret">
                <span class="stat-label">SIRET</span>
                <span class="stat-val">{{ artisan.siret }}</span>
              </div>
              <div class="stat-row">
                <span class="stat-label">Inscrit le</span>
                <span class="stat-val">{{ formatDate(artisan.created_at) }}</span>
              </div>
            </div>
          </aside>
        </div>
      </div>

      <!-- Recettes -->
      <section v-if="artisan.recipes?.length" class="section">
        <div class="container">
          <h2 class="section-title">Recettes avec ses produits</h2>
          <div class="recipe-grid">
            <RecipeMiniCard
              v-for="recipe in artisan.recipes"
              :key="recipe.id"
              :recipe="recipe"
              :artisan-email="artisan.email"
            />
          </div>
        </div>
      </section>

      <!-- À proximité -->
      <section v-if="artisan.nearby?.length" class="section">
        <div class="container">
          <h2 class="section-title">Autour de {{ artisan.company_name }}</h2>
          <ArtisanNearbyMap :artisan="artisan" :nearby="artisan.nearby" />
          <div class="nearby-list">
            <div v-for="place in artisan.nearby" :key="`${place.kind}-${place.id}`" class="nearby-item">
              <span class="kind" :class="place.kind">{{ place.kind === 'prospect' ? 'Commerce' : 'Service' }}</span>
              <strong>{{ place.name }}</strong>
              <span class="type">{{ place.type }}</span>
              <span class="distance">{{ Math.round(place.distance_meters) }} m</span>
            </div>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import {
  fetchArtisan,
  contactArtisan,
  fetchArtisanServices,
  fetchTestimonials,
  markTestimonialHelpful,
  reportTestimonial,
  fetchGames,
  CITY_NAME,
} from '../api.js'
import { useGamification } from '../composables/useGamification.js'
import RecipeMiniCard from '../components/RecipeMiniCard.vue'
import ArtisanNearbyMap from '../components/ArtisanNearbyMap.vue'
import TestimonialCard from '../components/TestimonialCard.vue'
import TestimonialComposer from '../components/TestimonialComposer.vue'
import GameCard from '../components/GameCard.vue'

const route = useRoute()
let artisanId = parseInt(route.params.id)

const { recordAction } = useGamification()

const artisan = ref(null)
const loading = ref(true)
const error   = ref(false)

const services = ref([])
const testimonials = ref([])
const artisanGames = ref([])
const activeTab = ref('services')

const catalogMap = computed(() => {
  const map = {}
  for (const s of services.value) {
    if (s.catalog_key) {
      map[s.catalog_key] = {
        label: s.catalog_label || s.name,
        icon: s.catalog_icon || s.icon,
      }
    }
  }
  return map
})

// Contact
const showContactForm = ref(false)
const contactForm  = ref({ name: '', email: '', message: '' })
const contactSending = ref(false)
const contactMsg   = ref('')
const contactSuccess = ref(false)

async function loadServices() {
  if (!artisanId) return
  try {
    const res = await fetchArtisanServices(artisanId)
    services.value = res.data || []
  } catch (e) {
    console.error('Erreur chargement services', e)
    services.value = []
  }
}

async function loadTestimonials() {
  if (!artisanId) return
  try {
    const res = await fetchTestimonials({ artisan_id: artisanId, limit: 50 })
    testimonials.value = res.data || []
  } catch (e) {
    console.error('Erreur chargement témoignages', e)
    testimonials.value = []
  }
}

async function loadGames() {
  if (!artisanId) return
  try {
    const res = await fetchGames({ artisan_id: artisanId })
    artisanGames.value = (res.data || []).filter(g => g.is_active)
  } catch (e) {
    console.error('Erreur chargement jeux', e)
    artisanGames.value = []
  }
}

async function loadArtisan() {
  loading.value = true
  error.value = false
  try {
    const res = await fetchArtisan(artisanId)
    artisan.value = res.data
    await loadServices()
    await loadTestimonials()
    await loadGames()
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }

  if (!error.value && artisan.value) {
    try {
      await recordAction('artisan_view', `artisan:${artisanId}`)
    } catch (e) {
      // Gamification failure should not break the artisan page
    }
  }
}

watch(() => route.params.id, async (newId) => {
  const newIdNum = parseInt(newId)
  if (!newIdNum || newIdNum === artisanId) return
  artisanId = newIdNum
  await loadArtisan()
})

async function submitContact() {
  contactSending.value = true; contactMsg.value = ''
  try {
    const res = await contactArtisan(artisanId, contactForm.value)
    contactSuccess.value = res.success
    contactMsg.value = res.message || (res.success ? 'Message envoyé !' : 'Erreur')
    if (res.success) { showContactForm.value = false; contactForm.value = { name: '', email: '', message: '' } }
  } catch { contactMsg.value = 'Erreur réseau' }
  finally { contactSending.value = false }
}

async function markHelpful(id) {
  try {
    await markTestimonialHelpful(id)
    await loadTestimonials()
  } catch (e) {
    console.error('Erreur mark helpful', e)
  }
}

async function openReport(id) {
  const reason = prompt('Pourquoi signalez-vous ce témoignage ?')
  if (!reason) return
  try {
    await reportTestimonial(id, reason)
    await loadTestimonials()
  } catch (e) {
    console.error('Erreur signalement', e)
  }
}

function formatDate(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })
}

onMounted(loadArtisan)
</script>

<style scoped>
.artisan-hero {
  padding: 60px 0 40px;
  color: white;
}
.artisan-hero-content { position: relative; }
.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: rgba(255,255,255,0.8);
  font-size: 0.88rem;
  margin-bottom: 28px;
  transition: color 0.2s;
}
.back-link:hover { color: white; }
.artisan-hero-main { display: flex; align-items: flex-start; gap: 24px; }
.artisan-avatar {
  width: 90px; height: 90px;
  background: rgba(255,255,255,0.2);
  border-radius: var(--r-lg);
  display: flex; align-items: center; justify-content: center;
  font-size: 2.8rem;
  flex-shrink: 0;
  backdrop-filter: blur(8px);
}
.artisan-category-badge {
  font-size: 0.78rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.08em;
  opacity: 0.75; margin-bottom: 6px;
}
.artisan-hero-info h1 { color: white; font-size: 2rem; margin-bottom: 10px; }
.hero-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
.hero-rating { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; opacity: 0.9; }

.artisan-body { padding: 40px 0 60px; }
.artisan-layout { display: grid; grid-template-columns: 1fr 320px; gap: 32px; align-items: start; }
.artisan-main  { display: flex; flex-direction: column; gap: 24px; }
.artisan-sidebar { display: flex; flex-direction: column; gap: 20px; }

.artisan-tabs {
  display: flex;
  gap: 8px;
  border-bottom: 2px solid var(--c-border);
  padding-bottom: 8px;
}
.artisan-tabs button {
  padding: 10px 18px;
  border-radius: var(--r-md);
  border: 1px solid transparent;
  background: transparent;
  color: var(--c-text-2);
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.artisan-tabs button:hover { color: var(--c-green); }
.artisan-tabs button.active {
  background: var(--c-white);
  border-color: var(--c-border);
  color: var(--c-green);
}

.artisan-section h2 { margin-bottom: 16px; }
.testimonials-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-top: 16px;
}

.info-card {
  background: var(--c-white);
  border: 1px solid var(--c-border);
  border-radius: var(--r-lg);
  padding: 28px;
}
.info-card h2 { font-size: 1.2rem; margin-bottom: 16px; }
.info-card h3 { font-size: 1rem; margin-bottom: 12px; }
.info-card p  { color: var(--c-text-2); line-height: 1.7; }

/* Services */
.services-list { display: flex; flex-direction: column; gap: 16px; }
.service-item {
  display: flex; justify-content: space-between; align-items: flex-start;
  gap: 16px; padding: 16px; background: var(--c-cream-2); border-radius: var(--r-md);
}
.service-item h3 { font-size: 0.95rem; margin-bottom: 4px; }
.service-item p  { font-size: 0.83rem; color: var(--c-text-2); }
.service-item-meta { display: flex; flex-direction: column; gap: 4px; align-items: flex-end; flex-shrink: 0; }
.price-tag    { background: var(--c-green-tint); color: var(--c-green-dark); padding: 3px 10px; border-radius: var(--r-full); font-size: 0.8rem; font-weight: 600; }
.duration-tag { font-size: 0.78rem; color: var(--c-text-3); }

/* Reviews */
.reviews-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.reviews-header h2 { margin-bottom: 0; }
.review-form { background: var(--c-cream-2); border-radius: var(--r-md); padding: 20px; margin-bottom: 16px; }
.star-picker { display: flex; gap: 4px; }
.star-btn { font-size: 1.6rem; color: #E0E0E0; transition: color 0.15s; }
.star-btn.active { color: #FFB300; }
.no-reviews { text-align: center; padding: 32px; color: var(--c-text-3); }

/* Contact card */
.contact-card {
  background: var(--c-white);
  border: 2px solid var(--c-green);
  border-radius: var(--r-lg);
  padding: 24px;
}
.contact-card h3 { margin-bottom: 16px; }
.contact-btn {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 16px; border-radius: var(--r-md);
  margin-bottom: 10px; font-weight: 500; font-size: 0.9rem;
  transition: background 0.2s;
}
.contact-phone { background: var(--c-green-tint); color: var(--c-green-dark); }
.contact-email { background: #E3F2FD; color: #0D47A1; }
.contact-web   { background: var(--c-cream-2); color: var(--c-text-2); }
.contact-btn:hover { filter: brightness(0.95); }
.contact-form { animation: fadeIn 0.3s; }

/* Stats card */
.stats-card { font-size: 0.88rem; }
.stat-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--c-border); }
.stat-row:last-child { border-bottom: none; }
.stat-label { color: var(--c-text-3); }
.stat-val   { font-weight: 600; }

/* Loading */
.loading-spinner {
  width: 40px; height: 40px;
  border: 3px solid var(--c-border);
  border-top-color: var(--c-green);
  border-radius: 50%;
  margin: 0 auto;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 900px) {
  .artisan-layout { grid-template-columns: 1fr; }
  .artisan-sidebar { order: -1; }
}
@media (max-width: 600px) {
  .artisan-hero { padding: 36px 0 24px; }
  .artisan-hero-main { flex-direction: column; }
  .artisan-hero-info h1 { font-size: 1.5rem; }
  .info-card { padding: 20px; }
}

/* Recipes & nearby */
.section { padding: 40px 0; }
.section-title { font-size: 1.25rem; margin-bottom: 20px; }
.recipe-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 1rem;
}
.nearby-list {
  display: grid;
  gap: 0.5rem;
}
.nearby-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem;
  background: #f8f8f8;
  border-radius: 6px;
}
.nearby-item .kind {
  font-size: 0.7rem;
  text-transform: uppercase;
  padding: 2px 6px;
  border-radius: 4px;
  color: #fff;
}
.nearby-item .kind.prospect { background: #f97316; }
.nearby-item .kind.poi { background: #3b82f6; }
.nearby-item .type { color: #666; font-size: 0.85rem; }
.nearby-item .distance { margin-left: auto; font-size: 0.85rem; color: #888; }
</style>
