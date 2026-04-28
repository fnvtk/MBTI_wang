<template>
  <div class="order-stats">
    <!-- KPI 卡片行 -->
    <div class="kpi-grid" v-loading="loading">
      <div v-for="card in kpiCards" :key="card.key" class="kpi-card">
        <div class="kpi-left">
          <div class="kpi-label">{{ card.label }}</div>
          <div class="kpi-value">{{ card.value }}</div>
          <div v-if="card.sub" class="kpi-sub">{{ card.sub }}</div>
        </div>
        <div :class="['kpi-icon', card.tone]">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" v-html="card.svg"></svg>
        </div>
      </div>
    </div>

    <!-- 图表区 -->
    <div class="charts-row">
      <!-- 近 30 天订单趋势 -->
      <div class="chart-card chart-card--wide">
        <div class="chart-header">
          <span class="chart-title">近 30 天成交趋势</span>
          <div class="period-btns">
            <button
              v-for="p in periods"
              :key="p.value"
              :class="['period-btn', { active: period === p.value }]"
              @click="period = p.value; loadTrend()"
            >{{ p.label }}</button>
          </div>
        </div>
        <VChart v-if="hasTrend" class="chart-canvas" :option="trendOption" autoresize />
        <div v-else class="empty-chart">暂无趋势数据</div>
      </div>

      <!-- 产品类型分布 -->
      <div class="chart-card">
        <div class="chart-header">
          <span class="chart-title">产品销售占比</span>
        </div>
        <VChart v-if="hasProduct" class="chart-canvas" :option="productOption" autoresize />
        <div v-else class="empty-chart">暂无产品数据</div>
      </div>
    </div>

    <!-- 第二行图表 -->
    <div class="charts-row">
      <!-- 订单状态分布 -->
      <div class="chart-card">
        <div class="chart-header">
          <span class="chart-title">订单状态分布</span>
        </div>
        <VChart v-if="hasStatus" class="chart-canvas chart-canvas--bar" :option="statusOption" autoresize />
        <div v-else class="empty-chart">暂无数据</div>
      </div>

      <!-- 近 7 天转化漏斗 -->
      <div class="chart-card chart-card--wide">
        <div class="chart-header">
          <span class="chart-title">近 7 天每日收入明细</span>
        </div>
        <div class="daily-table">
          <div class="dt-head">
            <span>日期</span><span>订单数</span><span>成交金额</span><span>转化率</span>
          </div>
          <div v-if="dailyRows.length" class="dt-body">
            <div v-for="row in dailyRows" :key="row.date" class="dt-row">
              <span>{{ row.date }}</span>
              <span>{{ row.count }}</span>
              <span class="amount">¥{{ row.revenue }}</span>
              <span :class="['rate', row.rate >= 50 ? 'rate--good' : '']">{{ row.rate }}%</span>
            </div>
          </div>
          <div v-else class="dt-empty">暂无数据</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { request } from '@/utils/request'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { LineChart, BarChart, PieChart } from 'echarts/charts'
import { GridComponent, TooltipComponent, LegendComponent } from 'echarts/components'
import VChart from 'vue-echarts'

use([CanvasRenderer, LineChart, BarChart, PieChart, GridComponent, TooltipComponent, LegendComponent])

const loading = ref(false)
const period = ref(30)
const periods = [
  { label: '7天', value: 7 },
  { label: '30天', value: 30 },
  { label: '90天', value: 90 }
]

// KPI 统计数据
const statsData = ref<{
  totalOrders: number
  paidOrders: number
  totalRevenueFen: number
  todayRevenueFen: number
  todayOrders: number
  refundCount: number
  avgOrderFen: number
}>({
  totalOrders: 0,
  paidOrders: 0,
  totalRevenueFen: 0,
  todayRevenueFen: 0,
  todayOrders: 0,
  refundCount: 0,
  avgOrderFen: 0
})

const trendData = ref<Array<{ date: string; count: number; revenueFen: number }>>([])
const productData = ref<Array<{ label: string; value: number }>>([])
const statusData = ref<Array<{ status: string; count: number }>>([])

