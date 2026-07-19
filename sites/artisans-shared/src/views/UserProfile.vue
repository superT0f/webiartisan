<template>
  <div class="user-profile">
    <div v-if="loading" class="profile-card">
      <p>Chargement du profil…</p>
    </div>
    <template v-else>
      <div v-if="error" class="profile-card">
        <p>{{ error }}</p>
        <RouterLink to="/" class="btn btn-primary" style="margin-top: 16px;">Retour à l'accueil</RouterLink>
      </div>
      <template v-else>
        <div class="profile-card">
          <div class="avatar-ring">
            <svg class="avatar-ring-svg" viewBox="0 0 132 132" aria-hidden="true">
              <circle class="ring-bg" cx="66" cy="66" r="63" />
              <circle
                class="ring-fill"
                cx="66" cy="66" r="63"
                :stroke-dasharray="ringCircumference"
                :stroke-dashoffset="ringOffset"
                transform="rotate(-90 66 66)"
              />
            </svg>
            <img v-if="avatarUrl" :src="avatarUrl" :alt="`Avatar de ${displayName}`" class="profile-avatar" />
            <div v-else class="profile-avatar-placeholder">🙂</div>
            <span class="level-badge">Nv.{{ user?.level }}</span>
          </div>
          <h1>{{ displayName }}</h1>
          <p class="profile-title">{{ user?.title || 'Explorateur local' }}</p>
          <div class="profile-level">
            <span>Niveau {{ user?.level }}</span>
            <div class="xp-bar"><div class="xp-fill" :style="{ width: xpPercent + '%' }"></div></div>
            <span>{{ user?.xp }} / {{ user?.xp_needed }} XP</span>
          </div>
        </div>

        <div class="profile-sections">
          <section class="profile-section card">
            <h2>🎨 Personnage</h2>
            <p class="text-muted">Personnalisez votre avatar et votre pseudo public.</p>
            <button type="button" class="btn btn-primary" @click="goToCharacter">
              Modifier mon personnage
            </button>
          </section>

          <section class="profile-section card">
            <h2>🔐 Sécurité</h2>

            <div class="security-block">
              <div>
                <strong>Changer mon mot de passe</strong>
                <p class="text-muted small">Mettez à jour votre mot de passe de connexion.</p>
              </div>
              <button type="button" class="btn btn-outline" @click="showPasswordForm = !showPasswordForm">
                {{ showPasswordForm ? 'Annuler' : 'Modifier' }}
              </button>
            </div>

            <form v-if="showPasswordForm" class="password-form" @submit.prevent="submitPasswordChange">
              <label for="current-password">Mot de passe actuel</label>
              <input id="current-password" v-model="passwordForm.current" type="password" required />

              <label for="new-password">Nouveau mot de passe</label>
              <input id="new-password" v-model="passwordForm.new" type="password" minlength="8" required />

              <label for="confirm-password">Confirmer le mot de passe</label>
              <input id="confirm-password" v-model="passwordForm.confirm" type="password" minlength="8" required />

              <button type="submit" class="btn btn-primary" :disabled="passwordLoading">
                {{ passwordLoading ? 'Enregistrement…' : 'Enregistrer' }}
              </button>
              <p v-if="passwordMessage" class="form-message" :class="passwordMessageType">{{ passwordMessage }}</p>
            </form>

            <div v-if="biometricSupported" class="security-block">
              <div>
                <strong>Connexion par empreinte</strong>
                <p class="text-muted small">Activez la biométrie de votre téléphone pour vous connecter plus vite.</p>
              </div>
              <button v-if="!biometricEnabled" type="button" class="btn btn-outline" @click="enableBiometricAuth">
                Activer
              </button>
              <button v-else type="button" class="btn btn-outline" @click="disableBiometricAuth">
                Désactiver
              </button>
            </div>
            <p v-if="biometricMessage" class="form-message" :class="biometricMessageType">{{ biometricMessage }}</p>
          </section>

          <section class="profile-section card">
            <h2>🏅 Badges</h2>
            <div class="badges-list">
              <span v-for="b in user?.badges" :key="b.key" class="badge">
                {{ badgeIcons[b.key] || '🏅' }} {{ b.name }}
              </span>
              <p v-if="!user?.badges?.length">Aucun badge pour l’instant. Continuez à explorer !</p>
            </div>
          </section>

          <section class="profile-section card">
            <h2>🚪 Session</h2>
            <button type="button" class="btn btn-outline btn-danger" @click="logout">
              Se déconnecter
            </button>
          </section>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  getUserToken, fetchUserMe, removeUserToken, resolveAvatarUrl,
  changeUserPassword, enableBiometric, disableBiometric,
} from '../api.js'
import { biometrics } from '../utils/biometrics.js'

