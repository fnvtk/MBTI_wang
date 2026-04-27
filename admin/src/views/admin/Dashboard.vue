<template>
  <div class="dashboard-viewport" v-loading="loading">
    <!-- 顶部标题栏 -->
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

    <!-- KPI 统计卡 -->
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

    <!-- 测评目录卡 -->
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

    <!-- 主内容区 -->
    <div class="dash-main">
      <!-- 左：分布 + 趋势图 -->
      <section class="panel panel-chart">

        <!-- 测评结果分布 -->
        <div class="panel-section-head">
          <h2 class="panel-title">测评结果分布</h2>
          <span class="panel-meta">各类型高频结果（TOP 8）</span>
        </div>

        <div v-if="hasDistribution" class="distr-band">
          <div v-for="block in distributionBlocks" :key="block.key" class="distr-col">
            <div class="distr-col-head">
              <span class="distr-head-dot" :class="'dot-' + block.key"></span>
              <span class="distr-col-title">{{ block.title }}</span>
            </div>
            <div class="distr-list">
              <div v-for="it in block.items" :key="block.key + it.label" class="distr-row-line">
                <span class="distr-lab" :title="it.label">{{ it.label }}</span>
                <div class="distr-bar-track">
                  <div
                    class="distr-bar-fill"
                    :class="'bar-' + block.key"
                    :style="{ width: barWidthPct(block.max, it.count) }"
                  />
                </div>
                <span class="distr-pct">{{ barWidthPct(block.max, it.count) }}</span>
                <span class="distr-num">{{ it.count }}</span>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="panel-empty tight">暂无分布数据</div>

        <!-- 团队匹配洞察卡 -->
        <div class="team-match-section" v-if="hasDistribution && topMbtiType">
          <div class="team-match-head">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
              <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>团队匹配洞察 · 基于最高频类型</span>
          </div>
          <div class="team-match-cards">
            <div v-for="hint in teamMatchHints" :key="hint.type" class="match-card">
              <div class="match-card-header">
                <span class="match-type-badge" :class="'badge-' + hint.tone">{{ hint.type }}</span>
                <span class="match-card-name">{{ hint.name }}</span>
              </div>
              <div class="match-card-body">
                <div class="match-row">
                  <span class="match-row-label">最佳搭档</span>
                  <div class="match-tags">
                    <span v-for="m in hint.bestMatch" :key="m" class="match-tag tag-green">{{ m }}</span>
                  </div>
                </div>
                <div class="match-row">
                  <span class="match-row-label">核心能力</span>
                  <div class="match-tags">
                    <span v-for="s in hint.strengths" :key="s" class="match-tag tag-blue">{{ s }}</span>
                  </div>
                </div>
                <div class="match-row">
                  <span class="match-row-label">团队角色</span>
                  <span class="match-role">{{ hint.teamRole }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 趋势图 -->
        <div class="panel-section-head panel-section-head--chart">
          <h2 class="panel-title">近 14 日 · 测评完成趋势</h2>
          <div class="chart-legend">
            <button
              v-for="key in chartModeOptions"
              :key="key.value"
              class="chart-mode-btn"
              :class="{ 'is-active': chartMode === key.value }"
              @click="chartMode = key.value"
            >{{ key.label }}</button>
          </div>
        </div>
        <div class="chart-sub-meta">
          <span class="chart-meta-item"><em class="chart-meta-dot bar-mbti"></em>MBTI</span>
          <span class="chart-meta-item"><em class="chart-meta-dot bar-pdp"></em>PDP</span>
          <span class="chart-meta-item"><em class="chart-meta-dot bar-disc"></em>DISC</span>
          <span class="chart-meta-item"><em class="chart-meta-dot bar-face"></em>人脸</span>
          <span class="chart-meta-sum" v-if="trendTotalsText">累计 {{ trendTotalsText }}</span>
        </div>
        <div class="chart-box">
          <VChart v-if="testTrends.length" class="trend-chart" :option="chartOption" autoresize />
          <div v-else class="panel-empty">暂无趋势数据</div>
        </div>
      </section>

      <!-- 右：邀请码 -->
      <aside class="panel panel-side">
        <div class="side-block side-invite">
          <div class="panel-head row">
            <div>
              <h2 class="panel-title">邀请小程序码</h2>
              <p class="panel-sub">扫码邀请用户参与测评</p>
            </div>
            <el-button size="small" type="primary" @click="loadInviteQrcode" :loading="inviteLoading">
              {{ inviteQrcodeEnterprise || inviteQrcodePersonal ? '刷新' : '生成' }}
            </el-button>
          </div>
          <div class="invite-body">
            <template v-if="inviteQrcodeEnterprise || inviteQrcodePersonal">
              <div v-if="inviteQrcodeEnterprise" class="invite-card">
                <img :src="inviteQrcodeEnterprise" alt="企业版太阳码" class="invite-img" />
                <span class="invite-label">企业版</span>
              </div>
              <div v-if="inviteQrcodePersonal" class="invite-card">
                <img :src="inviteQrcodePersonal" alt="个人版太阳码" class="invite-img" />
                <span class="invite-label">个人版</span>
              </div>
            </template>
            <div v-else class="invite-empty">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" style="color:#CBD5E1">
                <rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.5"/>
                <rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.5"/>
                <rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.5"/>
                <rect x="14" y="14" width="3" height="3" rx=".5" fill="currentColor"/>
                <rect x="18" y="14" width="3" height="3" rx=".5" fill="currentColor"/>
                <rect x="14" y="18" width="3" height="3" rx=".5" fill="currentColor"/>
                <rect x="18" y="18" width="3" height="3" rx=".5" fill="currentColor"/>
              </svg>
              <span class="invite-placeholder">{{ inviteLoadError || '点击「生成」获取邀请码' }}</span>
            </div>
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

const stats = reactive({ totalUsers: 0, testsCompleted: 0, activeToday: 0 })

const testTrends = ref<Array<{ date: string; face: number; mbti: number; pdp: number; disc: number; total: number }>>([])
const testCatalog = ref<Array<{ key: string; label: string; records: number; uniqueUsers: number }>>([])
const distributionMbti = ref<Array<{ label: string; count: number }>>([])
const distributionDisc = ref<Array<{ label: string; count: number }>>([])
const distributionPdp = ref<Array<{ label: string; count: number }>>([])
const faceSubtypeHints = ref<{ mbti: Array<{ label: string; count: number }>; disc: Array<{ label: string; count: number }>; pdp: Array<{ label: string; count: number }> }>({ mbti: [], disc: [], pdp: [] })

const loading = ref(false)
const inviteLoading = ref(false)
const inviteQrcodeEnterprise = ref('')
const inviteQrcodePersonal = ref('')
const inviteLoadError = ref('')
const lastUpdatedAt = ref(0)

const chartModeOptions = [
  { label: '堆叠', value: 'stack' as const },
  { label: '折线', value: 'line' as const }
]
const chartMode = ref<'stack' | 'line'>('stack')

const lastUpdatedText = computed(() => {
  if (!lastUpdatedAt.value) return ''
  const d = new Date(lastUpdatedAt.value)
  return `更新 ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`
})

const trendTotalsText = computed(() => {
  const sum = { face: 0, mbti: 0, pdp: 0, disc: 0 }
  for (const d of testTrends.value) {
    sum.face += Number(d.face) || 0; sum.mbti += Number(d.mbti) || 0
    sum.pdp += Number(d.pdp) || 0; sum.disc += Number(d.disc) || 0
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
  face: { icon: Camera, tone: 'teal' }, mbti: { icon: Reading, tone: 'blue' },
  disc: { icon: Histogram, tone: 'indigo' }, pdp: { icon: Medal, tone: 'amber' }
}

const catalogRows = computed(() =>
  testCatalog.value.map(row => {
    const m = catalogIconMap[row.key] || { icon: Document, tone: 'blue' }
    return { ...row, icon: m.icon, tone: m.tone, records: row.records ?? 0, uniqueUsers: row.uniqueUsers ?? 0 }
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
    for (const it of arr || []) { if (k >= max) break; faceMerged.push({ label: `${prefix}${it.label}`, count: it.count }); k++ }
  }
  pushPref('面·MBTI ', fh.mbti, 3); pushPref('面·DISC ', fh.disc, 3); pushPref('面·PDP ', fh.pdp, 3)
  const blocks = [
    { key: 'mbti', title: 'MBTI（答题）', items: mbti, icon: Reading },
    { key: 'disc', title: 'DISC（答题）', items: disc, icon: Histogram },
    { key: 'pdp', title: 'PDP（答题）', items: pdp, icon: Medal },
    { key: 'face', title: '面相推测', items: faceMerged, icon: Camera }
  ]
  return blocks.map(b => ({ ...b, max: Math.max(1, ...b.items.map(i => i.count)) }))
})

const hasDistribution = computed(() => distributionBlocks.value.some(b => b.items.length > 0))

// 获取最高频 MBTI 类型（用于团队匹配洞察）
const topMbtiType = computed(() => distributionMbti.value[0]?.label || '')

// MBTI 团队匹配数据库
const mbtiMatchDb: Record<string, { name: string; bestMatch: string[]; strengths: string[]; teamRole: string; tone: string }> = {
  INTJ: { name: '建筑师', bestMatch: ['ENFP', 'ENTP'], strengths: ['战略思维', '系统规划', '独立执行'], teamRole: '战略制定者', tone: 'indigo' },
  INTP: { name: '逻辑学家', bestMatch: ['ENFJ', 'ENTJ'], strengths: ['分析能力', '创新思维', '逻辑推理'], teamRole: '问题解决者', tone: 'blue' },
  ENTJ: { name: '指挥官', bestMatch: ['INFP', 'INTP'], strengths: ['领导力', '决策力', '组织能力'], teamRole: '团队领导者', tone: 'red' },
  ENTP: { name: '辩论家', bestMatch: ['INFJ', 'INTJ'], strengths: ['创新能力', '辩证思维', '适应力'], teamRole: '创新推动者', tone: 'orange' },
  INFJ: { name: '提倡者', bestMatch: ['ENFP', 'ENTP'], strengths: ['洞察力', '同理心', '远见'], teamRole: '价值传递者', tone: 'purple' },
  INFP: { name: '调停者', bestMatch: ['ENFJ', 'ENTJ'], strengths: ['创造力', '价值观坚守', '共情能力'], teamRole: '文化塑造者', tone: 'pink' },
  ENFJ: { name: '主人公', bestMatch: ['INFP', 'ISFP'], strengths: ['激励他人', '社交能力', '领导感召力'], teamRole: '人才培育者', tone: 'teal' },
  ENFP: { name: '竞选者', bestMatch: ['INTJ', 'INFJ'], strengths: ['热情感染力', '创意思维', '人际关系'], teamRole: '关系连接者', tone: 'green' },
  ISTJ: { name: '物流师', bestMatch: ['ESTP', 'ESFP'], strengths: ['责任心', '执行力', '细节把控'], teamRole: '流程守护者', tone: 'slate' },
  ISFJ: { name: '守卫者', bestMatch: ['ESFP', 'ESTP'], strengths: ['忠诚度', '细心', '实际支持'], teamRole: '团队稳定器', tone: 'emerald' },
  ESTJ: { name: '总经理', bestMatch: ['ISFP', 'ISTP'], strengths: ['管理能力', '规则执行', '高效决策'], teamRole: '执行管理者', tone: 'amber' },
  ESFJ: { name: '执政官', bestMatch: ['ISFP', 'ISTP'], strengths: ['协调能力', '关怀他人', '团队凝聚'], teamRole: '团队协调者', tone: 'cyan' },
  ISTP: { name: '鉴赏家', bestMatch: ['ESTJ', 'ESFJ'], strengths: ['实践能力', '冷静分析', '技术专注'], teamRole: '技术执行者', tone: 'gray' },
  ISFP: { name: '探险家', bestMatch: ['ESTJ', 'ESFJ'], strengths: ['灵活适应', '美感设计', '实际行动'], teamRole: '创意实践者', tone: 'violet' },
  ESTP: { name: '企业家', bestMatch: ['ISFJ', 'ISTJ'], strengths: ['行动力', '危机处理', '谈判能力'], teamRole: '危机应对者', tone: 'rose' },
  ESFP: { name: '表演者', bestMatch: ['ISFJ', 'ISTJ'], strengths: ['感染力', '协作精神', '现场发挥'], teamRole: '氛围激活者', tone: 'yellow' },
}

const teamMatchHints = computed(() => {
  const top3 = distributionMbti.value.slice(0, 3).map(it => it.label)
  return top3.map(type => {
    const db = mbtiMatchDb[type] || {
      name: type, bestMatch: ['ENFP', 'ENTJ'], strengths: ['综合能力', '团队合作'],
      teamRole: '团队成员', tone: 'blue'
    }
    return { type, ...db }
  })
})

function barWidthPct(max: number, count: number) {
  if (!max || !count) return '0%'
  return `${Math.round((count / max) * 100)}%`
}

const seriesColors: Record<'face' | 'mbti' | 'pdp' | 'disc', { color: string; fill: string }> = {
  mbti: { color: '#4F46E5', fill: 'rgba(79,70,229,0.12)' },
  pdp: { color: '#F59E0B', fill: 'rgba(245,158,11,0.12)' },
  disc: { color: '#0EA5E9', fill: 'rgba(14,165,233,0.12)' },
  face: { color: '#10B981', fill: 'rgba(16,185,129,0.12)' }
}

const chartOption = computed(() => {
  const dates = testTrends.value.map(d => d.date.slice(5))
  const rows = testTrends.value
  const mode = chartMode.value

  const buildSeries = (name: string, key: 'face' | 'mbti' | 'pdp' | 'disc') => {
    const c = seriesColors[key]
    if (mode === 'stack') {
      return { name, type: 'bar' as const, stack: 'total', barMaxWidth: 22, itemStyle: { color: c.color, borderRadius: key === 'face' ? [4,4,0,0] : 0 }, emphasis: { focus: 'series' as const }, data: rows.map(d => d[key]) }
    }
    return { name, type: 'line' as const, smooth: 0.25, showSymbol: false, lineStyle: { width: 2.4, color: c.color }, itemStyle: { color: c.color }, areaStyle: { color: c.fill }, emphasis: { focus: 'series' as const }, data: rows.map(d => d[key]) }
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
        let html = `<div style="font-weight:600;margin-bottom:6px;color:#0f172a">${arr[0].axisValueLabel ?? arr[0].axisValue ?? ''}</div>`
        let sum = 0
        for (const p of arr) { const v = Number(p.data) || 0; sum += v; html += `<div style="display:flex;align-items:center;gap:6px;margin:2px 0;font-size:12px;color:#475569">${p.marker || ''}<span style="flex:1">${p.seriesName}</span><b style="color:#0f172a">${v}</b></div>` }
        html += `<div style="margin-top:6px;padding-top:6px;border-top:1px dashed #e2e8f0;font-size:12px;color:#64748b">当日合计 <b style="color:#0f172a">${sum}</b> 人次</div>`
        return html
      }
    },
    grid: { left: 44, right: 16, top: 16, bottom: 28 },
    xAxis: { type: 'category', data: dates, boundaryGap: mode === 'stack', axisLine: { lineStyle: { color: '#e2e8f0' } }, axisTick: { show: false }, axisLabel: { color: '#64748b', fontSize: 11, margin: 10 } },
    yAxis: { type: 'value', minInterval: 1, axisLine: { show: false }, axisTick: { show: false }, splitLine: { lineStyle: { color: '#f1f5f9', type: 'dashed' } }, axisLabel: { color: '#94a3b8', fontSize: 11 } },
    series: [buildSeries('MBTI', 'mbti'), buildSeries('PDP', 'pdp'), buildSeries('DISC', 'disc'), buildSeries('人脸', 'face')]
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
      distributionMbti.value = Array.isArray(response.data.distributionMbti) ? response.data.distributionMbti : []
      distributionDisc.value = Array.isArray(response.data.distributionDisc) ? response.data.distributionDisc : []
      distributionPdp.value = Array.isArray(response.data.distributionPdp) ? response.data.distributionPdp : []
      const fh = response.data.faceSubtypeHints
      faceSubtypeHints.value = fh && typeof fh === 'object'
        ? { mbti: Array.isArray(fh.mbti) ? fh.mbti : [], disc: Array.isArray(fh.disc) ? fh.disc : [], pdp: Array.isArray(fh.pdp) ? fh.pdp : [] }
        : { mbti: [], disc: [], pdp: [] }
      lastUpdatedAt.value = Date.now()
    }
  } catch (error: any) {
    ElMessage.error(error.message || '加载数据失败')
  } finally {
    loading.value = false
  }
}

const refreshAll = async () => {
  await Promise.all([loadData(), loadInviteQrcode()])
  ElMessage.success('数据已刷新')
}

onMounted(() => { void loadData(); void loadInviteQrcode() })

const loadInviteQrcode = async () => {
  if (inviteLoading.value) return
  inviteLoading.value = true
  inviteLoadError.value = ''
  try {
    const res: any = await request.get('/admin/invite/qrcode')
    const d = res?.data
    const ent = d?.enterprise?.qrcode ?? d?.qrcode
    const per = d?.personal?.qrcode
    inviteQrcodeEnterprise.value = typeof ent === 'string' && ent ? ent : ''
    inviteQrcodePersonal.value = typeof per === 'string' && per ? per : ''
    if (!inviteQrcodeEnterprise.value && !inviteQrcodePersonal.value) {
      const msg = res?.message || res?.msg || '生成失败，请确认企业绑定'
      inviteLoadError.value = msg
    }
  } catch (error: any) {
    inviteLoadError.value = error?.message || '生成失败'
  } finally {
    inviteLoading.value = false
  }
}
</script>

<style scoped lang="scss">
@keyframes dashFadeUp {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

.dashboard-viewport {
  min-height: calc(100vh - 56px);
  padding: 20px 20px 24px;
  background: #F4F6FB;
  box-sizing: border-box;
}

/* ── 头部 ── */
.dash-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 16px;
  animation: dashFadeUp 0.4s ease-out both;
}
.dash-title { margin: 0 0 4px; font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -0.02em; }
.dash-tagline { margin: 0; font-size: 12.5px; color: #6B7280; }
.dash-head-right { display: flex; align-items: center; gap: 10px; }
.dash-updated { font-size: 11px; color: #9CA3AF; font-variant-numeric: tabular-nums; }

/* ── KPI 卡 ── */
.dash-kpis {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 12px;
}
.stat-card {
  background: #fff;
  border-radius: 14px;
  padding: 16px 18px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border: 1px solid #E5E7EB;
  box-shadow: 0 1px 3px rgba(16,24,40,0.04), 0 4px 12px rgba(16,24,40,0.03);
  animation: dashFadeUp 0.45s ease-out both;
  transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;

  &:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(79,70,229,0.1); border-color: #C7D2FE; }
  .stat-label { font-size: 11.5px; color: #6B7280; margin-bottom: 5px; font-weight: 500; }
  .stat-value { font-size: 28px; font-weight: 800; color: #111827; font-variant-numeric: tabular-nums; letter-spacing: -0.02em; line-height: 1; }
  .stat-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center; font-size: 22px;
    &.blue { background: #EEF2FF; color: #4F46E5; }
    &.green { background: #ECFDF5; color: #10B981; }
    &.purple { background: #F5F3FF; color: #7C3AED; }
  }
}

/* ── 目录卡 ── */
.dash-catalog {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 14px;
}
.catalog-card {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 14px;
  background: #fff;
  border-radius: 12px;
  border: 1px solid #E5E7EB;
  box-shadow: 0 1px 3px rgba(16,24,40,0.04);
  animation: dashFadeUp 0.45s ease-out both;
  transition: transform 0.2s, border-color 0.2s;
  &:hover { transform: translateY(-1px); border-color: #C7D2FE; }
}
.catalog-icon {
  width: 38px; height: 38px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;
  &.teal { background: #ECFDF5; color: #10B981; }
  &.blue { background: #EEF2FF; color: #4F46E5; }
  &.indigo { background: #E0F2FE; color: #0EA5E9; }
  &.amber { background: #FFFBEB; color: #F59E0B; }
}
.catalog-label { font-size: 12.5px; font-weight: 600; color: #111827; margin-bottom: 3px; }
.catalog-metrics { font-size: 11px; color: #6B7280; em { font-style: normal; font-weight: 700; color: #374151; } .sep { margin: 0 4px; color: #D1D5DB; } }

/* ── 主区域 ── */
.dash-main {
  display: grid;
  grid-template-columns: 1fr minmax(260px, 30%);
  gap: 14px;
  animation: dashFadeUp 0.5s ease-out 0.08s both;
}

.panel {
  background: #fff;
  border-radius: 16px;
  border: 1px solid #E5E7EB;
  box-shadow: 0 1px 3px rgba(16,24,40,0.04), 0 4px 12px rgba(16,24,40,0.03);
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
}

.panel-section-head {
  display: flex; align-items: baseline;
  justify-content: space-between; gap: 10px;
  flex-wrap: wrap; margin-bottom: 12px;
  padding-bottom: 10px; border-bottom: 1px solid #F3F4F6;
  &--chart { margin-top: 20px; }
}
.panel-title { margin: 0; font-size: 15px; font-weight: 700; color: #111827; }
.panel-sub { margin: 4px 0 0; font-size: 11.5px; color: #9CA3AF; }
.panel-meta { font-size: 11px; color: #9CA3AF; font-weight: 500; }

/* ── 分布区块 ── */
.distr-band {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 16px;
}
.distr-col {
  background: #FAFBFF;
  border-radius: 10px;
  padding: 12px;
  border: 1px solid #EAECF0;
}
.distr-col-head {
  display: flex; align-items: center; gap: 7px;
  margin-bottom: 10px;
}
.distr-head-dot {
  width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0;
  &.dot-mbti { background: #4F46E5; }
  &.dot-disc { background: #0EA5E9; }
  &.dot-pdp { background: #F59E0B; }
  &.dot-face { background: #10B981; }
}
.distr-col-title { font-size: 11.5px; font-weight: 700; color: #374151; }
.distr-list { display: flex; flex-direction: column; gap: 6px; }
.distr-row-line {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(0, 2fr) 34px 26px;
  align-items: center;
  gap: 5px;
}
.distr-lab { font-size: 11px; color: #4B5563; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500; }
.distr-bar-track { height: 6px; background: #E5E7EB; border-radius: 3px; overflow: hidden; }
.distr-bar-fill {
  height: 100%; border-radius: 3px; transition: width 0.4s ease;
  &.bar-mbti { background: linear-gradient(90deg, #4F46E5, #818CF8); }
  &.bar-disc { background: linear-gradient(90deg, #0EA5E9, #38BDF8); }
  &.bar-pdp { background: linear-gradient(90deg, #F59E0B, #FCD34D); }
  &.bar-face { background: linear-gradient(90deg, #10B981, #34D399); }
}
.distr-pct { font-size: 10px; color: #9CA3AF; text-align: right; font-variant-numeric: tabular-nums; }
.distr-num { font-size: 11px; font-weight: 700; color: #374151; text-align: right; font-variant-numeric: tabular-nums; }

/* ── 团队匹配洞察 ── */
.team-match-section {
  background: linear-gradient(135deg, #F8FAFF 0%, #EEF2FF 100%);
  border: 1px solid #C7D2FE;
  border-radius: 12px;
  padding: 14px 16px;
  margin-bottom: 16px;
}
.team-match-head {
  display: flex; align-items: center; gap: 8px;
  font-size: 12px; font-weight: 700; color: #4F46E5;
  margin-bottom: 12px;
}
.team-match-cards {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}
.match-card {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #E0E7FF;
  padding: 12px;
  box-shadow: 0 1px 3px rgba(79,70,229,0.06);
}
.match-card-header {
  display: flex; align-items: center; gap: 8px; margin-bottom: 10px;
}
.match-type-badge {
  font-size: 13px; font-weight: 800; padding: 3px 10px; border-radius: 6px;
  &.badge-indigo { background: #EEF2FF; color: #4F46E5; }
  &.badge-blue { background: #EFF6FF; color: #2563EB; }
  &.badge-red { background: #FEF2F2; color: #DC2626; }
  &.badge-orange { background: #FFF7ED; color: #EA580C; }
  &.badge-purple { background: #FAF5FF; color: #9333EA; }
  &.badge-pink { background: #FDF2F8; color: #DB2777; }
  &.badge-teal { background: #F0FDFA; color: #0D9488; }
  &.badge-green { background: #F0FDF4; color: #16A34A; }
  &.badge-slate { background: #F8FAFC; color: #475569; }
  &.badge-emerald { background: #ECFDF5; color: #059669; }
  &.badge-amber { background: #FFFBEB; color: #D97706; }
  &.badge-cyan { background: #ECFEFF; color: #0891B2; }
  &.badge-gray { background: #F9FAFB; color: #6B7280; }
  &.badge-violet { background: #F5F3FF; color: #7C3AED; }
  &.badge-rose { background: #FFF1F2; color: #E11D48; }
  &.badge-yellow { background: #FEFCE8; color: #CA8A04; }
}
.match-card-name { font-size: 12px; font-weight: 600; color: #374151; }
.match-card-body { display: flex; flex-direction: column; gap: 6px; }
.match-row { display: flex; align-items: flex-start; gap: 8px; }
.match-row-label { font-size: 10.5px; color: #9CA3AF; font-weight: 600; white-space: nowrap; padding-top: 1px; min-width: 48px; }
.match-tags { display: flex; flex-wrap: wrap; gap: 4px; }
.match-tag {
  font-size: 10.5px; font-weight: 700; padding: 2px 7px; border-radius: 5px;
  &.tag-green { background: #ECFDF5; color: #059669; }
  &.tag-blue { background: #EFF6FF; color: #2563EB; }
}
.match-role { font-size: 11px; color: #4B5563; font-weight: 500; }

/* ── 趋势图 ── */
.chart-legend {
  display: inline-flex; background: #F1F5F9; padding: 3px; border-radius: 8px; gap: 2px;
}
.chart-mode-btn {
  border: 0; background: transparent; color: #64748B; font-size: 12px; padding: 4px 12px; border-radius: 6px; cursor: pointer; transition: all 0.18s; font-weight: 500;
  &:hover { color: #0F172A; }
  &.is-active { background: #fff; color: #0F172A; font-weight: 600; box-shadow: 0 1px 2px rgba(15,23,42,0.06); }
}
.chart-sub-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; margin-bottom: 10px; font-size: 12px; color: #64748B; }
.chart-meta-item { display: inline-flex; align-items: center; gap: 6px; }
.chart-meta-dot {
  display: inline-block; width: 10px; height: 10px; border-radius: 3px;
  &.bar-mbti { background: #4F46E5; }
  &.bar-pdp { background: #F59E0B; }
  &.bar-disc { background: #0EA5E9; }
  &.bar-face { background: #10B981; }
}
.chart-meta-sum { margin-left: auto; color: #475569; font-variant-numeric: tabular-nums; font-weight: 600; }
.chart-box { flex: 1; min-height: 200px; position: relative; }
.trend-chart { width: 100%; height: 100%; min-height: 200px; }
.panel-empty { display: flex; align-items: center; justify-content: center; height: 100px; color: #9CA3AF; font-size: 13px; &.tight { height: 60px; } }

/* ── 侧栏邀请码 ── */
.panel-side { gap: 0; padding: 18px 18px; }
.panel-head { flex: 0 0 auto; margin-bottom: 16px; &.row { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; } }
.invite-body { display: flex; flex-wrap: wrap; justify-content: center; gap: 16px; }
.invite-card { display: flex; flex-direction: column; align-items: center; gap: 8px; }
.invite-img { width: 120px; height: 120px; border-radius: 12px; border: 1px solid #E5E7EB; object-fit: contain; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.invite-label { font-size: 12px; font-weight: 600; color: #374151; background: #F3F4F6; padding: 3px 12px; border-radius: 20px; }
.invite-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; min-height: 160px; width: 100%; }
.invite-placeholder { font-size: 12.5px; color: #9CA3AF; text-align: center; }

/* ── 响应式 ── */
@media (max-width: 1200px) {
  .team-match-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 1100px) {
  .dash-main { grid-template-columns: 1fr; }
}
@media (max-width: 900px) {
  .distr-band { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .team-match-cards { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .dashboard-viewport { padding: 14px 14px 20px; }
  .dash-kpis { grid-template-columns: repeat(2, 1fr); }
  .dash-catalog { grid-template-columns: repeat(2, 1fr); }
  .distr-band { grid-template-columns: 1fr; }
  .stat-card .stat-value { font-size: 22px; }
}
</style>
