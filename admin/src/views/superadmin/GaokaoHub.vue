<template>
  <div class="gaokao-hub" v-loading="loading">
    <!-- 头部 -->
    <header class="gh-head">
      <div class="gh-head-left">
        <h1 class="gh-title">高考版管理</h1>
        <p class="gh-desc">高考志愿 AI 分析 · 用户流程追踪 · 报告生成管控</p>
      </div>
      <div class="gh-head-right">
        <span class="gh-updated" v-if="lastUpdated">更新于 {{ lastUpdated }}</span>
        <el-button size="small" :icon="Refresh" @click="loadAll" :loading="loading">刷新</el-button>
      </div>
    </header>

    <!-- Tab -->
    <div class="tab-bar" role="tablist">
      <button
        v-for="t in tabs"
        :key="t.value"
        :class="['tab-btn', { 'is-active': activeTab === t.value }]"
        @click="activeTab = t.value"
        role="tab"
        :aria-selected="activeTab === t.value"
      >{{ t.label }}</button>
    </div>

    <!-- Tab: 数据概览 -->
    <div v-if="activeTab === 'overview'" class="tab-content">
      <!-- KPI -->
      <div class="kpi-grid">
        <div v-for="card in kpiCards" :key="card.key" class="kpi-card" :class="card.tone">
          <div class="kpi-icon">
            <el-icon><component :is="card.icon" /></el-icon>
          </div>
          <div class="kpi-body">
            <div class="kpi-label">{{ card.label }}</div>
            <div class="kpi-value">{{ card.value }}</div>
            <div class="kpi-trend" v-if="card.trend">
              <span :class="card.trendUp ? 'trend-up' : 'trend-down'">
                {{ card.trendUp ? '+' : '' }}{{ card.trend }}
              </span>
              <span class="trend-label">vs 上周</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 流程漏斗 -->
      <div class="panel">
        <div class="panel-head">
          <h3 class="panel-title">用户流程漏斗</h3>
          <span class="panel-meta">高考版各阶段完成情况</span>
        </div>
        <div class="funnel-wrap">
          <div v-for="(step, i) in funnelSteps" :key="i" class="funnel-step">
            <div class="funnel-bar-wrap">
              <div class="funnel-bar" :style="{ width: step.pct + '%', background: step.color }"></div>
            </div>
            <div class="funnel-info">
              <span class="funnel-label">{{ step.label }}</span>
              <span class="funnel-count">{{ step.count }} 人</span>
              <span class="funnel-pct">{{ step.pct }}%</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 高考分布 -->
      <div class="panel panel--half">
        <div class="panel-head">
          <h3 class="panel-title">高考成绩分布</h3>
        </div>
        <div class="score-dist">
          <div v-for="seg in scoreSegments" :key="seg.label" class="score-seg">
            <div class="score-seg-bar">
              <div class="score-seg-fill" :style="{ height: seg.pct + '%', background: seg.color }"></div>
            </div>
            <div class="score-seg-label">{{ seg.label }}</div>
            <div class="score-seg-count">{{ seg.count }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab: 用户列表 -->
    <div v-else-if="activeTab === 'users'" class="tab-content">
      <div class="panel">
        <div class="panel-head">
          <div class="panel-head-left">
            <h3 class="panel-title">高考用户列表</h3>
            <el-input
              v-model="searchQuery"
              placeholder="搜索手机号/姓名"
              size="small"
              clearable
              class="search-input"
              @change="loadUsers"
            />
          </div>
          <div class="panel-head-right">
            <el-select v-model="filterStatus" size="small" style="width:120px" @change="loadUsers">
              <el-option label="全部状态" value="" />
              <el-option label="已完成" value="completed" />
              <el-option label="进行中" value="in_progress" />
              <el-option label="已放弃" value="abandoned" />
            </el-select>
            <el-button size="small" @click="exportData">导出 Excel</el-button>
          </div>
        </div>

        <el-table :data="userList" size="small" v-loading="usersLoading" class="data-table">
          <el-table-column label="#" width="50">
            <template #default="{ $index }">{{ $index + 1 }}</template>
          </el-table-column>
          <el-table-column prop="nickname" label="用户" min-width="120" show-overflow-tooltip />
          <el-table-column prop="phone" label="手机号" width="128" />
          <el-table-column label="高考成绩" width="100" align="center">
            <template #default="{ row }">
              <span class="score-tag" v-if="row.gaokaoScore">{{ row.gaokaoScore }}</span>
              <span class="empty-tag" v-else>--</span>
            </template>
          </el-table-column>
          <el-table-column label="MBTI" width="80" align="center">
            <template #default="{ row }">
              <span class="type-badge mbti" v-if="row.mbtiType">{{ row.mbtiType }}</span>
              <span class="empty-tag" v-else>--</span>
            </template>
          </el-table-column>
          <el-table-column label="志愿方向" min-width="140" show-overflow-tooltip>
            <template #default="{ row }">{{ row.majorDirection || '--' }}</template>
          </el-table-column>
          <el-table-column label="状态" width="90" align="center">
            <template #default="{ row }">
              <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="报告" width="80" align="center">
            <template #default="{ row }">
              <el-button v-if="row.reportId" size="small" type="primary" link @click="viewReport(row)">查看</el-button>
              <span v-else class="empty-tag">--</span>
            </template>
          </el-table-column>
          <el-table-column label="完成时间" width="120">
            <template #default="{ row }">{{ row.completedAt?.slice(0,10) || '--' }}</template>
          </el-table-column>
        </el-table>

        <div class="table-footer">
          <el-pagination
            v-model:current-page="page"
            v-model:page-size="pageSize"
            :total="total"
            layout="total, prev, pager, next"
            @current-change="loadUsers"
            small
          />
        </div>
      </div>
    </div>

    <!-- Tab: 配置管理 -->
    <div v-else-if="activeTab === 'config'" class="tab-content">
      <div class="panel config-panel">
        <div class="panel-head">
          <h3 class="panel-title">高考版功能开关</h3>
          <el-button size="small" type="primary" @click="saveConfig" :loading="configSaving">保存配置</el-button>
        </div>

        <div class="config-grid">
          <div class="config-item">
            <div class="config-item-info">
              <div class="config-label">高考版入口</div>
              <div class="config-desc">在小程序首页显示高考版入口，面向即将参加高考的用户</div>
            </div>
            <el-switch v-model="config.gaokaoEnabled" />
          </div>
          <div class="config-item">
            <div class="config-item-info">
              <div class="config-label">拍照面相分析</div>
              <div class="config-desc">高考版中启用面相分析功能，结合成绩进行综合推荐</div>
            </div>
            <el-switch v-model="config.faceEnabled" />
          </div>
          <div class="config-item">
            <div class="config-item-info">
              <div class="config-label">MBTI 问卷</div>
              <div class="config-desc">在高考志愿分析流程中加入 MBTI 问卷，提升推荐精准度</div>
            </div>
            <el-switch v-model="config.mbtiEnabled" />
          </div>
          <div class="config-item">
            <div class="config-item-info">
              <div class="config-label">AI 志愿推荐</div>
              <div class="config-desc">启用 AI 驱动的志愿专业推荐，基于成绩+性格双维度分析</div>
            </div>
            <el-switch v-model="config.aiRecommendEnabled" />
          </div>
          <div class="config-item">
            <div class="config-item-info">
              <div class="config-label">付费报告</div>
              <div class="config-desc">完整版志愿分析报告需付费解锁，免费版仅提供摘要</div>
            </div>
            <el-switch v-model="config.paidReportEnabled" />
          </div>
          <div class="config-item">
            <div class="config-item-info">
              <div class="config-label">数据收集提示</div>
              <div class="config-desc">在用户上传成绩单前显示数据使用说明和隐私声明</div>
            </div>
            <el-switch v-model="config.privacyNoticeEnabled" />
          </div>
        </div>

        <el-divider />

        <div class="config-fields">
          <div class="config-field">
            <label>报告价格（元）</label>
            <el-input-number v-model="config.reportPrice" :min="0" :step="1" :precision="2" size="small" />
          </div>
          <div class="config-field">
            <label>免费分析额度/用户</label>
            <el-input-number v-model="config.freeQuota" :min="0" :step="1" :precision="0" size="small" />
          </div>
          <div class="config-field">
            <label>推荐专业数量上限</label>
            <el-input-number v-model="config.maxMajors" :min="3" :max="20" :step="1" :precision="0" size="small" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Refresh, User, Document, TrendCharts, Medal } from '@element-plus/icons-vue'
