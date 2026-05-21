// ============================================================
// FlowForge Frontend — TypeScript Types
// ============================================================

export type UserRole = 'admin' | 'editor' | 'viewer'
export type StepType = 'http' | 'script' | 'delay' | 'condition'
export type StepStatus = 'pending' | 'running' | 'success' | 'failed' | 'skipped' | 'retrying'
export type WorkflowRunStatus = 'pending' | 'running' | 'success' | 'failed' | 'timeout' | 'cancelled'
export type TriggerType = 'manual' | 'schedule' | 'webhook'

// ──────────────────────────────────────────────────────────────
// Auth
// ──────────────────────────────────────────────────────────────

export interface Tenant {
  id: string
  name: string
  slug: string
}

export interface User {
  id: string
  name: string
  email: string
  role: UserRole
  tenant: Tenant
}

export interface AuthResponse {
  user: User
  token: string
  token_type: 'bearer'
  expires_in: number
}

// ──────────────────────────────────────────────────────────────
// Workflow Definition (DAG)
// ──────────────────────────────────────────────────────────────

export interface RetryConfig {
  max_attempts: number
  backoff: 'exponential' | 'linear' | 'fixed'
  base_delay_ms: number
}

export interface HttpStepConfig {
  url: string
  method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  headers?: Record<string, string>
  body?: Record<string, unknown>
  timeout?: number
}

export interface ScriptStepConfig {
  script: string
  timeout?: number
}

export interface DelayStepConfig {
  duration_ms: number
}

export interface ConditionStepConfig {
  [key: string]: unknown
}

export type StepConfig = HttpStepConfig | ScriptStepConfig | DelayStepConfig | ConditionStepConfig

export interface WorkflowStep {
  id: string
  name: string
  type: StepType
  config: StepConfig
  depends_on: string[]
  retry?: RetryConfig
  condition?: string
  on_true?: string | null
  on_false?: string | null
}

export interface WorkflowDefinition {
  steps: WorkflowStep[]
}

// ──────────────────────────────────────────────────────────────
// Workflow
// ──────────────────────────────────────────────────────────────

export interface WorkflowVersion {
  version: number
  change_notes: string | null
  created_by: string
  created_at: string
}

export interface Workflow {
  id: string
  name: string
  description: string | null
  definition: WorkflowDefinition
  version: number
  is_active: boolean
  trigger_type: TriggerType
  cron_expression: string | null
  timeout_seconds: number
  tags: string[]
  step_count: number
  creator?: { id: string; name: string; email: string }
  versions?: WorkflowVersion[]
  created_at: string
  updated_at: string
}

// ──────────────────────────────────────────────────────────────
// Workflow Runs
// ──────────────────────────────────────────────────────────────

export interface StepRun {
  id: string
  step_id: string
  step_name: string
  step_type: StepType
  status: StepStatus
  retry_count: number
  duration_ms: number | null
  error_message: string | null
  started_at: string | null
  completed_at: string | null
}

export interface WorkflowRun {
  id: string
  workflow_id: string
  workflow?: { id: string; name: string }
  status: WorkflowRunStatus
  trigger_type: TriggerType
  workflow_version: number
  triggered_by?: { id: string; name: string } | null
  input_data?: Record<string, unknown>
  output_data?: Record<string, unknown>
  error_message: string | null
  duration_seconds: number | null
  started_at: string | null
  completed_at: string | null
  timeout_at: string | null
  step_runs?: StepRun[]
  created_at: string
}

// ──────────────────────────────────────────────────────────────
// Dashboard
// ──────────────────────────────────────────────────────────────

export interface DashboardHealth {
  period: '24h'
  active_runs: number
  total_runs: number
  success_count: number
  failed_count: number
  success_rate: number | null
  avg_duration_seconds: number | null
}

// ──────────────────────────────────────────────────────────────
// API Pagination
// ──────────────────────────────────────────────────────────────

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  links: {
    first: string
    last: string
    prev: string | null
    next: string | null
  }
}

// ──────────────────────────────────────────────────────────────
// WebSocket Events
// ──────────────────────────────────────────────────────────────

export interface RunUpdatedEvent {
  id: string
  workflow_id: string
  status: WorkflowRunStatus
  trigger_type: TriggerType
  started_at: string | null
  completed_at: string | null
  duration_seconds: number | null
  error_message: string | null
}

export interface StepUpdatedEvent {
  id: string
  workflow_run_id: string
  step_id: string
  step_name: string
  step_type: StepType
  status: StepStatus
  retry_count: number
  started_at: string | null
  completed_at: string | null
  error_message: string | null
  duration_ms: number | null
}

// ──────────────────────────────────────────────────────────────
// AI
// ──────────────────────────────────────────────────────────────

export interface AIGenerateResponse {
  definition: WorkflowDefinition
  step_count: number
  message: string
}

export interface DAGValidationResponse {
  valid: boolean
  step_count?: number
  batch_count?: number
  execution_order?: string[][]
  errors?: string[]
}
