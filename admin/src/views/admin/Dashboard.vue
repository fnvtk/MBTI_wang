<template>
  <div class="dashboard-viewport" v-loading="loading">
    <header class="dash-head">
      <h1 class="dash-title">数据概览</h1>
      <p class="dash-tagline">
        企业侧测评闭环：小程序面相与 MBTI / DISC / PDP 答题 → MySQL 落库 → 本页看用户与测试趋势、拉新邀请码（全链路见开发文档《小程序全链路功能与接口》）。
      </p>
    </header>

    <div class="dash-kpis">
      <div v-for="(card, i) in kpiCards" :key="card.key" class="stat-card" :style="{ animationDelay: `${i * 45}ms` }">
        <div class="stat-info">
          <div class="stat-label">{{ card.label }}</div>
          <div class="stat-value">{{ card.value }}</div>
        </div>
        <div :class="['stat-icon', card.tone]">
          <el-icon><component :is="card.icon" /></el-icon>
        </div>
      </div>
    </div>

    <div class="dash-main">
      <section class="panel panel-chart">
        <div class="panel-head">
          <h2 class="panel-title">近 14 日测试趋势</h2>
          <p class="panel-desc">人脸 · MBTI · PDP · DISC 完成量</p>
        </div>
        <div class="chart-box">
          <VChart v-if="testTrends.length" class="trend-chart" :option="chartOption" autoresize />
          <div v-else class="panel-empty">暂无趋势数据</div>
        </div>
      </section>

      <aside class="panel panel-side">
        <div class="side-block side-users">
          <div class="panel-head row">
            <div>
              <h2 class="panel-title">测试 Top 10</h2>
              <p class="panel-desc">按完成次数 · 本企业口径</p>
            </div>
            <el-button type="primary" link size="small" @click="router.push('/admin/users')">全部用户</el-button>
          </div>
          <div class="table-wrap">
            <el-table
              v-if="topTestUsers.length"
              :data="topTestUsers"
              size="small"
              stripe
              class="compact-table"
              :max-height="tableMaxH"
            >
              <el-table-column label="#" width="42">
                <template #default="{ $index }">{{ $index + 1 }}</template>
              </el-table-column>
              <el-table-column label="用户" min-width="88" show-overflow-tooltip>
                <template #default="{ row }">{{ row.username || '未命名' }}</template>
              </el-table-column>
              <el-table-column prop="testCount" label="次数" width="52" align="center" />
              <el-table-column label="摘要" min-width="100" show-overflow-tooltip>
                <template #default="{ row }">{{ summarizeTypes(row) }}</template>
              </el-table-column>
            </el-table>
            <div v-else class="panel-empty tight">暂无测试记录</div>
          </div>
        </div>

        <div class="side-block side-invite">
          <div class="panel-head row">
            <div>
              <h2 class="panel-title">邀请小程序码</h2>
              <p class="panel-desc">员工 / 客户扫码进入企业测评</p>
            </div>
            <el-button size="small" type="primary" @click="loadInviteQrcode" :loading="inviteLoading">
              {{ inviteQrcode ? '刷新' : '生成' }}
            </el-button>
          </div>
          <div class="invite-body">
            <img v-if="inviteQrcode" :src="inviteQrcode" alt="邀请码" class="invite-img" />
            <span v-else class="invite-placeholder">点击生成</span>
          </div>
        </div>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { User, Document, TrendCharts } from '@element-plus/icons-vue'
import { request } from '@/utils/request'
import { ElMessage } from 'element-plus'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { LineChart } from 'echarts/charts'
import { GridComponent, TooltipComponent, LegendComponent } from 'echarts/components'
import VChart from 'vue-echarts'

use([CanvasRenderer, LineChart, GridComponent, TooltipComponent, LegendComponent])

const router = useRouter()

interface TopUserRow {
  id: number
  username: string
  phone: string
  testCount: number
  lastTestAt: number | null
  mbtiType: string
  pdpType: string
  discType: string
  faceMbtiType: string
  faceDiscType: string
  facePdpType: string
}

const stats = reactive({
  totalUsers: 0,
  testsCompleted: 0,
  activeToday: 0,
  pendingReviews: 0
})

