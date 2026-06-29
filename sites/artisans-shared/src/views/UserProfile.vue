<template>
  <div class="user-profile">
    <div v-if="loading" class="profile-card">
      <p>Chargement du profil…</p>
    </div>
    <template v-else>
      <div v-if="error" class="profile-card">
        <p>{{ error }}</p>
      </div>
      <template v-else>
        <div class="profile-card">
          <img v-if="avatarUrl" :src="avatarUrl" :alt="`Avatar de ${displayName}`" class="profile-avatar" />
          <div v-else class="profile-avatar-placeholder">🙂</div>
          <h1>{{ displayName }}</h1>
          <p class="profile-title">{{ user?.title }}</p>
          <div class="profile-level">
            <span>Niveau {{ user?.level }}</span>
            <div class="xp-bar"><div class="xp-fill" :style="{ width: xpPercent + '%' }"></div></div>
            <span>{{ user?.xp }} / {{ user?.xp_needed }} XP</span>
          </div>
          <button type="button" @click="goToCharacter">Modifier mon personnage</button>
        </div>

        <h2>Badges</h2>
        <div class="badges-list">
          <span v-for="b in user?.badges" :key="b.key" class="badge">{{ b.name }}</span>
          <p v-if="!user?.badges?.length">Aucun badge pour l’instant. Continuez à explorer !</p>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { API_BASE, getUserToken, fetchUserMe, removeUserToken } from '../api.js'

const router = useRouter()
const user = ref(null)
const loading = ref(true)
const error = ref(null)
let abortController = null

const displayName = computed(() => user.value?.display_name || user.value?.email?.split('@')[0] || 'Explorateur')
const xpPercent = computed(() => user.value ? Math.min(100, (user.value.xp / user.value.xp_needed) * 100) : 0)
const avatarUrl = computed(() => {
  if (!user.value?.avatar_url) return null
  try {
    const url = new URL(user.value.avatar_url, API_BASE)
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return null
    return url.href
  } catch {
    return null
  }
})

// /personnage route is added in Task 8
function goToCharacter() {
  router.push('/personnage')
}

onMounted(async () => {
  const token = getUserToken()
  if (!token) {
    loading.value = false
    router.replace('/roue')
    return
  }

  abortController = new AbortController()
  try {
    const res = await fetchUserMe(token, { signal: abortController.signal })
    if (res.success) {
      user.value = res.data
    } else if (res.status === 401) {
      removeUserToken()
      router.replace('/roue')
    } else {
      error.value = res.error || 'Impossible de charger le profil.'
      user.value = null
    }
  } catch (e) {
    if (e.name === 'AbortError') return
    console.warn('Failed to load user profile', e)
    error.value = 'Impossible de charger le profil.'
    user.value = null
  } finally {
    loading.value = false
  }
})

onUnmounted(() => {
  abortController?.abort()
})
</script>

<style scoped>
.user-profile { max-width: 640px; margin: 0 auto; padding: 24px; }
.profile-card { text-align: center; background: #f8fafc; border-radius: 16px; padding: 32px; margin-bottom: 24px; }
.profile-avatar, .profile-avatar-placeholder { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 48px; background: #e2e8f0; }
.profile-title { color: #64748b; }
.profile-level { margin: 16px 0; }
.xp-bar { width: 100%; height: 12px; background: #e2e8f0; border-radius: 6px; overflow: hidden; margin: 8px 0; }
.xp-fill { height: 100%; background: #10b981; transition: width 0.3s; }
.badges-list { display: flex; flex-wrap: wrap; gap: 8px; }
.badge { background: #1a1a2e; color: #fff; padding: 6px 12px; border-radius: 20px; font-size: 13px; }
</style>
