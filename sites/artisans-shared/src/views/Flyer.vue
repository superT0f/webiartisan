<template>
  <div class="flyer-page">
    <!-- Showcase header and helper section (hidden during print) -->
    <div class="no-print hero-section">
      <div class="container text-center">
        <RouterLink to="/" class="back-link">← Retour à l'accueil</RouterLink>
        <h1>Kit de Communication Local</h1>
        <p class="hero-subtitle">Imprimez nos supports et aidez-nous à dynamiser le commerce local à {{ cityName }} !</p>
      </div>
    </div>

    <!-- Info cards for users & artisans (hidden during print) -->
    <div class="no-print container info-grid">
      <div class="info-card user-card">
        <div class="card-icon">👥</div>
        <h3>Pour les Habitants & Usagers</h3>
        <p>Aidez vos voisins à trouver des artisans de confiance ! Téléchargez, imprimez et déposez ces flyers dans votre commerce de quartier, boulangerie ou panneau d'affichage municipal.</p>
      </div>
      <div class="info-card artisan-card">
        <div class="card-icon">🛠️</div>
        <h3>Pour les Artisans Locaux</h3>
        <p>Valorisez votre présence sur l'annuaire de la ville. Affichez le poster A4 dans votre atelier ou véhicule, ou distribuez les mini-cartes à vos clients pour encourager les avis locaux.</p>
      </div>
    </div>

    <!-- Controls section (hidden during print) -->
    <div class="no-print controls container">
      <div class="selector-box">
        <label class="selector-label">Format d'impression :</label>
        <div class="format-buttons">
          <button 
            v-for="format in formats" 
            :key="format.id"
            :class="['format-btn', { active: selectedFormat === format.id }]"
            @click="selectedFormat = format.id"
          >
            <span class="btn-emoji">{{ format.emoji }}</span>
            <div class="btn-text">
              <strong>{{ format.label }}</strong>
              <span>{{ format.desc }}</span>
            </div>
          </button>
        </div>
      </div>

      <div class="actions-box">
        <button class="btn btn-primary btn-lg" @click="printPage">
          🖨️ Imprimer la sélection
        </button>
        <p class="print-help">
          💡 <strong>Conseil d'impression :</strong> Pour un rendu parfait, dans vos options d'impression, activez <strong>"Images d'arrière-plan" (Background graphics)</strong> et réglez les marges sur <strong>"Aucune"</strong>.
        </p>
      </div>
    </div>

    <!-- Print area layout -->
    <div :class="['print-area', `show-format-${selectedFormat}`]">
      
      <!-- Format A4 complet (Affiche vitrine / mairie) -->
      <div class="page a4-page">
        <div class="a4-content">
          <div class="a4-header">
            <span class="a4-eyebrow">Annuaire Officiel & Gratuit</span>
            <h1>Artisans de <span class="text-green">{{ cityName }}</span></h1>
            <p class="a4-subtitle">Plombiers, Électriciens, Peintres, Jardiniers, Maçons...</p>
          </div>

          <div class="a4-body">
            <div class="a4-features">
              <div class="a4-feature">
                <span class="feat-icon">📍</span>
                <div>
                  <strong>Proximité Garantie</strong>
                  <p>Uniquement des artisans qualifiés de {{ cityName }} et ses environs directs.</p>
                </div>
              </div>
              <div class="a4-feature">
                <span class="feat-icon">🤝</span>
                <div>
                  <strong>Relation Directe</strong>
                  <p>Pas de commission ni d'intermédiaires. Vous traitez en direct avec l'artisan.</p>
                </div>
              </div>
              <div class="a4-feature">
                <span class="feat-icon">⭐</span>
                <div>
                  <strong>Recommandations Locales</strong>
                  <p>Consultez les avis authentiques de vos voisins avant de choisir.</p>
                </div>
              </div>
            </div>

            <div class="a4-qr-box">
              <img :src="qrUrl" alt="QR Code" class="a4-qr" />
              <div class="a4-qr-text">
                <p>Scannez le QR Code pour y accéder :</p>
                <strong>{{ domain }}</strong>
              </div>
            </div>
          </div>

          <div class="a4-footer">
            <p>Soutenez l'économie de proximité • Une initiative collaborative et citoyenne</p>
            <span class="footer-platform">Développé avec ❤️ par WebIArtisan</span>
          </div>
        </div>
      </div>

      <!-- Format A5 (2 flyers par page A4) -->
      <div class="page a5-page">
        <div class="a5-wrapper">
          <!-- Flyer A5 du haut -->
          <div class="a5-half top-half">
            <div class="a5-content">
              <div class="a5-header">
                <span class="a5-badge">📍 100% Local</span>
                <h2>Trouvez un artisan à <strong>{{ cityName }}</strong></h2>
              </div>
              <div class="a5-body">
                <div class="a5-info">
                  <p class="a5-pitch">Besoin d'un plombier, électricien ou peintre de confiance ? Accédez à notre annuaire local sans frais ni inscription.</p>
                  <ul class="a5-list">
                    <li>✓ Annuaire gratuit</li>
                    <li>✓ Contact direct</li>
                    <li>✓ Recommandations locales</li>
                  </ul>
                </div>
                <div class="a5-qr-block">
                  <img :src="qrUrl" alt="QR Code" class="a5-qr" />
                  <span class="a5-url">{{ domain }}</span>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Ligne de coupe -->
          <div class="cutting-line">
            <span>✂ Découper ici</span>
          </div>

          <!-- Flyer A5 du bas -->
          <div class="a5-half bottom-half">
            <div class="a5-content">
              <div class="a5-header">
                <span class="a5-badge">📍 100% Local</span>
                <h2>Trouvez un artisan à <strong>{{ cityName }}</strong></h2>
              </div>
              <div class="a5-body">
                <div class="a5-info">
                  <p class="a5-pitch">Besoin d'un plombier, électricien ou peintre de confiance ? Accédez à notre annuaire local sans frais ni inscription.</p>
                  <ul class="a5-list">
                    <li>✓ Annuaire gratuit</li>
                    <li>✓ Contact direct</li>
                    <li>✓ Recommandations locales</li>
                  </ul>
                </div>
                <div class="a5-qr-block">
                  <img :src="qrUrl" alt="QR Code" class="a5-qr" />
                  <span class="a5-url">{{ domain }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Format mini-flyers / cartes de visites (à découper, 8 par page A4) -->
      <div class="page cards-page">
        <div class="cards-grid">
          <div class="mini-card" v-for="n in 8" :key="n">
            <div class="card-top">
              <div class="card-logo">🏠 WebIArtisan</div>
              <div class="card-title">Trouvez un artisan à<br><strong>{{ cityName }}</strong></div>
            </div>
            <div class="card-middle">
              <img :src="qrUrl" alt="QR Code" class="card-qr" />
              <div class="card-url">{{ domain }}</div>
            </div>
            <div class="card-bottom">
              <span>📍 Local</span> • <span>🆓 Gratuit</span> • <span>🤝 Direct</span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { CITY_NAME } from '../api.js'

const cityName = CITY_NAME
const domain = typeof window !== 'undefined' ? window.location.host : ''
const fullUrl = typeof window !== 'undefined' ? window.location.origin : ''

const selectedFormat = ref('all')

const formats = [
  { id: 'all', emoji: '📚', label: 'Tous les formats', desc: 'Imprime tout le kit' },
  { id: 'a4', emoji: '📄', label: 'Affiche A4', desc: 'Vitrine, mairie ou local' },
  { id: 'a5', emoji: '✂️', label: 'Flyer A5', desc: 'Commerces (2 par page)' },
  { id: 'cards', emoji: '📇', label: 'Mini Cartes', desc: 'Commerces (8 par page)' }
]

// API pour générer le QR Code
const qrUrl = computed(() => {
  if (!fullUrl) return ''
  return `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(fullUrl)}`
})

function printPage() {
  window.print()
}
</script>

<style scoped>
/* ============================================================
   Aesthetics for Screen View (Showcase / Dashboard)
   ============================================================ */
.flyer-page {
  background: var(--c-cream-2);
  min-height: 100vh;
  padding-bottom: 80px;
}

.hero-section {
  background: linear-gradient(135deg, var(--c-green-dark), var(--c-green));
  color: white;
  padding: 60px 0 40px;
  text-align: center;
  margin-bottom: 40px;
}
.hero-section h1 {
  font-size: 2.8rem;
  margin: 15px 0 10px;
  color: var(--c-cream);
}
.hero-subtitle {
  font-size: 1.2rem;
  opacity: 0.9;
  max-width: 700px;
  margin: 0 auto;
}
.back-link {
  color: var(--c-gold);
  font-weight: 600;
  font-size: 1rem;
  transition: color 0.2s;
}
.back-link:hover {
  color: white;
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 30px;
  margin-bottom: 40px;
}
.info-card {
  background: white;
  border-radius: var(--r-lg);
  border: 1px solid var(--c-border);
  padding: 30px;
  box-shadow: 0 10px 30px var(--c-shadow);
  display: flex;
  flex-direction: column;
  gap: 15px;
}
.card-icon {
  font-size: 2.5rem;
}
.info-card h3 {
  color: var(--c-green-dark);
  font-size: 1.35rem;
}
.info-card p {
  color: var(--c-text-2);
  font-size: 0.95rem;
  line-height: 1.6;
}

.controls {
  background: white;
  border-radius: var(--r-lg);
  border: 1px solid var(--c-border);
  padding: 30px;
  margin-bottom: 40px;
  box-shadow: 0 10px 30px var(--c-shadow);
  display: flex;
  flex-direction: column;
  gap: 30px;
}
.selector-box {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.selector-label {
  font-weight: 700;
  font-family: var(--font-title);
  color: var(--c-green-dark);
}
.format-buttons {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 15px;
}
.format-btn {
  background: var(--c-cream);
  border: 2px solid var(--c-border);
  border-radius: var(--r-md);
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 14px;
  text-align: left;
  transition: all 0.25s var(--ease-spring);
}
.format-btn:hover {
  border-color: var(--c-green-light);
  transform: translateY(-2px);
}
.format-btn.active {
  border-color: var(--c-green);
  background: var(--c-green);
  color: white;
}
.btn-emoji {
  font-size: 1.8rem;
}
.btn-text {
  display: flex;
  flex-direction: column;
}
.btn-text strong {
  font-size: 0.95rem;
}
.btn-text span {
  font-size: 0.75rem;
  opacity: 0.8;
}

.actions-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 30px;
  border-top: 1px solid var(--c-border);
  padding-top: 20px;
}
.print-help {
  flex: 1;
  font-size: 0.85rem;
  color: var(--c-text-2);
  max-width: 500px;
}

.print-area {
  display: flex;
  flex-direction: column;
  gap: 40px;
  align-items: center;
}

.page {
  background: white;
  box-shadow: 0 15px 40px rgba(0,0,0,0.1);
  width: 210mm;
  height: 297mm;
  position: relative;
  overflow: hidden;
}

/* ============================================================
   A4 AFFICHE DESIGN
   ============================================================ */
.a4-content {
  display: flex;
  flex-direction: column;
  height: 100%;
  border: 10mm solid var(--c-green);
  padding: 15mm;
  justify-content: space-between;
}
.a4-header {
  text-align: center;
  margin-top: 5mm;
}
.a4-eyebrow {
  display: inline-block;
  background: var(--c-gold);
  color: white;
  padding: 6px 20px;
  border-radius: var(--r-full);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 2px;
  margin-bottom: 8mm;
  font-size: 14pt;
}
.a4-header h1 {
  font-size: 38pt;
  line-height: 1.15;
  margin-bottom: 5mm;
  color: var(--c-text);
}
.a4-subtitle {
  font-size: 18pt;
  color: var(--c-text-2);
}

.a4-body {
  display: flex;
  flex-direction: column;
  gap: 12mm;
  margin: 10mm 0;
}
.a4-features {
  display: flex;
  flex-direction: column;
  gap: 8mm;
  margin-left: 10mm;
}
.a4-feature {
  display: flex;
  align-items: center;
  gap: 8mm;
}
.feat-icon {
  font-size: 28pt;
  background: var(--c-cream);
  width: 20mm; height: 20mm;
  display: flex; justify-content: center; align-items: center;
  border-radius: 50%;
  flex-shrink: 0;
}
.a4-feature strong { font-size: 16pt; display: block; color: var(--c-green-dark); margin-bottom: 1.5mm; }
.a4-feature p { font-size: 13pt; color: var(--c-text-2); }

.a4-qr-box {
  margin: 5mm auto 0;
  background: var(--c-cream-2);
  border-radius: var(--r-lg);
  padding: 8mm 12mm;
  display: flex;
  align-items: center;
  gap: 8mm;
  border: 1px solid var(--c-border);
  width: fit-content;
}
.a4-qr {
  width: 40mm;
  height: 40mm;
}
.a4-qr-text {
  font-size: 14pt;
  color: var(--c-text);
}
.a4-qr-text strong {
  display: block;
  font-size: 20pt;
  color: var(--c-green);
  margin-top: 2mm;
}

.a4-footer {
  text-align: center;
  font-size: 11pt;
  color: var(--c-text-3);
  padding-top: 5mm;
  border-top: 1px solid var(--c-border);
}
.footer-platform {
  display: inline-block;
  font-size: 9pt;
  font-weight: 700;
  opacity: 0.8;
  margin-top: 1mm;
}

/* ============================================================
   A5 FLYERS DESIGN (2 per page)
   ============================================================ */
.a5-wrapper {
  display: flex;
  flex-direction: column;
  height: 100%;
  justify-content: space-between;
}
.a5-half {
  height: 144mm;
  padding: 10mm;
  box-sizing: border-box;
}
.cutting-line {
  height: 9mm;
  border-top: 1px dashed var(--c-text-3);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 9pt;
  color: var(--c-text-3);
  background: var(--c-cream-2);
}
.a5-content {
  border: 3px solid var(--c-green);
  border-radius: var(--r-md);
  padding: 8mm;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  box-sizing: border-box;
}
.a5-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.a5-badge {
  background: var(--c-gold);
  color: white;
  font-weight: 700;
  font-size: 9pt;
  padding: 3px 10px;
  border-radius: var(--r-full);
}
.a5-header h2 {
  font-size: 16pt;
  color: var(--c-text);
  margin-left: 5mm;
  text-align: right;
  flex: 1;
}
.a5-header h2 strong {
  color: var(--c-green);
}
.a5-body {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8mm;
  margin: 5mm 0;
}
.a5-info {
  flex: 1;
}
.a5-pitch {
  font-size: 11pt;
  line-height: 1.4;
  color: var(--c-text-2);
  margin-bottom: 4mm;
}
.a5-list {
  list-style: none;
  font-weight: 600;
  font-size: 10.5pt;
  color: var(--c-green-dark);
}
.a5-list li {
  margin-bottom: 2mm;
}
.a5-qr-block {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2mm;
  flex-shrink: 0;
}
.a5-qr {
  width: 32mm;
  height: 32mm;
}
.a5-url {
  font-size: 9pt;
  font-weight: 700;
  color: var(--c-text);
}

/* ============================================================
   BUSINESS CARDS (8 per page)
   ============================================================ */
.cards-page {
  padding: 10mm;
}
.cards-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  grid-template-rows: repeat(4, 1fr);
  height: 100%;
}
.mini-card {
  border: 1px dashed var(--c-border);
  display: flex;
  flex-direction: column;
  padding: 6mm;
  text-align: center;
  justify-content: space-between;
}
.card-logo {
  font-weight: 800;
  color: var(--c-green);
  font-size: 11pt;
  margin-bottom: 1.5mm;
}
.card-title {
  font-size: 12pt;
  line-height: 1.25;
}
.card-title strong {
  color: var(--c-green-dark);
  font-size: 14pt;
}
.card-middle {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2mm;
  margin: 3mm 0;
}
.card-qr {
  width: 24mm;
  height: 24mm;
}
.card-url {
  font-size: 9pt;
  font-weight: 700;
  color: var(--c-text);
}
.card-bottom {
  font-size: 8.5pt;
  font-weight: 600;
  color: var(--c-text-2);
}

