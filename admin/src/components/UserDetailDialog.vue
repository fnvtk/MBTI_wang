<template>
  <el-dialog
    :model-value="modelValue"
    class="user-detail-dialog"
    title="用户详情"
    width="min(1180px, 96vw)"
    top="3vh"
    destroy-on-close
    append-to-body
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <!-- 侧栏 ud-sidebar 与主区 ud-main-pane 之间间距由 .ud-layout 的 gap 控制 -->
    <div v-loading="loading" class="ud-layout">
      <template v-if="user">
        <aside class="ud-sidebar">
          <div class="ud-profile-avatar">
            <img v-if="user.avatar" :src="user.avatar" class="ud-profile-avatar__img" referrerpolicy="no-referrer" />
            <div v-else class="ud-profile-avatar__letter">{{ avatarLetter }}</div>
            <div class="ud-profile-name">{{ user.username || '未命名用户' }}</div>
          </div>
          <div class="ud-profile-meta">
            <div class="ud-profile-meta__row">
              <el-icon><Key /></el-icon>
              <span>ID {{ user.id }}</span>
            </div>
            <div class="ud-profile-meta__row" v-if="user.phone">
              <el-icon><Phone /></el-icon>
              <span>{{ user.phone }}</span>
            </div>
            <div class="ud-profile-meta__row">
              <el-icon><Calendar /></el-icon>
              <span>{{ formatDate(user.createdAt) }}</span>
            </div>
          </div>
          <div class="ud-quick-stats">
            <el-tooltip content="测试次数" placement="top">
              <div class="ud-quick-stats__tile"><el-icon><DataAnalysis /></el-icon>{{ user.testCount ?? 0 }}</div>
            </el-tooltip>
            <el-tooltip content="MBTI" placement="top">
              <div class="ud-quick-stats__tile"><el-icon><Aim /></el-icon>{{ shortOrDash(user.mbtiType) }}</div>
            </el-tooltip>
            <el-tooltip content="PDP" placement="top">
              <div class="ud-quick-stats__tile"><el-icon><TrendCharts /></el-icon>{{ shortOrDash(user.pdpType, 4) }}</div>
            </el-tooltip>
            <el-tooltip content="DISC" placement="top">
              <div class="ud-quick-stats__tile"><el-icon><PieChart /></el-icon>{{ shortOrDash(user.discType, 3) }}</div>
            </el-tooltip>
          </div>
          <div class="ud-dimension-tags" v-if="profileTags.length">
            <div class="ud-dimension-tags__title">维度标签</div>
            <el-tag v-for="t in profileTags" :key="t" size="small" class="ud-dimension-tags__item">{{ t }}</el-tag>
          </div>
        </aside>

        <main class="ud-main-pane">
          <el-tabs v-model="udTab" class="ud-tabs">
            <el-tab-pane label="分析结果" name="analysis">
              <div class="ud-main-scroll">
                <div class="ud-chart-row">
                  <div class="ud-chart-panel">
                    <div class="ud-chart-heading"><el-icon><Aim /></el-icon> MBTI</div>
                    <VChart v-if="mbtiRadarOption" class="ud-chart-echart" :option="mbtiRadarOption" autoresize />
                    <div v-else class="ud-chart-placeholder">暂无 MBTI 测评</div>
                    <div v-if="mbtiSummary" class="ud-chart-footnote">{{ mbtiSummary }}</div>
                  </div>
                  <div class="ud-chart-panel">
                    <div class="ud-chart-heading"><el-icon><TrendCharts /></el-icon> PDP</div>
                    <VChart v-if="pdpRadarOption" class="ud-chart-echart" :option="pdpRadarOption" autoresize />
                    <div v-else class="ud-chart-placeholder">暂无 PDP 测评</div>
                  </div>
                  <div class="ud-chart-panel">
                    <div class="ud-chart-heading"><el-icon><PieChart /></el-icon> DISC</div>
                    <VChart v-if="discRadarOption" class="ud-chart-echart" :option="discRadarOption" autoresize />
                    <div v-else class="ud-chart-placeholder">暂无 DISC 测评</div>
                  </div>
                </div>

                <div class="ud-dual-cards">
                  <div class="ud-panel-card">
                    <div class="ud-panel-card__head"><el-icon><Star /></el-icon> 盖洛普优势</div>
                    <div v-if="gallupList.length" class="ud-gallup-list">
                      <div v-for="(g, idx) in gallupList" :key="idx" class="ud-gallup-list__item">
                        <span class="ud-gallup-list__index">{{ Number(idx) + 1 }}</span>
                        <span class="ud-gallup-list__text">{{ g }}</span>
                      </div>
                    </div>
                    <div v-else class="ud-empty-hint">暂无盖洛普数据（深度报告解锁后可见）</div>
                  </div>
                  <div class="ud-panel-card ud-panel-card--wide">
                    <div class="ud-panel-card__head"><el-icon><OfficeBuilding /></el-icon> 岗位匹配参考</div>
                    <div class="ud-rolefit-list">
                      <div v-for="r in roleFitList" :key="r.name" class="ud-rolefit-list__row">
                        <span class="ud-rolefit-list__name">{{ r.name }}</span>
                        <el-progress :percentage="r.pct" :stroke-width="6" :show-text="false" />
                        <span class="ud-rolefit-list__percent">{{ r.pct }}%</span>
                      </div>
                    </div>
                  </div>
                </div>

                <div v-if="showEnterpriseMatch" class="ud-panel-card ud-enterprise-match">
                  <div class="ud-panel-card__head">
                    <el-icon><Connection /></el-icon> 推荐匹配企业
                    <span class="ud-panel-card__hint">按登记企业测评池与您维度同质度排序</span>
                  </div>
                  <div v-if="matchingEnterprises.length" class="ud-enterprise-match__list">
                    <div v-for="ent in matchingEnterprises" :key="ent.id" class="ud-enterprise-match__item">
                      <div class="ud-enterprise-match__body">
                        <div class="ud-enterprise-match__name">{{ ent.name }}</div>
                        <div class="ud-enterprise-match__meta">{{ ent.matchTypeLabel }} · 匹配度 {{ ent.matchScore }}%</div>
                        <div class="ud-enterprise-match__desc">{{ ent.matchReason }}</div>
                        <div v-if="ent.contactName || ent.contactPhone" class="ud-enterprise-match__contact">
                          <el-icon><User /></el-icon>
                          {{ ent.contactName || '负责人' }}
                          <span v-if="ent.contactPhone" class="ud-enterprise-match__phone">{{ ent.contactPhone }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div v-else class="ud-empty-hint">暂无企业数据</div>
                </div>
                <div v-else class="ud-empty-hint ud-enterprise-match__hint">
                  跨企业人才匹配与负责人直连请在「超级管理后台 → 用户总览」查看。
                </div>
              </div>
            </el-tab-pane>

            <el-tab-pane label="测试记录" name="tests">
              <el-table v-if="testTableData.length" :data="paginatedTests" size="small" max-height="360" class="ud-test-records-table">
                <el-table-column width="52" align="center">
                  <template #default="{ row }">
                    <el-icon class="ud-test-type-icon" :class="testIconClass(row.testType)"><component :is="testIcon(row.testType)" /></el-icon>
                  </template>
                </el-table-column>
                <el-table-column prop="createdAt" label="时间" width="108">
                  <template #default="{ row }">{{ formatDate(row.createdAt) }}</template>
                </el-table-column>
                <el-table-column prop="testType" label="类型" width="100">
                  <template #default="{ row }">{{ formatTestType(row.testType) }}</template>
                </el-table-column>
                <el-table-column v-if="hasTestScopeCol" prop="testScope" label="版本" width="78" align="center">
                  <template #default="{ row }">
                    <el-tag size="small" :type="row.testScope === 'enterprise' ? 'primary' : 'info'">
                      {{ row.testScope === 'enterprise' ? '企业' : '个人' }}
                    </el-tag>
                  </template>
                </el-table-column>
                <el-table-column prop="summary" label="摘要" min-width="120" show-overflow-tooltip />
                <el-table-column label="付费" width="120" align="center">
                  <template #default="{ row }">
                    <el-tag size="small" :type="row.isPaid ? 'success' : 'info'">{{ row.isPaid ? '已付' : '未付' }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="操作" width="72" align="center">
                  <template #default="{ row }">
                    <el-button link type="primary" size="small" @click.stop="$emit('view-test', row)">详情</el-button>
                  </template>
                </el-table-column>
              </el-table>
              <div v-else class="ud-empty-hint">暂无测试记录</div>
              <div class="ud-test-records-pagination" v-if="testTableData.length > testPageSize">
                <el-pagination
                  v-model:current-page="testPage"
                  :page-size="testPageSize"
                  :total="testTableData.length"
                  layout="prev, pager, next, total"
                  small
                />
              </div>
            </el-tab-pane>

            <el-tab-pane label="人像相册" name="photos">
              <div v-if="facePhotos.length" class="ud-photo-gallery">
                <el-image
                  v-for="(url, idx) in facePhotos"
                  :key="url + idx"
                  :src="url"
                  fit="cover"
                  class="ud-photo-gallery__thumb"
                  :preview-src-list="facePhotos"
                />
              </div>
              <div v-else class="ud-empty-hint">暂无人脸分析照片</div>
            </el-tab-pane>
          </el-tabs>
        </main>
      </template>
    </div>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  Aim,
  Calendar,
  Connection,
  DataAnalysis,
  DataLine,
  Document,
  Key,
  OfficeBuilding,
  Phone,
  PieChart,
  Picture,
  Star,
  TrendCharts,
  User
} from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { RadarChart } from 'echarts/charts'
import {
  GridComponent,
  TooltipComponent,
  LegendComponent,
  RadarComponent
} from 'echarts/components'
import VChart from 'vue-echarts'

use([CanvasRenderer, RadarChart, GridComponent, TooltipComponent, LegendComponent, RadarComponent])

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    user: Record<string, any> | null
    loading?: boolean
    showEnterpriseMatch?: boolean
  }>(),
  { loading: false, showEnterpriseMatch: false }
)

