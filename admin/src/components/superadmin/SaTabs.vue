<template>
  <div class="sa-tabs" role="tablist">
    <div
      v-for="item in items"
      :key="String(item.value)"
      role="tab"
      :class="['sa-tab-item', { 'is-active': modelValue === item.value }]"
      @click="onSelect(item.value)"
    >
      <span v-if="withDot" class="sa-tab-dot" />
      {{ item.label }}
      <span v-if="item.badge != null" class="sa-tab-badge">{{ item.badge }}</span>
    </div>
  </div>
</template>

<script setup lang="ts" generic="T extends string | number">
interface SaTabItem<V> {
  label: string
  value: V
  badge?: string | number
}

const props = defineProps<{
  modelValue: T
  items: SaTabItem<T>[]
  withDot?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: T): void
  (e: 'change', v: T): void
}>()

function onSelect(v: T) {
  if (v === props.modelValue) return
  emit('update:modelValue', v)
  emit('change', v)
}
</script>

<style scoped>
.sa-tab-badge {
  margin-left: 6px;
  padding: 1px 8px;
  font-size: 11px;
  font-weight: 500;
  line-height: 1.5;
  background: #f1f5f9;
  color: #64748b;
  border-radius: 999px;
  vertical-align: middle;
}
.sa-tab-item.is-active .sa-tab-badge {
  background: var(--sa-primary-soft, #eef2ff);
  color: var(--sa-primary, #6366f1);
}
</style>
