<template>
  <div class="commerce-hub">
    <div class="hub-header">
      <h2>订单和财务</h2>
      <p class="hub-subtitle">
        监督全平台订单与资金流水；全局定价影响各企业在管理后台侧的采购与结算。企业日常接单请在「管理后台」查看。
      </p>
    </div>

    <div class="custom-tabs-container tabs-scroll">
      <div class="custom-tabs tabs-many">
        <div
          v-for="t in innerTabs"
          :key="t.value"
          :class="['tab-item', { active: activeTab === t.value }]"
          @click="selectTab(t.value)"
        >
          {{ t.label }}
        </div>
      </div>
    </div>

    <div class="hub-body">
      <Orders
        v-if="activeTab === 'orders'"
        embedded
        orders-api-path="/superadmin/orders"
      />
      <Finance v-if="activeTab === 'finance'" embedded />
      <!-- 与独立「全局定价」一致：含个人版价格 + 深度服务左右栏（个人/企业） -->
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

const selectTab = (tab: TabId) => {
  activeTab.value = tab
  const q: Record<string, string> = {}
  Object.entries(route.query).forEach(([k, v]) => {
    if (v !== undefined && v !== null && k !== 'tab') {
      q[k] = Array.isArray(v) ? String(v[0]) : String(v)
    }
  })
  if (tab !== 'orders') {
    q.tab = tab
  }
  router.replace({ path: '/superadmin/commerce', query: Object.keys(q).length ? q : {} })
}

watch(
  () => route.query.tab,
  () => applyRouteTab()
)

onMounted(() => {
  applyRouteTab()
})
</script>

<style scoped lang="scss">
.commerce-hub {
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

.custom-tabs-container {
  background-color: #f3f4f6;
  padding: 4px;
  border-radius: 8px;
  margin-bottom: 20px;
  width: 100%;

  &.tabs-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  .custom-tabs {
    display: flex;
    gap: 4px;
    min-width: min-content;

    &.tabs-many .tab-item {
      flex: 0 0 auto;
      padding: 8px 20px;
      font-size: 14px;
    }

    .tab-item {
      cursor: pointer;
      border-radius: 6px;
      white-space: nowrap;
      color: #6b7280;
      transition: all 0.2s;

      &:hover {
        color: #111827;
      }

      &.active {
        background: #fff;
        color: #111827;
        font-weight: 600;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
      }
    }
  }
}

.hub-body {
  width: 100%;
}
</style>
