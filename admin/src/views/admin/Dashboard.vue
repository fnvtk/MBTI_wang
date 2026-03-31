<template>
  <div class="dashboard-viewport" v-loading="loading">
    <header class="dash-head">
      <h1 class="dash-title">数据概览</h1>
      <p class="dash-tagline"></p>
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
      <div class="stat-card stat-card-recharge" :style="{ animationDelay: `${kpiCards.length * 45}ms` }">
        <div class="stat-card-recharge-inner">
          <div class="stat-info stat-info--recharge">
            <div class="stat-label">企业余额</div>
            <div class="stat-value-row">
              <span class="stat-value">{{ enterpriseBalanceDisplay }}</span>
              <el-button link type="primary" size="small" class="recharge-btn-inline" @click="goEnterpriseRecharge">充值</el-button>
            </div>
          </div>
        </div>
        <div class="stat-icon amber">
          <el-icon><Wallet /></el-icon>
        </div>
      </div>
    </div>

    <div class="dash-catalog" v-if="testCatalog.length">
      <div
        v-for="(row, i) in catalogRows"
        :key="row.key"
        class="catalog-card"
        :style="{ animationDelay: `${120 + i * 40}ms` }"
      >
        <div :class="['catalog-icon', row.tone]">
          <el-icon><component :is="row.icon" /></el-icon>
        </div>
        <div class="catalog-body">
          <div class="catalog-label">{{ row.label }}</div>
          <div class="catalog-metrics">
            <span><em>{{ row.records }}</em> 人次</span>
            <span class="sep">·</span>
            <span><em>{{ row.uniqueUsers }}</em> 人</span>
          </div>
        </div>
      </div>
    </div>

    <div class="dash-main">
      <section class="panel panel-chart">
        <div class="panel-head">
          <h2 class="panel-title">近 14 日测试趋势</h2>
          <p class="panel-desc">人脸 · MBTI · PDP · DISC 完成量；下方为性格结果分布（与本企业测评数据一致）</p>
        </div>
        <div v-if="hasDistribution" class="distr-band">
          <div v-for="block in distributionBlocks" :key="block.key" class="distr-col">
            <div class="distr-col-head">
              <el-icon class="distr-head-ic"><component :is="block.icon" /></el-icon>
              <span>{{ block.title }}</span>
            </div>
            <div class="distr-list">
              <div v-for="it in block.items" :key="block.key + it.label" class="distr-row-line">
                <span class="distr-lab" :title="it.label">{{ it.label }}</span>
                <div class="distr-bar-track">
                  <div
                    class="distr-bar-fill"
                    :class="block.barClass"
                    :style="{ width: barWidthPct(block.max, it.count) }"
                  />
                </div>
                <span class="distr-num">{{ it.count }}</span>
              </div>
            </div>
          </div>
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
              <h2 class="panel-title">测试 Top 20</h2>
              <p class="panel-desc">按完成次数 · 本企业口径</p>
            </div>
            <el-button type="primary" link size="small" @click="router.push('/admin/users')">全部用户</el-button>
          </div>
          <div ref="tableWrapRef" class="table-wrap">
            <el-table
              v-if="topTestUsers.length"
              :data="topTestUsers"
              size="small"
              stripe
              class="compact-table"
              :height="sideTableHeight"
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
              <p class="panel-desc">企业版进企业测评，个人版进小程序首页</p>
            </div>
            <el-button size="small" type="primary" @click="loadInviteQrcode" :loading="inviteLoading">
              {{ inviteQrcodeEnterprise || inviteQrcodePersonal ? '刷新' : '生成' }}
            </el-button>
          </div>
          <div class="invite-body">
            <template v-if="inviteQrcodeEnterprise || inviteQrcodePersonal">
              <div v-if="inviteQrcodeEnterprise" class="invite-card">
                <span class="invite-label">企业版</span>
                <img :src="inviteQrcodeEnterprise" alt="企业版太阳码" class="invite-img" />
              </div>
              <div v-if="inviteQrcodePersonal" class="invite-card">
                <span class="invite-label">个人版</span>
                <img :src="inviteQrcodePersonal" alt="个人版太阳码" class="invite-img" />
              </div>
            </template>
            <span v-else class="invite-placeholder">{{ inviteLoadError || '进入页面将自动加载，也可点击生成' }}</span>
          </div>
        </div>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted, computed, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import {
  User,
  Document,
  TrendCharts,
  Camera,
  Reading,
  Histogram,
  Medal,
  Wallet
} from '@element-plus/icons-vue'
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
  activeToday: 0
})

