<template>
  <span
    :class="[
      'inline-block w-2.5 h-2.5 rounded-full shrink-0',
      dotClass,
      status === 'running' && 'animate-pulse',
    ]"
  />
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ status: string }>()

const dotClass = computed(() => {
  const map: Record<string, string> = {
    pending: 'bg-yellow-400',
    running: 'bg-blue-500',
    success: 'bg-green-500',
    failed: 'bg-red-500',
    timeout: 'bg-orange-500',
    cancelled: 'bg-gray-400',
    retrying: 'bg-purple-500',
    skipped: 'bg-gray-300',
  }
  return map[props.status] ?? 'bg-gray-400'
})
</script>
