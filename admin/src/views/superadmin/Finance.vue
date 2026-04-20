<template>
  <div class="page-container" :class="{ 'is-embedded': embedded }">
    <!-- 页面标题 -->
    <div class="page-header" :class="{ 'header-embedded': embedded }">
      <div v-if="!embedded" class="header-left">
        <h2>财务管理</h2>
        <p class="subtitle">收入、成本及利润分析</p>
      </div>
      <div class="header-actions">
        <el-button variant="outline" size="small" @click="exportData">
          <el-icon class="mr-1"><Download /></el-icon>导出报表
        </el-button>
      </div>
    </div>

    <!-- 无已支付订单时的说明 -->
    <el-alert
      v-if="!overviewLoading && financialOverview.paidOrderCount === 0"
      type="info"
      show-icon
      class="zero-hint"
      title="当前暂无已支付订单，因此收入/成本/利润均为 0。"
    >
      <template #default>
        请确认：1）小程序用户是否已完成支付；2）支付成功后是否调用了「支付通知」接口（/api/payment/notify）以将订单状态更新为「已支付」。只有状态为「已支付」或「已完成」的订单才会计入收入。
      </template>
    </el-alert>

    <!-- 核心财务指标（金额：元，接口返回分需 /100） -->
    <div class="stats-grid" v-loading="overviewLoading">
      <div class="stat-card">
        <div class="stat-info">
          <div class="stat-label">总收入</div>
          <div class="stat-value">{{ formatMoneyYuan(fenToYuan(financialOverview.totalRevenue)) }}</div>
          <div class="stat-trend">
            <el-icon class="trend-icon"><ArrowUp /></el-icon>
            <span class="trend-text positive">本月 {{ formatMoneyYuan(fenToYuan(financialOverview.monthRevenue)) }}</span>
          </div>
        </div>
        <div class="stat-icon green">
          <el-icon><Money /></el-icon>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-info">
          <div class="stat-label">总成本（估算）</div>
          <div class="stat-value">{{ formatMoneyYuan(fenToYuan(financialOverview.totalCost)) }}</div>
          <div class="stat-trend">
            <span class="trend-text">本月 {{ formatMoneyYuan(fenToYuan(financialOverview.monthCost)) }}</span>
          </div>
        </div>
        <div class="stat-icon orange">
          <el-icon><TrendCharts /></el-icon>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-info">
          <div class="stat-label">净利润</div>
          <div class="stat-value">{{ formatMoneyYuan(fenToYuan(financialOverview.netProfit)) }}</div>
          <div class="stat-trend">
            <el-icon v-if="financialOverview.monthGrowth >= 0" class="trend-icon"><ArrowUp /></el-icon>
            <el-icon v-else class="trend-icon down"><Bottom /></el-icon>
            <span :class="['trend-text', financialOverview.monthGrowth >= 0 ? 'positive' : 'negative']">
              {{ financialOverview.monthGrowth }}% 环比
            </span>
          </div>
        </div>
        <div class="stat-icon green">
          <el-icon><Money /></el-icon>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-info">
          <div class="stat-label">已支付订单数</div>
          <div class="stat-value">{{ financialOverview.paidOrderCount }}</div>
          <div class="stat-trend">
            <span class="trend-text">笔</span>
          </div>
        </div>
        <div class="stat-icon blue">
          <el-icon><TrendCharts /></el-icon>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-info">
          <div class="stat-label">利润率</div>
          <div class="stat-value">{{ financialOverview.profitRate }}%</div>
          <div class="profit-bar-container">
            <div class="profit-bar-bg">
              <div class="profit-bar-fill" :style="{ width: `${financialOverview.profitRate}%` }"></div>
            </div>
          </div>
        </div>
        <div class="stat-icon purple">
          <el-icon><TrendCharts /></el-icon>
        </div>
      </div>
    </div>

    <!-- 收入和成本明细 Tabs -->
    <div class="finance-content">
      <div class="custom-tabs-container">
        <div class="custom-tabs">
          <div
            v-for="tab in tabs"
            :key="tab.value"
            :class="['tab-item', { active: activeTab === tab.value }]"
            @click="activeTab = tab.value"
          >
            {{ tab.label }}
          </div>
        </div>
      </div>

      <div class="tab-content-card">
        <!-- 收入明细 -->
        <div v-if="activeTab === 'revenue'" class="tab-content">
          <div class="detail-card">
            <div class="card-title">收入构成</div>
            <div class="detail-list">
            <div v-for="(item, index) in revenueDetails" :key="index" class="detail-item">
              <div class="detail-content">
                <div class="detail-header">
                  <span class="detail-name">{{ item.type }}</span>
                  <div class="detail-amounts">
                    <span class="detail-amount">{{ formatMoneyYuan(fenToYuan(item.amount)) }}</span>
                    <span class="detail-percent">{{ item.percent }}%</span>
                  </div>
                </div>
                <div class="progress-bar-bg">
                  <div
                    :class="['progress-bar-fill', `color-${index}`]"
                    :style="{ width: `${item.percent}%` }"
                  ></div>
                </div>
              </div>
            </div>
          </div>

          <!-- 总计 -->
          <div class="detail-total">
            <span class="total-label">总收入</span>
            <span class="total-value">
              {{ formatMoneyYuan(fenToYuan(revenueDetails.reduce((s: number, i: any) => s + i.amount, 0))) }}
            </span>
          </div>
        </div>
        </div>

        <!-- 成本明细 -->
        <div v-if="activeTab === 'cost'" class="tab-content">
          <div class="detail-card">
            <div class="card-title">成本构成（估算）</div>
            <div class="detail-list">
            <div v-for="(item, index) in costDetails" :key="index" class="detail-item">
              <div class="detail-content">
                <div class="detail-header">
                  <span class="detail-name">{{ item.type }}</span>
                  <div class="detail-amounts">
                    <span class="detail-amount">{{ formatMoneyYuan(fenToYuan(item.amount)) }}</span>
                    <span class="detail-percent">{{ item.percent }}%</span>
                  </div>
                </div>
                <div class="progress-bar-bg">
                  <div
                    class="progress-bar-fill color-cost"
                    :style="{ width: `${item.percent}%` }"
                  ></div>
                </div>
              </div>
            </div>
          </div>

          <!-- 总计 -->
          <div class="detail-total">
            <span class="total-label">总成本（估算）</span>
            <span class="total-value">
              {{ formatMoneyYuan(fenToYuan(costDetails.reduce((s: number, i: any) => s + i.amount, 0))) }}
            </span>
          </div>

          <!-- 利润分析卡片 -->
          <div class="profit-analysis">
            <div class="profit-card">
              <p class="profit-label">总收入</p>
              <p class="profit-value">{{ formatMoneyYuan(fenToYuan(financialOverview.totalRevenue)) }}</p>
            </div>
            <div class="profit-card">
              <p class="profit-label">总成本</p>
              <p class="profit-value">{{ formatMoneyYuan(fenToYuan(financialOverview.totalCost)) }}</p>
            </div>
            <div class="profit-card highlight">
              <p class="profit-label highlight-text">净利润</p>
              <p class="profit-value highlight-value">{{ formatMoneyYuan(fenToYuan(financialOverview.netProfit)) }}</p>
            </div>
          </div>
        </div>
        </div>

        <!-- 企业支付记录 -->
        <div v-if="activeTab === 'records'" class="tab-content">
          <div class="detail-card">
            <div class="card-title">企业支付记录</div>
            <div class="table-container" v-if="rechargeRecords.length > 0">
              <table class="recharge-table">
                <thead>
                  <tr>
                    <th class="text-left">订单号</th>
                    <th class="text-left">企业名称</th>
                    <th class="text-right">金额</th>
                    <th class="text-left">支付方式</th>
                    <th class="text-left">时间</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(record, index) in rechargeRecords" :key="index">
                    <td><span class="order-no">{{ record.orderNo || '—' }}</span></td>
                    <td>
                      <div class="enterprise-cell">
                        <el-icon class="enterprise-icon"><OfficeBuilding /></el-icon>
                        <span class="enterprise-name">{{ record.enterprise }}</span>
                      </div>
                    </td>
                    <td class="text-right">
                      <span class="amount-positive">+{{ formatMoneyYuan(fenToYuan(record.amount)) }}</span>
                    </td>
                    <td><span class="method-text">{{ record.method }}</span></td>
                    <td><span class="date-text">{{ record.date }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="empty-placeholder">
              <el-icon class="empty-icon"><Document /></el-icon>
              <p class="empty-text">暂无企业支付记录</p>
            </div>
            <div class="pagination-wrap" v-if="rechargeTotal > rechargePageSize">
              <el-pagination
                v-model:current-page="rechargePage"
                :page-size="rechargePageSize"
                :total="rechargeTotal"
                layout="prev, pager, next, total"
                @current-change="loadRechargeRecords"
              />
            </div>
          </div>
        </div>

        <!-- 支付记录（全部已支付订单） -->
        <div v-if="activeTab === 'payments'" class="tab-content">
          <div class="detail-card">
            <div class="card-title">支付记录</div>
            <div class="toolbar-row">
              <el-input
                v-model="paymentKeyword"
                placeholder="订单号/用户ID"
                clearable
                class="search-input"
                @keyup.enter="loadPaymentRecords"
              >
                <template #prefix><el-icon><Search /></el-icon></template>
              </el-input>
              <el-button type="primary" @click="loadPaymentRecords">搜索</el-button>
            </div>
            <div class="table-container" v-if="paymentRecords.length > 0">
              <table class="recharge-table">
                <thead>
                  <tr>
                    <th class="text-left">订单号</th>
                    <th class="text-left">用户</th>
                    <th class="text-left">企业</th>
                    <th class="text-left">产品</th>
                    <th class="text-right">金额</th>
                    <th class="text-left">支付方式</th>
                    <th class="text-left">时间</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(record, index) in paymentRecords" :key="index">
                    <td><span class="order-no">{{ record.orderNo }}</span></td>
                    <td><span class="user-name">{{ record.userName }}</span></td>
                    <td>
                      <div class="enterprise-cell">
                        <el-icon v-if="record.enterprise && record.enterprise !== '个人'" class="enterprise-icon"><OfficeBuilding /></el-icon>
                        <span class="enterprise-name">{{ record.enterprise || '个人' }}</span>
                      </div>
                    </td>
                    <td><span class="product-type">{{ record.productType }}</span></td>
                    <td class="text-right">
                      <span class="amount-positive">+{{ formatMoneyYuan(fenToYuan(record.amount)) }}</span>
                    </td>
                    <td><span class="method-text">{{ record.method }}</span></td>
                    <td><span class="date-text">{{ record.date }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="empty-placeholder">
              <el-icon class="empty-icon"><Document /></el-icon>
              <p class="empty-text">暂无支付记录</p>
            </div>
            <div class="pagination-wrap" v-if="paymentTotal > 0">
              <el-pagination
                v-model:current-page="paymentPage"
                :page-size="paymentPageSize"
                :total="paymentTotal"
                layout="prev, pager, next, total"
                @current-change="loadPaymentRecords"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import { Download, ArrowUp, Bottom, OfficeBuilding, Money, TrendCharts, Document, Search } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { request } from '@/utils/request'
import { formatMoneyYuan } from '@/utils/format'

withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false })