defineEmits<{
  'update:modelValue': [v: boolean]
  'view-test': [row: any]
}>()

const udTab = ref('analysis')
const testPage = ref(1)
const testPageSize = 8

watch(
  () => props.modelValue,
  v => {
    if (v) {
      udTab.value = 'analysis'
      testPage.value = 1
    }
  }
)

watch(
  () => props.user?.id,
  () => {
    testPage.value = 1
  }
)

const avatarLetter = computed(() => {
  const n = (props.user?.username || props.user?.nickname || '?').trim()
  return (n.charAt(0) || '?').toUpperCase()
})

const rawTests = computed(() => (props.user?.testList || []) as any[])

const hasTestScopeCol = computed(() => rawTests.value.some(t => t.testScope != null))

const testTableData = computed(() =>
  rawTests.value.map(t => ({
    ...t,
    summary: extractTestSummary(t)
  }))
)

const paginatedTests = computed(() => {
  const start = (testPage.value - 1) * testPageSize
  return testTableData.value.slice(start, start + testPageSize)
})

const matchingEnterprises = computed(() => {
  const m = props.user?.matchingEnterprises
  return Array.isArray(m) ? m : []
})

function shortOrDash(s: string | undefined, max = 6) {
  if (!s) return '—'
  return s.length > max ? s.slice(0, max) + '…' : s
}

