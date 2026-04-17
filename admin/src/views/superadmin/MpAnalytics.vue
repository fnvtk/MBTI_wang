<template>
  <div class="mp-analytics" :class="{ 'is-embedded': embedded }">
    <el-alert
      v-if="tableMissing"
      type="warning"
      show-icon
      :closable="false"
      class="setup-alert"
      title="埋点数据表未就绪"
      description="请在数据库执行 api/database/migrations/add_mp_analytics_events.sql（表前缀需与 .env 中 DATABASE_PREFIX 一致），执行后刷新本页。"
    />
    <div class="page-head" v-if="!embedded">
      <h2>小程序埋点</h2>
      <p class="sub">仅超级管理员可见；统计来自小程序上报的 page_view 与业务点击事件。</p>
      <div class="toolbar">
        <span class="label">统计范围</span>
        <el-select v-model="days" style="width: 120px" @change="loadAll">
          <el-option :value="7" label="近 7 天" />
          <el-option :value="14" label="近 14 天" />
          <el-option :value="30" label="近 30 天" />
        </el-select>
        <el-button type="primary" :loading="loading" @click="loadAll">刷新</el-button>
      </div>
    </div>
    <div v-else class="embedded-toolbar">
      <span class="hint">监督各端上报行为；page_view 与业务点击</span>
      <div class="toolbar">
        <span class="label">统计范围</span>
        <el-select v-model="days" style="width: 120px" @change="loadAll">
          <el-option :value="7" label="近 7 天" />
          <el-option :value="14" label="近 14 天" />
          <el-option :value="30" label="近 30 天" />
        </el-select>
        <el-button type="primary" :loading="loading" @click="loadAll">刷新</el-button>
      </div>
    </div>

    <el-card class="card-block" shadow="never">
      <template #header>
        <span>分享漏斗（结果页→分享→登录→付费→分润）</span>
      </template>
      <div class="funnel-wrap" v-loading="loadingFunnel">
        <VChart v-if="funnelData.length" class="funnel-chart" :option="funnelOption" autoresize />
        <div v-else class="empty-hint">暂无漏斗数据</div>
      </div>
    </el-card>

    <el-card class="card-block" shadow="never">
      <template #header>
        <span>事件汇总（共 {{ summaryTotal }} 条记录）</span>
      </template>
      <div class="filter-row">
        <el-select
          v-model="filterEvent"
          clearable
          filterable
          placeholder="按事件筛选（中文名）"
          style="width: 320px"
          @change="onFilterChange"
        >
          <el-option
            v-for="opt in labelOptions"
            :key="opt.value"
            :value="opt.value"
            :label="opt.label"
          />
        </el-select>
      </div>
      <el-table
        v-loading="loading"
        :data="summaryList"
        stripe
        :empty-text="tableMissing ? '表未创建，见上方说明' : '暂无数据（小程序有访问后会出现统计）'"
      >
        <el-table-column prop="eventNameCn" label="事件（中文）" min-width="220">
          <template #default="{ row }">
            <strong>{{ row.eventNameCn || row.eventName }}</strong>
            <div class="props-preview">{{ row.eventName }}</div>
          </template>
        </el-table-column>
        <el-table-column prop="cnt" label="次数" width="120" />
      </el-table>
    </el-card>

    <el-card class="card-block" shadow="never">
      <template #header>
        <span>分享 & 邀请 · 用户榜 TOP {{ shareRows.length }}</span>
      </template>
      <el-table v-loading="loadingShare" :data="shareRows" stripe empty-text="暂无分享记录（需小程序分享或分销绑定后）">
        <el-table-column label="#" width="60">
          <template #default="{ $index }">{{ $index + 1 }}</template>
        </el-table-column>
        <el-table-column label="用户" min-width="180">
          <template #default="{ row }">
            <div style="display:flex;align-items:center;gap:8px;">
              <el-avatar :src="row.avatar" :size="28" v-if="row.avatar" />
              <div>
                <div style="font-weight:600;">{{ row.nickname || '未命名' }}</div>
                <div style="font-size:12px;color:#94a3b8;">ID {{ row.userId }} · {{ row.phone || '无手机号' }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="shareCount" label="分享次数" width="110" align="center" />
        <el-table-column prop="inviteBound" label="邀请人数" width="110" align="center" />
        <el-table-column label="累计分润(元)" width="130" align="center">
          <template #default="{ row }">¥{{ (Number(row.totalCommissionFen || 0) / 100).toFixed(2) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="120">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="openUserJourney(row.userId, row.nickname)">用户旅程</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="pager">
        <el-pagination
          v-model:current-page="sharePage"
          v-model:page-size="sharePageSize"
          :total="shareTotal"
          :page-sizes="[20, 30, 50]"
          layout="total, sizes, prev, pager, next"
          @current-change="loadShareStats"
          @size-change="loadShareStats"
        />
      </div>
    </el-card>

    <el-dialog v-model="journeyVisible" :title="journeyTitle" width="720px">
      <el-table :data="journeyRows" v-loading="journeyLoading" stripe empty-text="暂无记录" max-height="520">
        <el-table-column prop="createdAt" label="时间" width="170" />
        <el-table-column label="事件" min-width="220">
          <template #default="{ row }">
            <strong>{{ row.eventNameCn || row.eventName }}</strong>
            <div class="props-preview">{{ row.eventName }}</div>
          </template>
        </el-table-column>
        <el-table-column prop="pagePath" label="页面" min-width="200" show-overflow-tooltip />
        <el-table-column label="附加" min-width="180">
          <template #default="{ row }">
            <span class="props-preview">{{ formatProps(row.props) }}</span>
          </template>
        </el-table-column>
      </el-table>
    </el-dialog>

    <el-card class="card-block" shadow="never">
      <template #header>
        <span>最近明细</span>
      </template>
      <el-table v-loading="loadingEvents" :data="eventRows" stripe empty-text="暂无明细">
        <el-table-column prop="id" label="ID" width="90" />
        <el-table-column prop="createdAt" label="时间" width="170" />
        <el-table-column label="事件" min-width="220">
          <template #default="{ row }">
            <strong>{{ row.eventNameCn || row.eventName }}</strong>
            <div class="props-preview">{{ row.eventName }}</div>
          </template>
        </el-table-column>
        <el-table-column prop="pagePath" label="页面" min-width="180" show-overflow-tooltip />
        <el-table-column prop="userId" label="用户ID" width="100" />
        <el-table-column label="附加" min-width="200">
          <template #default="{ row }">
            <span class="props-preview">{{ formatProps(row.props) }}</span>
          </template>
        </el-table-column>
      </el-table>
      <div class="pager">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :total="eventTotal"
          :page-sizes="[20, 50, 100]"
          layout="total, sizes, prev, pager, next"
          @current-change="loadEvents"
          @size-change="loadEvents"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { request } from '@/utils/request'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { FunnelChart } from 'echarts/charts'
import { TooltipComponent, LegendComponent, TitleComponent } from 'echarts/components'
import VChart from 'vue-echarts'

use([CanvasRenderer, FunnelChart, TooltipComponent, LegendComponent, TitleComponent])

withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false })

