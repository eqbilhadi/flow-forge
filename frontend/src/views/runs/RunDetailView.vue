<template>
  <div class="p-6 space-y-6 max-w-5xl">
    <!-- Back -->
    <button @click="router.back()" class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
      <ArrowLeft class="w-4 h-4" /> Back
    </button>

    <div v-if="loading" class="text-center py-16 text-muted-foreground">Loading run…</div>

    <template v-else-if="run">
      <!-- Run Header -->
      <div class="bg-card border rounded-xl p-5 space-y-3">
        <div class="flex items-start justify-between">
          <div class="space-y-1">
            <div class="flex items-center gap-3">
              <h2 class="text-lg font-bold">{{ run.workflow?.name ?? 'Workflow Run' }}</h2>
              <StatusBadge :status="run.status" />
            </div>
            <p class="text-sm text-muted-foreground font-mono">{{ run.id }}</p>
          </div>
          <button
            v-if="run.status === 'running' || run.status === 'pending'"
            @click="cancelRun"
            class="text-sm text-destructive border border-destructive/30 px-3 py-1.5 rounded-md hover:bg-destructive/10 transition-colors"
          >
            Cancel
          </button>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-2 border-t text-sm">
          <div>
            <p class="text-muted-foreground text-xs">Trigger</p>
            <p class="font-medium capitalize">{{ run.trigger_type }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs">Version</p>
            <p class="font-medium">v{{ run.workflow_version }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs">Duration</p>
            <p class="font-medium">{{ formatDuration(run.duration_seconds) }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs">Started</p>
            <p class="font-medium">{{ run.started_at ? formatRelative(run.started_at) : '—' }}</p>
          </div>
        </div>

        <div v-if="run.error_message" class="bg-destructive/10 border border-destructive/20 rounded-md px-4 py-3 text-sm text-destructive">
          <p class="font-medium">Error</p>
          <p class="font-mono text-xs mt-0.5">{{ run.error_message }}</p>
          <button
            v-if="run.status === 'failed'"
            @click="analyzeFailure"
            :disabled="analyzingFailure"
            class="mt-2 text-xs bg-destructive/10 hover:bg-destructive/20 px-3 py-1.5 rounded transition-colors inline-flex items-center gap-1.5"
          >
            <Sparkles class="w-3 h-3" />
            {{ analyzingFailure ? 'Analyzing…' : 'AI Diagnosis' }}
          </button>
        </div>

        <div v-if="aiAnalysis" class="bg-muted rounded-md px-4 py-3 text-sm space-y-1">
          <p class="font-medium flex items-center gap-1.5"><Sparkles class="w-3.5 h-3.5 text-primary" /> AI Diagnosis</p>
          <p class="text-muted-foreground whitespace-pre-wrap">{{ aiAnalysis }}</p>
        </div>
      </div>

      <!-- Step Timeline -->
      <div class="bg-card border rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b flex items-center justify-between">
          <h3 class="font-semibold">Step Execution Timeline</h3>
          <span v-if="connected" class="text-xs flex items-center gap-1.5 text-green-600">
            <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse" /> Live
          </span>
        </div>

        <div v-if="!run.step_runs?.length" class="p-8 text-center text-muted-foreground text-sm">
          No steps executed yet.
        </div>
        <div v-else class="p-4 space-y-2">
          <div
            v-for="step in run.step_runs"
            :key="step.id"
            class="flex items-center gap-4 p-3 rounded-lg border bg-background"
          >
            <!-- Status icon -->
            <div :class="['w-8 h-8 rounded-full flex items-center justify-center shrink-0', stepBg(step.status)]">
              <component :is="stepIcon(step.status)" :class="['w-4 h-4', stepIconColor(step.status)]" />
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <p class="text-sm font-medium">{{ step.step_name }}</p>
                <span class="text-xs text-muted-foreground capitalize bg-muted px-1.5 py-0.5 rounded">{{ step.step_type }}</span>
              </div>
              <p v-if="step.error_message" class="text-xs text-destructive font-mono mt-0.5 truncate">{{ step.error_message }}</p>
              <p v-if="step.retry_count > 0" class="text-xs text-muted-foreground">
                {{ step.retry_count }} {{ step.retry_count === 1 ? 'retry' : 'retries' }}
              </p>
            </div>

            <div class="text-right shrink-0 space-y-0.5">
              <StatusBadge :status="step.status" />
              <p class="text-xs text-muted-foreground">{{ formatDurationMs(step.duration_ms) }}</p>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, CheckCircle, XCircle, Clock, Loader, Sparkles, SkipForward } from 'lucide-vue-next'
import { formatDistanceToNow } from 'date-fns'
import { useWorkflowsStore } from '@/stores/workflows'
import { runsApi } from '@/api'
import { useRunChannel } from '@/composables/useEcho'
import { formatDuration, formatDurationMs } from '@/lib/utils'
import StatusBadge from '@/components/workflow/StatusBadge.vue'

const route = useRoute()
const router = useRouter()
const workflowsStore = useWorkflowsStore()

const run = ref(workflowsStore.currentRun)
const loading = ref(true)
const aiAnalysis = ref<string | null>(null)
const analyzingFailure = ref(false)
const { connected } = useRunChannel(route.params.id as string)

onMounted(async () => {
  try {
    run.value = await workflowsStore.fetchRun(route.params.id as string)
  } finally {
    loading.value = false
  }
})

// Keep run in sync with store
import { watch } from 'vue'
watch(() => workflowsStore.currentRun, (val) => { if (val) run.value = val }, { deep: true })

function formatRelative(date: string) {
  return formatDistanceToNow(new Date(date), { addSuffix: true })
}

async function cancelRun() {
  if (!run.value) return
  await workflowsStore.cancelRun(run.value.id)
}

async function analyzeFailure() {
  if (!run.value) return
  analyzingFailure.value = true
  try {
    const { data } = await runsApi.analyzeFailure(run.value.id)
    aiAnalysis.value = data.analysis
  } finally {
    analyzingFailure.value = false
  }
}

function stepBg(status: string) {
  const map: Record<string, string> = {
    success: 'bg-green-100 dark:bg-green-900/20',
    failed: 'bg-red-100 dark:bg-red-900/20',
    running: 'bg-blue-100 dark:bg-blue-900/20',
    pending: 'bg-gray-100 dark:bg-gray-800',
    skipped: 'bg-gray-100 dark:bg-gray-800',
    retrying: 'bg-purple-100 dark:bg-purple-900/20',
  }
  return map[status] ?? 'bg-gray-100'
}

function stepIcon(status: string) {
  const map: Record<string, any> = {
    success: CheckCircle,
    failed: XCircle,
    running: Loader,
    pending: Clock,
    skipped: SkipForward,
    retrying: Loader,
  }
  return map[status] ?? Clock
}

function stepIconColor(status: string) {
  const map: Record<string, string> = {
    success: 'text-green-600',
    failed: 'text-red-600',
    running: 'text-blue-600 animate-spin',
    pending: 'text-gray-400',
    skipped: 'text-gray-400',
    retrying: 'text-purple-600 animate-spin',
  }
  return map[status] ?? 'text-gray-400'
}
</script>
