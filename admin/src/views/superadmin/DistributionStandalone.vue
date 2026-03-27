<template>
  <div class="distribution-hub">
    <div class="hub-header">
      <h2>分销管理</h2>
      <p class="hub-subtitle">
        平台级分销规则、提现与佣金；小程序埋点用于监督各端行为数据。企业管理员日常操作在「管理后台」侧完成。
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
      <Distribution v-if="activeTab === 'distribution'" embedded />
      <MpAnalytics v-if="activeTab === 'analytics'" embedded />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Distribution from './Distribution.vue'
import MpAnalytics from './MpAnalytics.vue'

const TAB_IDS = ['distribution', 'analytics'] as const
type TabId = (typeof TAB_IDS)[number]

function isTabId(s: string): s is TabId {
  return (TAB_IDS as readonly string[]).includes(s)
}

const route = useRoute()
const router = useRouter()
const activeTab = ref<TabId>('distribution')

const innerTabs: { label: string; value: TabId }[] = [
  { label: '分销推广', value: 'distribution' },
  { label: '小程序埋点', value: 'analytics' }
]

const applyRouteTab = () => {
  const t = route.query.tab
  if (typeof t === 'string' && isTabId(t)) {
    activeTab.value = t
  } else {
    activeTab.value = 'distribution'
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
  if (tab !== 'distribution') {
    q.tab = tab
  }
  router.replace({
    path: '/superadmin/distribution',
    query: Object.keys(q).length ? q : {}
  })
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
.distribution-hub {
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
    line-height: 1.55;
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
