<template>
  <footer class="footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <div class="footer-logo">
            <span>🏠</span>
            <span>Artisans {{ CITY_NAME }}</span>
          </div>
          <p class="footer-desc">
            L'annuaire des artisans locaux de {{ CITY_NAME }} ({{ CITY_CP }}).
            Gratuit pour les habitants et les artisans.
          </p>
          <div class="footer-badges">
            <span class="f-badge">🇫🇷 100% Français</span>
            <span class="f-badge">🆓 Gratuit</span>
            <span class="f-badge">🔓 Open Source</span>
            <span class="f-badge">📍 Local</span>
          </div>
        </div>

        <div class="footer-col">
          <h4>Navigation</h4>
          <ul>
            <li><RouterLink to="/">Accueil</RouterLink></li>
            <li><RouterLink to="/inscrire">Inscrire mon entreprise</RouterLink></li>
            <li><RouterLink to="/espace">Espace artisan</RouterLink></li>
            <li><RouterLink to="/flyers">🖨️ Flyers & Plaquettes</RouterLink></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>Services Locaux</h4>
          <ul>
            <li><a href="/annuaire#meteo">Météo locale</a></li>
            <li><a href="/annuaire#horaires">Horaires</a></li>
            <li><a href="/annuaire#bus">Transports en commun</a></li>
            <li><a v-if="CITY_SLUG" :href="'https://www.' + CITY_SLUG + '.fr'" target="_blank" rel="noopener">Site de la ville ↗</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>À propos</h4>
          <ul>
            <li><a href="https://webiartisan.prigent.tech" target="_blank" rel="noopener">WebiArtisan ↗</a></li>
            <li><a href="mailto:webiartisan@prigent.tech">Nous contacter</a></li>
            <li><a href="https://github.com/superT0f/webiartisan" target="_blank" rel="noopener">GitHub ↗</a></li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <span>
          © {{ year }} WebiArtisan · {{ CITY_NAME }} · {{ CITY_CP }} · v{{ APP_VERSION }}
          · <a class="app-link" href="https://appdistribution.firebase.dev/i/1297b31002780ac2" target="_blank" rel="noopener">📱 Installer l'app Android ↗</a>
        </span>
        <span>Fait avec ❤️ en Seine-et-Marne</span>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { CITY_NAME, CITY_CP, CITY_SLUG, APP_VERSION } from '../api.js'

const year = new Date().getFullYear()
</script>

<style scoped>
.footer {
  background: var(--c-green-dark);
  color: rgba(255,255,255,0.85);
  padding: 56px 0 24px;
  margin-top: 80px;
}
.footer-grid {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 40px;
  margin-bottom: 48px;
}
.footer-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  font-family: var(--font-title);
  font-weight: 800;
  font-size: 1.1rem;
  color: white;
  margin-bottom: 14px;
}
.footer-desc {
  font-size: 0.88rem;
  line-height: 1.65;
  margin-bottom: 16px;
  color: rgba(255,255,255,0.7);
}
.footer-badges { display: flex; flex-wrap: wrap; gap: 8px; }
.f-badge {
  background: rgba(255,255,255,0.12);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: var(--r-full);
  padding: 4px 12px;
  font-size: 0.78rem;
  color: rgba(255,255,255,0.9);
}
.footer-col h4 {
  font-family: var(--font-title);
  font-size: 0.85rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: rgba(255,255,255,0.5);
  margin-bottom: 16px;
}
.footer-col ul li { margin-bottom: 10px; }
.footer-col ul li a {
  font-size: 0.9rem;
  color: rgba(255,255,255,0.75);
  transition: color 0.2s;
}
.footer-col ul li a:hover { color: var(--c-gold); }
.app-link {
  color: var(--c-gold);
  text-decoration: underline;
  margin-left: 4px;
}
.app-link:hover { color: white; }
.footer-bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 24px;
  border-top: 1px solid rgba(255,255,255,0.15);
  font-size: 0.82rem;
  color: rgba(255,255,255,0.45);
}
@media (max-width: 768px) {
  .footer-grid { grid-template-columns: 1fr 1fr; gap: 28px; }
  .footer-bottom { flex-direction: column; gap: 8px; text-align: center; }
}
@media (max-width: 480px) {
  .footer-grid { grid-template-columns: 1fr; }
}
</style>
