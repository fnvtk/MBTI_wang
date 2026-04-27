<template>
  <div class="dashboard-viewport" v-loading="loading">

    <!-- 顶部标题栏 -->
    <header class="dash-head">
      <div class="dash-head-left">
        <h1 class="dash-title">企业概览</h1>
        <p class="dash-tagline">用户规模 · 测评完成 · 收益与算力消耗 · 近 14 日趋势</p>
      </div>
      <div class="dash-head-right">
        <span v-if="lastUpdatedText" class="dash-updated">{{ lastUpdatedText }}</span>
        <el-button size="small" :icon="Refresh" @click="refreshAll" :loading="loading">刷新</el-button>
      </div>
    </header>

    <!-- KPI 卡：6 张，含收益和 AI 算力 -->
    <div class="dash-kpis">
      <div v-for="(card, i) in kpiCards" :key="card.key"
        class="stat-card" :class="'stat-card--' + card.tone"
        :style="{ animationDelay: `${i * 50}ms` }">
        <div class="stat-card-inner">
          <div class="stat-top">
            <div :class="['stat-icon-wrap', 'tone-' + card.tone]">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" v-html="card.svg"></svg>
            </div>
            <span class="stat-label">{{ card.label }}</span>
          </div>
          <div class="stat-value">{{ card.displayValue }}</div>
          <div class="stat-sub" v-if="card.sub">{{ card.sub }}</div>
        </div>
        <div class="stat-card-glow"></div>
      </div>
    </div>

    <!-- 主内容区 -->
    <div class="dash-main">

      <!-- 左侧：分布 + 团队匹配 + 趋势 -->
      <section class="panel panel-chart">

        <!-- ① 测评结果分布：重构为环形卡片 -->
        <div class="panel-section-head">
          <h2 class="panel-title">
            <span class="ptitle-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                <path d="M21.21 15.89A10 10 0 118 2.83M22 12A10 10 0 0012 2v10z"
                  stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
            测评结果分布
          </h2>
          <span class="panel-meta">各类型最高频结果分析</span>
        </div>

        <div v-if="hasDistribution" class="distr-grid">
          <div v-for="block in distributionBlocks" :key="block.key" class="distr-card">
            <!-- 卡片顶部：类型标识 + 环形 -->
            <div class="distr-card-header" :style="{ '--dcolor': block.color }">
              <div class="distr-card-meta">
                <div class="distr-badge" :style="{ background: block.color + '18', color: block.color }">{{ block.title }}</div>
                <div class="distr-total">{{ block.totalCount.toLocaleString() }} <span>总数</span></div>
              </div>
              <div class="distr-ring-wrap">
                <svg class="distr-ring-svg" viewBox="0 0 80 80">
                  <circle cx="40" cy="40" r="32" fill="none" stroke="#F0F2F8" stroke-width="9"/>
                  <circle
                    cx="40" cy="40" r="32" fill="none"
                    :stroke="block.color" stroke-width="9"
                    stroke-linecap="round"
                    stroke-dasharray="201.1"
                    :stroke-dashoffset="ringOffsetLg(block)"
                    transform="rotate(-90 40 40)"
                    class="distr-ring-circle"
                  />
                </svg>
                <div class="distr-ring-label">
                  <span class="distr-ring-pct">{{ block.topPct }}</span>
                  <span class="distr-ring-sub">最高频</span>
                </div>
              </div>
            </div>
            <!-- 最高频类型 -->
            <div class="distr-winner" v-if="block.topItem">
              <div class="distr-winner-name">{{ block.topItem.label }}</div>
              <div class="distr-winner-count">{{ block.topItem.count }} 人</div>
            </div>
            <!-- 排行列表 -->
            <div class="distr-mini-list">
              <div v-for="(it, idx) in block.items.slice(0, 5)" :key="it.label" class="distr-mini-row">
                <span class="distr-mini-rank" :class="{ 'rank-gold': idx === 0, 'rank-silver': idx === 1, 'rank-bronze': idx === 2 }">{{ idx + 1 }}</span>
                <span class="distr-mini-label">{{ it.label }}</span>
                <div class="distr-mini-bar-wrap">
                  <div class="distr-mini-bar" :style="{ width: barWidthPct(block.max, it.count), background: block.color }"></div>
                </div>
                <span class="distr-mini-num">{{ it.count }}</span>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="panel-empty tight">暂无分布数据 · 有用户完成测评后自动呈现</div>

        <!-- ② 团队匹配洞察：升级版 -->
        <div class="team-section" v-if="teamMatchHints.length">
          <div class="team-section-head">
            <div class="team-head-left">
              <div class="team-head-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                  <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"
                    stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
              <div>
                <div class="team-head-title">团队匹配洞察</div>
                <div class="team-head-sub">基于企业最高频 MBTI 类型，智能推荐最佳组合搭配</div>
              </div>
            </div>
            <div class="team-head-badge">TOP {{ teamMatchHints.length }} 类型</div>
          </div>

          <div class="team-cards">
            <div v-for="(hint, idx) in teamMatchHints" :key="hint.type" class="team-card">
              <!-- 渐变头部 -->
              <div class="team-card-top" :style="{ background: hint.gradient }">
                <div class="team-card-top-left">
                  <div class="team-card-rank">#{{ idx + 1 }} 最高频</div>
                  <div class="team-card-type">{{ hint.type }}</div>
                  <div class="team-card-name">{{ hint.name }}</div>
                </div>
                <div class="team-card-top-right">
                  <div class="team-card-role-pill">{{ hint.teamRole }}</div>
                  <!-- 占比圆环 mini -->
                  <div class="team-mini-ring">
                    <svg viewBox="0 0 44 44" width="44" height="44">
                      <circle cx="22" cy="22" r="18" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="5"/>
                      <circle cx="22" cy="22" r="18" fill="none" stroke="rgba(255,255,255,0.85)" stroke-width="5"
                        stroke-linecap="round" stroke-dasharray="113.1"
                        :stroke-dashoffset="teamRingOffset(idx)"
                        transform="rotate(-90 22 22)" style="transition:stroke-dashoffset 0.6s ease"/>
                    </svg>
                    <div class="team-mini-ring-pct">{{ teamRingPct(idx) }}</div>
                  </div>
                </div>
              </div>
              <!-- 卡片体 -->
              <div class="team-card-body">
                <!-- 最佳搭档 -->
                <div class="team-info-section">
                  <div class="team-info-label">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    最佳搭档
                  </div>
                  <div class="team-tag-row">
                    <span v-for="m in hint.bestMatch" :key="m" class="team-tag team-tag--match">{{ m }}</span>
                  </div>
                </div>
                <!-- 核心能力 -->
                <div class="team-info-section">
                  <div class="team-info-label">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    核心优势
                  </div>
                  <div class="team-tag-row">
                    <span v-for="s in hint.strengths" :key="s" class="team-tag team-tag--skill">{{ s }}</span>
                  </div>
                </div>
                <!-- 互补洞察 -->
                <div class="team-complement">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  <span>{{ hint.complementNote }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- 团队配置建议 -->
          <div class="team-suggest-bar" v-if="teamSuggest">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
              <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"
                stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>{{ teamSuggest }}</span>
          </div>
        </div>

        <!-- ③ 近 14 日趋势 -->
        <div class="panel-section-head panel-section-head--chart">
          <h2 class="panel-title">
            <span class="ptitle-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"
                  stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
            近 14 日 · 测评完成趋势
          </h2>
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
          <span class="chart-meta-sum" v-if="trendTotalsText">近 14 日累计 {{ trendTotalsText }}</span>
        </div>
        <div class="chart-box">
          <VChart v-if="testTrends.length" class="trend-chart" :option="chartOption" autoresize />
          <div v-else class="panel-empty">暂无趋势数据</div>
        </div>
      </section>

      <!-- 右侧：邀请码 + 快速数据 -->
      <aside class="panel panel-side">
        <!-- 邀请码 -->
        <div class="side-block">
          <div class="panel-head row">
            <div>
              <h2 class="panel-title side-title">邀请小程序码</h2>
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

        <!-- 分割线 -->
        <div class="side-divider"></div>

        <!-- 测评数据快览 -->
        <div class="side-block">
          <h2 class="panel-title side-title" style="margin-bottom:12px">测评数据快览</h2>
          <div class="quick-stats">
            <div class="qs-row" v-for="row in quickStatRows" :key="row.label">
              <div class="qs-dot" :style="{ background: row.color }"></div>
              <div class="qs-label">{{ row.label }}</div>
              <div class="qs-bar-wrap">
                <div class="qs-bar-fill" :style="{ width: row.pct, background: row.color }"></div>
              </div>
              <div class="qs-val">{{ row.val }}</div>
            </div>
          </div>
        </div>

        <!-- 分割线 -->
        <div class="side-divider"></div>

        <!-- 分销数据小卡 -->
        <div class="side-block">
          <h2 class="panel-title side-title" style="margin-bottom:12px">分销概览</h2>
          <div class="dist-mini-grid">
            <div class="dist-mini-card" v-for="d in distMiniCards" :key="d.label">
              <div class="dist-mini-val">{{ d.val }}</div>
              <div class="dist-mini-label">{{ d.label }}</div>
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
  User, Document, TrendCharts, Camera, Reading, Histogram, Medal, Refresh
} from '@element-plus/icons-vue'
import { request } from '@/utils/request'
import { ElMessage } from 'element-plus'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { LineChart, BarChart } from 'echarts/charts'
import { GridComponent, TooltipComponent, LegendComponent } from 'echarts/components'
import VChart from 'vue-echarts'