function formatDate(date: number | string | null | undefined) {
  if (date == null) return '-'
  if (typeof date === 'number') {
    const d = new Date(date * 1000)
    return (
      d.getFullYear() +
      '-' +
      String(d.getMonth() + 1).padStart(2, '0') +
      '-' +
      String(d.getDate()).padStart(2, '0')
    )
  }
  return String(date)
}

function formatTestType(testType: string) {
  const t = (testType || '').toLowerCase()
  if (!t) return '-'
  if (t === 'mbti') return 'MBTI'
  if (t === 'disc') return 'DISC'
  if (t === 'pdp') return 'PDP'
  if (t === 'face' || t === 'ai') return '人脸'
  if (t === 'resume') return '简历'
  return testType
}

function extractTestSummary(test: any): string {
  const raw = test?.result
  if (typeof raw !== 'string' || !raw) return ''
  let data: any
  try {
    data = JSON.parse(raw)
  } catch {
    return raw
  }
  if (!data || typeof data !== 'object') return raw
  const type = (test?.testType || '').toLowerCase()
  if (type === 'mbti') return String(data.mbtiType ?? data.type ?? data.result ?? '')
  if (type === 'disc') {
    const desc = data.description?.type
    if (typeof desc === 'string' && desc) return desc
    if (data.dominantType) return String(data.dominantType) + '型'
    return String(data.disc ?? '')
  }
  if (type === 'pdp') {
    const desc = data.description?.type
    if (typeof desc === 'string' && desc) return desc
    if (data.dominantType) return String(data.dominantType)
    return String(data.pdp ?? '')
  }
  if (type === 'face' || type === 'ai') return '人脸分析'
  if (type === 'resume') {
    const c = String(data.content ?? '')
    return c ? c.substring(0, 24).replace(/\n/g, ' ') + (c.length > 24 ? '…' : '') : '简历'
  }
  return String(data.type ?? data.result ?? '')
}