const days = ref(7)
const tableMissing = ref(false)
const loading = ref(false)
const loadingEvents = ref(false)
const summaryList = ref<{ eventName: string; eventNameCn?: string; cnt: number }[]>([])
const summaryTotal = ref(0)
const eventRows = ref<any[]>([])
const eventTotal = ref(0)
const page = ref(1)
const pageSize = ref(50)
const filterEvent = ref('')
const labelOptions = ref<{ value: string; label: string }[]>([])

const loadingShare = ref(false)
const shareRows = ref<any[]>([])
const shareTotal = ref(0)
const sharePage = ref(1)
const sharePageSize = ref(30)

const journeyVisible = ref(false)
const journeyLoading = ref(false)
const journeyRows = ref<any[]>([])
const journeyTitle = ref('')

const loadingFunnel = ref(false)
const funnelData = ref<{ stage: string; value: number }[]>([])

const funnelOption = computed(() => {
  const palette = ['#6366f1', '#8b5cf6', '#ec4899', '#f97316', '#14b8a6']
  return {
    tooltip: { trigger: 'item' as const },
    legend: { bottom: 0 },
    series: [
      {
        name: '分享漏斗',
        type: 'funnel',
        left: '6%',
        right: '6%',
        top: 20,
        bottom: 40,
        width: '88%',
        sort: 'none',
        gap: 4,
        labelLine: { length: 18 },
        itemStyle: {
          borderColor: '#fff',
          borderWidth: 2
        },
        label: {
          show: true,
          position: 'inside',
          color: '#fff',
          fontWeight: 600,
          formatter: (p: any) => `${p.name}\n${p.value}`
        },
        data: funnelData.value.map((item, i) => ({
          name: item.stage,
          value: Number(item.value || 0),
          itemStyle: { color: palette[i % palette.length] }
        }))
      }
    ]
  }
})

