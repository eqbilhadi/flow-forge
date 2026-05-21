<template>
  <div class="p-6 max-w-4xl space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
      <button @click="router.back()" class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft class="w-4 h-4" /> Back
      </button>
      <div>
        <h2 class="text-xl font-bold">{{ isEdit ? 'Edit Workflow' : 'New Workflow' }}</h2>
        <p class="text-sm text-muted-foreground">{{ isEdit ? `Editing v${workflow?.version} — saving creates a new version` : 'Define your workflow steps and configuration' }}</p>
      </div>
    </div>

    <form @submit.prevent="handleSave" class="space-y-6">
      <!-- Basic Info -->
      <div class="bg-card border rounded-xl p-5 space-y-4">
        <h3 class="font-semibold">Basic Information</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="text-sm font-medium">Workflow Name <span class="text-destructive">*</span></label>
            <input
              v-model="form.name"
              type="text"
              required
              placeholder="e.g. Daily Data Sync"
              class="w-full px-3 py-2 bg-background border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
            />
          </div>

          <div class="space-y-1.5">
            <label class="text-sm font-medium">Trigger Type</label>
            <select
              v-model="form.trigger_type"
              class="w-full px-3 py-2 bg-background border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
              <option value="manual">Manual</option>
              <option value="schedule">Schedule (Cron)</option>
              <option value="webhook">Webhook</option>
            </select>
          </div>
        </div>

        <div class="space-y-1.5">
          <label class="text-sm font-medium">Description</label>
          <textarea
            v-model="form.description"
            rows="2"
            placeholder="Optional description of what this workflow does"
            class="w-full px-3 py-2 bg-background border rounded-md text-sm resize-none focus:outline-none focus:ring-2 focus:ring-primary/30"
          />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-if="form.trigger_type === 'schedule'" class="space-y-1.5">
            <label class="text-sm font-medium">Cron Expression</label>
            <input
              v-model="form.cron_expression"
              type="text"
              placeholder="0 9 * * 1-5"
              class="w-full px-3 py-2 bg-background border rounded-md text-sm font-mono focus:outline-none focus:ring-2 focus:ring-primary/30"
            />
            <p class="text-xs text-muted-foreground">e.g. <code>0 9 * * 1-5</code> = weekdays at 9am</p>
          </div>

          <div class="space-y-1.5">
            <label class="text-sm font-medium">Timeout (seconds)</label>
            <input
              v-model.number="form.timeout_seconds"
              type="number"
              min="1"
              max="86400"
              class="w-full px-3 py-2 bg-background border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/30"
            />
          </div>
        </div>

        <div class="flex items-center gap-3">
          <button
            type="button"
            @click="form.is_active = !form.is_active"
            :class="['relative w-10 h-6 rounded-full transition-colors', form.is_active ? 'bg-primary' : 'bg-muted']"
          >
            <span :class="['absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform', form.is_active ? 'translate-x-5' : 'translate-x-1']" />
          </button>
          <label class="text-sm font-medium cursor-pointer" @click="form.is_active = !form.is_active">
            {{ form.is_active ? 'Active' : 'Inactive' }}
          </label>
        </div>
      </div>

      <!-- Steps Builder -->
      <div class="bg-card border rounded-xl p-5 space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="font-semibold">Workflow Steps</h3>
            <p class="text-xs text-muted-foreground">Define the DAG — each step can depend on previous steps</p>
          </div>
          <button
            type="button"
            @click="addStep"
            class="inline-flex items-center gap-2 text-sm bg-primary/10 text-primary px-3 py-1.5 rounded-md hover:bg-primary/20 transition-colors"
          >
            <Plus class="w-4 h-4" /> Add Step
          </button>
        </div>

        <div v-if="form.steps.length === 0" class="text-center py-8 border-2 border-dashed rounded-xl text-muted-foreground text-sm">
          No steps yet. Click "Add Step" to get started.
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="(step, index) in form.steps"
            :key="step._key"
            class="border rounded-lg p-4 bg-background space-y-3"
          >
            <!-- Step Header -->
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div :class="['w-7 h-7 rounded-md flex items-center justify-center text-xs font-bold', stepTypeBg(step.type)]">
                  {{ step.type.charAt(0).toUpperCase() }}
                </div>
                <span class="text-sm font-medium">Step {{ index + 1 }}</span>
              </div>
              <button type="button" @click="removeStep(index)" class="text-muted-foreground hover:text-destructive transition-colors">
                <Trash2 class="w-4 h-4" />
              </button>
            </div>

            <!-- Step Fields -->
            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-1">
                <label class="text-xs font-medium">Step ID <span class="text-destructive">*</span></label>
                <input
                  v-model="step.id"
                  type="text"
                  placeholder="fetch_data"
                  class="w-full px-2.5 py-1.5 bg-card border rounded text-xs font-mono focus:outline-none focus:ring-2 focus:ring-primary/30"
                />
              </div>
              <div class="space-y-1">
                <label class="text-xs font-medium">Step Name <span class="text-destructive">*</span></label>
                <input
                  v-model="step.name"
                  type="text"
                  placeholder="Fetch Data"
                  class="w-full px-2.5 py-1.5 bg-card border rounded text-xs focus:outline-none focus:ring-2 focus:ring-primary/30"
                />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-1">
                <label class="text-xs font-medium">Type</label>
                <select
                  v-model="step.type"
                  class="w-full px-2.5 py-1.5 bg-card border rounded text-xs focus:outline-none focus:ring-2 focus:ring-primary/30"
                >
                  <option value="http">HTTP Request</option>
                  <option value="script">Script</option>
                  <option value="delay">Delay</option>
                  <option value="condition">Condition</option>
                </select>
              </div>
              <div class="space-y-1">
                <label class="text-xs font-medium">Depends On</label>
                <select
                  v-model="step.depends_on"
                  multiple
                  class="w-full px-2.5 py-1.5 bg-card border rounded text-xs focus:outline-none focus:ring-2 focus:ring-primary/30 min-h-[32px]"
                >
                  <option
                    v-for="other in form.steps.filter((_, i) => i !== index)"
                    :key="other._key"
                    :value="other.id"
                  >{{ other.id || `step_${index}` }}</option>
                </select>
              </div>
            </div>

            <!-- HTTP Config -->
            <template v-if="step.type === 'http'">
              <div class="grid grid-cols-3 gap-3">
                <div class="space-y-1">
                  <label class="text-xs font-medium">Method</label>
                  <select v-model="step.config.method" class="w-full px-2.5 py-1.5 bg-card border rounded text-xs focus:outline-none focus:ring-2 focus:ring-primary/30">
                    <option>GET</option><option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option>
                  </select>
                </div>
                <div class="col-span-2 space-y-1">
                  <label class="text-xs font-medium">URL <span class="text-destructive">*</span></label>
                  <input v-model="step.config.url" type="text" placeholder="https://api.example.com/data" class="w-full px-2.5 py-1.5 bg-card border rounded text-xs font-mono focus:outline-none focus:ring-2 focus:ring-primary/30" />
                </div>
              </div>
              <div class="space-y-1">
                <label class="text-xs font-medium">Request Body (JSON)</label>
                <textarea v-model="step.config.bodyRaw" rows="2" placeholder='{"key": "value"}' class="w-full px-2.5 py-1.5 bg-card border rounded text-xs font-mono resize-none focus:outline-none focus:ring-2 focus:ring-primary/30" />
              </div>
            </template>

            <!-- Script Config -->
            <template v-else-if="step.type === 'script'">
              <div class="space-y-1">
                <label class="text-xs font-medium">Script Command <span class="text-destructive">*</span></label>
                <input v-model="step.config.script" type="text" placeholder="echo Hello World" class="w-full px-2.5 py-1.5 bg-card border rounded text-xs font-mono focus:outline-none focus:ring-2 focus:ring-primary/30" />
                <p class="text-xs text-muted-foreground">Allowed: echo, date, printf, expr</p>
              </div>
            </template>

            <!-- Delay Config -->
            <template v-else-if="step.type === 'delay'">
              <div class="space-y-1">
                <label class="text-xs font-medium">Duration (milliseconds) <span class="text-destructive">*</span></label>
                <input v-model.number="step.config.duration_ms" type="number" min="0" placeholder="1000" class="w-full px-2.5 py-1.5 bg-card border rounded text-xs focus:outline-none focus:ring-2 focus:ring-primary/30" />
              </div>
            </template>

            <!-- Condition Config -->
            <template v-else-if="step.type === 'condition'">
              <div class="space-y-1">
                <label class="text-xs font-medium">Condition Expression <span class="text-destructive">*</span></label>
                <input v-model="step.condition" type="text" placeholder="{{steps.step_id.output.field}} == value" class="w-full px-2.5 py-1.5 bg-card border rounded text-xs font-mono focus:outline-none focus:ring-2 focus:ring-primary/30" />
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                  <label class="text-xs font-medium">On True (step ID)</label>
                  <input v-model="step.on_true" type="text" placeholder="next_step_id" class="w-full px-2.5 py-1.5 bg-card border rounded text-xs font-mono focus:outline-none focus:ring-2 focus:ring-primary/30" />
                </div>
                <div class="space-y-1">
                  <label class="text-xs font-medium">On False (step ID)</label>
                  <input v-model="step.on_false" type="text" placeholder="fallback_step_id" class="w-full px-2.5 py-1.5 bg-card border rounded text-xs font-mono focus:outline-none focus:ring-2 focus:ring-primary/30" />
                </div>
              </div>
            </template>

            <!-- Retry Config -->
            <details class="text-xs">
              <summary class="cursor-pointer text-muted-foreground hover:text-foreground select-none">Retry settings</summary>
              <div class="grid grid-cols-3 gap-3 mt-2">
                <div class="space-y-1">
                  <label class="text-xs font-medium">Max Attempts</label>
                  <input v-model.number="step.retry.max_attempts" type="number" min="1" max="10" class="w-full px-2.5 py-1.5 bg-card border rounded text-xs focus:outline-none" />
                </div>
                <div class="space-y-1">
                  <label class="text-xs font-medium">Backoff</label>
                  <select v-model="step.retry.backoff" class="w-full px-2.5 py-1.5 bg-card border rounded text-xs focus:outline-none">
                    <option value="exponential">Exponential</option>
                    <option value="linear">Linear</option>
                    <option value="fixed">Fixed</option>
                  </select>
                </div>
                <div class="space-y-1">
                  <label class="text-xs font-medium">Base Delay (ms)</label>
                  <input v-model.number="step.retry.base_delay_ms" type="number" min="100" class="w-full px-2.5 py-1.5 bg-card border rounded text-xs focus:outline-none" />
                </div>
              </div>
            </details>
          </div>
        </div>
      </div>

      <!-- Change Notes (edit mode) -->
      <div v-if="isEdit" class="bg-card border rounded-xl p-5 space-y-2">
        <label class="text-sm font-medium">Change Notes</label>
        <input
          v-model="form.change_notes"
          type="text"
          placeholder="What changed in this version?"
          class="w-full px-3 py-2 bg-background border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/30"
        />
      </div>

      <!-- Error -->
      <p v-if="errorMsg" class="text-sm text-destructive bg-destructive/10 px-4 py-3 rounded-md">{{ errorMsg }}</p>

      <!-- Actions -->
      <div class="flex items-center gap-3">
        <button
          type="submit"
          :disabled="saving"
          class="inline-flex items-center gap-2 bg-primary text-primary-foreground px-6 py-2.5 rounded-md text-sm font-medium hover:bg-primary/90 disabled:opacity-60 transition-colors"
        >
          <Save class="w-4 h-4" />
          {{ saving ? 'Saving…' : (isEdit ? 'Save Changes' : 'Create Workflow') }}
        </button>
        <button type="button" @click="router.back()" class="px-4 py-2.5 border rounded-md text-sm hover:bg-accent transition-colors">
          Cancel
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Plus, Trash2, Save } from 'lucide-vue-next'
import { useWorkflowsStore } from '@/stores/workflows'

