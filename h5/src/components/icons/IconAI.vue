<template>
  <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
    <path d="M12 2a10 10 0 110 20A10 10 0 0112 2z" :stroke="active?'#6C3EF6':'#9CA3AF'" stroke-width="2"/>
    <path d="M8 12h.01M12 12h.01M16 12h.01" :stroke="active?'#6C3EF6':'#9CA3AF'" stroke-width="2.5" stroke-linecap="round"/>
  </svg>
</template>
<script setup lang="ts">
defineProps<{ active?: boolean }>()
</script>
