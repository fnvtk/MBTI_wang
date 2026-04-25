<template>
  <div class="users-hub">
    <div class="hub-header">
      <h2>用户运营</h2>
    </div>

    <div class="pill-tabs" role="tablist">
      <button
        v-for="t in innerTabs"
        :key="t.value"
        type="button"
        class="pill-tab"
        :class="{ 'is-active': activeTab === t.value }"
        @click="selectTab(t.value)"
      >
        {{ t.label }}
      </button>
    </div>

    <div
      class="hub-card"
      :class="{ 'flat-embed': activeTab === 'users' || activeTab === 'journey' || activeTab === 'rfm' }"
    >
      <Users v-if="activeTab === 'users'" embedded />
      <TopTestUsersPanel v-else-if="activeTab === 'top20'" />
      <UserJourneyPanel v-else-if="activeTab === 'journey'" />
      <UserRfmPanel v-else-if="activeTab === 'rfm'" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Users from './Users.vue'
import TopTestUsersPanel from '@/components/TopTestUsersPanel.vue'
import UserJourneyPanel from '@/components/UserJourneyPanel.vue'
import UserRfmPanel from '@/components/UserRfmPanel.vue'

const TAB_IDS = ['users', 'journey', 'rfm', 'top20'] as const
type TabId = (typeof TAB_IDS)[number]

function isTabId(s: string): s is TabId {
  return (TAB_IDS as readonly string[]).includes(s)
}

const route = useRoute()
const router = useRouter()
const activeTab = ref<TabId>('users')

const innerTabs: { label: string; value: TabId }[] = [
  { label: '用户列表', value: 'users' },
  { label: '旅程漏斗', value: 'journey' },
  { label: 'RFM 价值分层', value: 'rfm' },
  { label: '测评 Top 20', value: 'top20' }
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
  if (tab !== 'users') {
    q.tab = tab
  }
  router.replace({ path: '/admin/users', query: Object.keys(q).length ? q : {} })
}

watch(
  () => route.query.tab,
  () => {
    applyRouteTab()
  }
)

onMounted(() => {
  applyRouteTab()
})
</script>

<style scoped lang="scss">
.users-hub {
  padding: 24px;
  min-height: 100vh;
  background: #f9fafb;
}

.hub-header {
  margin-bottom: 20px;

  h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    color: #111827;
  }
}

.pill-tabs {
  display: inline-flex;
  background: #f1f5f9;
  padding: 4px;
  border-radius: 10px;
  gap: 2px;
  margin-bottom: 20px;
  max-width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.pill-tab {
  border: 0;
  background: transparent;
  color: #64748b;
  font-size: 13px;
  font-weight: 500;
  padding: 7px 18px;
  border-radius: 6px;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.18s;

  &:hover {
    color: #0f172a;
  }

  &.is-active {
    background: #ffffff;
    color: #0f172a;
    font-weight: 600;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
  }
}

.hub-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.03);

  &.flat-embed {
    background: transparent;
    border: none;
    box-shadow: none;
    padding: 0;
  }
}
</style>
