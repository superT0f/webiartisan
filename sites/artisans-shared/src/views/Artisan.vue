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
      <div class="artisan-hero" :style="{ background: artisan.category_color || '#2D6A4F' }">
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

            <!-- À propos -->
            <div class="info-card" v-if="artisan.description">
              <h2>À propos</h2>
              <p>{{ artisan.description }}</p>
            </div>

            <!-- Services -->
            <div class="info-card" v-if="artisan.services?.length">
              <h2>Services proposés</h2>
              <div class="services-list">
                <div v-for="svc in artisan.services" :key="svc.id" class="service-item">
                  <div class="service-item-main">
                    <h3>{{ svc.name }}</h3>
                    <p v-if="svc.description">{{ svc.description }}</p>
                  </div>
                  <div class="service-item-meta">
                    <span v-if="svc.price_range" class="price-tag">{{ svc.price_range }}</span>
                    <span v-if="svc.duration"    class="duration-tag">⏱ {{ svc.duration }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Avis -->
            <div class="info-card">
              <div class="reviews-header">
                <h2>Avis clients</h2>
                <button class="btn btn-outline btn-sm" @click="showReviewForm = !showReviewForm">
                  {{ showReviewForm ? 'Annuler' : '+ Laisser un avis' }}
                </button>
              </div>

              <!-- Formulaire avis -->
              <Transition name="slide-up">
                <form v-if="showReviewForm" class="review-form" @submit.prevent="submitReview">
                  <div class="grid-2">
                    <div class="form-group">
                      <label class="form-label">Votre nom *</label>
                      <input v-model="reviewForm.reviewer_name" class="form-input" required placeholder="Jean Dupont" />
                    </div>
                    <div class="form-group">
                      <label class="form-label">Email (non publié)</label>
                      <input v-model="reviewForm.reviewer_email" class="form-input" type="email" placeholder="vous@email.fr" />
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Note *</label>
                    <div class="star-picker">
                      <button type="button" v-for="n in 5" :key="n" class="star-btn" :class="{ active: reviewForm.rating >= n }" @click="reviewForm.rating = n">★</button>
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Commentaire</label>
                    <textarea v-model="reviewForm.comment" class="form-textarea" placeholder="Décrivez votre expérience…"></textarea>
                  </div>
                  <div v-if="reviewMsg" class="alert" :class="reviewSuccess ? 'alert-success' : 'alert-error'">{{ reviewMsg }}</div>
                  <button type="submit" class="btn btn-primary" :disabled="reviewSending">
                    {{ reviewSending ? 'Envoi…' : 'Envoyer mon avis' }}
                  </button>
                </form>
              </Transition>

              <!-- Liste avis -->
              <div v-if="artisan.rating_count === 0 && !showReviewForm" class="no-reviews">
                <div style="font-size:2rem;">💬</div>
                <p>Pas encore d'avis. Soyez le premier !</p>
              </div>
              <div v-else class="reviews-list">
                <!-- (Les avis seraient chargés depuis /artisans/:id/reviews) -->
              </div>
            </div>
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
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { fetchArtisan, postReview, contactArtisan, CITY_NAME } from '../api.js'

const route = useRoute()
const id    = parseInt(route.params.id)

const artisan = ref(null)
const loading = ref(true)
const error   = ref(false)

// Reviews
const showReviewForm = ref(false)
const reviewForm = ref({ reviewer_name: '', reviewer_email: '', rating: 5, comment: '' })
const reviewSending = ref(false)
const reviewMsg     = ref('')
const reviewSuccess = ref(false)

// Contact
const showContactForm = ref(false)
const contactForm  = ref({ name: '', email: '', message: '' })
const contactSending = ref(false)
const contactMsg   = ref('')
const contactSuccess = ref(false)

async function submitReview() {
  reviewSending.value = true; reviewMsg.value = ''
  try {
    const res = await postReview(id, reviewForm.value)
    reviewSuccess.value = res.success
    reviewMsg.value = res.message || (res.success ? 'Avis envoyé !' : 'Erreur')
    if (res.success) { showReviewForm.value = false; reviewForm.value = { reviewer_name: '', reviewer_email: '', rating: 5, comment: '' } }
  } catch { reviewMsg.value = 'Erreur réseau' }
  finally { reviewSending.value = false }
}

async function submitContact() {
  contactSending.value = true; contactMsg.value = ''
  try {
    const res = await contactArtisan(id, contactForm.value)
    contactSuccess.value = res.success
    contactMsg.value = res.message || (res.success ? 'Message envoyé !' : 'Erreur')
    if (res.success) { showContactForm.value = false; contactForm.value = { name: '', email: '', message: '' } }
  } catch { contactMsg.value = 'Erreur réseau' }
  finally { contactSending.value = false }
}

function formatDate(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })
}

onMounted(async () => {
  try {
    const res = await fetchArtisan(id)
    artisan.value = res.data
  } catch { error.value = true }
  finally { loading.value = false }
})
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
.price-tag    { background: #D8F3DC; color: var(--c-green-dark); padding: 3px 10px; border-radius: var(--r-full); font-size: 0.8rem; font-weight: 600; }
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
.contact-phone { background: #D8F3DC; color: var(--c-green-dark); }
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
</style>
