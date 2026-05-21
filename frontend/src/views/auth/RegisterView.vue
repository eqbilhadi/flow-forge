<template>
  <div class="min-h-screen bg-background flex items-center justify-center p-4">
    <div class="w-full max-w-md space-y-6">
      <div class="flex flex-col items-center gap-3">
        <div class="w-12 h-12 bg-primary rounded-xl flex items-center justify-center">
          <Zap class="w-6 h-6 text-primary-foreground" />
        </div>
        <div class="text-center">
          <h1 class="text-2xl font-bold">FlowForge</h1>
          <p class="text-muted-foreground text-sm">Create your workspace</p>
        </div>
      </div>

      <div class="bg-card border rounded-xl p-6 shadow-sm space-y-4">
        <div>
          <h2 class="text-lg font-semibold">Create Account</h2>
          <p class="text-sm text-muted-foreground">You'll be the Admin of your workspace</p>
        </div>

        <form @submit.prevent="handleRegister" class="space-y-4">
          <div class="space-y-1.5">
            <label class="text-sm font-medium">Workspace Name</label>
            <input
              v-model="form.tenant_name"
              type="text"
              placeholder="Acme Corp"
              required
              class="w-full px-3 py-2 bg-background border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
            />
          </div>
          <div class="space-y-1.5">
            <label class="text-sm font-medium">Your Name</label>
            <input
              v-model="form.name"
              type="text"
              placeholder="John Doe"
              required
              class="w-full px-3 py-2 bg-background border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
            />
          </div>
          <div class="space-y-1.5">
            <label class="text-sm font-medium">Email</label>
            <input
              v-model="form.email"
              type="email"
              placeholder="you@company.com"
              required
              class="w-full px-3 py-2 bg-background border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
            />
          </div>
          <div class="space-y-1.5">
            <label class="text-sm font-medium">Password</label>
            <input
              v-model="form.password"
              type="password"
              placeholder="Min. 8 characters"
              required
              class="w-full px-3 py-2 bg-background border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
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
            <span v-if="loading">Creating workspace…</span>
            <span v-else>Create Workspace</span>
          </button>
        </form>

        <p class="text-sm text-center text-muted-foreground">
          Already have an account?
          <RouterLink to="/login" class="text-primary font-medium hover:underline">Sign in</RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { Zap } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const router = useRouter()
const form = ref({ tenant_name: '', name: '', email: '', password: '' })
const loading = ref(false)
const errorMsg = ref('')

async function handleRegister() {
  loading.value = true
  errorMsg.value = ''
  try {
    await authStore.register(form.value)
    router.push('/dashboard')
  } catch (e: any) {
    errorMsg.value = e.response?.data?.message || 'Registration failed.'
  } finally {
    loading.value = false
  }
}
</script>