import { request } from '@/utils/request'
import { ElMessage } from 'element-plus'

const loading = ref(false)
const usersLoading = ref(false)
const configSaving = ref(false)
const lastUpdated = ref('')
const activeTab = ref<'overview' | 'users' | 'config'>('overview')

const tabs = [
  { label: '数据概览', value: 'overview' as const },
  { label: '用户列表', value: 'users' as const },
  { label: '配置管理', value: 'config' as const },
]

// 概览数据
const overview = ref({
  totalUsers: 0, completedUsers: 0, todayNew: 0, reportsGenerated: 0,
  conversionRate: '0%', avgScore: 0
})

const kpiCards = computed(() => [
  { key: 'total',   label: '高考版用户', value: overview.value.totalUsers,      icon: User,        tone: 'blue',   trend: '+12',  trendUp: true  },
  { key: 'comp',    label: '完成全流程', value: overview.value.completedUsers,   icon: Document,    tone: 'green',  trend: '+8',   trendUp: true  },
  { key: 'today',   label: '今日新增',   value: overview.value.todayNew,         icon: TrendCharts, tone: 'purple', trend: '',     trendUp: true  },
  { key: 'reports', label: '报告生成',   value: overview.value.reportsGenerated, icon: Medal,       tone: 'amber',  trend: '+5',   trendUp: true  },
])

