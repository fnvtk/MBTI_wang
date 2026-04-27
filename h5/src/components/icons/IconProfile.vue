<template>
  <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
    <circle cx="12" cy="7" r="4" :stroke="active?'#6C3EF6':'#9CA3AF'" stroke-width="2"/>
    <path d="M4 21c0-4 3.58-7 8-7s8 3 8 7" :stroke="active?'#6C3EF6':'#9CA3AF'" stroke-width="2" stroke-linecap="round"/>
  </svg>
</template>
<script setup lang="ts">
defineProps<{ active?: boolean }>()
</script>
