import { ref, onUnmounted } from 'vue'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { useAuthStore } from '@/stores/auth'
import { useWorkflowsStore } from '@/stores/workflows'
import type { RunUpdatedEvent, StepUpdatedEvent } from '@/types'

let echoInstance: Echo | null = null

function getEcho(): Echo {
  if (!echoInstance) {
    const authStore = useAuthStore()

    // @ts-ignore
    window.Pusher = Pusher

    echoInstance = new Echo({
      broadcaster: 'pusher',
      key: import.meta.env.VITE_PUSHER_APP_KEY || 'flowforge-key',
      wsHost: import.meta.env.VITE_PUSHER_HOST || 'localhost',
      wsPort: parseInt(import.meta.env.VITE_PUSHER_PORT || '6001'),
      wssPort: parseInt(import.meta.env.VITE_PUSHER_PORT || '6001'),
      forceTLS: false,
      disableStats: true,
      enabledTransports: ['ws', 'wss'],
      authEndpoint: `${import.meta.env.VITE_API_URL || 'http://localhost:8000/api'}/broadcasting/auth`,
      auth: {
        headers: {
          Authorization: `Bearer ${authStore.token}`,
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

/**
 * Subscribe to a specific workflow run channel for live step updates.
 */
export function useRunChannel(runId: string) {
  const workflowsStore = useWorkflowsStore()
  const connected = ref(false)

  const echo = getEcho()
  const channel = echo.channel(`run.${runId}`)

  channel.listen('.run.updated', (event: RunUpdatedEvent) => {
    workflowsStore.updateRunFromEvent(event)
  })

  channel.listen('.step.updated', (event: StepUpdatedEvent) => {
    workflowsStore.updateStepFromEvent(event)
  })

  channel.subscribed(() => {
    connected.value = true
  })

  function unsubscribe() {
    echo.leave(`run.${runId}`)
    connected.value = false
  }

  onUnmounted(unsubscribe)

  return { connected, unsubscribe }
}

/**
 * Subscribe to the tenant-wide channel for dashboard updates.
 */
export function useTenantChannel(tenantId: string, onRunUpdate?: (e: RunUpdatedEvent) => void) {
  const echo = getEcho()
  const channel = echo.channel(`tenant.${tenantId}`)

  channel.listen('.run.updated', (event: RunUpdatedEvent) => {
    onRunUpdate?.(event)
  })

  function unsubscribe() {
    echo.leave(`tenant.${tenantId}`)
  }

  onUnmounted(unsubscribe)

  return { unsubscribe }
}