const fen2yuan = (fen: number) => (fen / 100).toFixed(2)

const kpiCards = computed(() => [
  {
    key: 'total',
    label: '总订单数',
    value: statsData.value.totalOrders.toLocaleString(),
    sub: `今日 +${statsData.value.todayOrders}`,
    tone: 'tone-blue',
    svg: '<path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><line x1="3" y1="6" x2="21" y2="6" stroke="currentColor" stroke-width="1.75"/>'
  },
  {
    key: 'paid',
    label: '已成交订单',
    value: statsData.value.paidOrders.toLocaleString(),
    sub: `转化率 ${statsData.value.totalOrders ? ((statsData.value.paidOrders / statsData.value.totalOrders) * 100).toFixed(1) : 0}%`,
    tone: 'tone-green',
    svg: '<path d="M22 11.08V12a10 10 0 11-5.93-9.14" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/><polyline points="22 4 12 14.01 9 11.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>'
  },
  {
    key: 'revenue',
    label: '累计收入',
    value: '¥' + Number(fen2yuan(statsData.value.totalRevenueFen)).toLocaleString('zh-CN', { minimumFractionDigits: 2 }),
    sub: `今日 ¥${fen2yuan(statsData.value.todayRevenueFen)}`,
    tone: 'tone-purple',
    svg: '<line x1="12" y1="1" x2="12" y2="23" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>'
  },
  {
    key: 'avg',
    label: '客单价',
    value: '¥' + fen2yuan(statsData.value.avgOrderFen),
    sub: '成交均价',
    tone: 'tone-amber',
    svg: '<rect x="2" y="3" width="20" height="14" rx="2" ry="2" stroke="currentColor" stroke-width="1.75"/><line x1="8" y1="21" x2="16" y2="21" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/><line x1="12" y1="17" x2="12" y2="21" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>'
  },
  {
    key: 'refund',
    label: '退款订单',
    value: statsData.value.refundCount.toLocaleString(),
    sub: `退款率 ${statsData.value.totalOrders ? ((statsData.value.refundCount / statsData.value.totalOrders) * 100).toFixed(1) : 0}%`,
    tone: 'tone-red',
    svg: '<polyline points="1 4 1 10 7 10" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>'
  }
])

const hasTrend = computed(() => trendData.value.some(d => d.count > 0))
const hasProduct = computed(() => productData.value.some(d => d.value > 0))
const hasStatus = computed(() => statusData.value.some(d => d.count > 0))

const trendOption = computed(() => ({
  tooltip: {
    trigger: 'axis',
    axisPointer: { type: 'cross' },
    formatter: (params: any[]) => {
      const date = params[0]?.name || ''
      const count = params[0]?.value ?? 0
      const rev = params[1]?.value ?? 0
      return `${date}<br/>订单数：${count}<br/>收入：¥${rev}`
    }
  },
  legend: { data: ['订单数', '收入(元)'], top: 0, textStyle: { color: '#64748b', fontSize: 11 } },
  grid: { left: 50, right: 60, top: 32, bottom: 28 },
  xAxis: {
    type: 'category',
    data: trendData.value.map(d => d.date.slice(5)),
    axisLine: { lineStyle: { color: '#e2e8f0' } },
    axisLabel: { color: '#94a3b8', fontSize: 10 }
  },
  yAxis: [
    {
      type: 'value',
      name: '订单数',
      nameTextStyle: { color: '#94a3b8', fontSize: 10 },
      splitLine: { lineStyle: { color: '#f1f5f9' } },
      axisLabel: { color: '#94a3b8', fontSize: 10 }
    },
    {
      type: 'value',
      name: '收入(元)',
      nameTextStyle: { color: '#94a3b8', fontSize: 10 },
      splitLine: { show: false },
      axisLabel: { color: '#94a3b8', fontSize: 10, formatter: (v: number) => `¥${v}` }
    }
  ],
  series: [
    {
      name: '订单数',
      type: 'bar',
      barMaxWidth: 14,
      itemStyle: { color: '#4F46E5', borderRadius: [4, 4, 0, 0] },
      data: trendData.value.map(d => d.count)
    },
    {
      name: '收入(元)',
      type: 'line',
      smooth: true,
      yAxisIndex: 1,
      showSymbol: false,
      lineStyle: { color: '#10B981', width: 2 },
      areaStyle: { color: 'rgba(16,185,129,0.08)' },
      data: trendData.value.map(d => parseFloat(fen2yuan(d.revenueFen)))
    }
  ]
}))