/** 企业余额（分），来自 /admin/finance/overview，与财务页一致 */
const balanceFen = ref(0)

const fenToYuan = (fen: number) => (Number(fen || 0) / 100).toFixed(2)

const enterpriseBalanceDisplay = computed(() => `¥${fenToYuan(balanceFen.value)}`)

const testTrends = ref<
  Array<{ date: string; face: number; mbti: number; pdp: number; disc: number; total: number }>
>([])
const topTestUsers = ref<TopUserRow[]>([])
const testCatalog = ref<
  Array<{ key: string; label: string; records: number; uniqueUsers: number }>
>([])
const distributionMbti = ref<Array<{ label: string; count: number }>>([])
const distributionDisc = ref<Array<{ label: string; count: number }>>([])
const distributionPdp = ref<Array<{ label: string; count: number }>>([])
const faceSubtypeHints = ref<{
  mbti: Array<{ label: string; count: number }>
  disc: Array<{ label: string; count: number }>
  pdp: Array<{ label: string; count: number }>
}>({ mbti: [], disc: [], pdp: [] })

const loading = ref(false)
const inviteLoading = ref(false)
const inviteQrcodeEnterprise = ref<string>('')
const inviteQrcodePersonal = ref<string>('')
const inviteLoadError = ref<string>('')

/** 侧栏表格高度：随 `.table-wrap` 可用空间变化（适配 Top 20，避免固定 220px 导致大片留白） */
const tableWrapRef = ref<HTMLElement | null>(null)
const sideTableHeight = ref(360)
let tableWrapResizeObserver: ResizeObserver | null = null

function updateSideTableHeight() {
  const el = tableWrapRef.value
  if (!el) {
    return
  }
  const h = Math.floor(el.getBoundingClientRect().height)
  if (h >= 120) {
    sideTableHeight.value = h
  }
}

const kpiCards = computed(() => [
  { key: 'u', label: '总用户数', value: stats.totalUsers, icon: User, tone: 'blue' },
  { key: 't', label: '已完成测试', value: stats.testsCompleted, icon: Document, tone: 'green' },
  { key: 'a', label: '今日活跃', value: stats.activeToday, icon: TrendCharts, tone: 'purple' }
])

function goEnterpriseRecharge() {
  void router.push({ path: '/admin/settings', query: { tab: 'finance' } })
}

const catalogIconMap: Record<string, { icon: typeof Camera; tone: string }> = {
  face: { icon: Camera, tone: 'teal' },
  mbti: { icon: Reading, tone: 'blue' },
  disc: { icon: Histogram, tone: 'indigo' },
  pdp: { icon: Medal, tone: 'amber' }
}

const catalogRows = computed(() =>
  testCatalog.value.map(row => {
    const m = catalogIconMap[row.key] || { icon: Document, tone: 'blue' }
    return {
      ...row,
      icon: m.icon,
      tone: m.tone,
      records: row.records ?? 0,
      uniqueUsers: row.uniqueUsers ?? 0
    }
  })
)

function sliceItems(items: Array<{ label: string; count: number }>, n: number) {
  return (items || []).slice(0, n)
}

