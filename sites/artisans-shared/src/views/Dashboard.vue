<template>
  <div class="container section dashboard">
    <!-- État non connecté : formulaire de lien magique ou mot de passe -->
    <template v-if="!token">
      <div class="auth-card">
        <h1>Espace artisan</h1>

        <div class="auth-tabs" role="tablist" aria-label="Méthode de connexion">
          <button
            type="button"
            role="tab"
            :class="{ active: authTab === 'magic' }"
            @click="authTab = 'magic'"
          >Lien magique</button>
          <button
            type="button"
            role="tab"
            :class="{ active: authTab === 'password' }"
            @click="authTab = 'password'"
          >Mot de passe</button>
        </div>

        <div v-show="authTab === 'magic'" class="auth-form">
          <p class="text-muted">Recevez un lien de connexion sécurisé par email.</p>
          <form @submit.prevent="sendMagicLink">
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
        </div>

        <div v-show="authTab === 'password'" class="auth-form">
          <p class="text-muted">Connectez-vous avec votre email et mot de passe.</p>
          <form @submit.prevent="submitPasswordLogin">
            <label for="login-email">Adresse email</label>
            <input
              id="login-email"
              v-model="email"
              type="email"
              class="form-input"
              placeholder="votre@email.fr"
              autocomplete="email"
              required
              :disabled="sending"
            />
            <label for="login-password">Mot de passe</label>
            <input
              id="login-password"
              v-model="password"
              type="password"
              class="form-input"
              placeholder="Mot de passe"
              autocomplete="current-password"
              required
              :disabled="sending"
            />
            <label class="form-checkbox">
              <input v-model="rememberMe" type="checkbox" :disabled="sending" />
              Rester connecté sur cet appareil
            </label>
            <button type="submit" class="btn btn-primary" :disabled="sending || !email || !password">
              {{ sending ? 'Connexion…' : 'Se connecter' }}
            </button>
          </form>
        </div>

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

      <section v-if="artisan?.is_admin === 1" class="dashboard-section card admin-card">
        <div class="section-title">
          <h2>Administration</h2>
          <span class="badge badge-red">Admin</span>
        </div>
        <div class="prospect-list">
          <RouterLink to="/espace/admin" class="prospect-mini">
            <div>
              <strong>Gérer les artisans</strong>
              <span class="text-muted small">Activer, suspendre et modifier les fiches</span>
            </div>
            <span class="badge badge-green">Ouvrir</span>
          </RouterLink>
          <RouterLink to="/espace/admin/pois" class="prospect-mini">
            <div>
              <strong>Gérer les POI</strong>
              <span class="text-muted small">Services publics, horaires et points d'intérêt</span>
            </div>
            <span class="badge badge-green">Ouvrir</span>
          </RouterLink>
          <RouterLink to="/espace/admin-recettes" class="prospect-mini">
            <div>
              <strong>Modérer les recettes</strong>
              <span class="text-muted small">Publier, archiver et modérer</span>
            </div>
            <span class="badge badge-green">Ouvrir</span>
          </RouterLink>
        </div>
      </section>

      <section class="dashboard-section card">
        <div class="section-title">
          <h2>🔐 Sécurité</h2>
        </div>
        <div class="security-block">
          <div>
            <strong>Changer mon mot de passe</strong>
            <p class="text-muted small">Modifiez le mot de passe de votre espace artisan.</p>
          </div>
          <button type="button" class="btn btn-outline" @click="showPasswordForm = !showPasswordForm">
            {{ showPasswordForm ? 'Annuler' : 'Modifier' }}
          </button>
        </div>
        <form v-if="showPasswordForm" class="password-form" @submit.prevent="submitPasswordChange">
          <label for="artisan-current-password">Mot de passe actuel</label>
          <input id="artisan-current-password" v-model="passwordForm.current" type="password" required />

          <label for="artisan-new-password">Nouveau mot de passe</label>
          <input id="artisan-new-password" v-model="passwordForm.new" type="password" minlength="8" required />

          <label for="artisan-confirm-password">Confirmer le mot de passe</label>
          <input id="artisan-confirm-password" v-model="passwordForm.confirm" type="password" minlength="8" required />

          <button type="submit" class="btn btn-primary" :disabled="passwordLoading">
            {{ passwordLoading ? 'Enregistrement…' : 'Enregistrer' }}
          </button>
          <p v-if="passwordMessage" class="form-message" :class="passwordMessageType">{{ passwordMessage }}</p>
        </form>
      </section>

      <section class="dashboard-section card premium-card" :class="{ 'premium-active': isPremium }">
        <div class="section-title">
          <h2>Abonnement</h2>
          <span v-if="isPremium" class="badge badge-gold">Premium actif</span>
          <span v-else class="badge badge-grey">Gratuit</span>
        </div>
        <div v-if="loadingSubscription" class="skeleton" style="height: 60px; border-radius: 12px;"></div>
        <div v-else-if="isPremium" class="premium-content">
          <p class="text-muted">
            Vous bénéficiez de toutes les fonctionnalités premium.
            <span v-if="subscriptionStatus?.subscription_period_end">
              Prochain renouvellement le {{ formatPeriodEnd(subscriptionStatus.subscription_period_end) }}.
            </span>
          </p>
          <button type="button" class="btn btn-outline" @click="openPortal" :disabled="subscribing">
            Gérer mon abonnement
          </button>
        </div>
        <div v-else class="premium-content">
          <p class="text-muted">
            Passez Premium pour débloquer « Tournez l'avatar » en boutique et plus de services.
          </p>
          <button type="button" class="btn btn-gold" @click="startCheckout" :disabled="subscribing">
            {{ subscribing ? 'Redirection…' : 'Passer Premium — 2,99 €/mois' }}
          </button>
        </div>
      </section>

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

      <section class="dashboard-section card">
        <div class="section-title">
          <h2>🎮 Jouer</h2>
        </div>
        <div class="prospect-list">
          <button type="button" class="prospect-mini" @click="playAsConsumer" :disabled="linkingConsumer">
            <div><strong>Jouer sur la carte</strong><span class="text-muted small">Participer comme un habitant</span></div>
            <span class="badge badge-green">{{ linkingConsumer ? 'Lien…' : 'Jouer' }}</span>
          </button>
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
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { requestMagicLink, loginArtisan, fetchMe, updateMe, getMyProspects, getArtisanToken, setArtisanToken, removeArtisanToken, fetchArtisanConsumerToken, setUserToken, logoutArtisan, getSubscriptionStatus, createSubscriptionCheckout, createSubscriptionPortal, changeArtisanPassword } from '../api.js'