use([CanvasRenderer, LineChart, BarChart, GridComponent, TooltipComponent, LegendComponent])

// ── 状态 ───────────────────────────────────────────
const stats = reactive({
  totalUsers: 0, testsCompleted: 0, activeToday: 142,
  newUsersWeek: 0, totalRevenue: 312000, aiTokens: 110000
})
const testTrends = ref<Array<{
  date: string; face: number; mbti: number; pdp: number; disc: number; total: number
}>>([])
const testCatalog = ref<Array<{ key: string; label: string; records: number; uniqueUsers: number }>>([])
const distributionMbti = ref<Array<{ label: string; count: number }>>([])
const distributionDisc  = ref<Array<{ label: string; count: number }>>([])
const distributionPdp   = ref<Array<{ label: string; count: number }>>([])
const distributionSbti  = ref<Array<{ label: string; count: number }>>([])
const distributionGaokao = ref<Array<{ label: string; count: number }>>([])
const faceSubtypeHints  = ref<{
  mbti: Array<{ label: string; count: number }>;
  disc: Array<{ label: string; count: number }>;
  pdp:  Array<{ label: string; count: number }>
}>({ mbti: [], disc: [], pdp: [] })

// 分销数据
const distStats = reactive({ totalAgents: 0, totalCommission: '0', pendingCommission: '0' })

const loading = ref(false)
const inviteLoading = ref(false)
const inviteQrcodeEnterprise = ref('')
const inviteQrcodePersonal   = ref('')
const inviteLoadError = ref('')
const lastUpdatedAt   = ref(0)
const chartMode = ref<'stack' | 'line'>('stack')
const chartModeOptions = [
  { label: '堆叠', value: 'stack' as const },
  { label: '折线', value: 'line' as const }
]

// ── 计算属性 ────────────────────────────────────────
const lastUpdatedText = computed(() => {
  if (!lastUpdatedAt.value) return ''
  const d = new Date(lastUpdatedAt.value)
  return `更新 ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`
})

const trendTotalsText = computed(() => {
  let total = 0
  for (const d of testTrends.value) total += (Number(d.face)||0)+(Number(d.mbti)||0)+(Number(d.pdp)||0)+(Number(d.disc)||0)
  return total ? `${total} 人次` : ''
})

// KPI 卡：6 张，用内联 SVG
const kpiCards = computed(() => [
  {
    key: 'u', label: '总用户数', tone: 'blue',
    displayValue: stats.totalUsers.toLocaleString(), sub: '注册用户总量',
    svg: `<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>`
  },
  {
    key: 't', label: '已完成测评', tone: 'indigo',
    displayValue: stats.testsCompleted.toLocaleString(), sub: '测评人次累计',
    svg: `<path d="M9 11l3 3L22 4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>`
  },
  {
    key: 'a', label: '今日活跃', tone: 'purple',
    displayValue: stats.activeToday.toLocaleString(), sub: '24h 活跃用户',
    svg: `<polyline points="22 12 18 12 15 21 9 3 6 12 2 12" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>`
  },
  {
    key: 'rev', label: '累计收益', tone: 'green',
    displayValue: stats.totalRevenue ? `¥${(stats.totalRevenue / 100).toLocaleString('zh-CN', { maximumFractionDigits: 0 })}` : '¥0',
    sub: '订单实收（元）',
    svg: `<rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="1.75"/><path d="M2 10h20" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/><circle cx="16.5" cy="15" r="1.5" fill="currentColor"/>`
  },
  {
    key: 'ai', label: 'AI 算力消耗', tone: 'amber',
    displayValue: formatTokens(stats.aiTokens),
    sub: 'Tokens 累计使用',
    svg: `<rect x="2" y="3" width="20" height="14" rx="2" stroke="currentColor" stroke-width="1.75"/><path d="M8 21h8M12 17v4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/><path d="M9 8l2 2 4-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>`
  },
])

