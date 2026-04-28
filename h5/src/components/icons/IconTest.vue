<template>
  <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
    <rect x="4" y="2" width="16" height="20" rx="2" :stroke="active?'#6C3EF6':'#9CA3AF'" stroke-width="2"/>
    <path d="M8 7h8M8 11h8M8 15h5" :stroke="active?'#6C3EF6':'#9CA3AF'" stroke-width="2" stroke-linecap="round"/>
  </svg>
</template>
<script setup lang="ts">
defineProps<{ active?: boolean }>()
</script>