const testTrends = ref<
  Array<{ date: string; face: number; mbti: number; pdp: number; disc: number; total: number }>
>([])
const topTestUsers = ref<TopUserRow[]>([])
const loading = ref(false)
const inviteLoading = ref(false)
const inviteQrcode = ref<string>('')

/** 侧栏表格最大高度：单屏内滚动，不撑开整页 */
const tableMaxH = 220

const kpiCards = computed(() => [
  { key: 'u', label: '总用户数', value: stats.totalUsers, icon: User, tone: 'blue' },
  { key: 't', label: '已完成测试', value: stats.testsCompleted, icon: Document, tone: 'green' },
  { key: 'a', label: '今日活跃', value: stats.activeToday, icon: TrendCharts, tone: 'purple' },
  { key: 'p', label: '待审核', value: stats.pendingReviews, icon: User, tone: 'orange' }
])

const chartOption = computed(() => {
  const dates = testTrends.value.map(d => d.date.slice(5))
  return {
    animationDuration: 480,
    animationEasing: 'cubicOut',
    tooltip: { trigger: 'axis' },
    legend: {
      data: ['人脸', 'MBTI', 'PDP', 'DISC'],
      top: 0,
      textStyle: { fontSize: 11, color: '#6b7280' }
    },
    grid: { left: 36, right: 12, top: 36, bottom: 24 },
    xAxis: {
      type: 'category',
      data: dates,
      boundaryGap: false,
      axisLine: { lineStyle: { color: '#e5e7eb' } },
      axisLabel: { color: '#9ca3af', fontSize: 10 }
    },
    yAxis: {
      type: 'value',
      minInterval: 1,
      splitLine: { lineStyle: { color: '#f3f4f6' } },
      axisLabel: { color: '#9ca3af', fontSize: 10 }
    },
    series: [
      {
        name: '人脸',
        type: 'line',
        smooth: 0.35,
        showSymbol: false,
        lineStyle: { width: 2 },
        itemStyle: { color: '#22c55e' },
        data: testTrends.value.map(d => d.face)
      },
      {
        name: 'MBTI',
        type: 'line',
        smooth: 0.35,
        showSymbol: false,
        lineStyle: { width: 2 },
        itemStyle: { color: '#3b82f6' },
        data: testTrends.value.map(d => d.mbti)
      },
      {
        name: 'PDP',
        type: 'line',
        smooth: 0.35,
        showSymbol: false,
        lineStyle: { width: 2 },
        itemStyle: { color: '#f97316' },
        data: testTrends.value.map(d => d.pdp)
      },
      {
        name: 'DISC',
        type: 'line',
        smooth: 0.35,
        showSymbol: false,
        lineStyle: { width: 2 },
        itemStyle: { color: '#6366f1' },
        data: testTrends.value.map(d => d.disc)
      }
    ]
  }
})

function summarizeTypes(row: TopUserRow) {
  const parts: string[] = []
  if (row.mbtiType) parts.push(row.mbtiType)
  if (row.pdpType) parts.push(row.pdpType)
  if (row.discType) parts.push(row.discType)
  const faceBits = [row.faceMbtiType, row.facePdpType, row.faceDiscType].filter(Boolean)
  if (faceBits.length) parts.push('面:' + faceBits.join('/'))
  return parts.length ? parts.join(' · ') : '—'
}

const loadData = async () => {
  loading.value = true
  try {
    const response: any = await request.get('/admin/dashboard')
    if (response.code === 200 && response.data) {
      stats.totalUsers = response.data.totalUsers || 0
      stats.testsCompleted = response.data.testsCompleted || 0
      stats.activeToday = response.data.activeToday || 0
      stats.pendingReviews = response.data.pendingReviews || 0
      testTrends.value = response.data.testTrends || []
      topTestUsers.value = Array.isArray(response.data.topTestUsers) ? response.data.topTestUsers : []
    }
  } catch (error: any) {
    console.error('加载数据失败:', error)
    ElMessage.error(error.message || '加载数据失败')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadData()
})