const route = useRoute()
const router = useRouter()
const user = ref(null)
const loading = ref(true)
const error = ref(null)
let abortController = null

const showPasswordForm = ref(false)
const passwordForm = ref({ current: '', new: '', confirm: '' })
const passwordLoading = ref(false)
const passwordMessage = ref('')
const passwordMessageType = ref('info')

const biometricSupported = ref(false)
const biometricEnabled = ref(false)
const biometricMessage = ref('')
const biometricMessageType = ref('info')

const displayName = computed(() => user.value?.display_name || user.value?.email?.split('@')[0] || 'Explorateur')
const xpPercent = computed(() => user.value ? Math.min(100, (user.value.xp / user.value.xp_needed) * 100) : 0)
const ringCircumference = 2 * Math.PI * 63
const ringOffset = computed(() => ringCircumference * (1 - xpPercent.value / 100))
const avatarUrl = computed(() => resolveAvatarUrl(user.value?.avatar_url))

const badgeIcons = {
  first_visit: '🏠',
  curieux: '👀',
  ambassadeur: '📣',
  joueur: '🎮',
  vainqueur: '🏆',
  chanceux: '🍀',
  generous: '🔗',
  faithful: '🔥',
}

function goToCharacter() {
  router.push('/personnage')
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
    const token = getUserToken()
    const res = await changeUserPassword(token, passwordForm.value.current, passwordForm.value.new)
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

async function enableBiometricAuth() {
  biometricMessage.value = ''
  try {
    const token = getUserToken()
    if (!token) throw new Error('Session invalide')

    const result = await biometrics.enable('Activer la connexion par empreinte')
    if (!result.authenticated || !result.secret || !result.deviceId) {
      throw new Error('Authentification biométrique annulée')
    }

    const res = await enableBiometric(token, result.deviceId, result.secret, 'Smartphone WebiArtisan')
    if (res.success) {
      localStorage.setItem('biometric_enabled', '1')
      localStorage.setItem('biometric_device_id', result.deviceId)
      biometricEnabled.value = true
      biometricMessage.value = 'Empreinte activée avec succès.'
      biometricMessageType.value = 'success'
    } else {
      throw new Error(res.error || 'Erreur serveur')
    }
  } catch (e) {
    biometricMessage.value = e.message || 'Impossible d\'activer l\'empreinte.'
    biometricMessageType.value = 'error'
  }
}

async function disableBiometricAuth() {
  biometricMessage.value = ''
  try {
    const token = getUserToken()
    if (!token) throw new Error('Session invalide')

    const { deviceId } = await biometrics.getDeviceId()
    const res = await disableBiometric(token, deviceId)
    if (res.success) {
      await biometrics.clear()
      localStorage.removeItem('biometric_enabled')
      localStorage.removeItem('biometric_device_id')
      biometricEnabled.value = false
      biometricMessage.value = 'Empreinte désactivée.'
      biometricMessageType.value = 'success'
    } else {
      throw new Error(res.error || 'Erreur serveur')
    }
  } catch (e) {
    biometricMessage.value = e.message || 'Impossible de désactiver l\'empreinte.'
    biometricMessageType.value = 'error'
  }
}

function logout() {
  removeUserToken()
  router.push('/profil')
}

onMounted(async () => {
  const token = getUserToken()
  if (!token) {
    loading.value = false
    const redirectPath = route.path === '/profil' ? '/' : `/profil?redirect=${encodeURIComponent(route.fullPath)}`
    router.replace(redirectPath)
    return
  }

  abortController = new AbortController()
  try {
    const res = await fetchUserMe(token, { signal: abortController.signal })
    if (res.success) {
      user.value = res.data
    } else if (res.error === 'AbortError') {
      return
    } else if (res.status === 401) {
      removeUserToken()
      const redirectPath = route.path === '/profil' ? '/' : `/profil?redirect=${encodeURIComponent(route.fullPath)}`
      router.replace(redirectPath)
    } else {
      error.value = res.error || 'Impossible de charger le profil.'
      user.value = null
    }
  } catch (e) {
    console.warn('Failed to load user profile', e)
    error.value = 'Impossible de charger le profil.'
    user.value = null
  } finally {
    loading.value = false
  }

  // Détection biométrie Flutter
  try {
    biometricSupported.value = await biometrics.checkAvailable()
    biometricEnabled.value = localStorage.getItem('biometric_enabled') === '1' && biometricSupported.value
  } catch {
    biometricSupported.value = false
  }
})

onUnmounted(() => {
  abortController?.abort()
})
</script>

<style scoped>
.user-profile { max-width: 680px; margin: 0 auto; padding: 24px; }
.profile-card { text-align: center; background: #f8fafc; border-radius: 16px; padding: 32px; margin-bottom: 24px; }
.profile-avatar, .profile-avatar-placeholder { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 48px; background: #e2e8f0; }

/* Anneau de progression XP autour de l'avatar */
.avatar-ring { position: relative; width: 132px; height: 132px; margin: 0 auto; }
.avatar-ring .profile-avatar, .avatar-ring .profile-avatar-placeholder {
  position: absolute; top: 6px; left: 6px; margin: 0;
}
.avatar-ring-svg { position: absolute; inset: 0; width: 132px; height: 132px; }
.ring-bg { fill: none; stroke: var(--c-border, #e5e2d8); stroke-width: 6; }
.ring-fill {
  fill: none;
  stroke: var(--c-gold, #C07A2E);
  stroke-width: 6;
  stroke-linecap: round;
  transition: stroke-dashoffset 0.6s ease;
}
.level-badge {
  position: absolute;
  bottom: -6px;
  left: 50%;
  transform: translateX(-50%);
  background: var(--c-green, #2d6a4f);
  color: #fff;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 999px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  white-space: nowrap;
}
.profile-title { color: #64748b; margin-top: 4px; }
.profile-level { margin: 16px 0; }
.xp-bar { width: 100%; max-width: 280px; margin: 8px auto; height: 12px; background: #e2e8f0; border-radius: 6px; overflow: hidden; }
.xp-fill { height: 100%; background: #10b981; transition: width 0.3s; }

.profile-sections { display: flex; flex-direction: column; gap: 20px; }
.profile-section { padding: 24px; }
.profile-section h2 { font-size: 1.15rem; margin-bottom: 16px; }

.security-block { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 0; border-bottom: 1px solid var(--c-border, #e8e8e8); }
.security-block:last-of-type { border-bottom: none; }
.security-block strong { display: block; margin-bottom: 4px; }

.password-form { margin-top: 16px; display: flex; flex-direction: column; gap: 12px; padding-top: 16px; border-top: 1px solid var(--c-border, #e8e8e8); }
.password-form label { font-weight: 600; font-size: 0.9rem; }
.password-form input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }

.form-message { margin-top: 12px; padding: 10px 12px; border-radius: 8px; font-size: 0.9rem; }
.form-message.success { background: #e6f4ea; color: #1e7e34; }
.form-message.error { background: #fdecea; color: #c5221f; }

.badges-list { display: flex; flex-wrap: wrap; gap: 8px; }
.badge { background: var(--c-text); color: #fff; padding: 6px 12px; border-radius: 20px; font-size: 13px; }

.btn-danger { border-color: #b71c1c; color: #b71c1c; }
.btn-danger:hover { background: #b71c1c; color: #fff; }

.text-muted { color: #64748b; }
.small { font-size: 0.85rem; }
</style>