const router = useRouter()

const token = ref(getArtisanToken())
const email = ref('')
const password = ref('')
const rememberMe = ref(true)
const authTab = ref('magic')
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
const linkingConsumer = ref(false)
const subscriptionStatus = ref(null)
const loadingSubscription = ref(false)
const subscribing = ref(false)
let isMounted = false
const activeControllers = new Set()

const showPasswordForm = ref(false)
const passwordForm = ref({ current: '', new: '', confirm: '' })
const passwordLoading = ref(false)
const passwordMessage = ref('')
const passwordMessageType = ref('info')

const isPremium = computed(() =>
  subscriptionStatus.value?.plan === 'premium' || artisan.value?.plan === 'premium'
)

function newAbortSignal() {
  const controller = new AbortController()
  activeControllers.add(controller)
  return controller.signal
}

function cleanupSignal(signal) {
  for (const controller of activeControllers) {
    if (controller.signal === signal) {
      activeControllers.delete(controller)
      break
    }
  }
}

function abortAllPending() {
  activeControllers.forEach(c => c.abort())
  activeControllers.clear()
}

function setMessage(text, type = 'info') {
  message.value = text
  messageType.value = type
}

async function sendMagicLink() {
  sending.value = true
  message.value = ''
  const signal = newAbortSignal()
  try {
    const res = await requestMagicLink(email.value, rememberMe.value, { signal })
    if (!isMounted) return
    if (res.success) {
      setMessage(res.data?.message || 'Si votre email est valide, vous recevrez un lien de connexion.', 'success')
    } else {
      setMessage(res.error || 'Erreur lors de l\'envoi. Veuillez réessayer.', 'error')
    }
  } catch (e) {
    if (!isMounted) return
    setMessage(e.message || 'Erreur lors de l\'envoi. Veuillez réessayer.', 'error')
  } finally {
    cleanupSignal(signal)
    if (isMounted) sending.value = false
  }
}