const activeTab = ref('revenue')
const overviewLoading = ref(false)

const tabs = [
  { label: '收入明细', value: 'revenue' },
  { label: '成本明细', value: 'cost' },
  { label: '企业支付', value: 'records' },
  { label: '支付记录', value: 'payments' }
]

// 财务概览数据（金额为分，前端展示时 fenToYuan 转元）
const financialOverview = reactive({
  totalRevenue: 0,
  totalCost: 0,
  netProfit: 0,
  profitRate: 0,
  monthRevenue: 0,
  monthCost: 0,
  monthProfit: 0,
  monthGrowth: 0,
  paidOrderCount: 0,
})

function fenToYuan(fen: number | undefined | null): number {
  if (fen == null || !Number.isFinite(fen)) return 0
  return Number(fen) / 100
}

interface DetailItem { type: string; amount: number; percent: number }
interface RechargeRecord { orderNo?: string; enterprise: string; amount: number; method: string; date: string }
interface PaymentRecord { orderNo: string; userName: string; enterprise: string; productType: string; amount: number; method: string; date: string }

// 收入明细
const revenueDetails = ref<DetailItem[]>([])

// 成本明细
const costDetails = ref<DetailItem[]>([])

// 企业支付记录
const rechargeRecords = ref<RechargeRecord[]>([])
const rechargePage = ref(1)
const rechargePageSize = ref(20)
const rechargeTotal = ref(0)