/* ============================================================
   Interactive Format Display logic (Screen only)
   ============================================================ */
.show-format-a4 .page:not(.a4-page),
.show-format-a5 .page:not(.a5-page),
.show-format-cards .page:not(.cards-page) {
  display: none;
}

/* ============================================================
   PRINT MEDIA QUERY RULES
   ============================================================ */
@media print {
  @page { margin: 0; size: A4; }
  body { background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .no-print, .hero-section, .info-grid, .controls { display: none !important; }
  .flyer-page { padding: 0; background: white; min-height: 0; }
  .print-area { gap: 0; display: block; }
  .page {
    box-shadow: none;
    page-break-after: always;
    display: block !important;
  }
  
  /* Filter pages based on print selection */
  .show-format-a4 .page:not(.a4-page) { display: none !important; }
  .show-format-a5 .page:not(.a5-page) { display: none !important; }
  .show-format-cards .page:not(.cards-page) { display: none !important; }
}

/* Responsive screen layout */
@media (max-width: 768px) {
  .info-grid {
    grid-template-columns: 1fr;
    gap: 20px;
  }
  .controls {
    padding: 20px;
  }
  .actions-box {
    flex-direction: column;
    align-items: stretch;
    text-align: center;
  }
  .page {
    /* Scale down A4 size container on small screens to fit preview */
    transform: scale(0.42);
    transform-origin: top center;
    height: 125mm;
    margin-bottom: -150mm;
  }
  .print-area {
    margin-bottom: 160mm;
  }
}
</style>
