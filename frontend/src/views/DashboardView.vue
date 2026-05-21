<template>
  <div class="p-6 space-y-6">
    <!-- Health Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="card in healthCards" :key="card.label" class="bg-card border rounded-xl p-4 space-y-2">
        <div class="flex items-center justify-between">
          <p class="text-sm text-muted-foreground">{{ card.label }}</p>
          <component :is="card.icon" :class="['w-4 h-4', card.iconClass]" />
        </div>
        <p class="text-2xl font-bold">{{ card.value }}</p>
        <p v-if="card.sub" class="text-xs text-muted-foreground">{{ card.sub }}</p>
      </div>
    </div>

    <!-- Success Rate Bar -->
    <div v-if="health" class="bg-card border rounded-xl p-5 space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="font-semibold">Success Rate (24h)</h3>
        <span :class="['text-sm font-medium', successRateColor]">
          {{ health.success_rate !== null ? `${health.success_rate}%` : 'N/A' }}
        </span>
      </div>
      <div class="w-full bg-muted rounded-full h-2.5 overflow-hidden">
        <div
          class="h-full rounded-full transition-all duration-700"
          :class="successRateBarClass"
          :style="{ width: `${health.success_rate ?? 0}%` }"
        />
      </div>
      <div class="flex gap-4 text-xs text-muted-foreground">
        <span class="flex items-center gap-1"><span class="w-2 h-2 bg-green-500 rounded-full" />{{ health.success_count }} succeeded</span>
        <span class="flex items-center gap-1"><span class="w-2 h-2 bg-red-500 rounded-full" />{{ health.failed_count }} failed</span>
        <span class="flex items-center gap-1"><span class="w-2 h-2 bg-blue-500 rounded-full" />{{ health.active_runs }} active</span>
      </div>
    </div>

    <!-- Recent Runs -->
    <div class="bg-card border rounded-xl overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 border-b">
        <h3 class="font-semibold">Recent Runs</h3>
        <span class="text-xs text-muted-foreground">Auto-refreshes via WebSocket</span>
      </div>

      <div v-if="loadingRuns" class="p-8 text-center text-muted-foreground text-sm">Loading…</div>
      <div v-else-if="recentRuns.length === 0" class="p-8 text-center text-muted-foreground text-sm">
        No runs yet. <RouterLink to="/workflows" class="text-primary hover:underline">Trigger a workflow</RouterLink>
      </div>
      <div v-else class="divide-y">
        <div
          v-for="run in recentRuns"
          :key="run.id"
          class="flex items-center gap-4 px-5 py-3 hover:bg-muted/30 transition-colors cursor-pointer"
          @click="router.push(`/runs/${run.id}`)"
        >
          <StatusDot :status="run.status" />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium truncate">{{ run.workflow?.name ?? 'Unknown Workflow' }}</p>
            <p class="text-xs text-muted-foreground">
              {{ run.trigger_type }} · {{ formatRelative(run.created_at) }}
            </p>
          </div>
          <div class="text-right shrink-0">
            <StatusBadge :status="run.status" />
            <p class="text-xs text-muted-foreground mt-0.5">
              {{ formatDuration(run.duration_seconds) }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { Activity, CheckCircle, XCircle, Clock } from 'lucide-vue-next'
import { formatDistanceToNow } from 'date-fns'
import { dashboardApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { useTenantChannel } from '@/composables/useEcho'
import { formatDuration } from '@/lib/utils'
import StatusDot from '@/components/workflow/StatusDot.vue'
import StatusBadge from '@/components/workflow/StatusBadge.vue'
import type { DashboardHealth, WorkflowRun, RunUpdatedEvent } from '@/types'

const authStore = useAuthStore()
const router = useRouter()
const health = ref<DashboardHealth | null>(null)
const recentRuns = ref<WorkflowRun[]>([])
const loadingRuns = ref(true)

const healthCards = computed(() => [
  {
    label: 'Active Runs',
    value: health.value?.active_runs ?? '—',
    icon: Activity,
    iconClass: 'text-blue-500',
    sub: 'Currently executing',
  },
  {
    label: 'Total Runs (24h)',
    value: health.value?.total_runs ?? '—',
    icon: Clock,
    iconClass: 'text-gray-400',
    sub: 'Last 24 hours',
  },
  {
    label: 'Succeeded',
    value: health.value?.success_count ?? '—',
    icon: CheckCircle,
    iconClass: 'text-green-500',
    sub: health.value?.success_rate !== null ? `${health.value?.success_rate}% success rate` : '',
  },
  {
    label: 'Failed',
    value: health.value?.failed_count ?? '—',
    icon: XCircle,
    iconClass: 'text-red-500',
    sub: health.value?.avg_duration_seconds ? `Avg ${formatDuration(health.value.avg_duration_seconds)}` : '',
  },
])

const successRateColor = computed(() => {
  const r = health.value?.success_rate ?? 0
  if (r >= 90) return 'text-green-500'
  if (r >= 70) return 'text-yellow-500'
  return 'text-red-500'
})

const successRateBarClass = computed(() => {
  const r = health.value?.success_rate ?? 0
  if (r >= 90) return 'bg-green-500'
  if (r >= 70) return 'bg-yellow-500'
  return 'bg-red-500'
})

function formatRelative(date: string) {
  return formatDistanceToNow(new Date(date), { addSuffix: true })
}

async function loadData() {
  loadingRuns.value = true
  try {
    const [healthRes, runsRes] = await Promise.all([
      dashboardApi.health(),
      dashboardApi.recentRuns(),
    ])
    health.value = healthRes.data
    recentRuns.value = runsRes.data.data
  } finally {
    loadingRuns.value = false
  }
}

// Real-time: update run in list when status changes
function handleRunUpdate(event: RunUpdatedEvent) {
  const index = recentRuns.value.findIndex(r => r.id === event.id)
  if (index !== -1) {
    recentRuns.value[index] = { ...recentRuns.value[index], ...event }
  }
  // Refresh health counters
  dashboardApi.health().then(r => { health.value = r.data })
}

onMounted(() => {
  loadData()
  if (authStore.tenantId) {
    useTenantChannel(authStore.tenantId, handleRunUpdate)
  }
})
</script>
