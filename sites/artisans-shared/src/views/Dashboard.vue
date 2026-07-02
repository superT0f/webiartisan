<template>
  <div class="container section dashboard">
    <!-- État non connecté : formulaire de lien magique -->
    <template v-if="!token">
      <div class="auth-card">
        <h1>Espace artisan</h1>
        <p class="text-muted">Recevez un lien de connexion sécurisé par email.</p>

        <form @submit.prevent="sendMagicLink" class="auth-form">
          <label for="email">Adresse email</label>
          <input
            id="email"
            v-model="email"
            type="email"
            class="form-input"
            placeholder="votre@email.fr"
            required
            :disabled="sending"
          />
          <label class="form-checkbox">
            <input v-model="rememberMe" type="checkbox" :disabled="sending" />
            Rester connecté sur cet appareil
          </label>
          <button type="submit" class="btn btn-primary" :disabled="sending || !email">
            {{ sending ? 'Envoi…' : 'Recevoir mon lien' }}
          </button>
        </form>

        <div v-if="message" class="auth-message" :class="messageType">
          {{ message }}
        </div>
      </div>
    </template>

    <!-- État connecté : formulaire de profil -->
    <template v-else>
      <div class="dashboard-header flex-between">
        <div>
          <h1>Mon espace artisan</h1>
          <p class="text-muted">Gérez votre profil visible dans l'annuaire.</p>
        </div>
        <button class="btn btn-outline" @click="logout">Se déconnecter</button>
      </div>

      <section class="dashboard-section card">
        <div class="section-title">
          <h2>Roue des artisans</h2>
        </div>
        <div class="prospect-list">
          <RouterLink to="/espace/spin-offers" class="prospect-mini">
            <div><strong>Mes offres</strong><span class="text-muted small">Créer et gérer les lots</span></div>
            <span class="badge badge-green">Gérer</span>
          </RouterLink>
          <RouterLink to="/espace/spin-wins" class="prospect-mini">
            <div><strong>Valider un gain</strong><span class="text-muted small">Saisir un code gagnant</span></div>
            <span class="badge badge-green">Valider</span>
          </RouterLink>
        </div>
      </section>

      <section class="dashboard-section card">
        <div class="section-title">
          <h2>Mes services</h2>
        </div>
        <div class="prospect-list">
          <RouterLink to="/artisan/services" class="prospect-mini">
            <div><strong>Gérer mes services</strong><span class="text-muted small">Catalogue et services personnalisés</span></div>
            <span class="badge badge-green">Gérer</span>
          </RouterLink>
        </div>
      </section>

      <section class="dashboard-section card">
        <div class="section-title">
          <h2>Mini-jeux</h2>
        </div>
        <div class="prospect-list">
          <RouterLink to="/artisan/jeux" class="prospect-mini">
            <div><strong>Mes mini-jeux</strong><span class="text-muted small">Créer et gérer mes jeux interactifs</span></div>
            <span class="badge badge-green">Gérer</span>
          </RouterLink>
        </div>
      </section>

      <div v-if="loading" class="skeleton" style="height: 200px; border-radius: 12px;"></div>

      <div v-if="loadingProspects" class="skeleton" style="height: 120px; border-radius: 12px; margin-top: 24px;"></div>

      <section v-else-if="myProspects.length" class="dashboard-section card">
        <div class="section-title">
          <h2>Ma prospection</h2>
          <RouterLink to="/prospection" class="btn btn-outline btn-sm">Voir tout</RouterLink>
        </div>
        <div class="prospect-list">
          <RouterLink
            v-for="p in myProspects"
            :key="p.id"
            :to="`/prospect/${p.id}`"
            class="prospect-mini"
          >
            <div>
              <strong>{{ p.name }}</strong>
              <span class="text-muted small">{{ p.type }}</span>
            </div>
            <span class="badge" :class="'status-' + p.follow_status">{{ statusLabel[p.follow_status] || p.follow_status }}</span>
          </RouterLink>
        </div>
      </section>

      <form v-if="artisan" @submit.prevent="saveProfile" class="profile-form card">
        <div class="form-group">
          <label for="company_name">Nom de l'entreprise</label>
          <input id="company_name" v-model="form.company_name" class="form-input" required />
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="phone">Téléphone</label>
            <input id="phone" v-model="form.phone" class="form-input" />
          </div>
          <div class="form-group">
            <label for="website">Site web</label>
            <input id="website" v-model="form.website" class="form-input" placeholder="https://..." />
          </div>
        </div>

        <div class="form-group">
          <label for="address">Adresse</label>
          <input id="address" v-model="form.address" class="form-input" />
        </div>

        <div class="form-group">
          <label for="description">Description</label>
          <textarea id="description" v-model="form.description" class="form-input" rows="5"></textarea>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ saving ? 'Enregistrement…' : 'Enregistrer les modifications' }}
          </button>
          <RouterLink to="/" class="btn btn-outline">Retour à l'annuaire</RouterLink>
        </div>

        <div v-if="message" class="auth-message" :class="messageType">
          {{ message }}
        </div>
      </form>

      <div v-else class="empty-state">
        <div class="empty-icon">🔐</div>
        <h3>Session invalide</h3>
        <p>Votre lien de connexion a peut-être expiré.</p>
        <button class="btn btn-primary" style="margin-top: 16px;" @click="logout">
          Recommencer
        </button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { requestMagicLink, fetchMe, updateMe, getMyProspects, getArtisanToken, setArtisanToken, removeArtisanToken } from '../api.js'

