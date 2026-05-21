<template>
  <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-medium', badgeClass]">
    {{ label }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ status: string }>()

const config: Record<string, { label: string; class: string }> = {
  pending:   { label: 'Pending',   class: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' },
  running:   { label: 'Running',   class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
  success:   { label: 'Success',   class: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' },
  failed:    { label: 'Failed',    class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
  timeout:   { label: 'Timeout',   class: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' },
  cancelled: { label: 'Cancelled', class: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' },
  retrying:  { label: 'Retrying',  class: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' },
  skipped:   { label: 'Skipped',   class: 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-500' },
}

const badgeClass = computed(() => config[props.status]?.class ?? 'bg-gray-100 text-gray-600')
const label = computed(() => config[props.status]?.label ?? props.status)
</script>
