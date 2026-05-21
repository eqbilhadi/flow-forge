import api from './client'
import type {
  AuthResponse,
  Workflow,
  WorkflowRun,
  PaginatedResponse,
  DashboardHealth,
  AIGenerateResponse,
  DAGValidationResponse,
  WorkflowDefinition,
} from '@/types'

// ──────────────────────────────────────────────────────────────
// Auth API
// ──────────────────────────────────────────────────────────────
export const authApi = {
  login: (email: string, password: string) =>
    api.post<AuthResponse>('/auth/login', { email, password }),

  register: (data: { tenant_name: string; name: string; email: string; password: string }) =>
    api.post<AuthResponse>('/auth/register', data),

  logout: () => api.post('/auth/logout'),

  me: () => api.get<{ user: AuthResponse['user'] }>('/auth/me'),

  refresh: () => api.post<AuthResponse>('/auth/refresh'),
}

// ──────────────────────────────────────────────────────────────
// Workflows API
// ──────────────────────────────────────────────────────────────
export const workflowsApi = {
  list: (params?: Record<string, unknown>) =>
    api.get<PaginatedResponse<Workflow>>('/workflows', { params }),

  get: (id: string) =>
    api.get<{ workflow: Workflow }>(`/workflows/${id}`),

  create: (data: Partial<Workflow>) =>
    api.post<{ workflow: Workflow; message: string }>('/workflows', data),

  update: (id: string, data: Partial<Workflow>) =>
    api.put<{ workflow: Workflow; message: string }>(`/workflows/${id}`, data),

  delete: (id: string) =>
    api.delete<{ message: string }>(`/workflows/${id}`),

  trigger: (id: string, inputData?: Record<string, unknown>) =>
    api.post<{ run: WorkflowRun; message: string }>(`/workflows/${id}/trigger`, { input_data: inputData }),

  runs: (id: string, params?: Record<string, unknown>) =>
    api.get<PaginatedResponse<WorkflowRun>>(`/workflows/${id}/runs`, { params }),

  versions: (id: string) =>
    api.get(`/workflows/${id}/versions`),

  rollback: (id: string, version: number) =>
    api.post<{ workflow: Workflow; message: string }>(`/workflows/${id}/rollback`, { version }),
}

// ──────────────────────────────────────────────────────────────
// Workflow Runs API
// ──────────────────────────────────────────────────────────────
export const runsApi = {
  get: (id: string) =>
    api.get<{ run: WorkflowRun }>(`/runs/${id}`),

  cancel: (id: string) =>
    api.post<{ message: string }>(`/runs/${id}/cancel`),

  logs: (id: string) =>
    api.get(`/runs/${id}/logs`),

  analyzeFailure: (id: string) =>
    api.post<{ analysis: string }>(`/runs/${id}/analyze-failure`),
}

// ──────────────────────────────────────────────────────────────
// Dashboard API
// ──────────────────────────────────────────────────────────────
export const dashboardApi = {
  health: () =>
    api.get<DashboardHealth>('/dashboard/health'),

  recentRuns: () =>
    api.get<{ data: WorkflowRun[] }>('/dashboard/recent-runs'),
}

// ──────────────────────────────────────────────────────────────
// AI API
// ──────────────────────────────────────────────────────────────
export const aiApi = {
  generateWorkflow: (description: string) =>
    api.post<AIGenerateResponse>('/ai/generate-workflow', { description }),

  validateDefinition: (definition: WorkflowDefinition) =>
    api.post<DAGValidationResponse>('/ai/validate-definition', { definition }),
}
