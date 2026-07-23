<template>
  <div class="container section dashboard">
    <div class="dashboard-header">
      <h1>Mon quartier</h1>
      <p class="text-muted">Tes POI : change leur photo sur la carte, ou revendique-en de nouveaux.</p>
    </div>

    <p v-if="error" class="poi-error">{{ error }}</p>
    <p v-if="success" class="poi-success">{{ success }}</p>

    <section class="dashboard-section card">
      <h2>Mes POI</h2>
      <ul v-if="owned.length" class="poi-list">
        <li v-for="p in owned" :key="p.id" class="poi-item">
          <img v-if="p.image_url" :src="resolveAvatarUrl(p.image_url)" class="poi-thumb" alt="" />
          <span v-else class="poi-thumb poi-thumb--empty">📍</span>
          <span class="poi-name">{{ p.name }}</span>
          <label class="btn btn-outline btn-sm">
            {{ uploadingId === p.id ? 'Envoi…' : 'Changer l\'image' }}
            <input type="file" accept="image/jpeg,image/png,image/webp" hidden @change="onUpload(p, $event)" />
          </label>
          <button v-if="p.image_url" type="button" class="btn btn-outline btn-sm" @click="onDeleteImage(p)">Retirer</button>
        </li>
      </ul>
      <p v-else>Aucun POI pour l'instant — revendique-en un ci-dessous !</p>
    </section>

    <section class="dashboard-section card">
      <h2>Mes revendications</h2>
      <ul v-if="claims.length" class="poi-list">
        <li v-for="c in claims" :key="c.id" class="poi-item">
          <span class="poi-name">{{ c.poi_name }}</span>
          <span class="poi-status" :class="`poi-status--${c.status}`">
            {{ c.status === 'pending' ? '⏳ En attente' : c.status === 'approved' ? '✅ Approuvée' : '❌ Rejetée' }}
          </span>
        </li>
      </ul>
      <p v-else>Aucune revendication.</p>
    </section>

    <section class="dashboard-section card">
      <h2>Revendiquer un POI</h2>
      <ul v-if="claimable.length" class="poi-list">
        <li v-for="p in claimable" :key="p.id" class="poi-item">
          <span class="poi-name">{{ p.name }} <small class="text-muted">{{ p.type }}</small></span>
          <button type="button" class="btn btn-primary btn-sm" :disabled="claimingId === p.id" @click="onClaim(p)">
            {{ claimingId === p.id ? 'Envoi…' : 'Revendiquer' }}
          </button>
        </li>
      </ul>
      <p v-else>Tous les POI de ta ville sont déjà attribués.</p>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import {
  getArtisanToken, fetchClaimablePois, fetchMyPoiClaims, claimPoi,
  uploadPoiImage, deletePoiImage, resolveAvatarUrl,
} from '../../api.js'

const owned = ref([])
const claims = ref([])
const claimable = ref([])
const error = ref('')
const success = ref('')
const uploadingId = ref(null)
const claimingId = ref(null)

const token = getArtisanToken() || ''

async function load() {
  error.value = ''
  try {
    const [mine, all] = await Promise.all([
      fetchMyPoiClaims(token),
      fetchClaimablePois(token),
    ])
    if (mine.success) {
      owned.value = mine.data.owned || []
      claims.value = mine.data.claims || []
    }
    if (all.success) claimable.value = all.data || []
  } catch (e) {
    error.value = 'Erreur de chargement.'
  }
}

async function onClaim(p) {
  claimingId.value = p.id
  error.value = ''
  success.value = ''
  const res = await claimPoi(token, p.id)
  if (res.success) {
    success.value = `Revendication envoyée pour « ${p.name} » — en attente de validation.`
    await load()
  } else {
    error.value = res.error === 'already_owned' ? 'Ce POI est déjà attribué.' : (res.error || 'Revendication impossible')
  }
  claimingId.value = null
}

async function onUpload(p, event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return
  uploadingId.value = p.id
  error.value = ''
  success.value = ''
  const res = await uploadPoiImage(token, p.id, file)
  if (res.success) {
    success.value = `Image de « ${p.name} » mise à jour !`
    await load()
  } else {
    error.value = res.error === 'too_large' ? 'Image trop lourde (5 Mo max).' : res.error === 'bad_mime' ? 'Formats : JPEG, PNG, WebP.' : (res.error || 'Envoi impossible')
  }
  uploadingId.value = null
}

async function onDeleteImage(p) {
  error.value = ''
  success.value = ''
  const res = await deletePoiImage(token, p.id)
  if (res.success) {
    success.value = `Image de « ${p.name} » retirée.`
    await load()
  } else {
    error.value = res.error || 'Suppression impossible'
  }
}

onMounted(load)
</script>

<style scoped>
.poi-list { list-style: none; padding: 0; margin: 0; }
.poi-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-top: 1px solid #e2e8f0; flex-wrap: wrap; }
.poi-thumb { width: 44px; height: 44px; border-radius: 8px; object-fit: cover; }
.poi-thumb--empty { display: flex; align-items: center; justify-content: center; background: #f1f5f9; font-size: 1.3rem; }
.poi-name { flex: 1; min-width: 120px; }
.poi-status--pending { color: #b45309; }
.poi-status--approved { color: #047857; }
.poi-status--rejected { color: #dc2626; }
.poi-error { color: #dc2626; }
.poi-success { color: #047857; }
</style>
