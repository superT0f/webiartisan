<template>
  <div class="character-edit">
    <h1>Personnaliser mon personnage</h1>

    <div v-if="error" class="error" role="alert" aria-live="polite">{{ error }}</div>

    <p v-if="loadingProfile" class="loading-profile">Chargement du profil…</p>

    <form v-else @submit.prevent="save">
      <label for="display-name">Pseudo public</label>
      <input id="display-name" v-model="form.display_name" maxlength="80" @input="clearError" />

      <label for="avatar-gender">Genre</label>
      <select id="avatar-gender" v-model="form.avatar_gender">
        <option value="male">Homme</option>
        <option value="female">Femme</option>
        <option value="neutral">Neutre</option>
      </select>

      <fieldset class="avatar-fieldset">
        <legend>Avatar</legend>
        <p v-if="loadingAvatars && avatars.length === 0" class="loading-avatars">Chargement des avatars…</p>
        <div v-else class="avatar-grid">
          <button
            v-for="a in avatars"
            :key="`${a.gender}-${a.id}`"
            type="button"
            class="avatar-item"
            :class="{ locked: isAvatarLocked(a), selected: selectedAvatar?.id === a.id }"
            :aria-pressed="selectedAvatar?.id === a.id ? 'true' : 'false'"
            :aria-label="(a.name || a.id) + (isAvatarLocked(a) ? ' (verrouillé)' : '')"
            :disabled="isAvatarLocked(a)"
            @click="selectAvatar(a)"
          >
            <img :src="resolveAvatarUrl(a.url)" :alt="a.name" />
            <span>{{ a.name }}</span>
            <small v-if="a.unlock_level > 1">Niv. {{ a.unlock_level }}</small>
            <small v-else-if="a.unlock_badge">Badge requis</small>
          </button>
        </div>
      </fieldset>

      <label for="avatar-file">Avatar personnel</label>
      <input id="avatar-file" ref="fileInput" type="file" accept="image/png,image/jpeg" @change="onFileChange" />

      <img v-if="avatarPreviewUrl" :src="avatarPreviewUrl" alt="Aperçu de l'avatar" class="upload-preview" />

      <button type="submit" :disabled="saving">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { getUserToken, removeUserToken, fetchUserMe, fetchAvatars, updateUserProfile, updateUserAvatar, resolveAvatarUrl, notifyAuthChanged } from '../api.js'

const router = useRouter()
const user = ref(null)
const avatars = ref([])
const form = ref({ display_name: '', avatar_gender: 'neutral' })
const selectedAvatar = ref(null)
const avatarPreviewUrl = ref(null)
const uploadedBase64 = ref(null)
const saving = ref(false)
const loadingProfile = ref(true)
const error = ref('')
const avatarAbortController = ref(null)
const profileAbortController = ref(null)
const saveAbortController = ref(null)
const fileInput = ref(null)
const loadingAvatars = ref(false)
const avatarLoadRequestId = ref(0)

const unlockedBadgeKeys = computed(() => user.value?.badges?.map(b => b.key) ?? [])

onMounted(async () => {
  const token = getUserToken()
  if (!token) {
    loadingProfile.value = false
    return router.push('/roue')
  }
  try {
    profileAbortController.value = new AbortController()
    const res = await fetchUserMe(token, { signal: profileAbortController.value.signal })
    if (!res.success) {
      if (res.error === 'AbortError') return
      if (res.status === 401) {
        return router.push('/roue')
      }
      error.value = res.error || 'Impossible de charger le profil.'
      return
    }
    user.value = res.data
    form.value.display_name = res.data.display_name || ''
    form.value.avatar_gender = res.data.avatar_gender || 'neutral'
    if (user.value.avatar_type === 'upload' && user.value.avatar_url) {
      avatarPreviewUrl.value = resolveAvatarUrl(user.value.avatar_url)
      uploadedBase64.value = null
    }
    await loadAvatars()
  } catch (e) {
    console.error('Failed to load character page', e)
    router.push('/roue')
  } finally {
    if (!profileAbortController.value?.signal.aborted) {
      loadingProfile.value = false
    }
  }
})

onUnmounted(() => {
  profileAbortController.value?.abort()
  avatarAbortController.value?.abort()
  saveAbortController.value?.abort()
})

watch(() => form.value.avatar_gender, () => {
  selectedAvatar.value = null
  if (uploadedBase64.value) {
    // A custom upload is gender-agnostic: keep the preview and file input.
    avatarPreviewUrl.value = uploadedBase64.value
  } else if (user.value?.avatar_type === 'upload' && user.value?.avatar_url) {
    avatarPreviewUrl.value = resolveAvatarUrl(user.value.avatar_url)
  } else {
    avatarPreviewUrl.value = null
  }
  loadAvatars()
})