const distributionBlocks = computed(() => {
  const mbti = sliceItems(distributionMbti.value, 6)
  const disc = sliceItems(distributionDisc.value, 6)
  const pdp = sliceItems(distributionPdp.value, 6)
  const faceMerged: Array<{ label: string; count: number }> = []
  const fh = faceSubtypeHints.value || { mbti: [], disc: [], pdp: [] }
  const pushPref = (prefix: string, arr: Array<{ label: string; count: number }>, max: number) => {
    let k = 0
    for (const it of arr || []) {
      if (k >= max) break
      faceMerged.push({ label: `${prefix}${it.label}`, count: it.count })
      k++
    }
  }
  pushPref('面·MBTI ', fh.mbti, 2)
  pushPref('面·DISC ', fh.disc, 2)
  pushPref('面·PDP ', fh.pdp, 2)

  const blocks = [
    { key: 'mbti', title: 'MBTI（答题）', items: mbti, icon: Reading, barClass: 'bar-mbti' },
    { key: 'disc', title: 'DISC（答题）', items: disc, icon: Histogram, barClass: 'bar-disc' },
    { key: 'pdp', title: 'PDP（答题）', items: pdp, icon: Medal, barClass: 'bar-pdp' },
    { key: 'face', title: '面相推测', items: faceMerged, icon: Camera, barClass: 'bar-face' }
  ]
  return blocks.map(b => ({
    ...b,
    max: Math.max(1, ...b.items.map(i => i.count))
  }))
})

const hasDistribution = computed(() =>
  distributionBlocks.value.some(b => b.items.length > 0)
)

function barWidthPct(max: number, count: number) {
  if (!max || !count) return '0%'
  return `${Math.round((count / max) * 100)}%`
}

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

async function loadFinanceBalance() {
  try {
    const res: any = await request.get('/admin/finance/overview')
    balanceFen.value = Number(res?.data?.balanceFen ?? 0)
  } catch {
    balanceFen.value = 0
  }
}

const loadData = async () => {
  loading.value = true
  try {
    const [response] = await Promise.all([request.get('/admin/dashboard'), loadFinanceBalance()])
    if (response.code === 200 && response.data) {
      stats.totalUsers = response.data.totalUsers || 0
      stats.testsCompleted = response.data.testsCompleted || 0
      stats.activeToday = response.data.activeToday || 0
      testTrends.value = response.data.testTrends || []
      topTestUsers.value = Array.isArray(response.data.topTestUsers) ? response.data.topTestUsers : []
      testCatalog.value = Array.isArray(response.data.testCatalog) ? response.data.testCatalog : []
      distributionMbti.value = Array.isArray(response.data.distributionMbti)
        ? response.data.distributionMbti
        : []
      distributionDisc.value = Array.isArray(response.data.distributionDisc)
        ? response.data.distributionDisc
        : []
      distributionPdp.value = Array.isArray(response.data.distributionPdp)
        ? response.data.distributionPdp
        : []
      const fh = response.data.faceSubtypeHints
      faceSubtypeHints.value =
        fh && typeof fh === 'object'
          ? {
              mbti: Array.isArray(fh.mbti) ? fh.mbti : [],
              disc: Array.isArray(fh.disc) ? fh.disc : [],
              pdp: Array.isArray(fh.pdp) ? fh.pdp : []
            }
          : { mbti: [], disc: [], pdp: [] }
    }
  } catch (error: any) {
    console.error('加载数据失败:', error)
    ElMessage.error(error.message || '加载数据失败')
  } finally {
    loading.value = false
    void nextTick(() => updateSideTableHeight())
  }
}

onMounted(() => {
  void nextTick(() => {
    updateSideTableHeight()
    if (tableWrapRef.value && typeof ResizeObserver !== 'undefined') {
      tableWrapResizeObserver = new ResizeObserver(() => updateSideTableHeight())
      tableWrapResizeObserver.observe(tableWrapRef.value)
    }
  })
  window.addEventListener('resize', updateSideTableHeight)
  void loadData()
  void loadInviteQrcode()
})

onUnmounted(() => {
  tableWrapResizeObserver?.disconnect()
  tableWrapResizeObserver = null
  window.removeEventListener('resize', updateSideTableHeight)
})

