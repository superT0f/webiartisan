<template>
  <div class="page container section text-center">
    <div class="result-card card">
      <div class="result-icon">✅</div>
      <h1>Abonnement activé !</h1>
      <p class="text-muted">
        Merci pour votre confiance. Votre compte artisan est maintenant Premium.
      </p>
      <p v-if="loading" class="text-muted">Mise à jour de votre espace…</p>
      <RouterLink to="/espace" class="btn btn-primary">
        Retour à mon espace
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { getSubscriptionStatus } from '../api.js'

const router = useRouter()
const loading = ref(true)

onMounted(async () => {
  try {
    await getSubscriptionStatus()
  } catch (e) {
    console.error('Erreur rafraîchissement abonnement', e)
  } finally {
    loading.value = false
  }
  setTimeout(() => router.push('/espace'), 3000)
})
</script>

<style scoped>
.result-card {
  max-width: 480px;
  margin: 60px auto;
  padding: 48px 32px;
  text-align: center;
}
.result-icon {
  font-size: 3rem;
  margin-bottom: 16px;
}
.result-card h1 {
  font-size: 1.6rem;
  margin-bottom: 12px;
}
.result-card p {
  margin-bottom: 24px;
}
</style>