const loadInviteQrcode = async () => {
  if (inviteLoading.value) return
  inviteLoading.value = true
  try {
    const res: any = await request.get('/admin/invite/qrcode')
    const qrcode = res?.data?.qrcode
    if (qrcode && typeof qrcode === 'string') {
      inviteQrcode.value = qrcode
    } else {
      ElMessage.error(res?.message || res?.msg || '生成失败，请确认企业绑定')
    }
  } catch (error: any) {
    ElMessage.error(error?.message || '生成失败')
  } finally {
    inviteLoading.value = false
  }
}
</script>

<style scoped lang="scss">
@keyframes dashFadeUp {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.dashboard-viewport {
  height: calc(100vh - 56px);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  padding: 14px 18px 16px;
  box-sizing: border-box;
  background: #f3f4f6;
}

.dash-head {
  flex: 0 0 auto;
  margin-bottom: 10px;
  animation: dashFadeUp 0.4s ease-out both;
}

.dash-title {
  margin: 0 0 4px;
  font-size: 20px;
  font-weight: 700;
  color: #111827;
  letter-spacing: -0.02em;
}

.dash-tagline {
  margin: 0;
  font-size: 12px;
  line-height: 1.55;
  color: #6b7280;
  max-width: 920px;
}

.dash-kpis {
  flex: 0 0 auto;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 10px;
}

.stat-card {
  background: #fff;
  border-radius: 10px;
  padding: 12px 14px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
  animation: dashFadeUp 0.45s ease-out both;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;

  &:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.08);
  }

  .stat-label {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 4px;
  }

  .stat-value {
    font-size: 22px;
    font-weight: 700;
    color: #111827;
    line-height: 1;
    font-variant-numeric: tabular-nums;
    transition: color 0.2s ease;
  }

  .stat-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;

    &.blue {
      background: #eff6ff;
      color: #3b82f6;
    }
    &.green {
      background: #f0fdf4;
      color: #22c55e;
    }
    &.purple {
      background: #faf5ff;
      color: #a855f7;
    }
    &.orange {
      background: #fffbeb;
      color: #f59e0b;
    }
  }
}

.dash-main {
  flex: 1;
  min-height: 0;
  display: grid;
  grid-template-columns: 1fr minmax(280px, 30%);
  gap: 10px;
  animation: dashFadeUp 0.5s ease-out 0.08s both;
}

.panel {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  min-height: 0;
}

.panel-chart {
  min-height: 0;
}

.panel-side {
  gap: 10px;
  padding: 10px;
}

.panel-head {
  flex: 0 0 auto;
  margin-bottom: 8px;

  &.row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
  }
}

.panel-title {
  margin: 0 0 2px;
  font-size: 14px;
  font-weight: 700;
  color: #111827;
}

.panel-desc {
  margin: 0;
  font-size: 11px;
  color: #9ca3af;
}

.chart-box {
  flex: 1;
  min-height: 0;
  position: relative;
}

.trend-chart {
  width: 100%;
  height: 100%;
  min-height: 160px;
}

.panel-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 120px;
  color: #9ca3af;
  font-size: 13px;

  &.tight {
    height: 80px;
  }
}

.side-block {
  display: flex;
  flex-direction: column;
  min-height: 0;
}

.side-users {
  flex: 1;
  min-height: 0;
  padding: 10px 12px;
  background: #fafafa;
  border-radius: 8px;
  border: 1px solid #f3f4f6;
}

.table-wrap {
  flex: 1;
  min-height: 0;
}

.compact-table {
  width: 100%;
}

.side-invite {
  flex: 0 0 auto;
  padding: 10px 12px;
  background: #fafafa;
  border-radius: 8px;
  border: 1px solid #f3f4f6;
}

.invite-body {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 112px;
}

.invite-img {
  width: 112px;
  height: 112px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  object-fit: contain;
  background: #fff;
}

.invite-placeholder {
  font-size: 12px;
  color: #9ca3af;
}

@media (max-width: 1100px) {
  .dash-main {
    grid-template-columns: 1fr;
  }

  .dashboard-viewport {
    height: auto;
    min-height: calc(100vh - 56px);
    overflow-y: auto;
  }

  .panel-chart .chart-box {
    min-height: 220px;
  }
}

@media (max-width: 640px) {
  .dash-kpis {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