// 支付记录（全部）
const paymentRecords = ref<PaymentRecord[]>([])
const paymentPage = ref(1)
const paymentPageSize = ref(20)
const paymentTotal = ref(0)
const paymentKeyword = ref('')

// 加载财务概览
const loadFinancialOverview = async () => {
  overviewLoading.value = true
  try {
    const response: any = await request.get('/superadmin/finance/overview')
    if (response.code === 200 && response.data) {
      Object.assign(financialOverview, response.data)
    }
  } catch (error: any) {
    ElMessage.error('加载财务概览失败')
  } finally {
    overviewLoading.value = false
  }
}

// 加载收入明细
const loadRevenueDetails = async () => {
  try {
    const response: any = await request.get('/superadmin/finance/revenue-details')
    if (response.code === 200 && response.data) {
      revenueDetails.value = response.data
    }
  } catch {
    ElMessage.error('加载收入明细失败')
  }
}

// 加载成本明细
const loadCostDetails = async () => {
  try {
    const response: any = await request.get('/superadmin/finance/cost-details')
    if (response.code === 200 && response.data) {
      costDetails.value = response.data
    }
  } catch {
    ElMessage.error('加载成本明细失败')
  }
}

// 加载企业支付记录
const loadRechargeRecords = async () => {
  try {
    const response: any = await request.get('/superadmin/finance/recharge-records', {
      params: { page: rechargePage.value, pageSize: rechargePageSize.value }
    })
    if (response.code === 200 && response.data) {
      rechargeRecords.value = response.data.list || []
      rechargeTotal.value = response.data.total || 0
    }
  } catch {
    ElMessage.error('加载企业支付记录失败')
  }
}

