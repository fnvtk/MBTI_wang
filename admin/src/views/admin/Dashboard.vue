<template>
  <div class="dashboard-viewport" v-loading="loading">
    <header class="dash-head">
      <div class="dash-head-left">
        <h1 class="dash-title">企业概览</h1>
        <p class="dash-tagline">用户规模与测评完成情况 · 分布与近 14 日趋势</p>
      </div>
      <div class="dash-head-right">
        <span v-if="lastUpdatedText" class="dash-updated">{{ lastUpdatedText }}</span>
        <el-button size="small" :icon="Refresh" @click="refreshAll" :loading="loading">刷新</el-button>
      </div>
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
        <div class="panel-section-head">
          <h2 class="panel-title">测评结果分布</h2>
          <span class="panel-meta">各类型高频结果（TOP）</span>
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
        <div v-else class="panel-empty tight">暂无分布数据</div>

        <div class="panel-section-head panel-section-head--chart">
          <h2 class="panel-title">近 14 日 · 测评完成趋势</h2>
          <div class="chart-legend" role="tablist">
            <button
              v-for="key in chartModeOptions"
              :key="key.value"
              class="chart-mode-btn"
              :class="{ 'is-active': chartMode === key.value }"
              @click="chartMode = key.value"
            >
              {{ key.label }}
            </button>
          </div>
        </div>
        <div class="chart-sub-meta">
          <span class="chart-meta-item">
            <em class="chart-meta-dot bar-mbti"></em>MBTI
          </span>
          <span class="chart-meta-item">
            <em class="chart-meta-dot bar-pdp"></em>PDP
          </span>
          <span class="chart-meta-item">
            <em class="chart-meta-dot bar-disc"></em>DISC
          </span>
          <span class="chart-meta-item">
            <em class="chart-meta-dot bar-face"></em>人脸
          </span>
          <span class="chart-meta-sum" v-if="trendTotalsText">累计 {{ trendTotalsText }}</span>
        </div>
        <div class="chart-box">
          <VChart v-if="testTrends.length" class="trend-chart" :option="chartOption" autoresize />
          <div v-else class="panel-empty">暂无趋势数据</div>
        </div>
      </section>

      <aside class="panel panel-side">
        <div class="side-block side-invite">
          <div class="panel-head row">
            <div>
              <h2 class="panel-title">邀请小程序码</h2>
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
import { ref, reactive, onMounted, computed } from 'vue'
import {
  User,
  Document,
  TrendCharts,
  Camera,
  Reading,
  Histogram,
  Medal,
  Refresh
} from '@element-plus/icons-vue'
import { request } from '@/utils/request'
import { ElMessage } from 'element-plus'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { LineChart, BarChart } from 'echarts/charts'
import { GridComponent, TooltipComponent, LegendComponent } from 'echarts/components'
import VChart from 'vue-echarts'

use([CanvasRenderer, LineChart, BarChart, GridComponent, TooltipComponent, LegendComponent])

const stats = reactive({
  totalUsers: 0,
  testsCompleted: 0,
  activeToday: 0
})

const testTrends = ref<
  Array<{ date: string; face: number; mbti: number; pdp: number; disc: number; total: number }>
>([])
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
const lastUpdatedAt = ref<number>(0)

const chartModeOptions = [
  { label: '堆叠', value: 'stack' as const },
  { label: '折线', value: 'line' as const }
]
const chartMode = ref<'stack' | 'line'>('stack')

const lastUpdatedText = computed(() => {
  if (!lastUpdatedAt.value) return ''
  const d = new Date(lastUpdatedAt.value)
  const hh = String(d.getHours()).padStart(2, '0')
  const mm = String(d.getMinutes()).padStart(2, '0')
  return `更新 ${hh}:${mm}`
})

const trendTotalsText = computed(() => {
  const sum = { face: 0, mbti: 0, pdp: 0, disc: 0 }
  for (const d of testTrends.value) {
    sum.face += Number(d.face) || 0
    sum.mbti += Number(d.mbti) || 0
    sum.pdp += Number(d.pdp) || 0
    sum.disc += Number(d.disc) || 0
  }
  const total = sum.face + sum.mbti + sum.pdp + sum.disc
  return total ? `${total} 人次` : ''
})

const kpiCards = computed(() => [
  { key: 'u', label: '总用户数', value: stats.totalUsers, icon: User, tone: 'blue' },
  { key: 't', label: '已完成测试', value: stats.testsCompleted, icon: Document, tone: 'green' },
  { key: 'a', label: '今日活跃', value: stats.activeToday, icon: TrendCharts, tone: 'purple' }
])

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
  const mbti = sliceItems(distributionMbti.value, 8)
  const disc = sliceItems(distributionDisc.value, 8)
  const pdp = sliceItems(distributionPdp.value, 8)
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
  pushPref('面·MBTI ', fh.mbti, 3)
  pushPref('面·DISC ', fh.disc, 3)
  pushPref('面·PDP ', fh.pdp, 3)

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