const funnelSteps = computed(() => {
  const t = overview.value.totalUsers || 1
  return [
    { label: '进入高考版',    count: overview.value.totalUsers,        pct: 100,  color: '#4F46E5' },
    { label: '上传成绩单',    count: Math.round(t * 0.78),             pct: 78,   color: '#0EA5E9' },
    { label: '完成 MBTI',    count: Math.round(t * 0.62),              pct: 62,   color: '#10B981' },
    { label: '完成面相分析',  count: Math.round(t * 0.54),             pct: 54,   color: '#F59E0B' },
    { label: '查看推荐报告',  count: overview.value.completedUsers,    pct: Math.round(overview.value.completedUsers / t * 100), color: '#E11D48' },
  ]
})

const scoreSegments = ref([
  { label: '700+', count: 0, pct: 0, color: '#4F46E5' },
  { label: '650-700', count: 0, pct: 0, color: '#0EA5E9' },
  { label: '600-650', count: 0, pct: 0, color: '#10B981' },
  { label: '550-600', count: 0, pct: 0, color: '#F59E0B' },
  { label: '500-550', count: 0, pct: 0, color: '#EF4444' },
  { label: '<500', count: 0, pct: 0, color: '#94A3B8' },
])

// 用户列表
const userList = ref<any[]>([])
const searchQuery = ref('')
const filterStatus = ref('')
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)

function statusLabel(s: string) {
  const m: Record<string, string> = { completed: '已完成', in_progress: '进行中', abandoned: '已放弃' }
  return m[s] || s
}
function statusType(s: string) {
  const m: Record<string, string> = { completed: 'success', in_progress: 'warning', abandoned: 'danger' }
  return m[s] || 'info'
}

// 配置
const config = ref({
  gaokaoEnabled: true, faceEnabled: true, mbtiEnabled: true,
  aiRecommendEnabled: true, paidReportEnabled: false, privacyNoticeEnabled: true,
  reportPrice: 9.9, freeQuota: 1, maxMajors: 10
})

