<template>
  <div class="sa-page distribution-hub">
    <SaPageHeader
      title="分销管理"
      subtitle="平台级分销规则、提现与佣金；小程序埋点用于监督各端行为数据。企业管理员日常操作在「管理后台」侧完成。"
    />

    <SaTabs v-model="activeTab" :items="innerTabs" @change="onTabChange" />

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
import SaPageHeader from '@/components/superadmin/SaPageHeader.vue'
import SaTabs from '@/components/superadmin/SaTabs.vue'

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

const onTabChange = (tab: TabId) => {
  const q: Record<string, string> = {}
  Object.entries(route.query).forEach(([k, v]) => {
    if (v !== undefined && v !== null && k !== 'tab') {
      q[k] = Array.isArray(v) ? String(v[0]) : String(v)
    }
  })
  if (tab !== 'distribution') q.tab = tab
  router.replace({
    path: '/superadmin/distribution',
    query: Object.keys(q).length ? q : {}
  })
}

watch(() => route.query.tab, () => applyRouteTab())
onMounted(() => applyRouteTab())
</script>

<style scoped>
.hub-body { width: 100%; }
</style>
