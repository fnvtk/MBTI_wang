<template>
  <div class="sa-page enterprise-hub">
    <SaPageHeader
      title="企业管理"
      subtitle="维护企业档案与平台用户池，对应各企业管理员在「普通管理后台」可见的数据范围。个人侧无企业归属的测试用户在统计与列表中并入「存客宝」。"
    />

    <SaTabs v-model="activeTab" :items="innerTabs" @change="onTabChange" />

    <el-alert
      v-if="activeTab === 'users'"
      type="info"
      class="migrate-alert"
      show-icon
      :closable="false"
    >
      <template #title>数据库归并（可选）</template>
      <p class="migrate-text">
        将把无企业归属的用户写入「存客宝」、补齐其 <code>test_results</code> 的企业字段，并把 <code>user_profile</code> 中 personal 行与该企业 enterprise 行<strong>合并归档</strong>（计数相加后删除 personal）。先预览条数，再二次确认。
      </p>
      <el-button type="primary" plain size="small" :loading="migrateLoading" @click="runOrphanMigrate">
        预览并归并到存客宝
      </el-button>
    </el-alert>

    <div class="hub-body">
      <Enterprises v-if="activeTab === 'companies'" embedded />
      <Users v-if="activeTab === 'users'" :key="usersRefreshKey" embedded />
      <SoulArticles v-if="activeTab === 'soulArticles'" />
      <CooperationChoicesPanel v-if="activeTab === 'cooperation'" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { request } from '@/utils/request'
import Enterprises from './Enterprises.vue'
import Users from './Users.vue'
import SoulArticles from './SoulArticles.vue'
import CooperationChoicesPanel from './CooperationChoicesPanel.vue'
import SaPageHeader from '@/components/superadmin/SaPageHeader.vue'
import SaTabs from '@/components/superadmin/SaTabs.vue'

const TAB_IDS = ['companies', 'users', 'soulArticles', 'cooperation'] as const
type TabId = (typeof TAB_IDS)[number]

function isTabId(s: string): s is TabId {
  return (TAB_IDS as readonly string[]).includes(s)
}

/** 已从页签隐藏的 tab（旧链接兼容） */
const LEGACY_TAB_IDS = ['mpTabbar', 'profitRules']

const route = useRoute()
const router = useRouter()
const activeTab = ref<TabId>('companies')
const migrateLoading = ref(false)
const usersRefreshKey = ref(0)

async function runOrphanMigrate() {
  migrateLoading.value = true
  try {
    const res: any = await request.post('/superadmin/data-migration/attach-orphans-to-cunkbao', {
      dryRun: true
    })
    const d = res.data ?? res
    const name = d.enterpriseName || '存客宝'
    const wu = d.wechatUsersRows ?? 0
    const tr = d.testResultsRows ?? 0
    const pr = d.userProfilePersonalRows ?? 0
    if (wu === 0 && tr === 0 && pr === 0) {
      ElMessage.info((d.hint as string) || '当前无需归并')
      return
    }
    await ElMessageBox.confirm(
      `即将写入「${name}」：小程序用户约 ${wu} 人；待补全企业的测试记录约 ${tr} 条；待合并的 personal 画像行约 ${pr} 条。将修改 wechat_users、test_results、user_profile，是否继续？`,
      '确认归并',
      { type: 'warning', confirmButtonText: '写入', cancelButtonText: '取消' }
    )
    await request.post('/superadmin/data-migration/attach-orphans-to-cunkbao', {
      dryRun: false,
      confirm: true
    })
    ElMessage.success('归并完成，已刷新用户总览')
    usersRefreshKey.value += 1
  } catch (e: unknown) {
    if (e === 'cancel') return
    const msg = e instanceof Error ? e.message : '操作失败'
    ElMessage.error(msg)
  } finally {
    migrateLoading.value = false
  }
}

const innerTabs: { label: string; value: TabId }[] = [
  { label: '企业列表', value: 'companies' },
  { label: '用户总览', value: 'users' },
  { label: '引流文章', value: 'soulArticles' },
  { label: '合作意向', value: 'cooperation' }
]

function queryWithoutTab(): Record<string, string> {
  const q: Record<string, string> = {}
  Object.entries(route.query).forEach(([k, v]) => {
    if (v !== undefined && v !== null && k !== 'tab') {
      q[k] = Array.isArray(v) ? String(v[0]) : String(v)
    }
  })
  return q
}

const applyRouteTab = () => {
  const t = typeof route.query.tab === 'string' ? route.query.tab : ''
  if (t && LEGACY_TAB_IDS.includes(t)) {
    activeTab.value = 'companies'
    const q = queryWithoutTab()
    router.replace({ path: '/superadmin/enterprises', query: Object.keys(q).length ? q : {} })
    return
  }
  if (isTabId(t)) {
    activeTab.value = t
  } else {
    activeTab.value = 'companies'
  }
}

const onTabChange = (tab: TabId) => {
  const q: Record<string, string> = {}
  Object.entries(route.query).forEach(([k, v]) => {
    if (v !== undefined && v !== null && k !== 'tab') {
      q[k] = Array.isArray(v) ? String(v[0]) : String(v)
    }
  })
  if (tab !== 'companies') q.tab = tab
  router.replace({ path: '/superadmin/enterprises', query: Object.keys(q).length ? q : {} })
}

watch(() => route.query.tab, () => applyRouteTab())
onMounted(() => applyRouteTab())
</script>

<style scoped>
.hub-body { width: 100%; }
.migrate-alert {
  margin-bottom: 4px;
}
.migrate-text {
  margin: 0 0 10px;
  font-size: 13px;
  line-height: 1.5;
  color: #4b5563;
}
</style>
