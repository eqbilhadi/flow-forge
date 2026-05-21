import { defineStore } from 'pinia'
import { ref } from 'vue'
import { workflowsApi, runsApi } from '@/api'
import type { Workflow, WorkflowRun, PaginatedResponse } from '@/types'

export const useWorkflowsStore = defineStore('workflows', () => {
  const workflows = ref<Workflow[]>([])
  const currentWorkflow = ref<Workflow | null>(null)
  const runs = ref<WorkflowRun[]>([])
  const currentRun = ref<WorkflowRun | null>(null)
  const pagination = ref<PaginatedResponse<Workflow>['meta'] | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchWorkflows(params?: Record<string, unknown>) {
    loading.value = true
    error.value = null
    try {
      const { data } = await workflowsApi.list(params)
      workflows.value = data.data
      pagination.value = data.meta
    } catch (e: any) {
      error.value = e.response?.data?.message || 'Failed to load workflows'
    } finally {
      loading.value = false
    }
  }

  async function fetchWorkflow(id: string) {
    loading.value = true
    try {
      const { data } = await workflowsApi.get(id)
      currentWorkflow.value = data.workflow
      return data.workflow
    } finally {
      loading.value = false
    }
  }

  async function createWorkflow(payload: Partial<Workflow>) {
    const { data } = await workflowsApi.create(payload)
    workflows.value.unshift(data.workflow)
    return data.workflow
  }

  async function updateWorkflow(id: string, payload: Partial<Workflow>) {
    const { data } = await workflowsApi.update(id, payload)
    const index = workflows.value.findIndex(w => w.id === id)
    if (index !== -1) workflows.value[index] = data.workflow
    if (currentWorkflow.value?.id === id) currentWorkflow.value = data.workflow
    return data.workflow
  }

  async function deleteWorkflow(id: string) {
    await workflowsApi.delete(id)
    workflows.value = workflows.value.filter(w => w.id !== id)
    if (currentWorkflow.value?.id === id) currentWorkflow.value = null
  }

  async function triggerWorkflow(id: string, inputData?: Record<string, unknown>) {
    const { data } = await workflowsApi.trigger(id, inputData)
    return data.run
  }

  async function fetchRuns(workflowId: string) {
    const { data } = await workflowsApi.runs(workflowId)
    runs.value = data.data
    return data
  }

  async function fetchRun(runId: string) {
    const { data } = await runsApi.get(runId)
    currentRun.value = data.run
    return data.run
  }

  async function cancelRun(runId: string) {
    await runsApi.cancel(runId)
    if (currentRun.value?.id === runId) {
      currentRun.value = { ...currentRun.value, status: 'cancelled' }
    }
  }

  // Real-time update from WebSocket
  function updateRunFromEvent(event: Partial<WorkflowRun> & { id: string }) {
    if (currentRun.value?.id === event.id) {
      currentRun.value = { ...currentRun.value, ...event }
    }
    const runIndex = runs.value.findIndex(r => r.id === event.id)
    if (runIndex !== -1) {
      runs.value[runIndex] = { ...runs.value[runIndex], ...event }
    }
  }

  function updateStepFromEvent(event: any) {
    if (!currentRun.value) return
    const stepRuns = currentRun.value.step_runs ?? []
    const stepIndex = stepRuns.findIndex(s => s.id === event.id)
    if (stepIndex !== -1) {
      currentRun.value.step_runs![stepIndex] = { ...stepRuns[stepIndex], ...event }
    } else {
      if (!currentRun.value.step_runs) currentRun.value.step_runs = []
      currentRun.value.step_runs.push(event)
    }
  }

  return {
    workflows,
    currentWorkflow,
    runs,
    currentRun,
    pagination,
    loading,
    error,
    fetchWorkflows,
    fetchWorkflow,
    createWorkflow,
    updateWorkflow,
    deleteWorkflow,
    triggerWorkflow,
    fetchRuns,
    fetchRun,
    cancelRun,
    updateRunFromEvent,
    updateStepFromEvent,
  }
})