// 加载支付记录（全部）
const loadPaymentRecords = async () => {
  try {
    const response: any = await request.get('/superadmin/finance/payment-records', {
      params: {
        page: paymentPage.value,
        pageSize: paymentPageSize.value,
        keyword: paymentKeyword.value
      }
    })
    if (response.code === 200 && response.data) {
      paymentRecords.value = response.data.list || []
      paymentTotal.value = response.data.total || 0
    }
  } catch {
    ElMessage.error('加载支付记录失败')
  }
}

const exportData = async () => {
  try {
    const response: any = await request.post('/superadmin/finance/export')
    if (response.code === 200) {
      ElMessage.success('财务报表导出成功')
    }
  } catch (error: any) {
    ElMessage.error(error.message || '导出失败')
  }
}

onMounted(() => {
  loadFinancialOverview()
  loadRevenueDetails()
  loadCostDetails()
  loadRechargeRecords()
})
watch(activeTab, (tab) => {
  if (tab === 'payments') loadPaymentRecords()
})
</script>

<style scoped lang="scss">
.page-container {
  padding: 24px;
  min-height: calc(100vh - 64px);
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 24px;

  .header-left {
    h2 {
      font-size: 24px;
      font-weight: 700;
      color: #111827;
      margin: 0 0 4px 0;
    }

    .subtitle {
      font-size: 14px;
      color: #6b7280;
      margin: 0;
    }
  }

  .header-actions {
    display: flex;
    gap: 12px;
  }

  &.header-embedded {
    justify-content: flex-end;
  }
}

.page-container.is-embedded {
  padding-top: 0;
  min-height: auto;
}

.zero-hint {
  margin-bottom: 20px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  background: #fff;
  border-radius: 10px;
  padding: 20px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);

  .stat-info {
    flex: 1;

    .stat-label {
      font-size: 13px;
      color: #6b7280;
      margin-bottom: 8px;
    }

    .stat-value {
      font-size: 24px;
      font-weight: 700;
      color: #111827;
      line-height: 1;
      margin-bottom: 8px;
    }

    .stat-trend {
      display: flex;
      align-items: center;
      gap: 4px;
      font-size: 12px;
      color: #6b7280;

      .trend-icon {
        font-size: 14px;
        color: #22c55e;
      }

      .trend-text {
        &.positive {
          color: #22c55e;
        }
        &.negative {
          color: #ef4444;
        }
      }
      .trend-icon.down {
        color: #ef4444;
      }
    }

    .profit-bar-container {
      margin-top: 8px;

      .profit-bar-bg {
        width: 100%;
        height: 6px;
        background-color: #e5e7eb;
        border-radius: 9999px;
        overflow: hidden;

        .profit-bar-fill {
          height: 100%;
          background-color: #22c55e;
          border-radius: 9999px;
          transition: width 0.3s;
        }
      }
    }
  }

  .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;

    &.purple {
      background-color: #f3e8ff;
      color: #a855f7;
    }

    &.green {
      background-color: #dcfce7;
      color: #22c55e;
    }

    &.orange {
      background-color: #fed7aa;
      color: #f97316;
    }

    &.blue {
      background-color: #dbeafe;
      color: #3b82f6;
    }
  }
}

