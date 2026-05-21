import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '@/api'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('flowforge_token'))
  const loading = ref(false)

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isEditor = computed(() => user.value?.role === 'admin' || user.value?.role === 'editor')
  const tenantId = computed(() => user.value?.tenant.id)

  async function login(email: string, password: string) {
    loading.value = true
    try {
      const { data } = await authApi.login(email, password)
      setAuth(data.token, data.user)
      return data
    } finally {
      loading.value = false
    }
  }

  async function register(payload: { tenant_name: string; name: string; email: string; password: string }) {
    loading.value = true
    try {
      const { data } = await authApi.register(payload)
      setAuth(data.token, data.user)
      return data
    } finally {
      loading.value = false
    }
  }

  async function fetchMe() {
    if (!token.value) return
    try {
      const { data } = await authApi.me()
      user.value = data.user
    } catch {
      logout()
    }
  }

  function setAuth(newToken: string, newUser: User) {
    token.value = newToken
    user.value = newUser
    localStorage.setItem('flowforge_token', newToken)
  }

  function logout() {
    if (token.value) {
      authApi.logout().catch(() => {})
    }
    token.value = null
    user.value = null
    localStorage.removeItem('flowforge_token')
  }

  return {
    user,
    token,
    loading,
    isAuthenticated,
    isAdmin,
    isEditor,
    tenantId,
    login,
    register,
    fetchMe,
    logout,
    setAuth,
  }
})
