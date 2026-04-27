<template>
  <div class="orders-hub">
    <div class="hub-header">
      <div>
        <h2>订单管理</h2>
        <p class="hub-subtitle">支付订单、成交状态与关联数据</p>
      </div>
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

    <div class="hub-body flat">
      <Orders v-if="activeTab === 'orders'" embedded orders-api-path="/admin/orders" />
      <Pricing v-if="activeTab === 'pricing'" embedded />
      <Questions v-if="activeTab === 'questions'" embedded />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Orders from './Orders.vue'
import Pricing from './Pricing.vue'
import Questions from './Questions.vue'

const TAB_IDS = ['orders', 'pricing', 'questions'] as const
type TabId = (typeof TAB_IDS)[number]

function isTabId(s: string): s is TabId {
  return (TAB_IDS as readonly string[]).includes(s)
}

const route = useRoute()
const router = useRouter()
const activeTab = ref<TabId>('orders')

const innerTabs: { label: string; value: TabId }[] = [
  { label: '订单列表', value: 'orders' },
  { label: '价格设置', value: 'pricing' },
  { label: '题库管理', value: 'questions' }
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
  if (tab !== 'stats') q.tab = tab
  router.replace({ path: '/admin/orders', query: Object.keys(q).length ? q : {} })
}

watch(() => route.query.tab, () => applyRouteTab())

onMounted(() => {
  applyRouteTab()
})
</script>

<style scoped lang="scss">
.orders-hub {
  min-height: calc(100vh - 56px);
  background: #F4F6FB;
  display: flex;
  flex-direction: column;
}

.hub-header {
  padding: 20px 24px 0;
  margin-bottom: 0;

  h2 {
    margin: 0 0 4px;
    font-size: 22px;
    font-weight: 800;
    color: #111827;
    letter-spacing: -0.02em;
  }

  .hub-subtitle {
    margin: 0;
    font-size: 12.5px;
    color: #6B7280;
  }
}

.custom-tabs-container {
  padding: 16px 24px 0;
}

.hub-body.flat {
  flex: 1;
  width: 100%;
  background: #F4F6FB;
}
</style>
