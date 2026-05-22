import { onUnmounted } from 'vue'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { useAuthStore } from '@/stores/auth'
import { useWorkflowsStore } from '@/stores/workflows'
import type { RunUpdatedEvent, StepUpdatedEvent } from '@/types'

let echoInstance: Echo | null = null

function getEcho(): Echo {
  if (!echoInstance) {
    const authStore = useAuthStore()

    window.Pusher = Pusher

    echoInstance = new Echo({
      broadcaster: 'reverb',
      key: import.meta.env.VITE_REVERB_APP_KEY || 'flowforge-key',
      wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
      wsPort: parseInt(import.meta.env.VITE_REVERB_PORT || '8080'),
      wssPort: parseInt(import.meta.env.VITE_REVERB_PORT || '8080'),
      scheme: import.meta.env.VITE_REVERB_SCHEME || 'http',
      forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
      disableStats: true,
      enabledTransports: ['ws', 'wss'],
      authEndpoint: `${import.meta.env.VITE_API_URL || 'http://localhost:8000/api'}/broadcasting/auth`,
      auth: {
        headers: {
          Authorization: `Bearer ${authStore.token}`,
          Accept: 'application/json',
        },
      },
    })
  }
  return echoInstance
}

export function useEcho() {
  function disconnectEcho() {
    if (echoInstance) {
      echoInstance.disconnect()
      echoInstance = null
    }
  }
  return { getEcho, disconnectEcho }
}

export function useRunChannel(runId: string) {
  if (!runId) return { connected: { value: false }, unsubscribe: () => {} }

  let channel: any = null

  try {
    const echo = getEcho()
    const workflowsStore = useWorkflowsStore()
    channel = echo.channel(`run.${runId}`)

    channel.listen('.run.updated', (event: RunUpdatedEvent) => {
      workflowsStore.updateRunFromEvent(event)
    })
    channel.listen('.step.updated', (event: StepUpdatedEvent) => {
      workflowsStore.updateStepFromEvent(event)
    })
  } catch (e) {
    console.warn('WebSocket unavailable, running without real-time updates:', e)
  }

  function unsubscribe() {
    try {
      if (echoInstance && runId) echoInstance.leave(`run.${runId}`)
    } catch {}
  }

  onUnmounted(unsubscribe)
  return { connected: { value: !!channel }, unsubscribe }
}

export function useTenantChannel(tenantId: string, onRunUpdate?: (e: RunUpdatedEvent) => void) {
  if (!tenantId) return { unsubscribe: () => {} }

  try {
    const echo = getEcho()
    echo.channel(`tenant.${tenantId}`)
      .listen('.run.updated', (event: RunUpdatedEvent) => {
        onRunUpdate?.(event)
      })
  } catch (e) {
    console.warn('WebSocket tenant channel failed:', e)
  }

  function unsubscribe() {
    try {
      if (echoInstance && tenantId) echoInstance.leave(`tenant.${tenantId}`)
    } catch {}
  }

  onUnmounted(unsubscribe)
  return { unsubscribe }
}
