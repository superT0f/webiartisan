<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { resetPassword } from '../api.js'

const route = useRoute()
const router = useRouter()

const password = ref('')
const confirm = ref('')
const loading = ref(false)
const message = ref('')
const messageType = ref('info')
const success = ref(false)

async function submit() {
  message.value = ''
  if (password.value.length < 8) {
    message.value = 'Le mot de passe doit faire au moins 8 caractères.'
    messageType.value = 'error'
    return
  }
  if (password.value !== confirm.value) {
    message.value = 'Les mots de passe ne correspondent pas.'
    messageType.value = 'error'
    return
  }
  loading.value = true
  try {
    const res = await resetPassword(route.query.token, password.value)
    if (res.success) {
      success.value = true
      message.value = res.message
      messageType.value = 'success'
      setTimeout(() => router.push('/profil'), 2000)
    } else {
      message.value = res.error || 'Erreur lors de la réinitialisation.'
      messageType.value = 'error'
    }
  } catch (e) {
    message.value = 'Erreur réseau.'
    messageType.value = 'error'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="page">
    <div class="card">
      <h1>Réinitialiser le mot de passe</h1>
      <form v-if="!success" @submit.prevent="submit">
        <label>
          Nouveau mot de passe
          <input v-model="password" type="password" minlength="8" required />
        </label>
        <label>
          Confirmer le mot de passe
          <input v-model="confirm" type="password" minlength="8" required />
        </label>
        <button type="submit" :disabled="loading">
          {{ loading ? 'Enregistrement…' : 'Enregistrer' }}
        </button>
      </form>
      <p v-if="message" :class="['message', messageType]">{{ message }}</p>
    </div>
  </div>
</template>

<style scoped>
.page { max-width: 480px; margin: 40px auto; padding: 0 16px; }
.card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
h1 { font-size: 1.4rem; margin-bottom: 16px; }
label { display: block; margin-bottom: 12px; font-weight: 500; }
input { width: 100%; padding: 10px; margin-top: 4px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
button { width: 100%; padding: 12px; background: var(--c-green); color: #fff; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
button:disabled { opacity: 0.7; }
.message { margin-top: 16px; padding: 10px; border-radius: 8px; }
.message.success { background: #e6f4ea; color: #1e7e34; }
.message.error { background: #fdecea; color: #c5221f; }
</style>
