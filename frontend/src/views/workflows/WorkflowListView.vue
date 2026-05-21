<template>
  <div class="p-6 space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold">Workflows</h2>
        <p class="text-sm text-muted-foreground">{{ pagination?.total ?? 0 }} workflows in your workspace</p>
      </div>
      <RouterLink
        v-if="authStore.isEditor"
        to="/workflows/new"
        class="inline-flex items-center gap-2 bg-primary text-primary-foreground px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors"
      >
        <Plus class="w-4 h-4" />
        New Workflow
      </RouterLink>
    </div>

    <!-- Filters -->
    <div class="flex items-center gap-3 flex-wrap">
      <input
        v-model="search"
        type="text"
        placeholder="Search workflows…"
        class="px-3 py-2 bg-background border rounded-md text-sm w-64 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
      />
      <select
        v-model="filterTrigger"
        class="px-3 py-2 bg-background border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/30"
      >
        <option value="">All trigger types</option>
        <option value="manual">Manual</option>
        <option value="schedule">Schedule</option>
        <option value="webhook">Webhook</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="workflowsStore.loading" class="grid gap-3">
      <div v-for="i in 4" :key="i" class="bg-card border rounded-xl p-5 animate-pulse">
        <div class="h-4 bg-muted rounded w-1/3 mb-2" />
        <div class="h-3 bg-muted rounded w-2/3" />
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="workflowsStore.workflows.length === 0" class="text-center py-16 space-y-3">
      <GitBranch class="w-12 h-12 mx-auto text-muted-foreground/40" />
      <p class="text-muted-foreground">No workflows yet.</p>
      <RouterLink
        v-if="authStore.isEditor"
        to="/workflows/new"
        class="inline-flex items-center gap-2 text-sm text-primary hover:underline"
      >
        <Plus class="w-4 h-4" /> Create your first workflow
      </RouterLink>
    </div>

    <!-- Workflow Cards -->
    <div v-else class="grid gap-3">
      <div
        v-for="workflow in workflowsStore.workflows"
        :key="workflow.id"
        class="bg-card border rounded-xl p-5 hover:shadow-sm transition-shadow cursor-pointer group"
        @click="router.push(`/workflows/${workflow.id}`)"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1 min-w-0 space-y-1">
            <div class="flex items-center gap-2">
              <h3 class="font-semibold truncate">{{ workflow.name }}</h3>
              <span
                :class="[
                  'text-xs px-1.5 py-0.5 rounded',
                  workflow.is_active
                    ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400'
                    : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-500',
                ]"
              >{{ workflow.is_active ? 'Active' : 'Inactive' }}</span>
              <span class="text-xs text-muted-foreground">v{{ workflow.version }}</span>
            </div>
            <p v-if="workflow.description" class="text-sm text-muted-foreground truncate">
              {{ workflow.description }}
            </p>
            <div class="flex items-center gap-3 text-xs text-muted-foreground">
              <span class="flex items-center gap-1">
                <GitBranch class="w-3 h-3" />{{ workflow.step_count }} steps
              </span>
              <span class="flex items-center gap-1">
                <Zap class="w-3 h-3" />{{ workflow.trigger_type }}
              </span>
              <span v-if="workflow.tags?.length">
                {{ workflow.tags.slice(0, 2).join(', ') }}
              </span>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity" @click.stop>
            <button
              v-if="authStore.isEditor && workflow.is_active"
              @click="handleTrigger(workflow.id)"
              :disabled="triggering === workflow.id"
              class="inline-flex items-center gap-1.5 text-xs bg-primary/10 text-primary px-3 py-1.5 rounded-md hover:bg-primary/20 transition-colors disabled:opacity-60"
            >
              <Play class="w-3 h-3" />
              {{ triggering === workflow.id ? 'Starting…' : 'Run' }}
            </button>
            <RouterLink
              v-if="authStore.isEditor"
              :to="`/workflows/${workflow.id}/edit`"
              class="inline-flex items-center gap-1.5 text-xs bg-muted text-muted-foreground px-3 py-1.5 rounded-md hover:bg-accent transition-colors"
            >
              <Pencil class="w-3 h-3" /> Edit
            </RouterLink>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="pagination && pagination.last_page > 1" class="flex items-center justify-between pt-2">
      <p class="text-sm text-muted-foreground">
        Page {{ pagination.current_page }} of {{ pagination.last_page }}
      </p>
      <div class="flex gap-2">
        <button
          @click="page--"
          :disabled="page <= 1"
          class="px-3 py-1.5 text-sm border rounded-md hover:bg-accent disabled:opacity-40"
        >Prev</button>
        <button
          @click="page++"
          :disabled="page >= pagination.last_page"
          class="px-3 py-1.5 text-sm border rounded-md hover:bg-accent disabled:opacity-40"
        >Next</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { Plus, GitBranch, Play, Pencil, Zap } from 'lucide-vue-next'
import { useWorkflowsStore } from '@/stores/workflows'
import { useAuthStore } from '@/stores/auth'
import { useDebounceFn } from '@vueuse/core'

const workflowsStore = useWorkflowsStore()
const authStore = useAuthStore()
const router = useRouter()

const search = ref('')
const filterTrigger = ref('')
const page = ref(1)
const triggering = ref<string | null>(null)
const pagination = computed(() => workflowsStore.pagination)

const loadWorkflows = useDebounceFn(() => {
  workflowsStore.fetchWorkflows({
    search: search.value || undefined,
    trigger_type: filterTrigger.value || undefined,
    page: page.value,
  })
}, 300)

watch([search, filterTrigger], () => { page.value = 1; loadWorkflows() })
watch(page, loadWorkflows)
onMounted(loadWorkflows)

async function handleTrigger(id: string) {
  triggering.value = id
  try {
    const run = await workflowsStore.triggerWorkflow(id)
    router.push(`/runs/${run.id}`)
  } finally {
    triggering.value = null
  }
}
</script>
