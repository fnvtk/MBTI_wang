<template>
  <div class="sa-page commerce-hub">
    <SaPageHeader
      title="订单和财务"
      subtitle="监督全平台订单与资金流水；全局定价影响各企业在管理后台侧的采购与结算。企业日常接单请在「管理后台」查看。"
    />

    <SaTabs v-model="activeTab" :items="innerTabs" @change="onTabChange" />

    <div class="hub-body">
      <Orders
        v-if="activeTab === 'orders'"
        embedded
        orders-api-path="/superadmin/orders"
      />
      <Finance v-if="activeTab === 'finance'" embedded />
      <Pricing v-if="activeTab === 'pricing'" embedded />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Orders from '@/views/admin/Orders.vue'
import Finance from './Finance.vue'
import Pricing from './Pricing.vue'
import SaPageHeader from '@/components/superadmin/SaPageHeader.vue'
import SaTabs from '@/components/superadmin/SaTabs.vue'

const TAB_IDS = ['orders', 'finance', 'pricing'] as const
type TabId = (typeof TAB_IDS)[number]

function isTabId(s: string): s is TabId {
  return (TAB_IDS as readonly string[]).includes(s)
}

const route = useRoute()
const router = useRouter()
const activeTab = ref<TabId>('orders')

const innerTabs: { label: string; value: TabId }[] = [
  { label: '订单列表', value: 'orders' },
  { label: '财务看板', value: 'finance' },
  { label: '全局定价', value: 'pricing' }
]

const applyRouteTab = () => {
  const t = route.query.tab
  if (typeof t === 'string' && isTabId(t)) {
    activeTab.value = t
  } else {
    activeTab.value = 'orders'
  }
}

const onTabChange = (tab: TabId) => {
  const q: Record<string, string> = {}
  Object.entries(route.query).forEach(([k, v]) => {
    if (v !== undefined && v !== null && k !== 'tab') {
      q[k] = Array.isArray(v) ? String(v[0]) : String(v)
    }
  })
  if (tab !== 'orders') q.tab = tab
  router.replace({ path: '/superadmin/commerce', query: Object.keys(q).length ? q : {} })
}

watch(() => route.query.tab, () => applyRouteTab())
onMounted(() => applyRouteTab())
</script>

<style scoped>
.hub-body { width: 100%; }
</style>