function parseTestResult(test: any): any {
  const raw = test?.result
  if (typeof raw !== 'string' || !raw) return null
  try {
    const data = JSON.parse(raw)
    return data && typeof data === 'object' ? data : null
  } catch {
    return null
  }
}

function latestTest(type: string) {
  const t = type.toLowerCase()
  for (const r of rawTests.value) {
    if ((r.testType || '').toLowerCase() === t) return r
  }
  return null
}

const latestMbti = computed(() => parseTestResult(latestTest('mbti')))
const latestPdp = computed(() => parseTestResult(latestTest('pdp')))
const latestDisc = computed(() => parseTestResult(latestTest('disc')))
const latestFace = computed(() => parseTestResult(latestTest('face')) || parseTestResult(latestTest('ai')))
const latestResume = computed(() => parseTestResult(latestTest('resume')))

const mbtiSummary = computed(() => {
  const p = latestMbti.value
  if (!p) return ''
  const type = String(p.mbtiType ?? p.description?.type ?? p.type ?? '')
  const name = String(p.description?.name ?? '')
  return [type, name].filter(Boolean).join(' · ')
})

const profileTags = computed(() => {
  const u = props.user || {}
  const tags: string[] = []
  if (u.mbtiType) tags.push('MBTI-' + u.mbtiType)
  if (u.pdpType) tags.push('PDP-' + u.pdpType)
  if (u.discType) tags.push('DISC-' + u.discType)
  if (u.faceMbtiType) tags.push('面相MBTI')
  return tags
})

const gallupList = computed(() => {
  const face = latestFace.value
  if (face?.gallupTop3 && Array.isArray(face.gallupTop3)) return face.gallupTop3.slice(0, 5)
  const res = latestResume.value
  if (res?.gallupTop3 && Array.isArray(res.gallupTop3)) return res.gallupTop3.slice(0, 5)
  const m = latestMbti.value
  const st = m?.description?.strengths
  if (Array.isArray(st) && st.length) return st.slice(0, 3)
  return []
})

