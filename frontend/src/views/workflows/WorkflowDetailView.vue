<template>
  <div class="p-6 space-y-6 max-w-6xl">
    <!-- Back -->
    <button @click="router.back()" class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
      <ArrowLeft class="w-4 h-4" /> Back to Workflows
    </button>

    <div v-if="loading" class="space-y-4">
      <div class="h-8 bg-muted rounded w-1/3 animate-pulse" />
      <div class="h-32 bg-muted rounded animate-pulse" />
    </div>

    <template v-else-if="workflow">
      <!-- Header -->
      <div class="flex items-start justify-between gap-4">
        <div class="space-y-1">
          <div class="flex items-center gap-3">
            <h2 class="text-2xl font-bold">{{ workflow.name }}</h2>
            <span :class="['text-xs px-2 py-1 rounded-full font-medium', workflow.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
              {{ workflow.is_active ? 'Active' : 'Inactive' }}
            </span>
            <span class="text-xs text-muted-foreground bg-muted px-2 py-1 rounded-full">v{{ workflow.version }}</span>
          </div>
          <p v-if="workflow.description" class="text-muted-foreground">{{ workflow.description }}</p>
          <div class="flex items-center gap-4 text-xs text-muted-foreground">
            <span class="flex items-center gap-1"><Zap class="w-3 h-3" />{{ workflow.trigger_type }}</span>
            <span class="flex items-center gap-1"><GitBranch class="w-3 h-3" />{{ workflow.step_count }} steps</span>
            <span class="flex items-center gap-1"><Clock class="w-3 h-3" />Timeout: {{ workflow.timeout_seconds }}s</span>
            <span v-if="workflow.cron_expression" class="flex items-center gap-1">
              <CalendarClock class="w-3 h-3" />{{ workflow.cron_expression }}
            </span>
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <button
            v-if="authStore.isEditor && workflow.is_active"
            @click="handleTrigger"
            :disabled="triggering"
            class="inline-flex items-center gap-2 bg-primary text-primary-foreground px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 disabled:opacity-60 transition-colors"
          >
            <Play class="w-4 h-4" />
            {{ triggering ? 'Starting…' : 'Run Now' }}
          </button>
          <RouterLink
            v-if="authStore.isEditor"
            :to="`/workflows/${workflow.id}/edit`"
            class="inline-flex items-center gap-2 border px-4 py-2 rounded-md text-sm font-medium hover:bg-accent transition-colors"
          >
            <Pencil class="w-4 h-4" /> Edit
          </RouterLink>
        </div>
      </div>

      <!-- Tabs -->
      <div class="border-b flex gap-6">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="[
            'pb-3 text-sm font-medium border-b-2 transition-colors',
            activeTab === tab.id
              ? 'border-primary text-primary'
              : 'border-transparent text-muted-foreground hover:text-foreground',
          ]"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Tab: DAG View -->
      <div v-if="activeTab === 'dag'" class="space-y-4">
        <div class="bg-card border rounded-xl p-5 space-y-4">
          <h3 class="font-semibold">Workflow Steps (DAG)</h3>
          <div class="space-y-2">
            <div
              v-for="(batch, batchIndex) in stepBatches"
              :key="batchIndex"
              class="space-y-2"
            >
              <!-- Batch label -->
              <div class="flex items-center gap-3">
                <span class="text-xs text-muted-foreground font-mono bg-muted px-2 py-0.5 rounded">
                  {{ batch.length > 1 ? `Parallel batch ${batchIndex + 1}` : `Step ${batchIndex + 1}` }}
                </span>
                <div class="flex-1 h-px bg-border" />
              </div>

              <!-- Steps in batch -->
              <div :class="['grid gap-3', batch.length > 1 ? 'grid-cols-2 lg:grid-cols-3' : 'grid-cols-1 max-w-md']">
                <div
                  v-for="step in batch"
                  :key="step.id"
                  class="border rounded-lg p-4 bg-background space-y-2"
                >
                  <div class="flex items-center gap-2">
                    <div :class="['w-8 h-8 rounded-md flex items-center justify-center text-xs font-bold shrink-0', stepTypeBg(step.type)]">
                      {{ step.type.charAt(0).toUpperCase() }}
                    </div>
                    <div class="min-w-0">
                      <p class="text-sm font-medium truncate">{{ step.name }}</p>
                      <p class="text-xs text-muted-foreground font-mono truncate">{{ step.id }}</p>
                    </div>
                  </div>

                  <div class="text-xs space-y-1 text-muted-foreground">
                    <p>Type: <span class="capitalize text-foreground">{{ step.type }}</span></p>
                    <p v-if="step.depends_on.length">
                      Depends on: <span class="text-foreground font-mono">{{ step.depends_on.join(', ') }}</span>
                    </p>
                    <p v-if="step.retry">
                      Retry: {{ step.retry.max_attempts }}x ({{ step.retry.backoff }})
                    </p>
                  </div>

                  <!-- Config preview -->
                  <div v-if="step.config" class="bg-muted rounded p-2 text-xs font-mono overflow-hidden">
                    <span v-if="step.type === 'http'">{{ (step.config as any).method }} {{ (step.config as any).url }}</span>
                    <span v-else-if="step.type === 'delay'">{{ (step.config as any).duration_ms }}ms</span>
                    <span v-else-if="step.type === 'script'">{{ (step.config as any).script }}</span>
                    <span v-else-if="step.condition">if: {{ step.condition }}</span>
                  </div>
                </div>
              </div>

              <!-- Arrow down (if not last batch) -->
              <div v-if="batchIndex < stepBatches.length - 1" class="flex justify-center py-1">
                <ArrowDown class="w-4 h-4 text-muted-foreground" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab: Run History -->
      <div v-if="activeTab === 'runs'" class="space-y-4">
        <div class="bg-card border rounded-xl overflow-hidden">
          <div class="px-5 py-4 border-b">
            <h3 class="font-semibold">Run History</h3>
          </div>
          <div v-if="loadingRuns" class="p-8 text-center text-muted-foreground text-sm">Loading…</div>
          <div v-else-if="runs.length === 0" class="p-8 text-center text-muted-foreground text-sm">No runs yet.</div>
          <div v-else class="divide-y">
            <div
              v-for="run in runs"
              :key="run.id"
              class="flex items-center gap-4 px-5 py-3 hover:bg-muted/30 cursor-pointer transition-colors"
              @click="router.push(`/runs/${run.id}`)"
            >
              <StatusDot :status="run.status" />
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <StatusBadge :status="run.status" />
                  <span class="text-xs text-muted-foreground">{{ run.trigger_type }}</span>
                  <span class="text-xs text-muted-foreground">v{{ run.workflow_version }}</span>
                </div>
                <p class="text-xs text-muted-foreground mt-0.5">{{ formatRelative(run.created_at) }}</p>
              </div>
              <div class="text-right text-xs text-muted-foreground">
                {{ formatDuration(run.duration_seconds) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab: Versions -->
      <div v-if="activeTab === 'versions'" class="space-y-4">
        <div class="bg-card border rounded-xl overflow-hidden">
          <div class="px-5 py-4 border-b flex items-center justify-between">
            <h3 class="font-semibold">Version History</h3>
          </div>
          <div class="divide-y">
            <div
              v-for="ver in workflow.versions"
              :key="ver.version"
              class="flex items-center gap-4 px-5 py-3"
            >
              <div :class="['w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0', ver.version === workflow.version ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground']">
                v{{ ver.version }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium">{{ ver.change_notes ?? 'No notes' }}</p>
                <p class="text-xs text-muted-foreground">{{ ver.created_by }} · {{ formatRelative(ver.created_at) }}</p>
              </div>
              <div v-if="authStore.isEditor && ver.version !== workflow.version">
                <button
                  @click="handleRollback(ver.version)"
                  :disabled="rollingBack"
                  class="text-xs text-muted-foreground border px-3 py-1.5 rounded-md hover:bg-accent transition-colors disabled:opacity-60"
                >
                  Rollback
                </button>
              </div>
              <span v-else-if="ver.version === workflow.version" class="text-xs text-primary font-medium">Current</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ArrowLeft, ArrowDown, Play, Pencil, GitBranch, Zap, Clock, CalendarClock } from 'lucide-vue-next'
import { formatDistanceToNow } from 'date-fns'
import { useWorkflowsStore } from '@/stores/workflows'
import { useAuthStore } from '@/stores/auth'
import { formatDuration } from '@/lib/utils'
import StatusDot from '@/components/workflow/StatusDot.vue'
import StatusBadge from '@/components/workflow/StatusBadge.vue'
import type { WorkflowRun } from '@/types'

const route = useRoute()
const router = useRouter()
const workflowsStore = useWorkflowsStore()
const authStore = useAuthStore()

const workflow = ref(workflowsStore.currentWorkflow)
const runs = ref<WorkflowRun[]>([])
const loading = ref(true)
const loadingRuns = ref(false)
const triggering = ref(false)
const rollingBack = ref(false)
const activeTab = ref('dag')

const tabs = [
  { id: 'dag', label: 'DAG View' },
  { id: 'runs', label: 'Run History' },
  { id: 'versions', label: 'Versions' },
]

// Group steps into parallel batches using topological sort
const stepBatches = computed(() => {
  const steps = workflow.value?.definition?.steps ?? []
  if (!steps.length) return []

  const inDegree: Record<string, number> = {}
  const dependents: Record<string, string[]> = {}
  const stepMap: Record<string, any> = {}

  for (const step of steps) {
    stepMap[step.id] = step
    inDegree[step.id] = step.depends_on.length
    dependents[step.id] = []
  }

  for (const step of steps) {
    for (const dep of step.depends_on) {
      if (dependents[dep]) dependents[dep].push(step.id)
    }
  }

  const batches: any[][] = []
  let queue = Object.keys(inDegree).filter(id => inDegree[id] === 0)

  while (queue.length) {
    batches.push(queue.map(id => stepMap[id]))
    const next: string[] = []
    for (const id of queue) {
      for (const dep of dependents[id]) {
        inDegree[dep]--
        if (inDegree[dep] === 0) next.push(dep)
      }
    }
    queue = next
  }

  return batches
})

function formatRelative(date: string) {
  return formatDistanceToNow(new Date(date), { addSuffix: true })
}

function stepTypeBg(type: string) {
  const map: Record<string, string> = {
    http: 'bg-blue-100 text-blue-700',
    script: 'bg-purple-100 text-purple-700',
    delay: 'bg-yellow-100 text-yellow-700',
    condition: 'bg-orange-100 text-orange-700',
  }
  return map[type] ?? 'bg-gray-100 text-gray-700'
}

async function handleTrigger() {
  if (!workflow.value) return
  triggering.value = true
  try {
    const run = await workflowsStore.triggerWorkflow(workflow.value.id)
    router.push(`/runs/${run.id}`)
  } finally {
    triggering.value = false
  }
}

async function handleRollback(version: number) {
  if (!workflow.value) return
  rollingBack.value = true
  try {
    await workflowsStore.updateWorkflow(workflow.value.id, { version } as any)
    await workflowsStore.fetchWorkflow(workflow.value.id)
    workflow.value = workflowsStore.currentWorkflow
  } finally {
    rollingBack.value = false
  }
}

onMounted(async () => {
  try {
    workflow.value = await workflowsStore.fetchWorkflow(route.params.id as string)

    // Load runs
    loadingRuns.value = true
    const runsData = await workflowsStore.fetchRuns(route.params.id as string)
    runs.value = runsData.data
  } finally {
    loading.value = false
    loadingRuns.value = false
  }
})
</script>
