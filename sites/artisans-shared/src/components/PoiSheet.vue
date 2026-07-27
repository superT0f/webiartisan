<script setup>
import { ref, computed, watch } from 'vue'
import {
  getPoiPhotos, uploadPoiPhoto, deletePoiPhoto, reportPoiPhoto,
  uploadPoiImage, getArtisanToken, resolveAvatarUrl,
} from '../api.js'
import { pickImage, isFlutterApp } from '../utils/flutterBridge.js'

const props = defineProps({
  poi: { type: Object, default: null },
  checkinState: { type: Object, default: null },
  authenticated: { type: Boolean, default: false },
  isAdmin: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'navigate', 'checkin', 'toast'])

const TYPE_LABELS = {
  mairie: 'Mairie', piscine: 'Piscine', bibliotheque: 'Bibliothèque', mediatheque: 'Médiathèque',
  cinema: 'Cinéma', dechetterie: 'Déchèterie', poste: 'Poste', supermarche: 'Supermarché',
  transport: 'Transport', ecole: 'École', hopital: 'Hôpital', pharmacie: 'Pharmacie',
  parc: 'Parc', eglise: 'Église', autre: 'Lieu',
}
const typeLabel = computed(() => (TYPE_LABELS[props.poi?.type] || props.poi?.type || 'Lieu').toUpperCase())

const scheduleLine = computed(() => {
  const days = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']
  return (props.poi?.schedules || [])
    .filter(s => !s.is_closed)
    .map(s => `${days[s.day_of_week] || '?'} ${s.open_time?.slice(0, 5)}–${s.close_time?.slice(0, 5)}`)
    .join(' · ')
})

// Même logique que ArtisanSheet : cooldown grisé + compte à rebours
const checkinLabel = computed(() => {
  const s = props.checkinState
  if (!s) return '📍 Position indisponible'
  if (!s.inRange) return `📍 Trop loin (${s.distanceM} m)`
  if (s.dailyAvailable === false && s.nextSpinAt) {
    const ms = new Date(s.nextSpinAt).getTime() - Date.now()
    if (ms > 0) return `📍 Recharge (${Math.ceil(ms / 60000)} min)`
  }
  return s.dailyAvailable === false ? '📍 Check-in (+10 XP)' : '📍 Check-in (+100 XP)'
})

const checkinDisabled = computed(() => {
  const s = props.checkinState
  if (!s || !s.inRange) return true
  if (s.dailyAvailable === false && s.nextSpinAt && new Date(s.nextSpinAt).getTime() > Date.now()) return true
  return false
})

// --- Galerie photo ---------------------------------------------------

const tab = ref('photos')
const photos = ref([])
const uploading = ref(false)
const reportedIds = ref(new Set())
const fileInput = ref(null)
const coverInput = ref(null)

// Couverture puis photos communautaires. Les URLs sont relatives (/uploads/...)
// servies par l'API : même résolution que poi.image_url dans MapView.
const galleryItems = computed(() => {
  const items = []
  const cover = resolveAvatarUrl(props.poi?.image_url)
  if (cover) items.push({ key: 'cover', url: cover, cover: true })
  for (const p of photos.value) {
    const url = resolveAvatarUrl(p.url)
    if (url) items.push({ key: `photo-${p.id}`, url, photo: p })
  }
  return items
})

watch(() => props.poi?.id, async (id) => {
  tab.value = 'photos'
  photos.value = []
  reportedIds.value = new Set()
  if (!id) return
  const res = await getPoiPhotos(id)
  if (res.success) photos.value = res.data || []
}, { immediate: true })

function base64ToFile({ base64, mimeType, name }) {
  const bin = atob(base64)
  const bytes = new Uint8Array(bin.length)
  for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i)
  return new File([bytes], name || 'photo.jpg', { type: mimeType || 'image/jpeg' })
}

async function onAddPhoto() {
  if (!props.authenticated) { emit('toast', 'Connecte-toi pour ajouter une photo'); return }
  let file = null
  if (isFlutterApp()) {
    try { file = base64ToFile(await pickImage({ source: 'gallery', quality: 90, maxWidth: 2000 })) }
    catch (e) { if (e?.code !== 'cancelled') emit('toast', 'Photo non récupérée'); return }
  } else {
    fileInput.value?.click()
    return
  }
  await doUpload(file)
}

async function onFilePicked(e) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (file) await doUpload(file)
}

async function doUpload(file) {
  if (uploading.value) return
  uploading.value = true
  const res = await uploadPoiPhoto(props.poi.id, file)
  uploading.value = false
  if (res.success) {
    photos.value.unshift({ id: res.data.id, url: res.data.url, mine: true, created_at: new Date().toISOString() })
    emit('toast', '📷 Photo publiée, merci !')
  } else {
    emit('toast', res.message || res.error || 'Envoi impossible')
  }
}

async function onDeletePhoto(photo) {
  const res = await deletePoiPhoto(photo.id)
  if (res.success) photos.value = photos.value.filter(p => p.id !== photo.id)
  else emit('toast', res.error || 'Suppression impossible')
}

async function onReportPhoto(photo) {
  const res = await reportPoiPhoto(photo.id)
  if (res.success || res.status === 409) {
    reportedIds.value = new Set([...reportedIds.value, photo.id])
    emit('toast', 'Photo signalée, merci — un admin va regarder')
  } else {
    emit('toast', res.error || 'Signalement impossible')
  }
}

