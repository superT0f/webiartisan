<script setup>
import { ref } from 'vue'
import { betaSignup, shareText, CITY_NAME } from '../api.js'

const PLAY_TEST_URL = 'https://play.google.com/apps/testing/tech.prigent.webiartisan'

const email = ref('')
const loading = ref(false)
const done = ref(false)
const error = ref('')

async function submit() {
  const value = email.value.trim()
  if (!value || loading.value) return
  loading.value = true
  error.value = ''
  const res = await betaSignup(value, CITY_NAME)
  loading.value = false
  if (res.success) {
    done.value = true
  } else {
    error.value = res.error === 'Email invalide'
      ? 'Cette adresse email semble invalide.'
      : (res.error || 'Une erreur est survenue, réessaie dans un instant.')
  }
}

function share() {
  shareText(
    `Rejoins la bêta fermée de WebiArtisan : la carte de ${CITY_NAME} devient un jeu (Android) 🥖\n${window.location.origin}/beta`,
    'Bêta WebiArtisan',
  )
}
</script>

<template>
  <div class="beta-page">
    <section class="beta-hero card">
      <img src="/logo-baguette.svg" alt="WebiArtisan" class="beta-logo" />
      <h1>Rejoins la bêta fermée</h1>
      <p class="tagline">
        Ta ville devient un jeu. Gratuit, pour les habitants de {{ CITY_NAME }}.
      </p>
      <div class="beta-duo">
        <img src="/avatar/player-512.png" alt="La mascotte artisan" class="beta-mascot" />
        <img src="/boss/affamer-512.png" alt="Affamer de Gaffe, le méchant" class="beta-boss" />
      </div>
    </section>

    <section class="beta-features">
      <div class="feature card"><span>🗑️</span><p>Ramasse les déchets de ta ville et gagne de l'XP</p></div>
      <div class="feature card"><span>🎩</span><p>Bats <strong>Affamer de Gaffe</strong>, le magnat anti-artisans</p></div>
      <div class="feature card"><span>📷</span><p>Photographie tes lieux préférés et partage-les</p></div>
    </section>

    <section class="beta-steps card">
      <h2>Comment ça marche ?</h2>
      <ol>
        <li><strong>Laisse ton email</strong> ci-dessous — celui de ton <strong>compte Play Store</strong> (Play Store → touche ta photo de profil → ton email s'affiche, tu n'as qu'à le copier).</li>
        <li>On t'ajoute à la liste des testeurs Google <strong>sous 24-48 h</strong> (fait à la main, avec amour).</li>
        <li>Tu reçois le lien <strong>« Rejoindre le test »</strong> par email.</li>
        <li>Tu installes l'app depuis le <strong>Play Store</strong> et tu joues.</li>
      </ol>
    </section>

    <section class="beta-form card">
      <template v-if="!done">
        <h2>Je veux ma place 🎟️</h2>
        <form @submit.prevent="submit">
          <input
            v-model="email"
            type="email"
            required
            placeholder="ton@email.fr"
            class="beta-input"
            autocomplete="email"
          />
          <button type="submit" class="btn btn-primary beta-submit" :disabled="loading">
            {{ loading ? 'Inscription…' : 'Devenir bêta-testeur' }}
          </button>
        </form>
        <p v-if="error" class="beta-error">{{ error }}</p>
      </template>
      <template v-else>
        <div class="beta-done">
          <span class="beta-done-icon">🎉</span>
          <h2>Bienvenue à bord !</h2>
          <p>Vérifie ta boîte mail <strong>(et les spams)</strong> — on t'ajoute sous 24-48 h, puis tu recevras le lien d'installation.</p>
        </div>
      </template>
      <p class="beta-android">⚠️ <strong>Android uniquement</strong> pour le moment — iPhone arrive plus tard.</p>
      <a :href="PLAY_TEST_URL" target="_blank" rel="noopener" class="beta-already">
        Déjà inscrit à la bêta ? → Rejoindre le test sur le Play Store
      </a>
    </section>

    <button type="button" class="btn btn-outline beta-share" @click="share">
      ↗ Partager la bêta avec un voisin
    </button>
  </div>
</template>

<style scoped>
.beta-page {
  max-width: 560px;
  margin: 0 auto;
  padding: 24px 16px 48px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.beta-hero { text-align: center; padding: 28px 20px 20px; }
.beta-logo { width: 64px; height: 64px; }
.beta-hero h1 { margin: 12px 0 6px; font-size: 1.6rem; }
.tagline { color: var(--c-text-2); margin: 0; }
.beta-mascot { width: 140px; filter: drop-shadow(0 6px 12px rgba(0,0,0,0.25)); }
.beta-duo {
  margin-top: 12px;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  gap: 18px;
}
.beta-boss { width: 110px; filter: drop-shadow(0 6px 12px rgba(0,0,0,0.3)); }

.beta-features { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.feature { padding: 14px 10px; text-align: center; }
.feature span { font-size: 1.6rem; display: block; margin-bottom: 6px; }
.feature p { margin: 0; font-size: 0.8rem; color: var(--c-text-2); }
@media (max-width: 480px) { .beta-features { grid-template-columns: 1fr; } }

.beta-steps { padding: 20px; }
.beta-steps h2 { margin: 0 0 10px; font-size: 1.1rem; }
.beta-steps ol { margin: 0; padding-left: 22px; display: flex; flex-direction: column; gap: 8px; }
.beta-steps li { font-size: 0.92rem; }

.beta-form { padding: 20px; text-align: center; }
.beta-form h2 { margin: 0 0 14px; font-size: 1.15rem; }
.beta-form form { display: flex; flex-direction: column; gap: 10px; }
.beta-input {
  padding: 14px 16px;
  font-size: 1.05rem;
  border: 2px solid var(--c-border);
  border-radius: 999px;
  text-align: center;
}
.beta-input:focus { border-color: var(--c-green); outline: none; }
.beta-submit { padding: 14px; font-size: 1.05rem; border-radius: 999px; }
.beta-error { color: #b71c1c; font-size: 0.85rem; margin: 8px 0 0; }
.beta-done-icon { font-size: 2.4rem; display: block; }
.beta-done p { color: var(--c-text-2); font-size: 0.92rem; }
.beta-android { margin: 16px 0 0; font-size: 0.85rem; color: var(--c-text-2); }
.beta-already { display: inline-block; margin-top: 10px; font-size: 0.85rem; color: var(--c-green); font-weight: 600; }
.beta-share { align-self: center; }
</style>