const seriesColors: Record<'face' | 'mbti' | 'pdp' | 'disc', { color: string; fill: string }> = {
  mbti: { color: '#4F46E5', fill: 'rgba(79, 70, 229, 0.16)' },
  pdp: { color: '#F59E0B', fill: 'rgba(245, 158, 11, 0.16)' },
  disc: { color: '#0EA5E9', fill: 'rgba(14, 165, 233, 0.16)' },
  face: { color: '#10B981', fill: 'rgba(16, 185, 129, 0.16)' }
}

const chartOption = computed(() => {
  const dates = testTrends.value.map(d => d.date.slice(5))
  const rows = testTrends.value
  const mode = chartMode.value

  const buildSeries = (name: string, key: 'face' | 'mbti' | 'pdp' | 'disc') => {
    const c = seriesColors[key]
    if (mode === 'stack') {
      return {
        name,
        type: 'bar' as const,
        stack: 'total',
        barMaxWidth: 22,
        itemStyle: {
          color: c.color,
          borderRadius: key === 'face' ? [4, 4, 0, 0] : 0
        },
        emphasis: { focus: 'series' as const },
        data: rows.map(d => d[key])
      }
    }
    return {
      name,
      type: 'line' as const,
      smooth: 0.25,
      showSymbol: false,
      lineStyle: { width: 2.4, color: c.color },
      itemStyle: { color: c.color },
      areaStyle: { color: c.fill },
      emphasis: { focus: 'series' as const },
      data: rows.map(d => d[key])
    }
  }

  return {
    animationDuration: 480,
    animationEasing: 'cubicOut' as const,
    color: [seriesColors.mbti.color, seriesColors.pdp.color, seriesColors.disc.color, seriesColors.face.color],
    tooltip: {
      trigger: 'axis',
      axisPointer: { type: mode === 'stack' ? 'shadow' : 'line', lineStyle: { color: '#cbd5e1' } },
      backgroundColor: '#ffffff',
      borderColor: '#e2e8f0',
      borderWidth: 1,
      padding: [8, 12],
      textStyle: { color: '#0f172a', fontSize: 12 },
      extraCssText: 'box-shadow:0 6px 16px rgba(15,23,42,0.08);border-radius:10px;',
      formatter: (params: any) => {
        const arr = Array.isArray(params) ? params : params ? [params] : []
        if (!arr.length) return ''
        const first = arr[0]
        let html = `<div style="font-weight:600;margin-bottom:6px;color:#0f172a">${first.axisValueLabel ?? first.axisValue ?? ''}</div>`
        let sum = 0
        for (const p of arr) {
          const v = Number(p.data) || 0
          sum += v
          html += `<div style="display:flex;align-items:center;gap:6px;margin:2px 0;font-size:12px;color:#475569">${p.marker || ''}<span style="flex:1">${p.seriesName}</span><b style="color:#0f172a;font-variant-numeric:tabular-nums">${v}</b></div>`
        }
        html += `<div style="margin-top:6px;padding-top:6px;border-top:1px dashed #e2e8f0;font-size:12px;color:#64748b">当日合计 <b style="color:#0f172a">${sum}</b> 人次</div>`
        return html
      }
    },
    grid: { left: 44, right: 16, top: 16, bottom: 28 },
    xAxis: {
      type: 'category',
      data: dates,
      boundaryGap: mode === 'stack',
      axisLine: { lineStyle: { color: '#e2e8f0' } },
      axisTick: { show: false },
      axisLabel: { color: '#64748b', fontSize: 11, margin: 10 }
    },
    yAxis: {
      type: 'value',
      minInterval: 1,
      axisLine: { show: false },
      axisTick: { show: false },
      splitLine: { lineStyle: { color: '#f1f5f9', type: 'dashed' } },
      axisLabel: { color: '#94a3b8', fontSize: 11 }
    },
    series: [
      buildSeries('MBTI', 'mbti'),
      buildSeries('PDP', 'pdp'),
      buildSeries('DISC', 'disc'),
      buildSeries('人脸', 'face')
    ]
  }
})

const loadData = async () => {
  loading.value = true
  try {
    const response: any = await request.get('/admin/dashboard')
    if (response.code === 200 && response.data) {
      stats.totalUsers = response.data.totalUsers || 0
      stats.testsCompleted = response.data.testsCompleted || 0
      stats.activeToday = response.data.activeToday || 0
      testTrends.value = response.data.testTrends || []
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
      lastUpdatedAt.value = Date.now()
    }
  } catch (error: any) {
    console.error('加载数据失败:', error)
    ElMessage.error(error.message || '加载数据失败')
  } finally {
    loading.value = false
  }
}

const refreshAll = async () => {
  await Promise.all([loadData(), loadInviteQrcode()])
  ElMessage.success('数据已刷新')
}