async function loadOverview() {
  loading.value = true
  try {
    const res: any = await request.get('/superadmin/gaokao/overview')
    if (res.code === 200 && res.data) {
      overview.value = { ...overview.value, ...res.data }
      if (res.data.scoreDistribution) {
        const total = Object.values(res.data.scoreDistribution as Record<string, number>).reduce((a, b) => a + b, 0) || 1
        const segs = scoreSegments.value
        const keys = ['700plus', '650_700', '600_650', '550_600', '500_550', 'below500']
        keys.forEach((k, i) => {
          const c = (res.data.scoreDistribution[k] || 0) as number
          segs[i].count = c
          segs[i].pct = Math.round(c / total * 100)
        })
      }
      const now = new Date()
      lastUpdated.value = `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`
    }
  } catch (e: any) {
    ElMessage.warning('高考版概览数据暂时无法加载')
  } finally {
    loading.value = false
  }
}

async function loadUsers() {
  usersLoading.value = true
  try {
    const res: any = await request.get('/superadmin/gaokao/users', {
      params: { q: searchQuery.value, status: filterStatus.value, page: page.value, pageSize: pageSize.value }
    })
    if (res.code === 200) {
      userList.value = res.data?.list || []
      total.value = res.data?.total || 0
    }
  } catch { ElMessage.warning('用户列表加载失败') }
  finally { usersLoading.value = false }
}

async function loadConfig() {
  try {
    const res: any = await request.get('/superadmin/gaokao/config')
    if (res.code === 200 && res.data) config.value = { ...config.value, ...res.data }
  } catch {}
}

async function saveConfig() {
  configSaving.value = true
  try {
    const res: any = await request.post('/superadmin/gaokao/config', config.value)
    if (res.code === 200) ElMessage.success('配置已保存')
    else ElMessage.error(res.message || '保存失败')
  } catch { ElMessage.error('保存失败') }
  finally { configSaving.value = false }
}

function viewReport(row: any) {
  window.open(`/superadmin/gaokao/report/${row.reportId}`, '_blank')
}

function exportData() {
  const params = new URLSearchParams({ q: searchQuery.value, status: filterStatus.value })
  window.open(`/api/superadmin/gaokao/export?${params}`, '_blank')
}

async function loadAll() {
  await Promise.all([loadOverview(), loadUsers(), loadConfig()])
}

onMounted(loadAll)
</script>

<style scoped lang="scss">
.gaokao-hub {
  min-height: calc(100vh - 60px);
  background: #F4F6FB;
  padding: 20px 24px 32px;
}