function clearFileInput() {
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

function clearError() {
  error.value = ''
}

function isAvatarLocked(a) {
  if (a.unlock_level > (user.value?.level || 1)) return true
  if (a.unlock_badge && !unlockedBadgeKeys.value.includes(a.unlock_badge)) return true
  return false
}

async function loadAvatars() {
  const requestId = ++avatarLoadRequestId.value
  loadingAvatars.value = true
  avatars.value = []
  error.value = ''
  try {
    if (avatarAbortController.value) {
      avatarAbortController.value.abort()
    }
    avatarAbortController.value = new AbortController()
    const res = await fetchAvatars(form.value.avatar_gender, { signal: avatarAbortController.value.signal })
    if (requestId !== avatarLoadRequestId.value) return
    if (!res.success) {
      if (res.error === 'AbortError') return
      error.value = res.error || 'Impossible de charger les avatars.'
      avatars.value = []
      return
    }
    avatars.value = res.data || []
    if (selectedAvatar.value && !avatars.value.some(a => a.id === selectedAvatar.value.id)) {
      selectedAvatar.value = null
    }
    if (!selectedAvatar.value) {
      preselectCurrentAvatar()
    }
  } finally {
    if (requestId === avatarLoadRequestId.value) {
      loadingAvatars.value = false
    }
  }
}

function preselectCurrentAvatar() {
  if (!user.value || !user.value.avatar_url || user.value.avatar_type === 'upload') {
    selectedAvatar.value = null
    return
  }
  const match = avatars.value.find(a => a.url === user.value.avatar_url)
  if (match && !isAvatarLocked(match)) {
    selectedAvatar.value = match
    avatarPreviewUrl.value = null
  } else {
    selectedAvatar.value = null
  }
}

function selectAvatar(a) {
  if (isAvatarLocked(a)) return
  selectedAvatar.value = a
  avatarPreviewUrl.value = null
  uploadedBase64.value = null
  clearFileInput()
  error.value = ''
}

function onFileChange(e) {
  error.value = ''
  const file = e.target.files[0]
  if (!file) return
  if (!['image/png', 'image/jpeg', 'image/jpg'].includes(file.type)) {
    error.value = 'Format accepté : PNG ou JPEG.'
    e.target.value = ''
    avatarPreviewUrl.value = null
    uploadedBase64.value = null
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    error.value = 'Image trop lourde (max 2 Mo).'
    e.target.value = ''
    avatarPreviewUrl.value = null
    uploadedBase64.value = null
    return
  }
  const reader = new FileReader()
  reader.onload = () => {
    uploadedBase64.value = reader.result
    avatarPreviewUrl.value = reader.result
    selectedAvatar.value = null
  }
  reader.onerror = () => {
    error.value = 'Impossible de lire le fichier.'
    avatarPreviewUrl.value = null
    uploadedBase64.value = null
  }
  reader.readAsDataURL(file)
}

function handleAuthError() {
  error.value = 'Session expirée. Veuillez vous reconnecter.'
  removeUserToken()
  router.push('/roue')
}

function handleUpdateError(res, fallbackMessage) {
  if (res.error === 'AbortError') return
  if (res.status === 401) {
    handleAuthError()
  } else {
    error.value = res.error || fallbackMessage
  }
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    const token = getUserToken()
    if (!token) {
      handleAuthError()
      return
    }

    if (!form.value.display_name.trim()) {
      error.value = 'Veuillez saisir un pseudo public.'
      return
    }

    saveAbortController.value = new AbortController()
    const profileRes = await updateUserProfile(token, {
      display_name: form.value.display_name.trim(),
      avatar_gender: form.value.avatar_gender,
    }, { signal: saveAbortController.value.signal })
    if (!profileRes.success) {
      handleUpdateError(profileRes, 'Erreur lors de la mise à jour du profil.')
      return
    }
    user.value = profileRes.data

    // Note: profile and avatar are updated in two separate requests.
    // If the avatar update fails, the profile changes persist and the user can retry.
    let avatarRes = { success: true }
    if (selectedAvatar.value) {
      avatarRes = await updateUserAvatar(token, {
        avatar_id: selectedAvatar.value.id,
        avatar_gender: selectedAvatar.value.gender,
      }, { signal: saveAbortController.value.signal })
    } else if (uploadedBase64.value) {
      avatarRes = await updateUserAvatar(token, { base64_image: uploadedBase64.value }, { signal: saveAbortController.value.signal })
    }

    if (!avatarRes.success) {
      handleUpdateError(avatarRes, "Profil mis à jour, mais l'avatar n'a pas pu être enregistré. Veuillez réessayer.")
      return
    }

    notifyAuthChanged()
    router.push('/profil')
  } catch (e) {
    error.value = 'Une erreur inattendue est survenue. Veuillez réessayer.'
  } finally {
    saving.value = false
    saveAbortController.value = null
  }
}
</script>

<style scoped>
.character-edit { max-width: 640px; margin: 0 auto; padding: 24px; }
label, legend { display: block; margin-top: 16px; font-weight: 600; }
input, select { width: 100%; padding: 8px; margin-top: 4px; }
.avatar-fieldset { border: none; padding: 0; margin: 0; }
.avatar-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 12px; margin-top: 8px; }
.avatar-item { text-align: center; padding: 8px; border: 2px solid transparent; border-radius: 8px; cursor: pointer; background: transparent; font: inherit; color: inherit; }
.avatar-item.selected { border-color: #10b981; background: #ecfdf5; }
.avatar-item.locked { opacity: 0.4; cursor: not-allowed; }
.avatar-item img { width: 64px; height: 64px; object-fit: cover; border-radius: 50%; }
.upload-preview { display: block; width: 96px; height: 96px; object-fit: cover; border-radius: 50%; margin-top: 12px; }
button { margin-top: 24px; padding: 12px 24px; background: #1a1a2e; color: #fff; border: none; border-radius: 8px; cursor: pointer; }
button:disabled { opacity: 0.6; }
.error { margin-top: 16px; padding: 12px; background: #fee2e2; color: #991b1b; border-radius: 8px; }
.loading-avatars { margin-top: 8px; color: #64748b; }
.loading-profile { margin-top: 16px; color: #64748b; }
</style>