onMounted(() => {
  void loadData()
  void loadInviteQrcode()
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
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.dash-head-left { min-width: 0; }
.dash-head-right { display: flex; align-items: center; gap: 10px; }
.dash-updated {
  font-size: 11px;
  color: #94a3b8;
  font-variant-numeric: tabular-nums;
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

.wallet-strip {
  flex: 0 0 auto;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 10px;
}

.wallet-card {
  position: relative;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 14px 16px 16px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.03);
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  overflow: hidden;
  animation: dashFadeUp 0.45s ease-out both;
}

.wallet-info { min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.wallet-label { font-size: 12px; color: #64748b; font-weight: 500; }
.wallet-value {
  font-size: 22px;
  font-weight: 700;
  color: #0f172a;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
}
.wallet-foot { font-size: 11px; color: #94a3b8; }
.wallet-icon {
  width: 36px; height: 36px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}
.wallet-card--primary { background: linear-gradient(135deg, #eef2ff 0%, #ffffff 60%); }
.wallet-card--primary .wallet-icon { background: #eef2ff; color: #4f46e5; }
.wallet-card--primary .wallet-value { color: #3730a3; }
.wallet-card--consume .wallet-icon { background: #ecfdf5; color: #10b981; }
.wallet-card--frozen .wallet-icon { background: #fffbeb; color: #b45309; }
.wallet-card--suggest .wallet-icon { background: #fef2f2; color: #ef4444; }
.wallet-action {
  position: absolute;
  right: 12px;
  bottom: 12px;
  padding: 4px 12px;
  font-weight: 600;
  height: auto;
}

.recharge-dialog { display: flex; flex-direction: column; gap: 12px; }
.recharge-quick { display: flex; flex-wrap: wrap; gap: 6px; }
.recharge-action { display: flex; align-items: center; gap: 10px; }
.recharge-tip { font-size: 12px; color: #94a3b8; }
.recharge-qr { display: flex; flex-direction: column; align-items: center; gap: 8px; margin-top: 6px; }
.recharge-qr img { width: 200px; height: 200px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; padding: 8px; }
.recharge-qr-text { font-size: 13px; color: #475569; }

.dash-kpis {
  flex: 0 0 auto;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
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
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  animation: dashFadeUp 0.45s ease-out both;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease,
    border-color 0.2s ease;

  &:hover {
    transform: translateY(-1px);
    border-color: #c7d2fe;
    box-shadow: 0 6px 16px rgba(79, 70, 229, 0.08);
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
    background: #ecfdf5;
    color: #10b981;
  }

  &.blue {
    background: #eef2ff;
    color: #4f46e5;
  }

  &.indigo {
    background: #e0f2fe;
    color: #0ea5e9;
  }

  &.amber {
    background: #fffbeb;
    color: #f59e0b;
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
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  animation: dashFadeUp 0.45s ease-out both;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease,
    border-color 0.2s ease;

  &:hover {
    transform: translateY(-1px);
    border-color: #c7d2fe;
    box-shadow: 0 6px 16px rgba(79, 70, 229, 0.08);
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
      background: #eef2ff;
      color: #4f46e5;
    }
    &.green {
      background: #ecfdf5;
      color: #10b981;
    }
    &.purple {
      background: #f5f3ff;
      color: #7c3aed;
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
  grid-template-columns: 1fr minmax(280px, 32%);
  gap: 12px;
  animation: dashFadeUp 0.5s ease-out 0.08s both;
}

.panel {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  padding: 14px 16px;
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
    background: #4f46e5;
  }

  &.bar-disc {
    background: #0ea5e9;
  }

  &.bar-pdp {
    background: #f59e0b;
  }

  &.bar-face {
    background: #10b981;
  }
}

.chart-legend {
  display: inline-flex;
  background: #f1f5f9;
  padding: 3px;
  border-radius: 8px;
  gap: 2px;
}

.chart-mode-btn {
  border: 0;
  background: transparent;
  color: #64748b;
  font-size: 12px;
  padding: 4px 12px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.18s;
  font-weight: 500;

  &:hover {
    color: #0f172a;
  }

  &.is-active {
    background: #ffffff;
    color: #0f172a;
    font-weight: 600;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
  }
}

.chart-sub-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 14px;
  margin-bottom: 8px;
  font-size: 11.5px;
  color: #64748b;
}

.chart-meta-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.chart-meta-dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 3px;

  &.bar-mbti { background: #4f46e5; }
  &.bar-pdp { background: #f59e0b; }
  &.bar-disc { background: #0ea5e9; }
  &.bar-face { background: #10b981; }
}

.chart-meta-sum {
  margin-left: auto;
  color: #475569;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
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

.panel-section-head {
  flex: 0 0 auto;
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 10px;
  padding-bottom: 6px;
  border-bottom: 1px solid #f3f4f6;

  &--chart {
    margin-top: 14px;
  }
}

.panel-meta {
  font-size: 11px;
  color: #9ca3af;
  font-weight: 500;
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
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: #111827;
}

.chart-box {
  flex: 1;
  min-height: 0;
  position: relative;
}

.trend-chart {
  width: 100%;
  height: 100%;
  min-height: 220px;
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

@media (max-width: 1100px) {
  .wallet-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 640px) {
  .dash-kpis {
    grid-template-columns: repeat(2, 1fr);
  }

  .wallet-strip { grid-template-columns: 1fr; }

  .dash-catalog {
    grid-template-columns: repeat(2, 1fr);
  }

  .distr-band {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
