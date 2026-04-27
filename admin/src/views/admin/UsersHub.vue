<template>
  <div class="users-hub">
    <!-- 页面头部 -->
    <div class="hub-header">
      <div class="hub-header-left">
        <h1 class="hub-title">用户运营</h1>
        <p class="hub-desc">用户画像、旅程分析、价值分层与合作意向管理</p>
      </div>
    </div>

    <!-- Tab 导航 -->
    <div class="tab-bar" role="tablist">
      <button
        v-for="t in innerTabs"
        :key="t.value"
        type="button"
        class="tab-btn"
        :class="{ 'is-active': activeTab === t.value }"
        @click="selectTab(t.value)"
        :aria-selected="activeTab === t.value"
        role="tab"
      >
        <component :is="t.icon" class="tab-icon" />
        <span>{{ t.label }}</span>
        <span v-if="t.value === 'cooperation'" class="tab-dot"></span>
      </button>
    </div>

    <!-- 内容区 -->
    <div
      class="hub-body"
      :class="{ 'no-padding': true }"
    >
      <Users v-if="activeTab === 'users'" embedded />
      <UserJourneyPanel v-else-if="activeTab === 'journey'" />
      <UserRfmPanel v-else-if="activeTab === 'rfm'" />
      <CooperationChoicesPanel v-else-if="activeTab === 'cooperation'" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, defineAsyncComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Users from './Users.vue'
import UserJourneyPanel from '@/components/UserJourneyPanel.vue'
import UserRfmPanel from '@/components/UserRfmPanel.vue'
import CooperationChoices from './CooperationChoices.vue'
import {
  User,
  TrendCharts,
  DataAnalysis,
  Connection
} from '@element-plus/icons-vue'

const CooperationChoicesPanel = CooperationChoices

const TAB_IDS = ['users', 'journey', 'rfm', 'cooperation'] as const
type TabId = (typeof TAB_IDS)[number]

function isTabId(s: string): s is TabId {
  return (TAB_IDS as readonly string[]).includes(s)
}

const route = useRoute()
const router = useRouter()
const activeTab = ref<TabId>('users')

const innerTabs: { label: string; value: TabId; icon: any }[] = [
  { label: '用户列表',   value: 'users',       icon: User        },
  { label: '旅程漏斗',   value: 'journey',     icon: TrendCharts },
  { label: 'RFM 价值分层', value: 'rfm',       icon: DataAnalysis },
  { label: '合作意向',   value: 'cooperation', icon: Connection  },
]

const applyRouteTab = () => {
  const t = route.query.tab
  if (t === 'miniprogram' || t === 'poster') {
    router.replace({ path: '/admin/settings', query: { tab: 'terminal' } })
    activeTab.value = 'users'
    return
  }
  if (typeof t === 'string' && isTabId(t)) {
    activeTab.value = t
  } else {
    activeTab.value = 'users'
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
  if (tab !== 'users') q.tab = tab
  router.replace({ path: '/admin/users', query: Object.keys(q).length ? q : {} })
}

watch(() => route.query.tab, () => { applyRouteTab() })
onMounted(() => { applyRouteTab() })
</script>

<style scoped lang="scss">
.users-hub {
  min-height: calc(100vh - 56px);
  background: #F4F6FB;
  display: flex;
  flex-direction: column;
}

/* ── 头部 ── */
.hub-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 20px 24px 0;
  gap: 12px;
}
.hub-title {
  margin: 0 0 4px;
  font-size: 22px;
  font-weight: 800;
  color: #111827;
  letter-spacing: -0.02em;
}
.hub-desc {
  margin: 0;
  font-size: 12.5px;
  color: #6B7280;
}

/* ── Tab 导航 ── */
.tab-bar {
  display: flex;
  gap: 2px;
  padding: 16px 24px 0;
  border-bottom: 1px solid #E5E7EB;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  flex-shrink: 0;
  scrollbar-width: none;

  &::-webkit-scrollbar { display: none; }
}

.tab-btn {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 10px 18px;
  border: none;
  background: transparent;
  font-size: 13.5px;
  font-weight: 500;
  color: #6B7280;
  cursor: pointer;
  white-space: nowrap;
  border-radius: 8px 8px 0 0;
  transition: all 0.18s;
  position: relative;

  .tab-icon {
    font-size: 15px;
    opacity: 0.7;
  }

  .tab-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #4F46E5;
    position: absolute;
    top: 8px;
    right: 8px;
  }

  &:hover {
    background: #F3F4F6;
    color: #374151;
  }

  &.is-active {
    color: #4F46E5;
    font-weight: 700;
    background: #fff;
    box-shadow: 0 -2px 0 0 #4F46E5 inset, 1px 0 0 0 #E5E7EB inset, -1px 0 0 0 #E5E7EB inset;

    .tab-icon { opacity: 1; }
  }
}

/* ── 内容区 ── */
.hub-body {
  flex: 1;
  background: #fff;
  border-top: none;
  padding: 24px;
  min-height: 0;

  &.no-padding {
    padding: 0;
    background: #F4F6FB;
  }
}
</style>
