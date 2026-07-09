<template>
  <div class="page container section text-center">
    <div class="result-card card">
      <div class="result-icon">🙁</div>
      <h1>Paiement annulé</h1>
      <p class="text-muted">
        Votre paiement a été annulé. Vous pouvez réessayer quand vous le souhaitez.
      </p>
      <RouterLink to="/espace" class="btn btn-primary">
        Retour à mon espace
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
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
