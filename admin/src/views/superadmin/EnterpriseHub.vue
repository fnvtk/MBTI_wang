<template>
  <div class="enterprise-hub">
    <div class="hub-header">
      <h2>企业管理</h2>
      <p class="hub-subtitle">
        维护企业档案与平台用户池，对应各企业管理员在「普通管理后台」可见的数据范围。个人侧无企业归属的测试用户在统计与列表中并入「存客宝」。
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

const TAB_IDS = ['companies', 'users'] as const
type TabId = (typeof TAB_IDS)[number]

function isTabId(s: string): s is TabId {
  return (TAB_IDS as readonly string[]).includes(s)
}

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
  { label: '用户总览', value: 'users' }
]

const applyRouteTab = () => {
  const t = route.query.tab
  if (typeof t === 'string' && isTabId(t)) {
    activeTab.value = t
  } else {
    activeTab.value = 'companies'
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
  if (tab !== 'companies') {
    q.tab = tab
  }
  router.replace({ path: '/superadmin/enterprises', query: Object.keys(q).length ? q : {} })
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
.enterprise-hub {
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

.hub-body {
  width: 100%;
}

.migrate-alert {
  margin-bottom: 16px;
}
.migrate-text {
  margin: 0 0 10px;
  font-size: 13px;
  line-height: 1.5;
  color: #4b5563;
}
</style>
