<template>
  <div class="sa-page ops-hub">
    <SaPageHeader
      title="平台总览"
      subtitle="平台级数据对照各企业在「普通管理后台」的经营情况。分销与埋点在侧栏「分销管理」；订单与定价在「订单和财务」；模型与算力在「智能算力」。"
      badge="SuperAdmin"
    />
    <div class="hub-body">
      <Overview />
    </div>
  </div>
</template>

<script setup lang="ts">
import Overview from './Overview.vue'
import SaPageHeader from '@/components/superadmin/SaPageHeader.vue'
</script>

<style scoped>
.hub-body { width: 100%; }
</style>
