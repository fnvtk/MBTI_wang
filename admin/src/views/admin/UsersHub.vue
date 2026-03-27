<template>
  <div class="users-hub">
    <div class="hub-header">
      <h2>用户运营</h2>
      <p class="hub-subtitle">测试用户数据、小程序展示文案与分销海报一站式维护</p>
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

    <div
      class="hub-card"
      :class="{ 'no-pad': activeTab === 'poster', 'flat-embed': activeTab === 'users' }"
    >
      <Users v-if="activeTab === 'users'" embedded />
      <MiniprogramConfigPanel v-if="activeTab === 'miniprogram'" ref="miniRef" />
      <div v-if="activeTab === 'poster'" class="poster-wrap">
        <PosterEditor />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Users from './Users.vue'
import PosterEditor from './PosterEditor.vue'
import MiniprogramConfigPanel from './MiniprogramConfigPanel.vue'

const TAB_IDS = ['users', 'miniprogram', 'poster'] as const
type TabId = (typeof TAB_IDS)[number]

function isTabId(s: string): s is TabId {
  return (TAB_IDS as readonly string[]).includes(s)
}

const route = useRoute()
const router = useRouter()
const activeTab = ref<TabId>('users')
const miniRef = ref<InstanceType<typeof MiniprogramConfigPanel> | null>(null)

const innerTabs: { label: string; value: TabId }[] = [
  { label: '用户列表', value: 'users' },
  { label: '小程序配置', value: 'miniprogram' },
  { label: '海报配置', value: 'poster' }
]

const applyRouteTab = () => {
  const t = route.query.tab
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
    if (activeTab.value === 'miniprogram') {
      miniRef.value?.loadMiniprogramConfig?.()
    }
  }
)

watch(activeTab, (tab) => {
  if (tab === 'miniprogram') {
    miniRef.value?.loadMiniprogramConfig?.()
  }
})

onMounted(() => {
  applyRouteTab()
  if (activeTab.value === 'miniprogram') {
    miniRef.value?.loadMiniprogramConfig?.()
  }
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
      flex: 1;
      padding: 8px 18px;
      font-size: 14px;
      color: #6b7280;
      cursor: pointer;
      border-radius: 6px;
      white-space: nowrap;
      text-align: center;
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

.hub-card {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #f3f4f6;
  padding: 28px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);

  &.no-pad {
    padding: 16px;
  }

  &.flat-embed {
    background: transparent;
    border: none;
    box-shadow: none;
    padding: 0;
  }
}

.poster-wrap {
  min-height: 720px;
}
</style>
