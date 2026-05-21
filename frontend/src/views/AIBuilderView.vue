<template>
  <div class="p-6 max-w-3xl space-y-6">
    <div>
      <h2 class="text-xl font-bold flex items-center gap-2">
        <Sparkles class="w-5 h-5 text-primary" /> AI Workflow Builder
      </h2>
      <p class="text-sm text-muted-foreground">Describe your workflow in plain English and AI will generate the DAG definition.</p>
    </div>

    <!-- Input -->
    <div class="bg-card border rounded-xl p-5 space-y-4">
      <div class="space-y-2">
        <label class="text-sm font-medium">Describe your workflow</label>
        <textarea
          v-model="description"
          rows="5"
          placeholder="e.g. Fetch user data from our API, then send a welcome email, and finally log the result to our audit service."
          class="w-full px-3 py-2 bg-background border rounded-md text-sm resize-none focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
          :maxlength="2000"
        />
        <p class="text-xs text-muted-foreground text-right">{{ description.length }}/2000</p>
      </div>

      <button
        @click="generate"
        :disabled="generating || description.trim().length < 10"
        class="inline-flex items-center gap-2 bg-primary text-primary-foreground px-5 py-2.5 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors disabled:opacity-60"
      >
        <Sparkles class="w-4 h-4" />
        {{ generating ? 'Generating…' : 'Generate Workflow' }}
      </button>

      <p v-if="errorMsg" class="text-sm text-destructive">{{ errorMsg }}</p>
    </div>

    <!-- Generated Definition -->
    <template v-if="generated">
      <div class="bg-card border rounded-xl overflow-hidden space-y-0">
        <div class="px-5 py-4 border-b flex items-center justify-between bg-muted/30">
          <div>
            <h3 class="font-semibold">Generated Workflow</h3>
            <p class="text-xs text-muted-foreground">{{ generated.step_count }} steps • Review before saving</p>
          </div>
          <div class="flex gap-2">
            <button
              @click="saveWorkflow"
              :disabled="saving"
              class="inline-flex items-center gap-1.5 text-sm bg-primary text-primary-foreground px-4 py-2 rounded-md hover:bg-primary/90 transition-colors disabled:opacity-60"
            >
              <Save class="w-4 h-4" />{{ saving ? 'Saving…' : 'Save Workflow' }}
            </button>
          </div>
        </div>

        <!-- Step list preview -->
        <div class="p-5 space-y-3">
          <div
            v-for="step in generated.definition.steps"
            :key="step.id"
            class="flex items-start gap-3 p-3 border rounded-lg bg-background"
          >
            <div :class="['w-8 h-8 rounded-md flex items-center justify-center shrink-0 text-xs font-bold', stepTypeBg(step.type)]">
              {{ step.type.charAt(0).toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium">{{ step.name }}</p>
              <p class="text-xs text-muted-foreground font-mono">{{ step.id }}</p>
              <p class="text-xs text-muted-foreground mt-0.5">
                <span class="capitalize">{{ step.type }}</span>
                <span v-if="step.depends_on.length"> · depends on: {{ step.depends_on.join(', ') }}</span>
              </p>
            </div>
          </div>
        </div>

        <!-- Raw JSON toggle -->
        <div class="border-t px-5 py-3">
          <button @click="showJson = !showJson" class="text-xs text-muted-foreground hover:text-foreground flex items-center gap-1.5">
            <ChevronDown :class="['w-3 h-3 transition-transform', showJson && 'rotate-180']" />
            {{ showJson ? 'Hide' : 'Show' }} JSON definition
          </button>
          <pre v-if="showJson" class="mt-3 text-xs bg-muted rounded-md p-4 overflow-auto max-h-64 font-mono">{{ JSON.stringify(generated.definition, null, 2) }}</pre>
        </div>
      </div>

      <!-- Save dialog -->
      <div v-if="showSaveForm" class="bg-card border rounded-xl p-5 space-y-4">
        <h3 class="font-semibold">Save Workflow</h3>
        <div class="space-y-1.5">
          <label class="text-sm font-medium">Workflow Name</label>
          <input v-model="saveName" class="w-full px-3 py-2 bg-background border rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary/30" placeholder="My Workflow" />
        </div>
        <div class="flex gap-2">
          <button
            @click="confirmSave"
            :disabled="saving || !saveName.trim()"
            class="bg-primary text-primary-foreground px-4 py-2 rounded-md text-sm hover:bg-primary/90 disabled:opacity-60"
          >
            {{ saving ? 'Saving…' : 'Confirm Save' }}
          </button>
          <button @click="showSaveForm = false" class="px-4 py-2 rounded-md text-sm border hover:bg-accent">Cancel</button>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { Sparkles, Save, ChevronDown } from 'lucide-vue-next'
import { aiApi } from '@/api'
import { useWorkflowsStore } from '@/stores/workflows'
import type { AIGenerateResponse } from '@/types'

const router = useRouter()
const workflowsStore = useWorkflowsStore()

const description = ref('')
const generating = ref(false)
const saving = ref(false)
const generated = ref<AIGenerateResponse | null>(null)
const showJson = ref(false)
const showSaveForm = ref(false)
const saveName = ref('')
const errorMsg = ref('')

async function generate() {
  generating.value = true
  errorMsg.value = ''
  generated.value = null
  try {
    const { data } = await aiApi.generateWorkflow(description.value)
    generated.value = data
  } catch (e: any) {
    errorMsg.value = e.response?.data?.message || 'Generation failed. Please try again.'
  } finally {
    generating.value = false
  }
}

function saveWorkflow() {
  saveName.value = ''
  showSaveForm.value = true
}

async function confirmSave() {
  if (!generated.value) return
  saving.value = true
  try {
    const workflow = await workflowsStore.createWorkflow({
      name: saveName.value,
      definition: generated.value.definition,
      trigger_type: 'manual',
    })
    router.push(`/workflows/${workflow.id}`)
  } catch (e: any) {
    errorMsg.value = e.response?.data?.message || 'Save failed.'
  } finally {
    saving.value = false
  }
}

function stepTypeBg(type: string) {
  const map: Record<string, string> = {
    http: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30',
    script: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30',
    delay: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30',
    condition: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30',
  }
  return map[type] ?? 'bg-gray-100 text-gray-700'
}
</script>
