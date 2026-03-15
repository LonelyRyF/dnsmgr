<script setup lang="ts">
interface Props {
  status: number | string
  labels?: Record<string | number, string>
  activeLabel?: string
  inactiveLabel?: string
}

const props = withDefaults(defineProps<Props>(), {
  activeLabel: '正常',
  inactiveLabel: '已暂停',
})

function getStyle(status: number | string): string {
  const s = Number(status)
  if (s === 1) return 'badge-success'
  if (s === 0) return 'badge-muted'
  if (s === -1 || s === 2) return 'badge-danger'
  if (s === 3) return 'badge-warn'
  return 'badge-muted'
}

function getLabel(status: number | string): string {
  if (props.labels?.[status] !== undefined) return props.labels[status]
  const s = Number(status)
  if (s === 1) return props.activeLabel
  if (s === 0) return props.inactiveLabel
  if (s === -1) return '失败'
  if (s === 2) return '异常'
  if (s === 3) return '待处理'
  return String(status)
}
</script>

<template>
  <span :class="getStyle(status)">
    <span class="inline-block w-1.5 h-1.5 rounded-full bg-current" />
    {{ getLabel(status) }}
  </span>
</template>
