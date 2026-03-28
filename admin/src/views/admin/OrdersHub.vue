<template>
  <div class="orders-hub">
    <div class="hub-header">
      <h2>订单运营</h2>
      <p class="hub-subtitle">订单成交、测试定价与题库在同一入口完成</p>
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
      <Orders
        v-if="activeTab === 'orders'"
        embedded
        orders-api-path="/admin/orders"
      />
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
  if (tab !== 'orders') {
    q.tab = tab
  }
  router.replace({ path: '/admin/orders', query: Object.keys(q).length ? q : {} })
}

watch(() => route.query.tab, () => applyRouteTab())

onMounted(() => {
  applyRouteTab()
})
</script>

<style scoped lang="scss">
.orders-hub {
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
      padding: 8px 16px;
      font-size: 13px;
    }

    .tab-item {
      cursor: pointer;
      border-radius: 6px;
      white-space: nowrap;
      color: #6b7280;
      padding: 8px 18px;
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

.hub-body.flat {
  width: 100%;
}
</style>