const loadInviteQrcode = async () => {
  if (inviteLoading.value) return
  inviteLoading.value = true
  inviteLoadError.value = ''
  try {
    const res: any = await request.get('/admin/invite/qrcode')
    const d = res?.data
    const ent = d?.enterprise?.qrcode ?? d?.qrcode
    const per = d?.personal?.qrcode
    if (typeof ent === 'string' && ent) inviteQrcodeEnterprise.value = ent
    else inviteQrcodeEnterprise.value = ''
    if (typeof per === 'string' && per) inviteQrcodePersonal.value = per
    else inviteQrcodePersonal.value = ''

    if (!inviteQrcodeEnterprise.value && !inviteQrcodePersonal.value) {
      const msg = res?.message || res?.msg || '生成失败，请确认企业绑定'
      inviteLoadError.value = msg
      ElMessage.error(msg)
    }
  } catch (error: any) {
    const msg = error?.message || '生成失败'
    inviteLoadError.value = msg
    ElMessage.error(msg)
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
  margin-bottom: 8px;
}

.dash-catalog {
  flex: 0 0 auto;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 8px;
}

.catalog-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: #fff;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
  animation: dashFadeUp 0.45s ease-out both;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;

  &:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.06);
  }
}

.catalog-icon {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 17px;
  flex-shrink: 0;

  &.teal {
    background: #f0fdfa;
    color: #0d9488;
  }

  &.blue {
    background: #eff6ff;
    color: #3b82f6;
  }

  &.indigo {
    background: #eef2ff;
    color: #6366f1;
  }

  &.amber {
    background: #fffbeb;
    color: #d97706;
  }
}

.catalog-body {
  min-width: 0;
}

.catalog-label {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 2px;
}

.catalog-metrics {
  font-size: 11px;
  color: #6b7280;

  em {
    font-style: normal;
    font-weight: 700;
    color: #374151;
    font-variant-numeric: tabular-nums;
  }

  .sep {
    margin: 0 4px;
    color: #d1d5db;
  }
}

.stat-card-recharge {
  .stat-card-recharge-inner {
    flex: 1;
    min-width: 0;
  }

  .stat-info--recharge {
    text-align: left;

    .stat-value-row {
      display: flex;
      flex-direction: row;
      align-items: baseline;
      flex-wrap: wrap;
      gap: 2px 8px;
    }

    .stat-value {
      font-size: 22px;
      font-weight: 700;
      color: #111827;
      line-height: 1.15;
      font-variant-numeric: tabular-nums;
    }
  }

  .recharge-btn-inline {
    flex-shrink: 0;
    padding: 0 4px;
    height: auto;
    margin-bottom: -2px;
    font-weight: 600;
    font-size: 14px;
    vertical-align: baseline;
  }

  .stat-icon.amber {
    background: #fffbeb;
    color: #d97706;
  }
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

.distr-band {
  flex: 0 0 auto;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 8px;
  padding-bottom: 8px;
  border-bottom: 1px solid #f3f4f6;
}

.distr-col {
  min-width: 0;
  background: #fafafa;
  border-radius: 8px;
  padding: 8px 10px;
  border: 1px solid #f3f4f6;
}

.distr-col-head {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 700;
  color: #374151;
  margin-bottom: 6px;

  .distr-head-ic {
    font-size: 14px;
    color: #6b7280;
  }
}

.distr-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-height: 108px;
  overflow-y: auto;
}

.distr-row-line {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(36px, 42%) 22px;
  align-items: center;
  gap: 6px;
  font-size: 10px;
}

.distr-lab {
  color: #4b5563;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.distr-bar-track {
  height: 5px;
  background: #e5e7eb;
  border-radius: 3px;
  overflow: hidden;
}

.distr-bar-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.35s ease;

  &.bar-mbti {
    background: #3b82f6;
  }

  &.bar-disc {
    background: #6366f1;
  }

  &.bar-pdp {
    background: #f97316;
  }

  &.bar-face {
    background: #14b8a6;
  }
}

.distr-num {
  text-align: right;
  color: #6b7280;
  font-variant-numeric: tabular-nums;
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
  min-height: 140px;
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
  display: flex;
  flex-direction: column;
  padding: 10px 12px;
  background: #fafafa;
  border-radius: 8px;
  border: 1px solid #f3f4f6;
  overflow: hidden;
}

.table-wrap {
  flex: 1 1 0;
  min-height: 0;
  overflow: hidden;
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
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: center;
  gap: 16px;
  min-height: 112px;
}

.invite-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.invite-label {
  font-size: 12px;
  font-weight: 600;
  color: #374151;
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

  .dash-catalog {
    grid-template-columns: repeat(2, 1fr);
  }

  .distr-band {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
