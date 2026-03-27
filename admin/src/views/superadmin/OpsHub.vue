<template>
  <div class="ops-hub">
    <div class="hub-header">
      <h2>总览</h2>
      <p class="hub-subtitle">
        平台级数据总览，用于对照各企业在「普通管理后台」的经营情况。分销与埋点在侧栏「分销管理」；订单与定价在「订单和财务」；模型与算力在「智能算力」。
      </p>
    </div>
    <div class="hub-body">
      <Overview />
    </div>
  </div>
</template>

<script setup lang="ts">
import Overview from './Overview.vue'
</script>

<style scoped lang="scss">
.ops-hub {
  padding: 24px;
  min-height: 100vh;
  background: #f9fafb;
}

.hub-header {
  margin-bottom: 20px;

  h2 {
    margin: 0 0 6px;
    font-size: 22px;
    font-weight: 700;
    color: #111827;
  }

  .hub-subtitle {
    margin: 0;
    font-size: 14px;
    color: #6b7280;
  }
}

.hub-body {
  width: 100%;
}
</style>