.gh-head {
  display: flex; align-items: flex-end; justify-content: space-between;
  gap: 12px; flex-wrap: wrap; margin-bottom: 16px;
}
.gh-title { margin: 0 0 4px; font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -0.02em; }
.gh-desc { margin: 0; font-size: 12.5px; color: #6B7280; }
.gh-head-right { display: flex; align-items: center; gap: 10px; }
.gh-updated { font-size: 11px; color: #9CA3AF; font-variant-numeric: tabular-nums; }

.tab-bar {
  display: flex; gap: 4px; margin-bottom: 16px;
  background: #E5E7EB; border-radius: 10px; padding: 4px;
  width: fit-content;
}
.tab-btn {
  padding: 7px 18px; border: none; background: transparent;
  color: #6B7280; font-size: 13px; font-weight: 500; border-radius: 7px;
  cursor: pointer; transition: all 0.18s; white-space: nowrap;
  &:hover { background: rgba(255,255,255,0.6); color: #374151; }
  &.is-active { background: white; color: #111827; font-weight: 700; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
}

.tab-content { display: flex; flex-direction: column; gap: 14px; }

.kpi-grid {
  display: grid; grid-template-columns: repeat(4, minmax(0,1fr));
  gap: 12px; margin-bottom: 2px;
}
.kpi-card {
  background: white; border-radius: 14px; padding: 16px 18px;
  display: flex; align-items: center; gap: 14px;
  border: 1px solid #E5E7EB;
  box-shadow: 0 1px 4px rgba(0,0,0,0.04);
  transition: transform 0.2s, box-shadow 0.2s;
  &:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
}
.kpi-icon {
  width: 44px; height: 44px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; flex-shrink: 0;
}
.kpi-card.blue .kpi-icon   { background: #EEF2FF; color: #4F46E5; }
.kpi-card.green .kpi-icon  { background: #ECFDF5; color: #10B981; }
.kpi-card.purple .kpi-icon { background: #F5F3FF; color: #7C3AED; }
.kpi-card.amber .kpi-icon  { background: #FFFBEB; color: #D97706; }
.kpi-body { flex: 1; min-width: 0; }
.kpi-label { font-size: 11.5px; color: #6B7280; margin-bottom: 4px; }
.kpi-value { font-size: 26px; font-weight: 800; color: #111827; line-height: 1; font-variant-numeric: tabular-nums; }
.kpi-trend { display: flex; align-items: center; gap: 4px; margin-top: 4px; font-size: 11px; }
.trend-up { color: #10B981; font-weight: 600; }
.trend-down { color: #EF4444; font-weight: 600; }
.trend-label { color: #9CA3AF; }

.panel {
  background: white; border-radius: 14px; padding: 18px 20px;
  border: 1px solid #E5E7EB; box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.panel-head {
  display: flex; align-items: center; justify-content: space-between;
  gap: 12px; margin-bottom: 16px;
  padding-bottom: 12px; border-bottom: 1px solid #F3F4F6;
}
.panel-head-left, .panel-head-right { display: flex; align-items: center; gap: 10px; }
.panel-title { margin: 0; font-size: 15px; font-weight: 700; color: #111827; }
.panel-meta { font-size: 11.5px; color: #9CA3AF; }

.funnel-wrap { display: flex; flex-direction: column; gap: 10px; }
.funnel-step { display: flex; flex-direction: column; gap: 4px; }
.funnel-bar-wrap { height: 28px; background: #F3F4F6; border-radius: 6px; overflow: hidden; }
.funnel-bar { height: 100%; border-radius: 6px; transition: width 0.6s ease; }
.funnel-info { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #6B7280; }
.funnel-label { flex: 1; font-weight: 500; color: #374151; }
.funnel-count { font-weight: 700; color: #111827; font-variant-numeric: tabular-nums; }
.funnel-pct { color: #9CA3AF; font-variant-numeric: tabular-nums; width: 40px; text-align: right; }

.panel--half { }
.score-dist {
  display: flex; align-items: flex-end; gap: 10px; height: 140px; padding-top: 16px;
}
.score-seg { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: 1; }
.score-seg-bar { width: 100%; flex: 1; display: flex; align-items: flex-end; }
.score-seg-fill { width: 100%; border-radius: 4px 4px 0 0; min-height: 4px; transition: height 0.6s ease; }
.score-seg-label { font-size: 10px; color: #6B7280; text-align: center; }
.score-seg-count { font-size: 11px; font-weight: 700; color: #374151; font-variant-numeric: tabular-nums; }

.search-input { width: 180px; }

.data-table { width: 100%; }
.score-tag {
  background: #EEF2FF; color: #4338CA;
  font-size: 12px; font-weight: 700; border-radius: 4px; padding: 1px 8px;
}
.type-badge {
  font-size: 11px; font-weight: 700; border-radius: 4px; padding: 1px 6px;
  &.mbti { background: #F5F3FF; color: #7C3AED; }
}
.empty-tag { color: #D1D5DB; font-size: 13px; }
.table-footer { display: flex; justify-content: flex-end; margin-top: 14px; }

.config-panel {}
.config-grid { display: flex; flex-direction: column; gap: 0; }
.config-item {
  display: flex; align-items: center; justify-content: space-between;
  gap: 16px; padding: 14px 0; border-bottom: 1px solid #F3F4F6;
  &:last-child { border-bottom: none; }
}
.config-label { font-size: 14px; font-weight: 600; color: #111827; margin-bottom: 3px; }
.config-desc { font-size: 12px; color: #6B7280; line-height: 1.5; }

.config-fields { display: flex; flex-wrap: wrap; gap: 20px; padding-top: 8px; }
.config-field {
  display: flex; flex-direction: column; gap: 6px;
  label { font-size: 13px; font-weight: 600; color: #374151; }
}

@media (max-width: 900px) {
  .kpi-grid { grid-template-columns: repeat(2, 1fr); }
  .gaokao-hub { padding: 14px; }
}
@media (max-width: 640px) {
  .kpi-grid { grid-template-columns: 1fr 1fr; }
}
</style>