const PRODUCT_COLORS = ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#6366F1', '#3B82F6']
const productOption = computed(() => ({
  tooltip: {
    trigger: 'item',
    formatter: ({ name, value, percent }: { name: string; value: number; percent: number }) =>
      `${name}<br/>收入：¥${value}<br/>占比：${percent}%`
  },
  legend: { bottom: 0, left: 'center', icon: 'circle', textStyle: { color: '#6b7280', fontSize: 11 } },
  series: [{
    name: '产品销售',
    type: 'pie',
    radius: ['40%', '68%'],
    center: ['50%', '44%'],
    avoidLabelOverlap: true,
    itemStyle: { borderRadius: 8, borderColor: '#fff', borderWidth: 2 },
    label: { show: true, formatter: '{b}\n¥{c}', color: '#374151', fontSize: 11 },
    labelLine: { length: 10, length2: 8 },
    data: productData.value.map((d, i) => ({
      name: d.label,
      value: parseFloat(fen2yuan(d.value)),
      itemStyle: { color: PRODUCT_COLORS[i % PRODUCT_COLORS.length] }
    }))
  }]
}))

const statusLabelMap: Record<string, string> = {
  pending: '待支付', paid: '已支付', completed: '已完成',
  cancelled: '已取消', refunded: '已退款', failed: '失败'
}
const statusColorMap: Record<string, string> = {
  pending: '#F59E0B', paid: '#10B981', completed: '#4F46E5',
  cancelled: '#9CA3AF', refunded: '#6B7280', failed: '#EF4444'
}

const statusOption = computed(() => ({
  tooltip: { trigger: 'axis' },
  grid: { left: 72, right: 16, top: 12, bottom: 28 },
  xAxis: { type: 'value', axisLabel: { color: '#94a3b8', fontSize: 10 }, splitLine: { lineStyle: { color: '#f1f5f9' } } },
  yAxis: {
    type: 'category',
    data: statusData.value.map(d => statusLabelMap[d.status] || d.status),
    axisLabel: { color: '#374151', fontSize: 12 }
  },
  series: [{
    type: 'bar',
    barMaxWidth: 20,
    itemStyle: {
      borderRadius: [0, 6, 6, 0],
      color: (params: any) => {
        const status = statusData.value[params.dataIndex]?.status || ''
        return statusColorMap[status] || '#4F46E5'
      }
    },
    data: statusData.value.map(d => d.count),
    label: { show: true, position: 'right', color: '#6b7280', fontSize: 11 }
  }]
}))

const dailyRows = computed(() => {
  return [...trendData.value]
    .slice(-7)
    .reverse()
    .map(d => ({
      date: d.date,
      count: d.count,
      revenue: fen2yuan(d.revenueFen),
      rate: d.count > 0 ? Math.min(100, Math.round((d.revenueFen / (d.count * 2000)) * 100)) : 0
    }))
})

async function loadStats() {
  loading.value = true
  try {
    const res: any = await request.get('/admin/orders/stats')
    const d = res.data ?? res
    statsData.value = {
      totalOrders:    d.totalOrders    ?? 0,
      paidOrders:     d.paidOrders     ?? 0,
      totalRevenueFen:d.totalRevenueFen?? 0,
      todayRevenueFen:d.todayRevenueFen?? 0,
      todayOrders:    d.todayOrders    ?? 0,
      refundCount:    d.refundCount    ?? 0,
      avgOrderFen:    d.avgOrderFen    ?? 0
    }
    productData.value = d.productSeries ?? []
    statusData.value  = d.statusSeries  ?? []
  } catch {
    // 接口不可用时保持默认值
  } finally {
    loading.value = false
  }
}