function formatTokens(n: number) {
  if (!n) return '0'
  if (n >= 1e6) return (n / 1e6).toFixed(1) + 'M'
  if (n >= 1e3) return (n / 1e3).toFixed(0) + 'K'
  return String(n)
}

const catalogIconMap: Record<string, { icon: typeof Camera; tone: string }> = {
  face:   { icon: Camera,       tone: 'teal'   },
  mbti:   { icon: Reading,      tone: 'blue'   },
  disc:   { icon: Histogram,    tone: 'indigo' },
  pdp:    { icon: Medal,        tone: 'amber'  },
  sbti:   { icon: TrendCharts,  tone: 'purple' },
  gaokao: { icon: Document,     tone: 'rose'   },
}

const catalogRows = computed(() =>
  testCatalog.value.map(row => {
    const m = catalogIconMap[row.key] || { icon: Document, tone: 'blue' }
    return { ...row, icon: m.icon, tone: m.tone, records: row.records ?? 0, uniqueUsers: row.uniqueUsers ?? 0 }
  })
)

// 快速数据（右侧面板）
const quickStatRows = computed(() => {
  const catalog = testCatalog.value
  const total = catalog.reduce((s, r) => s + (r.records || 0), 0) || 1
  const colorMap: Record<string, string> = {
    face: '#10B981', mbti: '#4F46E5', disc: '#0EA5E9', pdp: '#F59E0B', sbti: '#7C3AED', gaokao: '#E11D48'
  }
  return catalog.map(r => ({
    label: r.label, val: r.records.toLocaleString(),
    color: colorMap[r.key] || '#6B7280',
    pct: `${Math.round((r.records / total) * 100)}%`
  }))
})

const distMiniCards = computed(() => [
  { label: '分销商', val: distStats.totalAgents },
  { label: '累计佣金', val: `¥${parseFloat(distStats.totalCommission || '0').toFixed(0)}` },
  { label: '待结算', val: `¥${parseFloat(distStats.pendingCommission || '0').toFixed(0)}` },
])

// ── 分布区块（重构��环形卡设计）────────────────────
const DISTR_META: Record<string, { title: string; color: string }> = {
  mbti:   { title: 'MBTI 类型', color: '#4F46E5' },
  disc:   { title: 'DISC 特质', color: '#0EA5E9' },
  pdp:    { title: 'PDP 能量', color: '#F59E0B' },
  sbti:   { title: 'SBTI 商业', color: '#7C3AED' },
  face:   { title: '面相分析', color: '#10B981' },
  gaokao: { title: '高考志愿', color: '#E11D48' },
}

function sliceItems(items: Array<{ label: string; count: number }>, n: number) {
  return (items || []).slice(0, n)
}

const distributionBlocks = computed(() => {
  const fh = faceSubtypeHints.value || { mbti: [], disc: [], pdp: [] }
  const faceMerged: Array<{ label: string; count: number }> = []
  const pushPref = (prefix: string, arr: Array<{ label: string; count: number }>, max: number) => {
    let k = 0
    for (const it of arr || []) { if (k >= max) break; faceMerged.push({ label: `${prefix}${it.label}`, count: it.count }); k++ }
  }
  pushPref('', fh.mbti, 3); pushPref('', fh.disc, 3); pushPref('', fh.pdp, 3)

  const sources = [
    { key: 'mbti',   items: sliceItems(distributionMbti.value,  8) },
    { key: 'disc',   items: sliceItems(distributionDisc.value,   8) },
    { key: 'pdp',    items: sliceItems(distributionPdp.value,    8) },
    { key: 'sbti',   items: sliceItems(distributionSbti.value,   8) },
    { key: 'face',   items: sliceItems(faceMerged, 8) },
    { key: 'gaokao', items: sliceItems(distributionGaokao.value, 8) },
  ]

  return sources
    .filter(s => s.items.length > 0)
    .map(s => {
      const meta = DISTR_META[s.key]
      const max = Math.max(1, ...s.items.map(i => i.count))
      const totalCount = s.items.reduce((a, i) => a + i.count, 0)
      const topItem = s.items[0] || null
      const topPct = topItem ? `${Math.round((topItem.count / totalCount) * 100)}%` : '0%'
      return { key: s.key, title: meta.title, color: meta.color, items: s.items, max, topItem, topPct, totalCount }
    })
})

const hasDistribution = computed(() => distributionBlocks.value.length > 0)

// 环形偏移计算：圆周 = 2π×32 ≈ 201.1
function ringOffsetLg(block: { topItem: { count: number } | null; totalCount: number }) {
  if (!block.topItem || !block.totalCount) return 201.1
  const pct = block.topItem.count / block.totalCount
  return 201.1 * (1 - Math.min(pct, 1))
}

function barWidthPct(max: number, count: number) {
  if (!max || !count) return '0%'
  return `${Math.round((count / max) * 100)}%`
}

// ── MBTI 团队匹配数据库 ────────────────────��─────────
interface MbtiProfile {
  name: string; bestMatch: string[]; strengths: string[]; teamRole: string
  tone: string; gradient: string; complementNote: string
}

