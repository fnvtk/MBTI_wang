<template>
  <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
    <path d="M3 12L12 3l9 9" :stroke="active?'#6C3EF6':'#9CA3AF'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M5 10v9a1 1 0 001 1h4v-5h4v5h4a1 1 0 001-1v-9" :stroke="active?'#6C3EF6':'#9CA3AF'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
</template>
<script setup lang="ts">
defineProps<{ active?: boolean }>()
</script>
