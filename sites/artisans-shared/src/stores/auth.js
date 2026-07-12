import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

/**
 * Central authentication store for the artisans frontends.
 *
 * Currently mirrors the existing localStorage/cookie-based auth flow
 * and provides a single source of truth for the current user.
 */
export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('user_token') || null)
  const user = ref(null)
  const isLoading = ref(false)
  const error = ref(null)

  const isLoggedIn = computed(() => !!token.value)

  function setToken(newToken) {
    token.value = newToken
    if (newToken) {
      localStorage.setItem('user_token', newToken)
    } else {
      localStorage.removeItem('user_token')
    }
  }

  function setUser(userData) {
    user.value = userData
  }

  function clearAuth() {
    token.value = null
    user.value = null
    error.value = null
    localStorage.removeItem('user_token')
  }

  function setAuthError(message) {
    error.value = message
  }

  return {
    token,
    user,
    isLoading,
    error,
    isLoggedIn,
    setToken,
    setUser,
    clearAuth,
    setAuthError,
  }
})