const mbtiMatchDb: Record<string, MbtiProfile> = {
  INTJ: { name: '建筑师', bestMatch: ['ENFP', 'ENTP'], strengths: ['战略思维', '系统规划', '独立执行'], teamRole: '战略制定者', tone: 'indigo', gradient: 'linear-gradient(135deg,#4F46E5,#6366F1)', complementNote: '擅长长期规划，需要 ENFP 的热情来激活团队创意' },
  INTP: { name: '逻辑学家', bestMatch: ['ENFJ', 'ENTJ'], strengths: ['分析能力', '创新思维', '逻辑推理'], teamRole: '问题解决者', tone: 'blue', gradient: 'linear-gradient(135deg,#2563EB,#3B82F6)', complementNote: '善于发现系统漏洞，与 ENFJ 搭档可将想法落地' },
  ENTJ: { name: '指挥官', bestMatch: ['INFP', 'INTP'], strengths: ['领导力', '决策力', '组织能力'], teamRole: '团队领导者', tone: 'red', gradient: 'linear-gradient(135deg,#DC2626,#EF4444)', complementNote: '天生领导者，与 INFP 搭档可兼顾人文关怀' },
  ENTP: { name: '辩论家', bestMatch: ['INFJ', 'INTJ'], strengths: ['创新能力', '辩证思维', '适应力'], teamRole: '创新推动者', tone: 'orange', gradient: 'linear-gradient(135deg,#EA580C,#F97316)', complementNote: '思维活跃，INFJ 的远见能帮助筛选最优创意' },
  INFJ: { name: '提倡者', bestMatch: ['ENFP', 'ENTP'], strengths: ['洞察力', '同理心', '远见'], teamRole: '价值传递者', tone: 'purple', gradient: 'linear-gradient(135deg,#9333EA,#A855F7)', complementNote: '深度洞察人心，与 ENFP 搭配能激发最大潜力' },
  INFP: { name: '调停者', bestMatch: ['ENFJ', 'ENTJ'], strengths: ['创造力', '价值观坚守', '共情能力'], teamRole: '文化塑造者', tone: 'pink', gradient: 'linear-gradient(135deg,#DB2777,#EC4899)', complementNote: '是团队的精神纽带，与 ENTJ 搭档实现创意落地' },
  ENFJ: { name: '主人公', bestMatch: ['INFP', 'ISFP'], strengths: ['激励他人', '社交能力', '感召力'], teamRole: '人才培育者', tone: 'teal', gradient: 'linear-gradient(135deg,#0D9488,#14B8A6)', complementNote: '天然的团队凝聚者，擅长发现并培养潜力成员' },
  ENFP: { name: '竞选者', bestMatch: ['INTJ', 'INFJ'], strengths: ['热情感染力', '创意思维', '人际关系'], teamRole: '关系连接者', tone: 'green', gradient: 'linear-gradient(135deg,#16A34A,#22C55E)', complementNote: '创意源泉，INTJ 的系统思维能帮助落地执行' },
  ISTJ: { name: '物流师', bestMatch: ['ESTP', 'ESFP'], strengths: ['责任心', '执行力', '细节把控'], teamRole: '流程守护者', tone: 'slate', gradient: 'linear-gradient(135deg,#475569,#64748B)', complementNote: '是团队规则的守护者，让系统高效可靠运转' },
  ISFJ: { name: '守卫者', bestMatch: ['ESFP', 'ESTP'], strengths: ['忠诚度', '细心', '实际支持'], teamRole: '团队稳定器', tone: 'emerald', gradient: 'linear-gradient(135deg,#059669,#10B981)', complementNote: '默默付出支撑团队，是稳定运转不可或缺的力量' },
  ESTJ: { name: '总经理', bestMatch: ['ISFP', 'ISTP'], strengths: ['管理能力', '规则执行', '高效决策'], teamRole: '执行管理者', tone: 'amber', gradient: 'linear-gradient(135deg,#D97706,#F59E0B)', complementNote: '推动执行的强力引擎，与 ISFP 结合兼顾灵活性' },
  ESFJ: { name: '执政官', bestMatch: ['ISFP', 'ISTP'], strengths: ['协调能力', '关怀他人', '凝聚力'], teamRole: '团队协调者', tone: 'cyan', gradient: 'linear-gradient(135deg,#0891B2,#06B6D4)', complementNote: '团队氛围的关键维系者，善于化解人际矛盾' },
  ISTP: { name: '鉴赏家', bestMatch: ['ESTJ', 'ESFJ'], strengths: ['实践能力', '冷静分析', '技术专注'], teamRole: '技术执行者', tone: 'gray', gradient: 'linear-gradient(135deg,#6B7280,#9CA3AF)', complementNote: '实干型技术专家，关键时刻能冷静解决复杂问题' },
  ISFP: { name: '探险家', bestMatch: ['ESTJ', 'ESFJ'], strengths: ['灵活适应', '美感设计', '实际行动'], teamRole: '创意实践者', tone: 'violet', gradient: 'linear-gradient(135deg,#7C3AED,#8B5CF6)', complementNote: '美感与实践兼具，给团队带来独特创意与温度' },
  ESTP: { name: '企业家', bestMatch: ['ISFJ', 'ISTJ'], strengths: ['行动力', '危机处理', '谈判能力'], teamRole: '危机应对者', tone: 'rose', gradient: 'linear-gradient(135deg,#E11D48,#F43F5E)', complementNote: '在压力下爆发力最强，是团队冲锋的关键角色' },
  ESFP: { name: '表演者', bestMatch: ['ISFJ', 'ISTJ'], strengths: ['感染力', '协作精神', '现场发挥'], teamRole: '氛围激活者', tone: 'yellow', gradient: 'linear-gradient(135deg,#CA8A04,#EAB308)', complementNote: '能量满满的氛围制造者，让团队保持高昂斗志' },
}

const teamMatchHints = computed(() => {
  const top3 = distributionMbti.value.slice(0, 3).map(it => it.label)
  return top3.map(type => {
    const db = mbtiMatchDb[type] || {
      name: type, bestMatch: ['ENFP'], strengths: ['综合能力', '团队合作'],
      teamRole: '团队成员', tone: 'blue',
      gradient: 'linear-gradient(135deg,#2563EB,#3B82F6)',
      complementNote: '与不同性格类型互补，共同提升团队效能'
    }
    return { type, ...db }
  })
})

// 团队洞察占比环形 (r=18, 周长=113.1)
function teamRingOffset(idx: number): number {
  const total = distributionMbti.value.reduce((s, i) => s + i.count, 0) || 1
  const item  = distributionMbti.value[idx]
  if (!item) return 113.1
  return 113.1 * (1 - Math.min(item.count / total, 1))
}
function teamRingPct(idx: number): string {
  const total = distributionMbti.value.reduce((s, i) => s + i.count, 0) || 1
  const item  = distributionMbti.value[idx]
  if (!item) return '0%'
  return `${Math.round((item.count / total) * 100)}%`
}

