<template>
  <div class="register-page">
    <div class="register-hero">
      <div class="container">
        <RouterLink to="/annuaire" class="back-link">← Retour à l'annuaire</RouterLink>
        <div class="register-hero-content">
          <div class="register-icon">🏗️</div>
          <h1>Inscrivez votre entreprise</h1>
          <p>Rejoignez l'annuaire des artisans de {{ CITY_NAME }}. Gratuit, sans engagement.</p>
        </div>
      </div>
    </div>

    <div class="container register-body">
      <div class="register-layout">

        <!-- Formulaire -->
        <div class="register-form-wrap">
          <div v-if="success" class="success-screen">
            <div class="success-icon">🎉</div>
            <h2>Inscription reçue !</h2>
            <p>Votre profil est en cours de validation. Vous recevrez un email de confirmation sous 24h.</p>
            <RouterLink to="/annuaire" class="btn btn-primary" style="margin-top:24px;">Voir l'annuaire</RouterLink>
          </div>

          <form v-else @submit.prevent="submit" class="register-form">
            <h2 class="form-title">Vos informations</h2>

            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Nom de l'entreprise *</label>
                <input v-model="form.company_name" class="form-input" required placeholder="Plomberie Dupont" />
              </div>
              <div class="form-group">
                <label class="form-label">Catégorie de métier *</label>
                <select v-model="form.category_slug" class="form-select" required>
                  <option value="">Choisir un métier…</option>
                  <option v-for="cat in categories" :key="cat.slug" :value="cat.slug">
                    {{ cat.icon }} {{ cat.name }}
                  </option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Description de votre activité</label>
              <textarea v-model="form.description" class="form-textarea" placeholder="Décrivez vos activités, votre expérience, votre zone d'intervention…" />
            </div>

            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Téléphone *</label>
                <input v-model="form.phone" class="form-input" type="tel" required placeholder="06 12 34 56 78" />
              </div>
              <div class="form-group">
                <label class="form-label">Email professionnel *</label>
                <input v-model="form.email" class="form-input" type="email" required placeholder="contact@votre-entreprise.fr" />
              </div>
            </div>

            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Site web</label>
                <input v-model="form.website" class="form-input" type="url" placeholder="https://votre-site.fr" />
              </div>
              <div class="form-group">
                <label class="form-label">SIRET (optionnel)</label>
                <input v-model="form.siret" class="form-input" placeholder="12345678901234" maxlength="14" />
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Adresse</label>
              <input v-model="form.address" class="form-input" :placeholder="CITY_CP + ' ' + CITY_NAME" />
            </div>

            <hr class="divider" />
            <h3 class="form-subtitle">Vos services (optionnel)</h3>
            <div class="services-inputs">
              <div v-for="(svc, i) in form.services" :key="i" class="service-input-row">
                <input v-model="svc.name" class="form-input" :placeholder="`Service ${i + 1} (ex: Dépannage fuite)`" />
                <input v-model="svc.price_range" class="form-input" style="max-width:160px;" placeholder="Tarif (ex: 60-120€)" />
                <button type="button" class="btn btn-outline btn-sm" @click="removeService(i)" v-if="form.services.length > 1">✕</button>
              </div>
              <button type="button" class="btn btn-outline btn-sm" @click="addService" v-if="form.services.length < 8">+ Ajouter un service</button>
            </div>

            <hr class="divider" />
            <h3 class="form-subtitle">Connexion (optionnel)</h3>
            <p class="form-hint">Créez un mot de passe pour accéder à votre espace et modifier votre profil.</p>
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Mot de passe</label>
                <input v-model="form.password" class="form-input" type="password" placeholder="8 caractères minimum" />
              </div>
            </div>

            <div class="form-check">
              <input id="accept" v-model="accepted" type="checkbox" required />
              <label for="accept">J'accepte que mon profil soit publié dans l'annuaire de {{ CITY_NAME }}.</label>
            </div>

            <div v-if="errorMsg" class="alert alert-error">{{ errorMsg }}</div>

            <button type="submit" class="btn btn-primary btn-lg" :disabled="sending" style="width:100%;">
              {{ sending ? 'Inscription en cours…' : '🚀 Créer mon profil gratuit' }}
            </button>
          </form>
        </div>

        <!-- Sidebar avantages -->
        <aside class="register-sidebar">
          <div class="advantages">
            <h3>Pourquoi s'inscrire ?</h3>
            <div class="advantage-list">
              <div class="adv-item" v-for="a in advantages" :key="a.title">
                <span class="adv-icon">{{ a.icon }}</span>
                <div>
                  <strong>{{ a.title }}</strong>
                  <p>{{ a.text }}</p>
                </div>
              </div>
            </div>
          </div>
          <div class="trust-box">
            <div class="trust-icon">🔒</div>
            <p>Vos données restent en France. Aucun démarchage commercial. Vous pouvez modifier ou supprimer votre profil à tout moment.</p>
          </div>
        </aside>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { registerArtisan, fetchCategories, CITY_NAME, CITY_CP } from '../api.js'