async function loadTrend() {
  try {
    const res: any = await request.get('/admin/orders/trend', { params: { days: period.value } })
    trendData.value = res.data?.list ?? res?.list ?? []
  } catch {
    trendData.value = []
  }
}

onMounted(async () => {
  await Promise.all([loadStats(), loadTrend()])
})
</script>

<style scoped lang="scss">
.order-stats {
  padding: 20px 24px 32px;
  background: #F4F6FB;
  min-height: calc(100vh - 130px);
}

/* KPI 卡片 */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 14px;
  margin-bottom: 20px;

  @media (max-width: 1200px) { grid-template-columns: repeat(3, 1fr); }
  @media (max-width: 768px) { grid-template-columns: repeat(2, 1fr); }
}

.kpi-card {
  background: #fff;
  border: 1px solid #E5E7EB;
  border-radius: 14px;
  padding: 18px 18px 16px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  box-shadow: 0 1px 3px rgba(16,24,40,0.04);
  transition: transform 0.18s, box-shadow 0.18s;

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79,70,229,0.08);
  }

  .kpi-label {
    font-size: 12.5px;
    color: #6B7280;
    margin-bottom: 6px;
  }

  .kpi-value {
    font-size: 22px;
    font-weight: 800;
    color: #111827;
    line-height: 1;
    letter-spacing: -0.02em;
  }

  .kpi-sub {
    font-size: 11.5px;
    color: #9CA3AF;
    margin-top: 6px;
  }

  .kpi-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
}

.tone-blue   { background: #EFF6FF; color: #3B82F6; }
.tone-green  { background: #ECFDF5; color: #10B981; }
.tone-purple { background: #F5F3FF; color: #7C3AED; }
.tone-amber  { background: #FFFBEB; color: #F59E0B; }
.tone-red    { background: #FEF2F2; color: #EF4444; }

/* 图表区 */
.charts-row {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 14px;
  margin-bottom: 14px;

  @media (max-width: 900px) { grid-template-columns: 1fr; }
}

.chart-card {
  background: #fff;
  border: 1px solid #E5E7EB;
  border-radius: 14px;
  padding: 18px 20px;
  box-shadow: 0 1px 3px rgba(16,24,40,0.04);

  &--wide { /* 已由 grid 控制 */ }
}

.chart-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;

  .chart-title {
    font-size: 13.5px;
    font-weight: 700;
    color: #111827;
  }
}

.period-btns {
  display: flex;
  gap: 4px;

  .period-btn {
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 12px;
    border: 1px solid #E5E7EB;
    background: #fff;
    color: #6B7280;
    cursor: pointer;
    transition: all 0.15s;

    &.active {
      background: #4F46E5;
      border-color: #4F46E5;
      color: #fff;
      font-weight: 600;
    }

    &:hover:not(.active) { background: #F9FAFB; }
  }
}

.chart-canvas {
  height: 220px;
  width: 100%;

  &--bar { height: 200px; }
}

.empty-chart {
  height: 180px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #CBD5E1;
  font-size: 13px;
}

/* 每日明细表 */
.daily-table {
  .dt-head, .dt-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1.5fr 1fr;
    padding: 7px 4px;
    font-size: 12.5px;
    gap: 4px;
  }

  .dt-head {
    color: #9CA3AF;
    font-weight: 600;
    font-size: 11.5px;
    border-bottom: 1px solid #F3F4F6;
    margin-bottom: 4px;
  }

  .dt-row {
    color: #374151;
    border-radius: 6px;
    transition: background 0.12s;
    &:hover { background: #F9FAFB; }
  }

  .amount { font-weight: 700; color: #111827; }
  .rate   { color: #9CA3AF; }
  .rate--good { color: #10B981; font-weight: 600; }

  .dt-empty {
    padding: 40px;
    text-align: center;
    color: #CBD5E1;
    font-size: 13px;
  }
}
</style>