function formatProps(p: unknown): string {
  if (p == null) return '—'
  try {
    const s = JSON.stringify(p)
    return s.length > 120 ? s.slice(0, 120) + '…' : s
  } catch {
    return '—'
  }
}

async function loadSummary() {
  loading.value = true
  try {
    const params: Record<string, string | number> = { days: days.value }
    if (filterEvent.value) params.eventName = filterEvent.value
    const res: any = await request.get('/superadmin/analytics/summary', { params })
    summaryList.value = res.data?.list || []
    summaryTotal.value = res.data?.total ?? 0
    tableMissing.value = !!res.data?.tableMissing

    const labels = res.data?.labels
    if (labels && typeof labels === 'object') {
      labelOptions.value = Object.entries(labels).map(([k, v]) => ({
        value: k,
        label: `${v as string}（${k}）`
      }))
    }
  } catch {
    summaryList.value = []
    summaryTotal.value = 0
    tableMissing.value = true
  } finally {
    loading.value = false
  }
}

async function loadEvents() {
  loadingEvents.value = true
  try {
    const params: Record<string, string | number> = {
      days: days.value,
      page: page.value,
      pageSize: pageSize.value
    }
    if (filterEvent.value) params.eventName = filterEvent.value
    const res: any = await request.get('/superadmin/analytics/events', { params })
    eventRows.value = res.data?.list || []
    eventTotal.value = res.data?.total ?? 0
    if (res.data?.tableMissing) {
      tableMissing.value = true
    }
  } catch {
    eventRows.value = []
    eventTotal.value = 0
    tableMissing.value = true
  } finally {
    loadingEvents.value = false
  }
}

async function loadShareStats() {
  loadingShare.value = true
  try {
    const res: any = await request.get('/superadmin/analytics/share-stats', {
      params: { days: days.value, page: sharePage.value, pageSize: sharePageSize.value }
    })
    shareRows.value = res.data?.list || []
    shareTotal.value = res.data?.total ?? 0
  } catch {
    shareRows.value = []
    shareTotal.value = 0
  } finally {
    loadingShare.value = false
  }
}

async function openUserJourney(userId: number, nickname: string) {
  journeyTitle.value = `用户旅程 · ${nickname || '未命名'}（ID ${userId}）`
  journeyVisible.value = true
  journeyLoading.value = true
  journeyRows.value = []
  try {
    const res: any = await request.get('/superadmin/analytics/user-journey', {
      params: { userId, days: days.value }
    })
    journeyRows.value = res.data?.list || []
  } finally {
    journeyLoading.value = false
  }
}

async function loadShareFunnel() {
  loadingFunnel.value = true
  try {
    const res: any = await request.get('/superadmin/analytics/share-funnel', {
      params: { days: days.value }
    })
    funnelData.value = res.data?.funnel || []
  } catch {
    funnelData.value = []
  } finally {
    loadingFunnel.value = false
  }
}

async function loadAll() {
  page.value = 1
  sharePage.value = 1
  await loadSummary()
  await loadEvents()
  await loadShareStats()
  await loadShareFunnel()
}

function onFilterChange() {
  page.value = 1
  void loadSummary()
  void loadEvents()
}

onMounted(() => {
  loadAll()
})
</script>

<style scoped lang="scss">
.setup-alert {
  margin-bottom: 16px;
}
.filter-row {
  display: flex;
  gap: 12px;
  margin-bottom: 12px;
}
.props-preview {
  font-size: 12px;
  color: #94a3b8;
}
.funnel-wrap {
  width: 100%;
  min-height: 340px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.funnel-chart {
  width: 100%;
  height: 360px;
}
.empty-hint {
  color: #94a3b8;
  font-size: 13px;
  padding: 48px 0;
}
.mp-analytics {
  padding: 24px;
  max-width: 1200px;
}
.mp-analytics.is-embedded {
  padding: 0;
  max-width: none;
}

.embedded-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;

  .hint {
    font-size: 13px;
    color: #6b7280;
    line-height: 1.4;
  }
}

.page-head {
  margin-bottom: 20px;
  h2 {
    margin: 0 0 8px;
    font-size: 22px;
    font-weight: 700;
    color: #111827;
  }
  .sub {
    margin: 0 0 16px;
    font-size: 14px;
    color: #6b7280;
  }
}
.toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  .label {
    font-size: 14px;
    color: #374151;
  }
}
.card-block {
  margin-bottom: 20px;
  border-radius: 12px;
}
.pager {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}
.props-preview {
  font-size: 12px;
  color: #6b7280;
  word-break: break-all;
}
</style>