async function onCoverUpload(e) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (!file) return
  const token = getArtisanToken()
  const res = await uploadPoiImage(token, props.poi.id, file)
  emit('toast', res.success ? '✅ Couverture mise à jour' : (res.message || res.error || 'Envoi impossible'))
}
</script>

<template>
  <Transition name="slide-up">
    <div v-if="poi" class="sheet-overlay" @click.self="$emit('close')">
      <div class="sheet">
        <button class="sheet-close" @click="$emit('close')">✕</button>
        <div class="sheet-header">
          <h3>{{ poi.name }}</h3>
          <span class="category">{{ typeLabel }}</span>
        </div>

        <div class="tabs">
          <button :class="{ active: tab === 'photos' }" @click="tab = 'photos'">Photos</button>
          <button :class="{ active: tab === 'details' }" @click="tab = 'details'">Détails</button>
        </div>

        <div v-if="tab === 'photos'">
          <div v-if="galleryItems.length" class="gallery">
            <div v-for="item in galleryItems" :key="item.key" class="gallery-item">
              <img :src="item.url" :alt="poi.name" loading="lazy" />
              <div v-if="!item.cover" class="gallery-actions">
                <button
                  :disabled="reportedIds.has(item.photo.id)"
                  title="Signaler la photo"
                  @click="onReportPhoto(item.photo)"
                >🚩</button>
                <button
                  v-if="item.photo.mine"
                  title="Supprimer ma photo"
                  @click="onDeletePhoto(item.photo)"
                >🗑️</button>
              </div>
            </div>
          </div>
          <p v-else class="gallery-empty">Sois le premier à photographier ce lieu !</p>

          <div class="gallery-btns">
            <button class="btn btn-primary" :disabled="uploading" @click="onAddPhoto">
              {{ uploading ? 'Envoi…' : '📷 Ajouter une photo' }}
            </button>
            <button v-if="isAdmin" class="btn btn-secondary" @click="coverInput?.click()">📷 Couverture</button>
          </div>
          <input ref="fileInput" type="file" accept="image/*" hidden @change="onFilePicked" />
          <input ref="coverInput" type="file" accept="image/*" hidden @change="onCoverUpload" />
        </div>

        <div v-else>
          <p v-if="poi.address" class="address">{{ poi.address }}</p>
          <p v-if="scheduleLine" class="schedules">🕐 {{ scheduleLine }}</p>
          <p v-if="poi.phone" class="address">📞 {{ poi.phone }}</p>
        </div>

        <div class="play-section">
          <button class="btn btn-primary play-btn" :disabled="checkinDisabled" @click="$emit('checkin')">
            {{ checkinLabel }}
          </button>
          <p v-if="!authenticated" class="play-hint">Connexion par email requise pour jouer.</p>
        </div>

        <div class="actions">
          <button class="btn btn-primary" @click="$emit('navigate', poi)">Itinéraire</button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.sheet-overlay {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: flex;
  align-items: flex-end;
  pointer-events: none;
}
.sheet {
  width: 100%;
  background: #fff;
  border-radius: 20px 20px 0 0;
  padding: 20px;
  box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
  pointer-events: auto;
  position: relative;
  max-height: 70vh;
  overflow-y: auto;
}
.sheet-close {
  position: absolute;
  top: 12px;
  right: 16px;
  background: none;
  border: none;
  font-size: 1.2rem;
  cursor: pointer;
  z-index: 1;
}
.sheet-header h3 { margin: 0 0 4px; }
.category { color: #1a73e8; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.04em; }
.address { color: #666; margin: 6px 0 0; }
.schedules { color: #666; margin: 6px 0 0; font-size: 0.9rem; }

.play-section {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--c-border);
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.play-btn { width: 100%; text-align: center; }
.play-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.play-hint { margin: 0; font-size: 0.8rem; color: var(--c-text-2); }

.actions { display: flex; gap: 10px; margin-top: 16px; }

.tabs { display: flex; gap: 8px; margin-bottom: 12px; margin-top: 12px; }
.tabs button { flex: 1; padding: 8px; border: none; border-radius: 999px; background: #f1f5f9; font-weight: 600; cursor: pointer; }
.tabs button.active { background: var(--c-green); color: #fff; }
.gallery { display: flex; gap: 10px; overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: 8px; }
.gallery-item { position: relative; flex: 0 0 78%; scroll-snap-align: center; }
.gallery-item img { width: 100%; max-height: 220px; object-fit: cover; border-radius: 12px; display: block; }
.gallery-actions { position: absolute; top: 8px; right: 8px; display: flex; gap: 6px; }
.gallery-actions button { background: rgba(255,255,255,0.9); border: none; border-radius: 999px; padding: 4px 8px; cursor: pointer; }
.gallery-actions button:disabled { opacity: 0.5; cursor: default; }
.gallery-empty { color: var(--c-text-2); font-size: 0.9rem; text-align: center; margin: 12px 0; }
.gallery-btns { display: flex; gap: 10px; margin-top: 10px; }

.slide-up-enter-active, .slide-up-leave-active { transition: transform 0.3s ease; }
.slide-up-enter-from, .slide-up-leave-to { transform: translateY(100%); }
</style>
