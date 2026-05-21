<template>
  <div class="min-h-screen bg-background flex items-center justify-center p-4">
    <div class="w-full max-w-md space-y-6">
      <!-- Logo -->
      <div class="flex flex-col items-center gap-3">
        <div class="w-12 h-12 bg-primary rounded-xl flex items-center justify-center">
          <Zap class="w-6 h-6 text-primary-foreground" />
        </div>
        <div class="text-center">
          <h1 class="text-2xl font-bold">FlowForge</h1>
          <p class="text-muted-foreground text-sm">Workflow Orchestration Engine</p>
        </div>
      </div>

      <!-- Card -->
      <div class="bg-card border rounded-xl p-6 shadow-sm space-y-4">
        <div>
          <h2 class="text-lg font-semibold">Sign In</h2>
          <p class="text-sm text-muted-foreground">Enter your credentials to continue</p>
        </div>

        <form @submit.prevent="handleLogin" class="space-y-4">
          <div class="space-y-1.5">
            <label class="text-sm font-medium">Email</label>
            <input
              v-model="form.email"
              type="email"
              placeholder="you@company.com"
              required
              class="w-full px-3 py-2 bg-background border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors"
            />
          </div>

          <div class="space-y-1.5">
            <label class="text-sm font-medium">Password</label>
            <input
              v-model="form.password"
              type="password"
              placeholder="••••••••"
              required
              class="w-full px-3 py-2 bg-background border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors"
            />
          </div>

          <p v-if="errorMsg" class="text-sm text-destructive bg-destructive/10 px-3 py-2 rounded-md">
            {{ errorMsg }}
          </p>

          <button
            type="submit"
            :disabled="loading"
            class="w-full bg-primary text-primary-foreground py-2.5 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors disabled:opacity-60"
          >
            <span v-if="loading">Signing in…</span>
            <span v-else>Sign In</span>
          </button>
        </form>

        <p class="text-sm text-center text-muted-foreground">
          Don't have an account?
          <RouterLink to="/register" class="text-primary font-medium hover:underline">Register</RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRouter, useRoute } from 'vue-router'
import { Zap } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()

const form = ref({ email: '', password: '' })
const loading = ref(false)
const errorMsg = ref('')

async function handleLogin() {
  loading.value = true
  errorMsg.value = ''
  try {
    await authStore.login(form.value.email, form.value.password)
    const redirect = (route.query.redirect as string) || '/dashboard'
    router.push(redirect)
  } catch (e: any) {
    errorMsg.value = e.response?.data?.message || 'Login failed. Please check your credentials.'
  } finally {
    loading.value = false
  }
}
</script>