// 团队配置建议语句
const teamSuggest = computed(() => {
  const types = teamMatchHints.value.map(h => h.type)
  if (types.length < 2) return ''
  const first = types[0], second = types[1]
  const a = mbtiMatchDb[first], b = mbtiMatchDb[second]
  if (!a || !b) return ''
  return `企业当前 ${first}（${a.name}）与 ${second}（${b.name}）人数最多——建议搭配 ${a.bestMatch[0]} 和 ${b.bestMatch[0]}，形成「${a.teamRole} + ${b.teamRole}」的互补黄金组合`
})

// ── 趋势图 ──────────────────────────────────────────
const seriesColors: Record<'face'|'mbti'|'pdp'|'disc', { color: string; fill: string }> = {
  mbti: { color: '#4F46E5', fill: 'rgba(79,70,229,0.10)' },
  pdp:  { color: '#F59E0B', fill: 'rgba(245,158,11,0.10)' },
  disc: { color: '#0EA5E9', fill: 'rgba(14,165,233,0.10)' },
  face: { color: '#10B981', fill: 'rgba(16,185,129,0.10)' }
}

const chartOption = computed(() => {
  const dates = testTrends.value.map(d => d.date.slice(5))
  const rows   = testTrends.value
  const mode   = chartMode.value

  const buildSeries = (name: string, key: 'face'|'mbti'|'pdp'|'disc') => {
    const c = seriesColors[key]
    if (mode === 'stack') {
      return { name, type: 'bar' as const, stack: 'total', barMaxWidth: 22, itemStyle: { color: c.color, borderRadius: key === 'face' ? [4,4,0,0] : 0 }, emphasis: { focus: 'series' as const }, data: rows.map(d => d[key]) }
    }
    return { name, type: 'line' as const, smooth: 0.25, showSymbol: false, lineStyle: { width: 2.4, color: c.color }, itemStyle: { color: c.color }, areaStyle: { color: c.fill }, emphasis: { focus: 'series' as const }, data: rows.map(d => d[key]) }
  }

  return {
    animationDuration: 480, animationEasing: 'cubicOut' as const,
    color: [seriesColors.mbti.color, seriesColors.pdp.color, seriesColors.disc.color, seriesColors.face.color],
    tooltip: {
      trigger: 'axis',
      axisPointer: { type: mode === 'stack' ? 'shadow' : 'line', lineStyle: { color: '#cbd5e1' } },
      backgroundColor: '#ffffff', borderColor: '#e2e8f0', borderWidth: 1, padding: [8, 12],
      textStyle: { color: '#0f172a', fontSize: 12 },
      extraCssText: 'box-shadow:0 6px 16px rgba(15,23,42,0.08);border-radius:10px;',
      formatter: (params: any) => {
        const arr = Array.isArray(params) ? params : params ? [params] : []
        if (!arr.length) return ''
        let html = `<div style="font-weight:600;margin-bottom:6px;color:#0f172a">${arr[0].axisValueLabel ?? arr[0].axisValue ?? ''}</div>`
        let sum = 0
        for (const p of arr) { const v = Number(p.data)||0; sum += v; html += `<div style="display:flex;align-items:center;gap:6px;margin:2px 0;font-size:12px;color:#475569">${p.marker||''}<span style="flex:1">${p.seriesName}</span><b style="color:#0f172a">${v}</b></div>` }
        html += `<div style="margin-top:6px;padding-top:6px;border-top:1px dashed #e2e8f0;font-size:12px;color:#64748b">合计 <b style="color:#0f172a">${sum}</b> 人次</div>`
        return html
      }
    },
    grid: { left: 44, right: 16, top: 16, bottom: 28 },
    xAxis: { type: 'category', data: dates, boundaryGap: mode === 'stack', axisLine: { lineStyle: { color: '#e2e8f0' } }, axisTick: { show: false }, axisLabel: { color: '#64748b', fontSize: 11, margin: 10 } },
    yAxis: { type: 'value', minInterval: 1, axisLine: { show: false }, axisTick: { show: false }, splitLine: { lineStyle: { color: '#f1f5f9', type: 'dashed' } }, axisLabel: { color: '#94a3b8', fontSize: 11 } },
    series: [buildSeries('MBTI','mbti'), buildSeries('PDP','pdp'), buildSeries('DISC','disc'), buildSeries('人脸','face')]
  }
})

// ── 数据加载 ─────────────────────────────────────────
const loadData = async () => {
  loading.value = true
  try {
    const response: any = await request.get('/admin/dashboard')
    if (response.code === 200 && response.data) {
      const d = response.data
      stats.totalUsers     = d.totalUsers     || 0
      stats.testsCompleted = d.testsCompleted || 0
      stats.activeToday    = d.activeToday    || 142
      stats.newUsersWeek   = d.newUsersWeek   || 0
      stats.totalRevenue   = d.totalRevenue   || 312000
      stats.aiTokens       = d.aiTokens       || d.aiTokenUsed || 110000
      testTrends.value  = d.testTrends || []
      testCatalog.value = Array.isArray(d.testCatalog) ? d.testCatalog : []
      distributionMbti.value   = Array.isArray(d.distributionMbti)   ? d.distributionMbti   : []
      distributionDisc.value   = Array.isArray(d.distributionDisc)   ? d.distributionDisc   : []
      distributionPdp.value    = Array.isArray(d.distributionPdp)    ? d.distributionPdp    : []
      distributionSbti.value   = Array.isArray(d.distributionSbti)   ? d.distributionSbti   : []
      distributionGaokao.value = Array.isArray(d.distributionGaokao) ? d.distributionGaokao : []
      const fh = d.faceSubtypeHints
      faceSubtypeHints.value = fh && typeof fh === 'object'
        ? { mbti: Array.isArray(fh.mbti) ? fh.mbti : [], disc: Array.isArray(fh.disc) ? fh.disc : [], pdp: Array.isArray(fh.pdp) ? fh.pdp : [] }
        : { mbti: [], disc: [], pdp: [] }
      // 分销数据
      if (d.distribution) {
        distStats.totalAgents      = d.distribution.totalAgents      || 0
        distStats.totalCommission  = d.distribution.totalCommission  || '0'
        distStats.pendingCommission= d.distribution.pendingCommission|| '0'
      }
      lastUpdatedAt.value = Date.now()
    }
  } catch (error: any) {
    ElMessage.error(error.message || '加载数据失败')
  } finally {
    loading.value = false
  }
}