.finance-content {
  display: flex;
  flex-direction: column;
  gap: 0;
}

/* .custom-tabs-container 视觉已统一在 admin-theme.css */

.tab-content-card {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #f3f4f6;
  padding: 32px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.tab-content {
  .detail-card {
    .card-title {
      font-size: 16px;
      font-weight: 600;
      color: #111827;
      margin-bottom: 20px;
    }
    .toolbar-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 16px;
      .search-input {
        max-width: 280px;
      }
    }
    .pagination-wrap {
      margin-top: 16px;
      display: flex;
      justify-content: flex-end;
    }
    .order-no {
      font-size: 12px;
      color: #374151;
      font-family: ui-monospace, monospace;
    }
    .user-name {
      font-size: 13px;
      color: #111827;
    }
    .product-type {
      font-size: 13px;
      color: #6b7280;
    }
  }
}

.detail-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 24px;
}

.detail-item {
  .detail-content {
    .detail-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 6px;

      .detail-name {
        font-size: 14px;
        font-weight: 500;
        color: #111827;
      }

      .detail-amounts {
        display: flex;
        align-items: center;
        gap: 12px;

        .detail-amount {
          font-size: 14px;
          font-weight: 500;
          color: #111827;
        }

        .detail-percent {
          font-size: 12px;
          color: #9ca3af;
          width: 48px;
          text-align: right;
        }
      }
    }

    .progress-bar-bg {
      width: 100%;
      height: 8px;
      background-color: #f3f4f6;
      border-radius: 9999px;
      overflow: hidden;

      .progress-bar-fill {
        height: 100%;
        border-radius: 9999px;
        transition: width 0.3s;

        &.color-0 {
          background-color: #6366f1;
        }

        &.color-1 {
          background-color: #3b82f6;
        }

        &.color-2 {
          background-color: #22c55e;
        }

        &.color-3 {
          background-color: #f59e0b;
        }

        &.color-cost {
          background-color: #f87171;
        }
      }
    }
  }
}

.detail-total {
  padding-top: 16px;
  border-top: 1px solid #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: space-between;

  .total-label {
    font-weight: 500;
    color: #111827;
  }

  .total-value {
    font-size: 18px;
    font-weight: 700;
    color: #111827;
  }
}

.profit-analysis {
  margin-top: 24px;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.profit-card {
  padding: 16px;
  background-color: #f9fafb;
  border-radius: 12px;
  text-align: center;

  &.highlight {
    background-color: #dcfce7;
  }

  .profit-label {
    font-size: 12px;
    color: #6b7280;
    margin: 0 0 4px 0;

    &.highlight-text {
      color: #22c55e;
    }
  }

  .profit-value {
    font-size: 18px;
    font-weight: 700;
    color: #111827;
    margin: 0;

    &.highlight-value {
      color: #22c55e;
    }
  }
}

.table-container {
  overflow-x: auto;
}

.recharge-table {
  width: 100%;
  border-collapse: collapse;

  thead {
    background-color: rgba(249, 250, 251, 0.8);

    tr {
      th {
        padding: 12px 16px;
        font-size: 12px;
        font-weight: 500;
        color: #6b7280;
        text-align: left;

        &.text-left {
          text-align: left;
        }

        &.text-right {
          text-align: right;
        }
      }
    }
  }

  tbody {
    tr {
      border-bottom: 1px solid #f3f4f6;

      &:hover {
        background-color: rgba(249, 250, 251, 0.5);
      }

      td {
        padding: 12px 16px;

        &.text-left {
          text-align: left;
        }

        &.text-right {
          text-align: right;
        }

        .enterprise-cell {
          display: flex;
          align-items: center;
          gap: 8px;

          .enterprise-icon {
            font-size: 16px;
            color: #9ca3af;
          }

          .enterprise-name {
            font-size: 14px;
            font-weight: 500;
            color: #111827;
          }
        }

        .amount-positive {
          font-size: 14px;
          font-weight: 500;
          color: #22c55e;
        }

        .method-text,
        .date-text {
          font-size: 14px;
          color: #6b7280;
        }
      }
    }
  }
}

.mr-1 {
  margin-right: 4px;
}
</style>
