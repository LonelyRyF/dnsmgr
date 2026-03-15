<script setup lang="ts">
interface Props {
  cols?: number
  rows?: number
}

const props = withDefaults(defineProps<Props>(), {
  cols: 5,
  rows: 6,
})
</script>

<template>
  <tbody>
    <tr v-for="r in rows" :key="r" class="border-b border-border/50">
      <td v-for="c in cols" :key="c" class="py-3 px-4">
        <div
          class="skeleton rounded-md"
          :style="{
            height: '14px',
            width: c === cols ? '60px' : `${50 + Math.random() * 50}%`,
          }"
        />
      </td>
    </tr>
  </tbody>
</template>