async function submitPasswordLogin() {
  sending.value = true
  message.value = ''
  try {
    const res = await loginArtisan({ email: email.value, password: password.value, rememberMe: rememberMe.value })
    if (!isMounted) return
    if (res.success && res.token) {
      setArtisanToken(res.token, rememberMe.value)
      token.value = res.token
      if (res.userToken) {
        setUserToken(res.userToken, rememberMe.value)
      }
      await router.replace('/espace')
      loadProfile()
      loadMyProspects()
    } else {
      setMessage(res.error || 'Email ou mot de passe incorrect.', 'error')
    }
  } catch (e) {
    if (!isMounted) return
    setMessage(e.message || 'Erreur lors de la connexion.', 'error')
  } finally {
    if (isMounted) sending.value = false
  }
}

async function loadProfile() {
  if (!token.value || !isMounted) return
  const currentToken = token.value
  loading.value = true
  message.value = ''
  const signal = newAbortSignal()
  try {
    const res = await fetchMe(currentToken, { signal })
    if (!isMounted || token.value !== currentToken) return
    if (res.success && res.data) {
      artisan.value = res.data
      Object.assign(form, {
        company_name: res.data.company_name || '',
        phone: res.data.phone || '',
        website: res.data.website || '',
        address: res.data.address || '',
        description: res.data.description || '',
      })
      // Lier automatiquement le compte consommateur
      if (res.userToken) {
        setUserToken(res.userToken, true)
      }
    } else if (token.value === currentToken) {
      await logout()
      if (!isMounted || token.value) return
      setMessage(res.error || 'Votre session a expiré. Veuillez vous reconnecter.', 'error')
    }
  } catch (e) {
    if (isMounted && e.name !== 'AbortError' && token.value === currentToken) {
      setMessage(e.message || 'Impossible de charger votre profil.', 'error')
    }
  } finally {
    cleanupSignal(signal)
    if (isMounted && token.value === currentToken) loading.value = false
  }
}

async function loadSubscription() {
  if (!token.value || !isMounted) return
  const currentToken = token.value
  loadingSubscription.value = true
  const signal = newAbortSignal()
  try {
    const res = await getSubscriptionStatus({ signal })
    if (!isMounted || token.value !== currentToken) return
    if (res.success && res.data) {
      subscriptionStatus.value = res.data
    }
  } catch (e) {
    if (e.name !== 'AbortError') {
      console.error('Erreur chargement abonnement', e)
    }
  } finally {
    cleanupSignal(signal)
    if (isMounted && token.value === currentToken) loadingSubscription.value = false
  }
}

function formatPeriodEnd(date) {
  if (!date) return ''
  const d = new Date(date)
  if (isNaN(d.getTime())) return ''
  return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
}

async function startCheckout() {
  subscribing.value = true
  message.value = ''
  try {
    const res = await createSubscriptionCheckout(window.location.origin + '/espace')
    if (res.success && res.data?.url) {
      window.location.href = res.data.url
    } else {
      setMessage(res.error || 'Impossible de démarrer le paiement.', 'error')
      subscribing.value = false
    }
  } catch (e) {
    setMessage(e.message || 'Erreur lors du paiement.', 'error')
    subscribing.value = false
  }
}

async function openPortal() {
  subscribing.value = true
  message.value = ''
  try {
    const res = await createSubscriptionPortal(window.location.origin + '/espace')
    if (res.success && res.data?.url) {
      window.location.href = res.data.url
    } else {
      setMessage(res.error || 'Impossible d\'ouvrir le portail.', 'error')
      subscribing.value = false
    }
  } catch (e) {
    setMessage(e.message || 'Erreur lors de l\'ouverture du portail.', 'error')
    subscribing.value = false
  }
}