const route = useRoute()
const router = useRouter()
const workflowsStore = useWorkflowsStore()

const isEdit = computed(() => !!route.params.id)
const workflow = ref(workflowsStore.currentWorkflow)
const saving = ref(false)
const errorMsg = ref('')

// Internal step type with extra fields for the form
interface FormStep {
  _key: string
  id: string
  name: string
  type: string
  depends_on: string[]
  condition?: string
  on_true?: string
  on_false?: string
  config: {
    url?: string
    method?: string
    bodyRaw?: string
    script?: string
    duration_ms?: number
    [key: string]: any
  }
  retry: {
    max_attempts: number
    backoff: string
    base_delay_ms: number
  }
}

const form = ref({
  name: '',
  description: '',
  trigger_type: 'manual',
  cron_expression: '',
  timeout_seconds: 3600,
  is_active: true,
  change_notes: '',
  steps: [] as FormStep[],
})

function makeStep(): FormStep {
  return {
    _key: Math.random().toString(36).slice(2),
    id: '',
    name: '',
    type: 'http',
    depends_on: [],
    config: { method: 'GET', url: '' },
    retry: { max_attempts: 1, backoff: 'exponential', base_delay_ms: 1000 },
  }
}

function addStep() {
  form.value.steps.push(makeStep())
}