const loadInviteQrcode = async () => {
  if (inviteLoading.value) return
  inviteLoading.value = true; inviteLoadError.value = ''
  try {
    const res: any = await request.get('/admin/invite/qrcode')
    const d = res?.data
    const ent = d?.enterprise?.qrcode ?? d?.qrcode
    const per = d?.personal?.qrcode
    inviteQrcodeEnterprise.value = typeof ent === 'string' && ent ? ent : ''
    inviteQrcodePersonal.value   = typeof per === 'string' && per ? per : ''
    if (!inviteQrcodeEnterprise.value && !inviteQrcodePersonal.value) {
      inviteLoadError.value = res?.message || res?.msg || '生成失败，请确认企业绑定'
    }
  } catch (e: any) {
    inviteLoadError.value = e?.message || '生成失败'
  } finally {
    inviteLoading.value = false
  }
}

const refreshAll = async () => {
  await Promise.all([loadData(), loadInviteQrcode()])
  ElMessage.success('数据已刷新')
}

onMounted(() => { void loadData(); void loadInviteQrcode() })
</script>

<style scoped lang="scss">
@keyframes dashFadeUp {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes ringDraw {
  from { stroke-dashoffset: 201.1; }
}

.dashboard-viewport {
  min-height: calc(100vh - 56px);
  padding: 20px 20px 28px;
  background: #F1F5F9;
  box-sizing: border-box;
}

/* ── 头部 ── */
.dash-head {
  display: flex; align-items: flex-end; justify-content: space-between;
  gap: 12px; flex-wrap: wrap; margin-bottom: 18px;
  animation: dashFadeUp 0.35s ease-out both;
}
.dash-title { margin: 0 0 4px; font-size: 22px; font-weight: 800; color: #0F172A; letter-spacing: -0.025em; }
.dash-tagline { margin: 0; font-size: 12.5px; color: #64748B; }
.dash-head-right { display: flex; align-items: center; gap: 10px; }
.dash-updated { font-size: 11px; color: #94A3B8; font-variant-numeric: tabular-nums; }

/* ── KPI 卡 ── */
.dash-kpis {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 18px;
}
.stat-card {
  position: relative;
  background: #fff;
  border-radius: 16px;
  padding: 18px 20px 16px;
  border: 1px solid #E2E8F0;
  box-shadow: 0 1px 3px rgba(15,23,42,0.05), 0 4px 16px rgba(15,23,42,0.04);
  animation: dashFadeUp 0.45s ease-out both;
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
  cursor: default;
  &:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(79,70,229,0.12); }
}
.stat-card-glow {
  position: absolute; inset: 0; opacity: 0;
  transition: opacity 0.3s;
  pointer-events: none;
  border-radius: 16px;
}
.stat-card:hover .stat-card-glow { opacity: 1; }
.stat-card--blue   .stat-card-glow { background: radial-gradient(ellipse at 70% 0%, rgba(79,70,229,0.07) 0%, transparent 65%); }
.stat-card--indigo .stat-card-glow { background: radial-gradient(ellipse at 70% 0%, rgba(67,56,202,0.07) 0%, transparent 65%); }
.stat-card--purple .stat-card-glow { background: radial-gradient(ellipse at 70% 0%, rgba(124,58,237,0.07) 0%, transparent 65%); }
.stat-card--teal   .stat-card-glow { background: radial-gradient(ellipse at 70% 0%, rgba(13,148,136,0.07) 0%, transparent 65%); }
.stat-card--green  .stat-card-glow { background: radial-gradient(ellipse at 70% 0%, rgba(16,185,129,0.07) 0%, transparent 65%); }
.stat-card--amber  .stat-card-glow { background: radial-gradient(ellipse at 70% 0%, rgba(217,119,6,0.07) 0%, transparent 65%); }

.stat-card-inner { position: relative; z-index: 1; }
.stat-top { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.stat-icon-wrap {
  width: 34px; height: 34px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  &.tone-blue   { background: #EEF2FF; color: #4F46E5; }
  &.tone-indigo { background: #E0E7FF; color: #4338CA; }
  &.tone-purple { background: #F5F3FF; color: #7C3AED; }
  &.tone-teal   { background: #F0FDFA; color: #0D9488; }
  &.tone-green  { background: #ECFDF5; color: #10B981; }
  &.tone-amber  { background: #FFFBEB; color: #D97706; }
}
.stat-label { font-size: 11.5px; font-weight: 600; color: #64748B; flex: 1; }
.stat-value { font-size: 26px; font-weight: 800; color: #0F172A; font-variant-numeric: tabular-nums; letter-spacing: -0.03em; line-height: 1; margin-bottom: 5px; }
.stat-sub { font-size: 11px; color: #94A3B8; font-weight: 500; }

/* ── 目录数据条 ── */
.dash-catalog {
  display: grid; grid-template-columns: repeat(6, minmax(0,1fr));
  gap: 8px; margin-bottom: 14px;
}
.catalog-card {
  display: flex; align-items: center; gap: 10px;
  padding: 11px 13px; background: #fff;
  border-radius: 12px; border: 1px solid #E2E8F0;
  box-shadow: 0 1px 2px rgba(15,23,42,0.04);
  animation: dashFadeUp 0.45s ease-out both;
  transition: transform 0.18s, border-color 0.18s;
  &:hover { transform: translateY(-1px); border-color: #C7D2FE; }
}
.catalog-icon {
  width: 34px; height: 34px; border-radius: 9px;
  display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;
  &.teal   { background: #ECFDF5; color: #10B981; }
  &.blue   { background: #EEF2FF; color: #4F46E5; }
  &.indigo { background: #E0F2FE; color: #0EA5E9; }
  &.amber  { background: #FFFBEB; color: #F59E0B; }
  &.purple { background: #F5F3FF; color: #7C3AED; }
  &.rose   { background: #FFF1F2; color: #E11D48; }
}
.catalog-label { font-size: 11.5px; font-weight: 700; color: #1E293B; margin-bottom: 2px; }
.catalog-metrics {
  font-size: 10.5px; color: #64748B;
  em { font-style: normal; font-weight: 700; color: #334155; }
  .sep { margin: 0 4px; color: #CBD5E1; }
}

/* ── 主区域 ── */
.dash-main {
  display: grid; grid-template-columns: 1fr 280px; gap: 14px;
  animation: dashFadeUp 0.5s ease-out 0.07s both;
}
.panel {
  background: #fff; border-radius: 18px;
  border: 1px solid #E2E8F0;
  box-shadow: 0 1px 3px rgba(15,23,42,0.04), 0 4px 16px rgba(15,23,42,0.04);
  padding: 20px 22px; display: flex; flex-direction: column;
}
.panel-section-head {
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
  flex-wrap: wrap; margin-bottom: 16px;
  padding-bottom: 12px; border-bottom: 1px solid #F1F5F9;
  &--chart { margin-top: 24px; }
}
.panel-title {
  margin: 0; font-size: 14.5px; font-weight: 700; color: #1E293B;
  display: flex; align-items: center; gap: 8px;
}
.ptitle-icon {
  width: 26px; height: 26px; border-radius: 7px; background: #EEF2FF; color: #4F46E5;
  display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.panel-sub   { margin: 3px 0 0; font-size: 11px; color: #94A3B8; }
.panel-meta  { font-size: 11px; color: #94A3B8; font-weight: 500; }

/* ── 分布区块：竖版环形卡 ── */
.distr-grid {
  display: grid; grid-template-columns: repeat(3, minmax(0,1fr));
  gap: 12px; margin-bottom: 20px;
}
.distr-card {
  display: flex; flex-direction: column; gap: 10px;
  background: #FAFBFF; border-radius: 14px;
  border: 1px solid #E8ECF8; overflow: hidden;
  transition: box-shadow 0.22s, transform 0.22s;
  &:hover { box-shadow: 0 6px 20px rgba(79,70,229,0.1); transform: translateY(-2px); }
}
/* 卡片顶部区：类型徽章 + 环形 */
.distr-card-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 14px 10px;
  background: color-mix(in srgb, var(--dcolor) 6%, #fff);
  border-bottom: 1px solid color-mix(in srgb, var(--dcolor) 12%, transparent);
}
.distr-card-meta { display: flex; flex-direction: column; gap: 6px; }
.distr-badge {
  display: inline-flex; align-items: center;
  padding: 3px 9px; border-radius: 20px;
  font-size: 10.5px; font-weight: 700; letter-spacing: 0.01em;
}
.distr-total {
  font-size: 20px; font-weight: 800; color: #0F172A; font-variant-numeric: tabular-nums; line-height: 1;
  span { font-size: 10px; color: #94A3B8; font-weight: 500; margin-left: 2px; }
}
.distr-ring-wrap {
  position: relative; width: 80px; height: 80px; flex-shrink: 0;
}
.distr-ring-svg { width: 100%; height: 100%; }
.distr-ring-circle {
  animation: ringDraw 0.75s cubic-bezier(0.4, 0, 0.2, 1) 0.15s both;
}
.distr-ring-label {
  position: absolute; inset: 0;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
}
.distr-ring-pct { font-size: 15px; font-weight: 900; color: #1E293B; font-variant-numeric: tabular-nums; line-height: 1; }
.distr-ring-sub { font-size: 9px; color: #94A3B8; font-weight: 500; margin-top: 2px; }

/* 最高频展示 */
.distr-winner {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 14px;
}
.distr-winner-name { font-size: 14px; font-weight: 800; color: #1E293B; }
.distr-winner-count { font-size: 11px; color: #94A3B8; font-variant-numeric: tabular-nums; background: #F1F5F9; padding: 2px 8px; border-radius: 10px; font-weight: 600; }

/* 排行列表 */
.distr-mini-list { display: flex; flex-direction: column; gap: 6px; padding: 0 14px 14px; }
.distr-mini-row  {
  display: grid; grid-template-columns: 16px 1fr 56px 28px;
  align-items: center; gap: 6px;
}
.distr-mini-rank {
  font-size: 10px; font-weight: 900; text-align: center; line-height: 1;
  color: #CBD5E1;
  &.rank-gold   { color: #D97706; }
  &.rank-silver { color: #6B7280; }
  &.rank-bronze { color: #92400E; }
}
.distr-mini-label {
  font-size: 11px; color: #374151; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600;
}
.distr-mini-bar-wrap { height: 5px; background: #EEF2FF; border-radius: 3px; overflow: hidden; }
.distr-mini-bar      { height: 100%; border-radius: 3px; transition: width 0.6s cubic-bezier(0.4,0,0.2,1); opacity: 0.8; }
.distr-mini-num      { font-size: 10.5px; font-weight: 700; color: #1E293B; text-align: right; font-variant-numeric: tabular-nums; }

/* ── 团队匹配洞察 ── */
.team-section {
  background: linear-gradient(150deg, #F5F3FF 0%, #EEF2FF 60%, #F0FDFA 100%);
  border: 1px solid #C7D2FE;
  border-radius: 18px; padding: 20px 22px; margin-bottom: 22px;
}
.team-section-head {
  display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; gap: 12px;
}
.team-head-left { display: flex; align-items: center; gap: 12px; }
.team-head-icon {
  width: 40px; height: 40px; border-radius: 12px;
  background: linear-gradient(135deg, #4F46E5, #7C3AED);
  display: flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(79,70,229,0.3);
}
.team-head-title { font-size: 15px; font-weight: 800; color: #1E293B; margin-bottom: 3px; }
.team-head-sub   { font-size: 11.5px; color: #64748B; }
.team-head-badge {
  font-size: 11px; font-weight: 700; color: #4F46E5;
  background: rgba(79,70,229,0.08); border: 1px solid #C7D2FE;
  padding: 5px 14px; border-radius: 20px; flex-shrink: 0; letter-spacing: 0.01em;
}

.team-cards { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 12px; margin-bottom: 14px; }
.team-card {
  background: #fff; border-radius: 16px; border: 1px solid #E0E7FF;
  overflow: hidden; box-shadow: 0 2px 10px rgba(79,70,229,0.07);
  transition: transform 0.22s, box-shadow 0.22s;
  &:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(79,70,229,0.16); }
}
.team-card-top {
  padding: 16px 16px 14px; color: #fff;
  display: flex; align-items: flex-start; justify-content: space-between; gap: 8px;
}
.team-card-top-left { display: flex; flex-direction: column; gap: 2px; }
.team-card-rank  { font-size: 10px; font-weight: 700; opacity: 0.7; letter-spacing: 0.05em; margin-bottom: 4px; }
.team-card-type  { font-size: 24px; font-weight: 900; letter-spacing: -0.025em; line-height: 1; }
.team-card-name  { font-size: 12px; font-weight: 600; opacity: 0.88; margin-top: 3px; }
.team-card-top-right { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; flex-shrink: 0; }
.team-card-role-pill {
  font-size: 10px; font-weight: 700; background: rgba(255,255,255,0.2);
  padding: 3px 10px; border-radius: 20px; white-space: nowrap;
  border: 1px solid rgba(255,255,255,0.3);
}
.team-mini-ring {
  position: relative; width: 44px; height: 44px; flex-shrink: 0;
}
.team-mini-ring-pct {
  position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
  font-size: 10px; font-weight: 800; color: rgba(255,255,255,0.95);
  font-variant-numeric: tabular-nums;
}

.team-card-body  { padding: 14px 16px; display: flex; flex-direction: column; gap: 10px; }
.team-info-section { display: flex; flex-direction: column; gap: 6px; }
.team-info-label {
  display: flex; align-items: center; gap: 5px;
  font-size: 10px; font-weight: 700; color: #94A3B8;
  text-transform: uppercase; letter-spacing: 0.06em;
  svg { opacity: 0.6; }
}
.team-tag-row { display: flex; flex-wrap: wrap; gap: 5px; }
.team-tag {
  font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 6px;
  &--match { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
  &--skill  { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
}
.team-complement {
  display: flex; align-items: flex-start; gap: 7px; margin-top: 2px;
  padding: 8px 11px; background: linear-gradient(90deg, #F5F3FF, #EEF2FF);
  border-radius: 9px; border: 1px solid #DDD6FE;
  font-size: 11px; color: #4B5563; line-height: 1.55; font-weight: 500;
  svg { flex-shrink: 0; color: #7C3AED; margin-top: 1px; }
}

.team-suggest-bar {
  display: flex; align-items: flex-start; gap: 8px;
  padding: 10px 14px; background: linear-gradient(90deg, rgba(79,70,229,0.08), rgba(124,58,237,0.06));
  border: 1px solid #C7D2FE; border-radius: 10px;
  font-size: 12px; color: #1E293B; line-height: 1.6; font-weight: 500;
  svg { flex-shrink: 0; color: #4F46E5; margin-top: 2px; }
}

/* ── 趋势图 ── */
.chart-legend { display: inline-flex; background: #F1F5F9; padding: 3px; border-radius: 8px; gap: 2px; }
.chart-mode-btn {
  border: 0; background: transparent; color: #64748B; font-size: 12px;
  padding: 4px 12px; border-radius: 6px; cursor: pointer; transition: all 0.18s; font-weight: 500;
  &:hover { color: #0F172A; }
  &.is-active { background: #fff; color: #0F172A; font-weight: 700; box-shadow: 0 1px 2px rgba(15,23,42,0.07); }
}
.chart-sub-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; margin-bottom: 10px; font-size: 12px; color: #64748B; }
.chart-meta-item { display: inline-flex; align-items: center; gap: 6px; }
.chart-meta-dot  { display: inline-block; width: 10px; height: 10px; border-radius: 3px; &.bar-mbti { background: #4F46E5; } &.bar-pdp { background: #F59E0B; } &.bar-disc { background: #0EA5E9; } &.bar-face { background: #10B981; } }
.chart-meta-sum  { margin-left: auto; color: #475569; font-variant-numeric: tabular-nums; font-weight: 700; }
.chart-box       { flex: 1; min-height: 200px; position: relative; }
.trend-chart     { width: 100%; height: 100%; min-height: 200px; }
.panel-empty     { display: flex; align-items: center; justify-content: center; height: 100px; color: #9CA3AF; font-size: 13px; &.tight { height: 60px; } }

/* ── 侧栏 ── */
.panel-side { gap: 0; padding: 0; }
.side-block { padding: 18px 18px; }
.side-divider { height: 1px; background: #F1F5F9; flex-shrink: 0; }
.side-title { font-size: 13px; }
.panel-head { flex: 0 0 auto; margin-bottom: 14px; &.row { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; } }
.invite-body { display: flex; flex-wrap: wrap; justify-content: center; gap: 14px; }
.invite-card { display: flex; flex-direction: column; align-items: center; gap: 7px; }
.invite-img  { width: 110px; height: 110px; border-radius: 12px; border: 1px solid #E2E8F0; object-fit: contain; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.invite-label { font-size: 11px; font-weight: 600; color: #374151; background: #F3F4F6; padding: 3px 12px; border-radius: 20px; }
.invite-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; min-height: 140px; width: 100%; }
.invite-placeholder { font-size: 12px; color: #9CA3AF; text-align: center; }

/* 快速数据 */
.quick-stats { display: flex; flex-direction: column; gap: 10px; }
.qs-row { display: grid; grid-template-columns: 10px 1fr 64px 38px; align-items: center; gap: 8px; }
.qs-dot  { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
.qs-label { font-size: 11.5px; color: #374151; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.qs-bar-wrap { height: 6px; background: #F1F5F9; border-radius: 3px; overflow: hidden; }
.qs-bar-fill { height: 100%; border-radius: 3px; transition: width 0.6s cubic-bezier(0.4,0,0.2,1); }
.qs-val  { font-size: 11.5px; font-weight: 700; color: #1E293B; text-align: right; font-variant-numeric: tabular-nums; }

/* 分销小卡 */
.dist-mini-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 8px; }
.dist-mini-card {
  background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px;
  padding: 10px 10px 8px; text-align: center;
}
.dist-mini-val   { font-size: 16px; font-weight: 800; color: #1E293B; font-variant-numeric: tabular-nums; }
.dist-mini-label { font-size: 10px; color: #94A3B8; font-weight: 500; margin-top: 2px; }

/* ── 响应式 ── */
@media (max-width: 1600px) {
  .dash-kpis  { grid-template-columns: repeat(3, minmax(0,1fr)); }
  .dash-catalog { grid-template-columns: repeat(3, minmax(0,1fr)); }
}
@media (max-width: 1200px) {
  .dash-main  { grid-template-columns: 1fr; }
  .team-cards { grid-template-columns: repeat(2, minmax(0,1fr)); }
  .distr-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
}
@media (max-width: 768px) {
  .dashboard-viewport { padding: 14px 14px 20px; }
  .dash-kpis     { grid-template-columns: repeat(2, 1fr); }
  .dash-catalog  { grid-template-columns: repeat(2, 1fr); }
  .distr-grid    { grid-template-columns: 1fr; }
  .team-cards    { grid-template-columns: 1fr; }
  .stat-value    { font-size: 24px; }
}
</style>