function mbtiLetterScores(typeStr: string): number[] {
  const order = [
    ['E', 'I'],
    ['S', 'N'],
    ['T', 'F'],
    ['J', 'P']
  ]
  const t = typeStr.toUpperCase().replace(/[^A-Z]/g, '')
  if (t.length < 4) return [50, 50, 50, 50]
  const out: number[] = []
  for (let i = 0; i < 4; i++) {
    const ch = t[i]
    const [a, b] = order[i]
    out.push(ch === a ? 78 : ch === b ? 72 : 55)
  }
  return out
}

const mbtiRadarOption = computed(() => {
  const p = latestMbti.value
  const typeStr = String(p?.mbtiType ?? p?.description?.type ?? p?.type ?? props.user?.mbtiType ?? '')
  if (!typeStr || typeStr.length < 2) return null
  const vals = mbtiLetterScores(typeStr)
  return {
    color: ['#7c3aed'],
    radar: {
      indicator: [
        { name: 'E 能量', max: 100 },
        { name: 'N 信息', max: 100 },
        { name: 'T 决策', max: 100 },
        { name: 'J 生活方式', max: 100 }
      ],
      radius: 58,
      splitNumber: 4,
      axisName: { fontSize: 10, color: '#6b7280' }
    },
    series: [
      {
        type: 'radar',
        data: [{ value: vals, name: 'MBTI' }],
        areaStyle: { opacity: 0.12 }
      }
    ],
    tooltip: { trigger: 'item' }
  }
})

const PDP_KEYS = ['Tiger', 'Peacock', 'Owl', 'Koala', 'Chameleon'] as const
const PDP_LABELS: Record<string, string> = {
  Tiger: '虎',
  Peacock: '孔雀',
  Owl: '猫头鹰',
  Koala: '考拉',
  Chameleon: '变色龙'
}

const pdpRadarOption = computed(() => {
  const p = latestPdp.value
  if (!p) return null
  const pct = p.percentages || {}
  const vals = PDP_KEYS.map(k => Number(pct[k] ?? pct[k.toLowerCase()] ?? 0) || 0)
  if (vals.every(v => v === 0)) {
    const dom = String(p.dominantType ?? p.description?.type ?? '')
    if (!dom) return null
    const idx = ['老虎', '孔雀', '猫头鹰', '考拉', '变色龙'].findIndex(x => dom.includes(x))
    if (idx < 0) return null
    const v2 = [15, 15, 15, 15, 15]
    v2[idx] = 55
    return buildPdpChart(v2)
  }
  return buildPdpChart(vals)
})

function buildPdpChart(values: number[]) {
  const indicators = PDP_KEYS.map(k => ({ name: PDP_LABELS[k] || k, max: 100 }))
  return {
    color: ['#d97706'],
    radar: {
      indicator: indicators,
      radius: 58,
      axisName: { fontSize: 10, color: '#6b7280' }
    },
    series: [{ type: 'radar', data: [{ value: values, name: 'PDP' }], areaStyle: { opacity: 0.1 } }],
    tooltip: { trigger: 'item' }
  }
}

const discRadarOption = computed(() => {
  const p = latestDisc.value
  if (!p) return null
  const pct = p.percentages || {}
  const d = Number(pct.D ?? pct.d ?? 0)
  const i = Number(pct.I ?? pct.i ?? 0)
  const s = Number(pct.S ?? pct.s ?? 0)
  const c = Number(pct.C ?? pct.c ?? 0)
  if (d + i + s + c < 1) return null
  return {
    color: ['#2563eb'],
    radar: {
      indicator: [
        { name: 'D', max: 100 },
        { name: 'I', max: 100 },
        { name: 'S', max: 100 },
        { name: 'C', max: 100 }
      ],
      radius: 58,
      axisName: { fontSize: 11, color: '#6b7280' }
    },
    series: [{ type: 'radar', data: [{ value: [d, i, s, c], name: 'DISC' }], areaStyle: { opacity: 0.1 } }],
    tooltip: { trigger: 'item' }
  }
})