async function loadMyProspects() {
  if (!token.value || !isMounted) return
  const currentToken = token.value
  loadingProspects.value = true
  const signal = newAbortSignal()
  try {
    const res = await getMyProspects(currentToken, { signal })
    if (isMounted && token.value === currentToken && res.success) {
      myProspects.value = (res.data || []).filter(p => p.follow_status)
    }
  } catch (e) {
    if (e.name !== 'AbortError') {
      console.error('Erreur chargement prospects suivis', e)
    }
  } finally {
    cleanupSignal(signal)
    if (isMounted && token.value === currentToken) loadingProspects.value = false
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
  const signal = newAbortSignal()
  try {
    const res = await updateMe(token.value, { ...form }, { signal })
    if (!isMounted || !token.value) return
    if (res.success) {
      setMessage('Profil mis à jour avec succès.', 'success')
    } else {
      setMessage(res.error || 'Erreur lors de la mise à jour.', 'error')
    }
  } catch (e) {
    if (!isMounted || !token.value) return
    setMessage(e.message || 'Erreur lors de la sauvegarde.', 'error')
  } finally {
    cleanupSignal(signal)
    if (isMounted) saving.value = false
  }
}

async function submitPasswordChange() {
  passwordMessage.value = ''
  if (passwordForm.value.new.length < 8) {
    passwordMessage.value = 'Le nouveau mot de passe doit faire au moins 8 caractères.'
    passwordMessageType.value = 'error'
    return
  }
  if (passwordForm.value.new !== passwordForm.value.confirm) {
    passwordMessage.value = 'Les mots de passe ne correspondent pas.'
    passwordMessageType.value = 'error'
    return
  }

  passwordLoading.value = true
  try {
    const res = await changeArtisanPassword(token.value, passwordForm.value.current, passwordForm.value.new, passwordForm.value.confirm)
    if (res.success) {
      passwordMessage.value = res.message || 'Mot de passe mis à jour.'
      passwordMessageType.value = 'success'
      passwordForm.value = { current: '', new: '', confirm: '' }
      setTimeout(() => { showPasswordForm.value = false }, 1500)
    } else {
      passwordMessage.value = res.error || 'Erreur lors de la mise à jour.'
      passwordMessageType.value = 'error'
    }
  } catch (e) {
    passwordMessage.value = 'Erreur réseau.'
    passwordMessageType.value = 'error'
  } finally {
    passwordLoading.value = false
  }
}

async function logout() {
  const currentToken = token.value
  const signal = newAbortSignal()
  try {
    if (currentToken) {
      await logoutArtisan(currentToken, { signal })
    }
  } catch (e) {
    console.error('Erreur lors de la déconnexion artisan', e)
  } finally {
    cleanupSignal(signal)
  }
  if (token.value === currentToken) {
    token.value = ''
    artisan.value = null
    message.value = ''
    myProspects.value = []
    removeArtisanToken()
  }
}

async function playAsConsumer() {
  if (!token.value) {
    setMessage('Session invalide.', 'error')
    return
  }
  linkingConsumer.value = true
  const signal = newAbortSignal()
  try {
    const res = await fetchArtisanConsumerToken(token.value, { signal })
    if (!isMounted || !token.value) return
    if (res.success && res.data?.token) {
      setUserToken(res.data.token, true)
      router.push('/carte')
    } else {
      setMessage(res.error || 'Impossible de créer le compte joueur.', 'error')
    }
  } catch (e) {
    if (!isMounted || !token.value) return
    setMessage(e.message || 'Erreur lors de la création du compte joueur.', 'error')
  } finally {
    cleanupSignal(signal)
    if (isMounted) linkingConsumer.value = false
  }
}

onMounted(() => {
  isMounted = true
  if (token.value) {
    loadProfile()
    loadMyProspects()
    loadSubscription()
  }
})

onBeforeUnmount(() => {
  isMounted = false
  abortAllPending()
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

.premium-card { border-left: 4px solid var(--c-gold); }
.premium-card.premium-active { border-left-color: var(--c-green); }
.admin-card { border-left: 4px solid #B71C1C; }
.premium-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.premium-content p { margin: 0; }

.security-block {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 16px 0;
  border-bottom: 1px solid var(--c-border, #e8e8e8);
}
.security-block:last-of-type { border-bottom: none; }
.security-block strong { display: block; margin-bottom: 4px; }

.password-form {
  margin-top: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid var(--c-border, #e8e8e8);
}
.password-form label {
  font-weight: 600;
  font-size: 0.9rem;
}
.password-form input {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 8px;
}

.form-message {
  margin-top: 12px;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 0.9rem;
}
.form-message.success {
  background: rgba(45, 106, 79, 0.1);
  color: var(--c-green-dark);
}
.form-message.error {
  background: rgba(183, 28, 28, 0.08);
  color: #b71c1c;
}

@media (max-width: 600px) {
  .form-row { grid-template-columns: 1fr; }
  .dashboard-header { flex-direction: column; }
}
</style>
