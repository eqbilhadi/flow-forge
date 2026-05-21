<template>
  <div class="flex h-screen bg-background overflow-hidden">
    <!-- Sidebar -->
    <aside
      :class="[
        'flex flex-col border-r bg-card transition-all duration-300 shrink-0',
        sidebarOpen ? 'w-64' : 'w-16',
      ]"
    >
      <!-- Logo -->
      <div class="flex items-center gap-3 px-4 py-5 border-b">
        <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center shrink-0">
          <Zap class="w-4 h-4 text-primary-foreground" />
        </div>
        <span v-if="sidebarOpen" class="font-bold text-lg tracking-tight">FlowForge</span>
      </div>

      <!-- Nav -->
      <nav class="flex-1 py-4 space-y-1 px-2">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
          :class="{ 'bg-accent text-accent-foreground': isActive(item.to) }"
        >
          <component :is="item.icon" class="w-5 h-5 shrink-0" />
          <span v-if="sidebarOpen">{{ item.label }}</span>
        </RouterLink>
      </nav>

      <!-- User -->
      <div class="border-t p-3">
        <div class="flex items-center gap-3 px-2 py-2 rounded-md">
          <div class="w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center shrink-0">
            <span class="text-xs font-bold text-primary">{{ userInitials }}</span>
          </div>
          <div v-if="sidebarOpen" class="flex-1 min-w-0">
            <p class="text-sm font-medium truncate">{{ authStore.user?.name }}</p>
            <p class="text-xs text-muted-foreground truncate">{{ authStore.user?.role }}</p>
          </div>
          <button v-if="sidebarOpen" @click="handleLogout" class="text-muted-foreground hover:text-foreground">
            <LogOut class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Toggle -->
      <button
        @click="sidebarOpen = !sidebarOpen"
        class="absolute -right-3 top-20 w-6 h-6 bg-border rounded-full flex items-center justify-center hover:bg-accent transition-colors"
      >
        <ChevronLeft :class="['w-3 h-3 transition-transform', !sidebarOpen && 'rotate-180']" />
      </button>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Topbar -->
      <header class="border-b bg-card px-6 py-3 flex items-center justify-between shrink-0">
        <div>
          <h1 class="text-lg font-semibold">{{ pageTitle }}</h1>
          <p class="text-xs text-muted-foreground">{{ authStore.user?.tenant?.name }}</p>
        </div>
        <div class="flex items-center gap-2">
          <span class="inline-flex items-center gap-1.5 text-xs bg-green-500/10 text-green-600 px-2 py-1 rounded-full">
            <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse" />
            Live
          </span>
        </div>
      </header>

      <!-- Page Content -->
      <main class="flex-1 overflow-auto">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import {
  LayoutDashboard, GitBranch, Play, Sparkles,
  Zap, LogOut, ChevronLeft,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()
const sidebarOpen = ref(true)

const navItems = [
  { to: '/dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { to: '/workflows', label: 'Workflows', icon: GitBranch },
  { to: '/ai-builder', label: 'AI Builder', icon: Sparkles },
]

const pageTitles: Record<string, string> = {
  '/dashboard': 'Dashboard',
  '/workflows': 'Workflows',
  '/ai-builder': 'AI Workflow Builder',
}

const pageTitle = computed(() => {
  for (const [path, title] of Object.entries(pageTitles)) {
    if (route.path.startsWith(path)) return title
  }
  return 'FlowForge'
})

const userInitials = computed(() => {
  const name = authStore.user?.name ?? ''
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
})

function isActive(path: string) {
  return route.path === path || (path !== '/dashboard' && route.path.startsWith(path))
}

async function handleLogout() {
  authStore.logout()
  router.push('/login')
}
</script>