const roleFitList = computed(() => {
  const res = latestResume.value
  const fromResume = res?.hrView?.roleRecommend?.bestFit
  let names: string[] = []
  if (Array.isArray(fromResume) && fromResume.length) {
    names = fromResume.slice(0, 6)
  } else {
    const careers = latestMbti.value?.description?.careers
    if (Array.isArray(careers) && careers.length) {
      names = careers.slice(0, 6)
    } else {
      names = ['增长', '用户运营', '内容运营', '产品', '研发', '设计']
    }
  }
  return names.map((name, i) => ({
    name,
    pct: 42 + ((i * 17 + (props.user?.id || 0)) % 37)
  }))
})

const facePhotos = computed(() => {
  const f = latestFace.value
  const urls = f?.photoUrls
  return Array.isArray(urls) ? urls : []
})

function testIcon(testType: string) {
  const t = (testType || '').toLowerCase()
  if (t === 'mbti') return Aim
  if (t === 'pdp') return TrendCharts
  if (t === 'disc') return PieChart
  if (t === 'face' || t === 'ai') return Picture
  if (t === 'resume') return Document
  return DataLine
}

function testIconClass(testType: string) {
  const t = (testType || '').toLowerCase()
  if (t === 'mbti') return 'tic-mbti'
  if (t === 'pdp') return 'tic-pdp'
  if (t === 'disc') return 'tic-disc'
  if (t === 'face' || t === 'ai') return 'tic-face'
  return 'tic-other'
}

function copyText(text: string, msg: string) {
  if (!text) return
  void navigator.clipboard.writeText(text).then(
    () => ElMessage.success(msg),
    () => ElMessage.error('复制失败')
  )
}

function openDial(phone: string) {
  if (!phone) return
  window.location.href = 'tel:' + phone
}

function openMail(email: string) {
  if (!email) return
  window.location.href = 'mailto:' + email
}
</script>

<style scoped lang="scss">
.user-detail-dialog {
  :deep(.el-dialog__body) {
    padding: 8px 16px 16px;
    max-height: 86vh;
    overflow: hidden;
  }
}

/* 整行布局：侧栏与主区之间保留视觉空隙（与设计稿一致约 16～24px） */
.ud-layout {
  display: flex;
  align-items: stretch;
  gap: 20px;
  min-height: 200px;
  max-height: 78vh;
}

.ud-sidebar {
  width: 200px;
  flex-shrink: 0;
  background: linear-gradient(180deg, #faf5ff 0%, #fff 40%);
  border: 1px solid #ede9fe;
  border-radius: 10px;
  padding: 12px;
}

.ud-profile-avatar {
  text-align: center;
  margin-bottom: 10px;
}

.ud-profile-avatar__img {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  object-fit: cover;
}

.ud-profile-avatar__letter {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #7c3aed;
  color: #fff;
  font-size: 22px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.ud-profile-name {
  margin-top: 6px;
  font-weight: 700;
  font-size: 14px;
  color: #111827;
}

.ud-profile-meta__row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #4b5563;
  margin-bottom: 6px;

  .el-icon {
    color: #a855f7;
  }
}

.ud-quick-stats {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
  margin-top: 10px;
}

.ud-quick-stats__tile {
  background: #fff;
  border-radius: 8px;
  padding: 6px 8px;
  font-size: 11px;
  color: #374151;
  display: flex;
  align-items: center;
  gap: 4px;
  border: 1px solid #f3e8ff;

  .el-icon {
    color: #7c3aed;
    font-size: 14px;
  }
}

.ud-dimension-tags {
  margin-top: 12px;
}

.ud-dimension-tags__title {
  font-size: 11px;
  color: #9ca3af;
  margin-bottom: 6px;
}

.ud-dimension-tags__item {
  margin: 0 4px 4px 0;
}

.ud-main-pane {
  flex: 1;
  min-width: 0;
  border: 1px solid #f3f4f6;
  border-radius: 10px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.ud-tabs {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;

  :deep(.el-tabs__content) {
    flex: 1;
    overflow: hidden;
  }

  :deep(.el-tab-pane) {
    height: 100%;
  }
}

.ud-main-scroll {
  max-height: calc(78vh - 120px);
  overflow-y: auto;
  padding-right: 4px;
}

.ud-chart-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}

.ud-chart-panel {
  background: #fafafa;
  border-radius: 8px;
  padding: 6px 4px 4px;
  text-align: center;
}

.ud-chart-heading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 2px;

  .el-icon {
    color: #7c3aed;
  }
}