const route = useRoute()
const router = useRouter()

const token = ref(getArtisanToken())
const email = ref('')
const rememberMe = ref(true)
const artisan = ref(null)
const form = reactive({
  company_name: '',
  phone: '',
  website: '',
  address: '',
  description: '',
})

const loading = ref(false)
const sending = ref(false)
const saving = ref(false)
const message = ref('')
const messageType = ref('')
const myProspects = ref([])
const loadingProspects = ref(false)

function setMessage(text, type = 'info') {
  message.value = text
  messageType.value = type
}

// Connexion via ?token=xxx dans l'URL
if (route.query.token) {
  token.value = route.query.token
  setArtisanToken(route.query.token, rememberMe.value)
  router.replace('/espace')
}

async function sendMagicLink() {
  sending.value = true
  message.value = ''
  try {
    const res = await requestMagicLink(email.value, rememberMe.value)
    setMessage(res.message || 'Si votre email est valide, vous recevrez un lien de connexion.', 'success')
  } catch (e) {
    setMessage('Erreur lors de l\'envoi. Veuillez réessayer.', 'error')
  } finally {
    sending.value = false
  }
}

async function loadProfile() {
  if (!token.value) return
  loading.value = true
  message.value = ''
  try {
    const res = await fetchMe(token.value)
    if (res.success && res.data) {
      artisan.value = res.data
      Object.assign(form, {
        company_name: res.data.company_name || '',
        phone: res.data.phone || '',
        website: res.data.website || '',
        address: res.data.address || '',
        description: res.data.description || '',
      })
    } else {
      logout()
      setMessage('Votre session a expiré. Veuillez vous reconnecter.', 'error')
    }
  } catch (e) {
    setMessage('Impossible de charger votre profil.', 'error')
  } finally {
    loading.value = false
  }
}

async function loadMyProspects() {
  if (!token.value) return
  loadingProspects.value = true
  try {
    const res = await getMyProspects(token.value)
    myProspects.value = (res.data || []).filter(p => p.follow_status)
  } catch (e) {
    console.error('Erreur chargement prospects suivis', e)
  } finally {
    loadingProspects.value = false
  }
}

const statusLabel = {
  tocontact: 'À contacter',
  contacted: 'Contacté',
  meeting: 'RDV pris',
  converted: 'Converti',
  declined: 'Refus',
}

async function saveProfile() {
  saving.value = true
  message.value = ''
  try {
    const res = await updateMe(token.value, { ...form })
    if (res.success) {
      setMessage('Profil mis à jour avec succès.', 'success')
    } else {
      setMessage(res.error || 'Erreur lors de la mise à jour.', 'error')
    }
  } catch (e) {
    setMessage('Erreur lors de la sauvegarde.', 'error')
  } finally {
    saving.value = false
  }
}

function logout() {
  token.value = ''
  artisan.value = null
  removeArtisanToken()
  message.value = ''
}

onMounted(() => {
  loadProfile()
  loadMyProspects()
})
</script>

<style scoped>
.dashboard { max-width: 720px; }

.dashboard-section { padding: 24px; margin-bottom: 24px; }
.section-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}
.section-title h2 { font-size: 1.2rem; }

.prospect-list { display: flex; flex-direction: column; gap: 10px; }
.prospect-mini {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  border-radius: var(--r-md);
  background: var(--c-cream);
  color: inherit;
  text-decoration: none;
  transition: background 0.2s;
}
.prospect-mini:hover { background: var(--c-cream-2); }
.prospect-mini strong { display: block; }
.prospect-mini .small { font-size: 0.8rem; }

.status-tocontact { background: #E3F2FD; color: #0D47A1; }
.status-contacted { background: #FFF3E0; color: #E65100; }
.status-meeting { background: #F3E5F5; color: #6A1B9A; }
.status-converted { background: #D8F3DC; color: #1B5E20; }
.status-declined { background: #FFEBEE; color: #B71C1C; }

.auth-card {
  max-width: 420px;
  margin: 40px auto;
  text-align: center;
}
.auth-card h1 { margin-bottom: 8px; }
.auth-card .text-muted { margin-bottom: 28px; }

.auth-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
  text-align: left;
}
.auth-form label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--c-text-2);
}

.form-checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
}

.form-checkbox input {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.auth-message {
  margin-top: 16px;
  padding: 12px 16px;
  border-radius: var(--r-md);
  font-size: 0.9rem;
}
.auth-message.success {
  background: rgba(45, 106, 79, 0.1);
  color: var(--c-green-dark);
}
.auth-message.error {
  background: rgba(183, 28, 28, 0.08);
  color: #b71c1c;
}

.dashboard-header {
  margin-bottom: 24px;
  align-items: flex-start;
  gap: 16px;
}
.dashboard-header h1 { margin-bottom: 4px; }

.profile-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 28px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.form-group label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--c-text-2);
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 8px;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
}
.empty-icon { font-size: 3rem; margin-bottom: 16px; }

@media (max-width: 600px) {
  .form-row { grid-template-columns: 1fr; }
  .dashboard-header { flex-direction: column; }
}
</style>
