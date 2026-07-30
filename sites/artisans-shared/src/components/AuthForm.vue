<template>
  <div class="auth-form">
    <div class="auth-form__tabs" role="tablist" aria-label="Mode de connexion">
      <button type="button" class="auth-form__tab" :class="{ active: mode === 'magic' }" @click="mode = 'magic'">
        Lien magique
      </button>
      <button type="button" class="auth-form__tab" :class="{ active: mode === 'password' }" @click="mode = 'password'">
        Mot de passe
      </button>
      <button type="button" class="auth-form__tab" :class="{ active: mode === 'register' }" @click="mode = 'register'">
        S'inscrire
      </button>
    </div>
    <p class="auth-form__intro">
      {{ mode === 'magic'
        ? 'Recevez un lien de connexion par email — une seule étape.'
        : mode === 'register'
          ? 'Crée ton compte joueur en 10 secondes : email + mot de passe, et c\'est parti.'
          : 'Si l\'email tarde (délais Gandi), connectez-vous avec votre mot de passe.' }}
    </p>
    <form class="auth-form__form" @submit.prevent="submit">
      <input
        v-model="email"
        type="email"
        class="form-input"
        placeholder="votre@email.fr"
        autocomplete="email"
        required
        :disabled="sending"
      />
      <input
        v-if="mode === 'register'"
        v-model="displayName"
        type="text"
        class="form-input"
        placeholder="Pseudo (optionnel)"
        autocomplete="nickname"
        maxlength="80"
        :disabled="sending"
      />
      <input
        v-if="mode === 'password' || mode === 'register'"
        v-model="password"
        type="password"
        class="form-input"
        :placeholder="mode === 'register' ? 'Mot de passe (8 caractères min)' : 'Mot de passe'"
        :autocomplete="mode === 'register' ? 'new-password' : 'current-password'"
        required
        :disabled="sending"
      />
      <label v-if="mode !== 'register'" class="form-checkbox">
        <input v-model="rememberMe" type="checkbox" :disabled="sending" />
        Rester connecté sur cet appareil
      </label>
      <button type="submit" class="btn btn-primary" :disabled="sending || !email || ((mode === 'password' || mode === 'register') && !password)">
        {{ sending ? 'Patiente…' : (mode === 'magic' ? 'Recevoir mon lien magique' : mode === 'register' ? 'Créer mon compte' : 'Se connecter') }}
      </button>
    </form>
    <div v-if="message" class="auth-message" :class="messageType" role="status" aria-live="polite">
      {{ message }}
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { requestUserMagicLink, loginUser, registerUser, setUserToken, notifyAuthChanged } from '../api.js'

const props = defineProps({
  redirect: { type: String, default: '/carte' },
})
const emit = defineEmits(['sent', 'authenticated'])

const mode = ref('magic')
const email = ref('')
const displayName = ref('')
const password = ref('')
const rememberMe = ref(true)
const sending = ref(false)
const message = ref('')
const messageType = ref('')

async function submit() {
  sending.value = true
  message.value = ''
  try {
    if (mode.value === 'magic') {
      const res = await requestUserMagicLink(email.value, rememberMe.value, props.redirect)
      message.value = res.message || 'Si votre email est valide, vous recevrez un lien de connexion.'
      messageType.value = 'success'
      emit('sent')
    } else if (mode.value === 'register') {
      if (password.value.length < 8) {
        message.value = 'Mot de passe trop court (8 caractères minimum).'
        messageType.value = 'error'
        return
      }
      const res = await registerUser({ email: email.value, password: password.value, display_name: displayName.value.trim() || undefined })
      if (res.success) {
        // Inscription OK → login direct dans la foulée
        const login = await loginUser({ email: email.value, password: password.value, rememberMe: true })
        if (login.success && login.token) {
          setUserToken(login.token, true)
          notifyAuthChanged()
          emit('authenticated', login.data)
          return
        }
        message.value = 'Compte créé ! Connecte-toi avec ton mot de passe.'
        messageType.value = 'success'
        mode.value = 'password'
        password.value = ''
      } else {
        message.value = res.error || 'Inscription impossible pour le moment.'
        messageType.value = 'error'
      }
    } else {
      const res = await loginUser({ email: email.value, password: password.value, rememberMe: rememberMe.value })
      if (res.success && res.token) {
        setUserToken(res.token, rememberMe.value)
        notifyAuthChanged()
        emit('authenticated', res.data)
      } else {
        message.value = res.error || 'Email ou mot de passe incorrect.'
        messageType.value = 'error'
      }
    }
  } catch (e) {
    message.value = mode.value === 'magic' ? 'Erreur lors de l\'envoi.' : 'Erreur de connexion.'
    messageType.value = 'error'
  } finally {
    sending.value = false
  }
}
</script>

<style scoped>
.auth-form__tabs { display: flex; gap: 8px; margin-bottom: 12px; }
.auth-form__tab {
  flex: 1; padding: 8px; border-radius: var(--r-full);
  font-size: 0.9rem; font-weight: 600; color: var(--c-text-2);
  background: var(--c-cream-2); transition: all 0.2s;
}
.auth-form__tab.active { background: var(--c-green); color: var(--c-white); }
.auth-form__intro { font-size: 0.95rem; color: var(--c-text-2); }
.auth-form__form { display: flex; flex-direction: column; gap: 12px; margin-top: 12px; }
.form-checkbox { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; cursor: pointer; }
.form-checkbox input { width: 18px; height: 18px; }
.auth-message { margin-top: 12px; padding: 10px; border-radius: 8px; font-size: 0.9rem; }
.auth-message.success { background: #e6f4ea; color: #1e7e34; }
.auth-message.error { background: #fdecea; color: #c5221f; }
</style>