.ud-chart-echart {
  height: 150px;
  width: 100%;
}

.ud-chart-placeholder {
  height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  color: #9ca3af;
}

.ud-chart-footnote {
  font-size: 11px;
  color: #6b7280;
  padding: 0 4px 4px;
}

.ud-dual-cards {
  display: flex;
  gap: 10px;
  margin-top: 10px;
}

.ud-panel-card {
  background: #fff;
  border: 1px solid #f3f4f6;
  border-radius: 8px;
  padding: 8px 10px;
  flex: 1;
  min-width: 0;
}

.ud-panel-card--wide {
  flex: 1.2;
}

.ud-panel-card__head {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 8px;

  .el-icon {
    color: #7c3aed;
  }
}

.ud-panel-card__hint {
  font-weight: 400;
  font-size: 11px;
  color: #9ca3af;
  margin-left: 4px;
}

.ud-gallup-list__item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  margin-bottom: 6px;
}

.ud-gallup-list__index {
  width: 18px;
  height: 18px;
  border-radius: 4px;
  background: #ede9fe;
  color: #5b21b6;
  font-size: 11px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.ud-rolefit-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ud-rolefit-list__row {
  display: grid;
  grid-template-columns: 72px 1fr 36px;
  gap: 8px;
  align-items: center;
  font-size: 11px;
}

.ud-rolefit-list__name {
  color: #4b5563;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ud-rolefit-list__percent {
  text-align: right;
  color: #7c3aed;
  font-weight: 600;
}

.ud-enterprise-match {
  margin-top: 10px;
}

.ud-enterprise-match__list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ud-enterprise-match__item {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  align-items: flex-start;
  padding: 8px;
  background: #fafafa;
  border-radius: 8px;
}

.ud-enterprise-match__name {
  font-weight: 600;
  font-size: 13px;
  color: #111827;
}

.ud-enterprise-match__meta {
  font-size: 11px;
  color: #7c3aed;
  margin-top: 2px;
}

.ud-enterprise-match__desc {
  font-size: 11px;
  color: #6b7280;
  margin-top: 4px;
  line-height: 1.4;
}

.ud-enterprise-match__contact {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 6px;
  font-size: 12px;
  color: #374151;

  .el-icon {
    color: #9ca3af;
  }
}

.ud-enterprise-match__phone {
  font-family: ui-monospace, monospace;
  color: #111827;
}

.ud-enterprise-match__actions {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex-shrink: 0;
}

.ud-empty-hint {
  font-size: 12px;
  color: #9ca3af;
  padding: 8px 0;
}

.ud-enterprise-match__hint {
  margin-top: 8px;
  line-height: 1.5;
}

.ud-test-records-table {
  width: 100%;
}

.ud-test-type-icon {
  font-size: 18px;
}
.tic-mbti {
  color: #4f46e5;
}
.tic-pdp {
  color: #d97706;
}
.tic-disc {
  color: #2563eb;
}
.tic-face {
  color: #059669;
}
.tic-other {
  color: #6b7280;
}

.ud-test-records-pagination {
  margin-top: 8px;
  display: flex;
  justify-content: center;
}

.ud-photo-gallery {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.ud-photo-gallery__thumb {
  width: 100px;
  height: 100px;
  border-radius: 8px;
}

@media (max-width: 900px) {
  .ud-layout {
    flex-direction: column;
    max-height: none;
  }
  .ud-sidebar {
    width: 100%;
  }
  .ud-chart-row {
    grid-template-columns: 1fr;
  }
}
</style>
