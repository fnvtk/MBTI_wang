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
        <span>事件汇总（共 {{ summaryTotal }} 条记录）</span>
      </template>
      <el-table
        v-loading="loading"
        :data="summaryList"
        stripe
        :empty-text="tableMissing ? '表未创建，见上方说明' : '暂无数据（小程序有访问后会出现统计）'"
      >
        <el-table-column prop="eventName" label="事件名" min-width="200" />
        <el-table-column prop="cnt" label="次数" width="120" />
      </el-table>
    </el-card>

    <el-card class="card-block" shadow="never">
      <template #header>
        <span>最近明细</span>
      </template>
      <el-table v-loading="loadingEvents" :data="eventRows" stripe empty-text="暂无明细">
        <el-table-column prop="id" label="ID" width="90" />
        <el-table-column prop="createdAt" label="时间" width="170" />
        <el-table-column prop="eventName" label="事件" min-width="140" />
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
import { ref, onMounted } from 'vue'
import { request } from '@/utils/request'

withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false })

const days = ref(7)
const tableMissing = ref(false)
const loading = ref(false)
const loadingEvents = ref(false)
const summaryList = ref<{ eventName: string; cnt: number }[]>([])
const summaryTotal = ref(0)
const eventRows = ref<any[]>([])
const eventTotal = ref(0)
const page = ref(1)
const pageSize = ref(50)

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
    const res: any = await request.get('/superadmin/analytics/summary', {
      params: { days: days.value }
    })
    summaryList.value = res.data?.list || []
    summaryTotal.value = res.data?.total ?? 0
    tableMissing.value = !!res.data?.tableMissing
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
    const res: any = await request.get('/superadmin/analytics/events', {
      params: {
        days: days.value,
        page: page.value,
        pageSize: pageSize.value
      }
    })
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

async function loadAll() {
  page.value = 1
  await loadSummary()
  await loadEvents()
}

onMounted(() => {
  loadAll()
})
</script>

<style scoped lang="scss">
.setup-alert {
  margin-bottom: 16px;
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