function removeStep(index: number) {
  form.value.steps.splice(index, 1)
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

function buildDefinition() {
  return {
    steps: form.value.steps.map(step => {
      const config: any = { ...step.config }

      // Parse body JSON for http steps
      if (step.type === 'http' && step.config.bodyRaw) {
        try { config.body = JSON.parse(step.config.bodyRaw) } catch {}
        delete config.bodyRaw
      }

      const base: any = {
        id: step.id,
        name: step.name,
        type: step.type,
        config,
        depends_on: step.depends_on,
        retry: step.retry,
      }

      if (step.type === 'condition') {
        base.condition = step.condition
        base.on_true = step.on_true || null
        base.on_false = step.on_false || null
      }

      return base
    }),
  }
}

async function handleSave() {
  if (form.value.steps.length === 0) {
    errorMsg.value = 'Add at least one step.'
    return
  }

  saving.value = true
  errorMsg.value = ''

  try {
    const payload: any = {
      name: form.value.name,
      description: form.value.description || null,
      trigger_type: form.value.trigger_type,
      cron_expression: form.value.cron_expression || null,
      timeout_seconds: form.value.timeout_seconds,
      is_active: form.value.is_active,
      definition: buildDefinition(),
    }

    if (isEdit.value) {
      payload.change_notes = form.value.change_notes
      await workflowsStore.updateWorkflow(route.params.id as string, payload)
      router.push(`/workflows/${route.params.id}`)
    } else {
      const created = await workflowsStore.createWorkflow(payload)
      router.push(`/workflows/${created.id}`)
    }
  } catch (e: any) {
    errorMsg.value = e.response?.data?.message || 'Failed to save workflow.'
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  if (isEdit.value) {
    const wf = await workflowsStore.fetchWorkflow(route.params.id as string)
    workflow.value = wf

    form.value.name = wf.name
    form.value.description = wf.description ?? ''
    form.value.trigger_type = wf.trigger_type
    form.value.cron_expression = wf.cron_expression ?? ''
    form.value.timeout_seconds = wf.timeout_seconds
    form.value.is_active = wf.is_active

    form.value.steps = wf.definition.steps.map(step => ({
      _key: Math.random().toString(36).slice(2),
      id: step.id,
      name: step.name,
      type: step.type,
      depends_on: step.depends_on,
      condition: step.condition,
      on_true: step.on_true ?? undefined,
      on_false: step.on_false ?? undefined,
      config: {
        ...step.config,
        bodyRaw: (step.config as any).body ? JSON.stringify((step.config as any).body, null, 2) : undefined,
      },
      retry: step.retry ?? { max_attempts: 1, backoff: 'exponential', base_delay_ms: 1000 },
    }))
  }
})
</script>