const categories = ref([])
const success    = ref(false)
const sending    = ref(false)
const errorMsg   = ref('')
const accepted   = ref(false)

const form = ref({
  company_name: '', category_slug: '', description: '',
  phone: '', email: '', website: '', siret: '', address: '',
  password: '',
  services: [{ name: '', price_range: '' }],
})

function addService()     { form.value.services.push({ name: '', price_range: '' }) }
function removeService(i) { form.value.services.splice(i, 1) }

async function submit() {
  if (!accepted.value) { errorMsg.value = 'Veuillez accepter les conditions.'; return }
  sending.value = true; errorMsg.value = ''
  try {
    const payload = {
      ...form.value,
      services: form.value.services.filter(s => s.name.trim()),
    }
    const res = await registerArtisan(payload)
    if (res.success) { success.value = true }
    else { errorMsg.value = res.error || 'Une erreur est survenue.' }
  } catch { errorMsg.value = 'Erreur réseau. Vérifiez votre connexion.' }
  finally { sending.value = false }
}

const advantages = [
  { icon: '🆓', title: 'Gratuit pour toujours',    text: 'Aucun abonnement, aucune commission sur vos chantiers.' },
  { icon: '📍', title: 'Visibilité locale',          text: 'Vos voisins vous trouvent en cherchant un artisan à ' + CITY_NAME + '.' },
  { icon: '⭐', title: 'Avis clients',               text: 'Collectez des avis authentiques pour renforcer votre réputation.' },
  { icon: '📱', title: 'Compatible mobile',          text: 'Votre fiche est visible sur smartphone, tablette et ordinateur.' },
  { icon: '✏️', title: 'Modifiable à tout moment',  text: 'Mettez à jour vos services, vos tarifs, vos photos librement.' },
]

onMounted(async () => {
  try { categories.value = await fetchCategories() } catch { /* mode démo */ }
})
</script>

<style scoped>
.register-hero {
  background: linear-gradient(135deg, var(--c-green-dark), var(--c-green));
  color: white;
  padding: 48px 0 36px;
}
.back-link {
  display: inline-block;
  color: rgba(255,255,255,0.75);
  font-size: 0.88rem;
  margin-bottom: 20px;
}
.back-link:hover { color: white; }
.register-hero-content { display: flex; align-items: center; gap: 20px; }
.register-icon { font-size: 3rem; }
.register-hero-content h1 { color: white; margin-bottom: 6px; }
.register-hero-content p  { color: rgba(255,255,255,0.8); font-size: 1.05rem; }

.register-body { padding: 40px 0 80px; }
.register-layout { display: grid; grid-template-columns: 1fr 300px; gap: 40px; align-items: start; }

.register-form-wrap {
  background: var(--c-white);
  border: 1px solid var(--c-border);
  border-radius: var(--r-xl);
  padding: 40px;
}
.form-title    { font-size: 1.3rem; margin-bottom: 28px; }
.form-subtitle { font-size: 1rem; margin-bottom: 8px; }
.form-hint     { font-size: 0.85rem; color: var(--c-text-3); margin-bottom: 16px; }

.services-inputs { display: flex; flex-direction: column; gap: 10px; margin-bottom: 8px; }
.service-input-row { display: flex; gap: 10px; align-items: center; }

.form-check {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin: 20px 0;
  font-size: 0.88rem;
  color: var(--c-text-2);
}
.form-check input { flex-shrink: 0; width: 18px; height: 18px; margin-top: 2px; accent-color: var(--c-green); }

/* Success */
.success-screen {
  text-align: center;
  padding: 40px 20px;
}
.success-icon { font-size: 4rem; margin-bottom: 20px; animation: bounce 0.6s var(--ease-spring); }
@keyframes bounce { from { transform: scale(0.5); } to { transform: scale(1); } }

/* Sidebar */
.register-sidebar { display: flex; flex-direction: column; gap: 20px; }
.advantages {
  background: var(--c-white);
  border: 1px solid var(--c-border);
  border-radius: var(--r-xl);
  padding: 28px;
}
.advantages h3 { margin-bottom: 20px; }
.advantage-list { display: flex; flex-direction: column; gap: 16px; }
.adv-item { display: flex; gap: 14px; align-items: flex-start; }
.adv-icon { font-size: 1.4rem; flex-shrink: 0; }
.adv-item strong { display: block; font-size: 0.9rem; margin-bottom: 2px; }
.adv-item p { font-size: 0.82rem; color: var(--c-text-3); }

.trust-box {
  background: #D8F3DC;
  border-radius: var(--r-lg);
  padding: 20px;
  display: flex;
  gap: 12px;
  align-items: flex-start;
}
.trust-icon { font-size: 1.4rem; flex-shrink: 0; }
.trust-box p { font-size: 0.83rem; color: var(--c-green-dark); line-height: 1.6; }

@media (max-width: 900px) {
  .register-layout { grid-template-columns: 1fr; }
  .register-sidebar { order: -1; }
  .register-form-wrap { padding: 24px; }
}
@media (max-width: 600px) {
  .service-input-row { flex-direction: column; }
}
</style>
