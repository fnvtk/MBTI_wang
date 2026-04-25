<template>
  <div class="sa-page-head" :class="{ 'sa-page-head-row': !!$slots.actions }">
    <div class="sa-head-main">
      <h2 class="sa-title">
        <slot name="title">{{ title }}</slot>
        <span v-if="badge" class="sa-title-badge">{{ badge }}</span>
      </h2>
      <p v-if="subtitle || $slots.subtitle" class="sa-subtitle">
        <slot name="subtitle">{{ subtitle }}</slot>
      </p>
    </div>
    <div v-if="$slots.actions" class="sa-head-actions">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title?: string
  subtitle?: string
  badge?: string
}>()
</script>
