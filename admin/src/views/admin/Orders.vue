<template>
  <div class="page-container" :class="{ 'is-embedded': embedded }">
    <div v-if="!embedded" class="page-header">
      <div class="header-left">
        <h2>成交与订单</h2>
        <p class="subtitle">支付订单、成交状态与关联测试数据</p>
      </div>
      <div class="header-actions">
        <el-button @click="loadOrders" class="refresh-btn">
          <el-icon><Refresh /></el-icon>
          <span>刷新</span>
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-info">
          <div class="stat-label">总订单数</div>
          <div class="stat-value">{{ total }}</div>
        </div>
        <div class="stat-icon blue">
          <el-icon><ShoppingCart /></el-icon>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-info">
          <div class="stat-label">已完成/已支付</div>
          <div class="stat-value">{{ paidCompletedTotal }}</div>
        </div>
        <div class="stat-icon green">
          <el-icon><Box /></el-icon>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-info">
          <div class="stat-label">总收入</div>
          <div class="stat-value">{{ totalIncomeDisplay }}</div>
        </div>
        <div class="stat-icon purple">
          <el-icon><Money /></el-icon>
        </div>
      </div>
    </div>

    <div class="content-card">
      <div class="toolbar">
        <div class="toolbar-left">
          <el-input
            v-model="searchQuery"
            placeholder="搜索订单号/用户ID/昵称/手机号..."
            clearable
            class="search-input"
            @clear="loadOrders"
            @keyup.enter="loadOrders"
          >
            <template #prefix>
              <el-icon><Search /></el-icon>
            </template>
          </el-input>
          <el-button type="primary" @click="loadOrders">搜索</el-button>
        </div>

        <div class="toolbar-right">
          <div class="filter-group">
            <div
              v-for="item in statusOptions"
              :key="item.value"
              :class="['filter-item', { active: statusFilter === item.value }]"
              @click="statusFilter = item.value; currentPage = 1; loadOrders()"
            >
              {{ item.label }}
            </div>
          </div>

          <div class="filter-divider"></div>

          <div class="filter-group">
            <div
              v-for="item in productOptions"
              :key="item.value"
              :class="['filter-item', { active: productFilter === item.value }]"
              @click="productFilter = item.value; currentPage = 1; loadOrders()"
            >
              {{ item.label }}
            </div>
          </div>
        </div>
      </div>

      <el-table :data="orders" style="width: 100%" v-loading="loading" class="custom-table">
        <el-table-column label="订单号" width="180">
          <template #default="{ row }">
            <span class="order-id">{{ row.orderNo }}</span>
          </template>
        </el-table-column>
        <el-table-column label="用户" min-width="140">
          <template #default="{ row }">
            <div class="user-cell">
              <div class="user-name">{{ row.userName || '—' }}</div>
              <div class="user-phone">{{ formatPhone(row.userPhone) }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="产品" min-width="180">
          <template #default="{ row }">
            <div class="product-cell">
              <div class="product-main">{{ row.productTitle || productTypeLabel(row.productType) }}</div>
              <div class="product-sub" v-if="row.productTitle">
                {{ productTypeLabel(row.productType) }}
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="金额" width="100" align="right">
          <template #default="{ row }">
            <span class="amount-cell">{{ formatAmount(row.amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="支付方式" width="100" align="center">
          <template #default="{ row }">
            {{ payMethodLabel(row.payMethod) }}
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="关联测试" min-width="200">
          <template #default="{ row }">
            <div class="test-data-cell">
              <template v-if="row.testData && row.testData.length">
                <div
                  v-for="(t, i) in row.testData"
                  :key="t.id || i"
                  class="test-item"
                >
                  <span class="test-type">{{ testTypeLabel(t.testType) }}</span>
                  <span class="test-summary">{{ t.resultSummary || '—' }}</span>
                </div>
              </template>
              <span v-else class="no-test">—</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="160">
          <template #default="{ row }">
            {{ formatDate(row.createdAt) }}
          </template>
        </el-table-column>
        <el-table-column label="支付时间" width="160">
          <template #default="{ row }">
            {{ row.payTime ? formatDate(row.payTime) : '—' }}
          </template>
        </el-table-column>
      </el-table>

      <div class="empty-state" v-if="orders.length === 0 && !loading">
        <span>暂无订单数据</span>
      </div>
      <div class="empty-state" v-if="orders.length === 0 && loading">
        <div class="loading-spinner"></div>
        <p class="loading-text">加载中...</p>
      </div>

      <div class="pagination-wrap" v-if="total > 0">
        <el-pagination
          v-model:current-page="currentPage"
          :page-size="pageSize"
          :total="total"
          layout="prev, pager, next, total"
          @current-change="loadOrders"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Refresh, ShoppingCart, Box, Money, Search } from '@element-plus/icons-vue'
import { request } from '@/utils/request'

const props = withDefaults(
  defineProps<{ embedded?: boolean; ordersApiPath?: string }>(),
  { embedded: false, ordersApiPath: '/admin/orders' }
)

const loading = ref(false)
const searchQuery = ref('')
const statusFilter = ref('')
const productFilter = ref('')
const orders = ref<any[]>([])
const total = ref(0)
const paidCompletedTotal = ref(0)
const totalRevenueFen = ref(0)
const currentPage = ref(1)
const pageSize = 20

const statusOptions = [
  { label: '全部', value: '' },
  { label: '待支付', value: 'pending' },
  { label: '已支付', value: 'paid' },
  { label: '已完成', value: 'completed' },
  { label: '已取消', value: 'cancelled' },
  { label: '已退款', value: 'refunded' },
  { label: '失败', value: 'failed' }
]

const productOptions = [
  { label: '全部产品', value: '' },
  { label: 'AI人脸分析', value: 'face' },
  { label: 'MBTI', value: 'mbti' },
  { label: 'DISC', value: 'disc' },
  { label: 'PDP', value: 'pdp' },
  { label: '完整报告', value: 'report' }
]

const totalIncomeDisplay = computed(() => {
  const n = totalRevenueFen.value
  if (!Number.isFinite(n)) return '¥0.00'
  return '¥' + (n / 100).toFixed(2)
})

function formatPhone(phone: string) {
  if (!phone) return '—'
  if (phone.length === 11) return phone.substring(0, 3) + '****' + phone.substring(7)
  return phone
}

function formatDate(ts: number | null | undefined) {
  if (ts == null) return '—'
  const d = new Date(ts * 1000)
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') + ' ' +
    String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0')
}

function formatAmount(amountFen: number | string | null | undefined) {
  if (amountFen == null) return '¥0.00'
  const n = typeof amountFen === 'number' ? amountFen : Number(amountFen)
  if (!Number.isFinite(n)) return '¥0.00'
  return '¥' + (n / 100).toFixed(2)
}

function productTypeLabel(type: string) {
  const map: Record<string, string> = {
    face: 'AI人脸分析',
    mbti: 'MBTI',
    disc: 'DISC',
    pdp: 'PDP',
    report: '完整报告'
  }
  return map[type || ''] || type || '—'
}

function testTypeLabel(type: string) {
  const map: Record<string, string> = {
    face: '人脸',
    ai: '人脸',
    mbti: 'MBTI',
    disc: 'DISC',
    pdp: 'PDP'
  }
  return map[(type || '').toLowerCase()] || type || '—'
}

function payMethodLabel(m: string) {
  if (!m) return '—'
  if (m === 'wechat') return '微信支付'
  return m
}

function statusLabel(s: string) {
  const map: Record<string, string> = {
    pending: '待支付',
    paid: '已支付',
    completed: '已完成',
    cancelled: '已取消',
    refunded: '已退款',
    failed: '失败'
  }
  return map[s || ''] || s || '—'
}

function statusTagType(
  s: string
): 'primary' | 'success' | 'warning' | 'info' | 'danger' {
  const map: Record<string, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
    pending: 'warning',
    paid: 'success',
    completed: 'success',
    cancelled: 'info',
    refunded: 'info',
    failed: 'danger'
  }
  return map[s || ''] || 'info'
}

async function loadOrders() {
  loading.value = true
  try {
    const res: any = await request.get(props.ordersApiPath, {
      params: {
        page: currentPage.value,
        pageSize,
        keyword: searchQuery.value,
        status: statusFilter.value,
        productType: productFilter.value
      }
    })
    const list = res.data?.list ?? res?.list ?? []
    orders.value = list
    total.value = res.data?.total ?? res?.total ?? 0
    paidCompletedTotal.value = Number(res.data?.paidCompletedCount ?? 0) || 0
    totalRevenueFen.value = Number(res.data?.totalRevenueFen ?? 0) || 0
  } catch {
    orders.value = []
    total.value = 0
    paidCompletedTotal.value = 0
    totalRevenueFen.value = 0
  } finally {
    loading.value = false
  }
}

onMounted(() => loadOrders())
</script>

<style scoped lang="scss">
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;

  .header-left {
    h2 {
      font-size: 22px;
      font-weight: 700;
      color: #111827;
      margin: 0 0 4px 0;
    }
    .subtitle {
      font-size: 13px;
      color: #6b7280;
      margin: 0;
    }
  }

  .refresh-btn {
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 13px;
    color: #374151;
    height: 34px;
    display: flex;
    align-items: center;
    gap: 6px;
    
    &:hover {
      background-color: #f9fafb;
      border-color: #d1d5db;
    }
  }
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  background: #fff;
  border-radius: 10px;
  padding: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);

  .stat-info {
    .stat-label {
      font-size: 13px;
      color: #6b7280;
      margin-bottom: 6px;
    }
    .stat-value {
      font-size: 24px;
      font-weight: 700;
      color: #111827;
      line-height: 1;
    }
    .realtime-tag {
      display: flex;
      align-items: center;
      gap: 4px;
      font-size: 12px;
      color: #22c55e;
      margin-top: 8px;
      font-weight: 500;
    }
  }

  .stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;

    &.blue { background-color: #eff6ff; color: #3b82f6; }
    &.green { background-color: #f0fdf4; color: #22c55e; }
    &.purple { background-color: #faf5ff; color: #a855f7; }
    &.orange { background-color: #fffbeb; color: #f59e0b; }
  }
}

.content-card {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.toolbar {
  padding: 16px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid #f3f4f6;
  gap: 20px;

  .toolbar-left {
    flex: 1;
    .search-input {
      max-width: 320px;
      :deep(.el-input__wrapper) {
        border-radius: 6px;
        background-color: #f9fafb;
        box-shadow: none;
        border: 1px solid #e5e7eb;
        
        &.is-focus {
          border-color: #7c3aed;
          background-color: #fff;
        }
      }
    }
  }

  .toolbar-right {
    display: flex;
    align-items: center;
    gap: 16px;

    .filter-divider {
      width: 1px;
      height: 20px;
      background-color: #e5e7eb;
    }

    .filter-group {
      display: flex;
      background-color: #f3f4f6;
      padding: 3px;
      border-radius: 6px;
      gap: 2px;

      .filter-item {
        padding: 4px 12px;
        font-size: 12px;
        color: #6b7280;
        cursor: pointer;
        border-radius: 4px;
        transition: all 0.2s;
        white-space: nowrap;

        &:hover {
          color: #111827;
        }

        &.active {
          background-color: #7c3aed;
          color: #fff;
          font-weight: 500;
        }
      }
    }
  }
}

.custom-table {
  :deep(.el-table__header) {
    th {
      background-color: #f9fafb;
      color: #6b7280;
      font-weight: 500;
      font-size: 13px;
      padding: 12px 0;
    }
  }

  .order-id {
    font-size: 13px;
    color: #374151;
  }

  .user-cell {
    .user-name {
      font-size: 13px;
      color: #111827;
      font-weight: 500;
    }
    .user-phone {
      font-size: 12px;
      color: #6b7280;
      margin-top: 2px;
    }
  }

  .amount-cell {
    font-size: 13px;
    color: #111827;
    font-weight: 500;
  }

  .test-data-cell {
    font-size: 12px;
    .test-item {
      display: flex;
      align-items: baseline;
      gap: 8px;
      margin-bottom: 4px;
      &:last-child {
        margin-bottom: 0;
      }
    }
    .test-type {
      flex-shrink: 0;
      color: #7c3aed;
      font-weight: 500;
    }
    .test-summary {
      color: #374151;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      max-width: 140px;
    }
    .no-test {
      color: #9ca3af;
    }
  }
}

.pagination-wrap {
  padding: 16px 20px;
  display: flex;
  justify-content: flex-end;
}

.empty-state {
  padding: 80px 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #9ca3af;
  font-size: 14px;

  .loading-spinner {
    width: 24px;
    height: 24px;
    border: 2px solid #f3f4f6;
    border-top: 2px solid #7c3aed;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 12px;
  }

  .loading-text {
    margin: 0;
    font-size: 13px;
    color: #6b7280;
  }
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

@media (max-width: 1200px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .toolbar {
    flex-direction: column;
    align-items: flex-start;
    .toolbar-right {
      width: 100%;
      overflow-x: auto;
      padding-bottom: 4px;
    }
  }
}

.page-container.is-embedded {
  min-height: auto;
}
</style>
