import type { Updater } from "@tanstack/vue-table"
import type { ClassValue } from "clsx"
import type { Ref } from "vue"
import { clsx } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function valueUpdater<T extends Updater<any>>(updaterOrValue: T, ref: Ref) {
  ref.value
    = typeof updaterOrValue === "function"
      ? updaterOrValue(ref.value)
      : updaterOrValue
}

export function formatDuration(seconds: number | null): string {
  if (seconds === null) return '—'
  if (seconds < 60) return `${seconds}s`
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return secs > 0 ? `${mins}m ${secs}s` : `${mins}m`
}

export function formatDurationMs(ms: number | null): string {
  if (ms === null) return '—'
  if (ms < 1000) return `${ms}ms`
  return formatDuration(Math.round(ms / 1000))
}

export function statusColor(status: string): string {
  const map: Record<string, string> = {
    pending: 'text-yellow-500',
    running: 'text-blue-500',
    success: 'text-green-500',
    failed: 'text-red-500',
    timeout: 'text-orange-500',
    cancelled: 'text-gray-400',
    retrying: 'text-purple-500',
    skipped: 'text-gray-400',
  }
  return map[status] ?? 'text-gray-400'
}

export function statusBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  const map: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    success: 'default',
    running: 'secondary',
    pending: 'outline',
    failed: 'destructive',
    timeout: 'destructive',
    cancelled: 'outline',
  }
  return map[status] ?? 'outline'
}
