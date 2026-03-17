<template>
  <div class="dashboard-container" v-loading="loading">
    <!-- 数据概览 -->
    <div class="overview-section">
      <div class="section-header">
        <h2 class="section-title">数据概览</h2>
        <p class="section-subtitle">超管端全局数据一览</p>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-info">
            <div class="stat-label">企业总数</div>
            <div class="stat-value">{{ stats.totalEnterprises }}</div>
            <div class="stat-trend">
              <span class="trend-text">+{{ stats.newEnterprises }} 本月新增</span>
            </div>
          </div>
          <div class="stat-icon purple">
            <el-icon><Document /></el-icon>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <div class="stat-label">总用户数</div>
            <div class="stat-value">{{ stats.totalUsers.toLocaleString() }}</div>
            <div class="stat-trend">
              <span class="trend-text">+{{ stats.newUsers }} 本月新增 · 注册 {{ stats.totalRegisteredUsers?.toLocaleString() ?? 0 }}</span>
            </div>
          </div>
          <div class="stat-icon purple">
            <el-icon><User /></el-icon>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <div class="stat-label">总收入</div>
            <div class="stat-value">{{ formatMoneyYuan(fenToYuan(stats.totalRevenue)) }}</div>
            <div class="stat-trend">
              <span class="trend-text">本月 {{ formatMoneyYuan(fenToYuan(stats.monthRevenue)) }}</span>
              <el-icon v-if="stats.revenueGrowth > 0" class="trend-icon"><ArrowUp /></el-icon>
              <span class="trend-text" :class="{ positive: stats.revenueGrowth > 0 }">
                {{ stats.revenueGrowth > 0 ? '+' : '' }}{{ stats.revenueGrowth }}% 环比
              </span>
            </div>
          </div>
          <div class="stat-icon green">
            <el-icon><Document /></el-icon>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <div class="stat-label">已支付订单</div>
            <div class="stat-value">{{ (stats.paidOrderCount ?? 0).toLocaleString() }}</div>
            <div class="stat-trend">
              <span class="trend-text">累计已支付笔数</span>
            </div>
          </div>
          <div class="stat-icon green">
            <el-icon><Document /></el-icon>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <div class="stat-label">测试总量</div>
            <div class="stat-value">{{ stats.totalTests.toLocaleString() }}</div>
            <div class="stat-trend">
              <span class="trend-text">+{{ stats.newTests }} 本月</span>
            </div>
          </div>
          <div class="stat-icon orange">
            <el-icon><TrendCharts /></el-icon>
          </div>
        </div>
      </div>
    </div>

    <!-- 快捷操作 -->
    <div class="quick-actions-section">
      <div class="section-header">
        <h2 class="section-title">
          <el-icon class="title-icon"><Lightning /></el-icon>
          快捷操作
        </h2>
      </div>

      <div class="actions-grid">
        <div class="action-card blue" @click="handleAction('create-enterprise')">
          <div class="action-icon">
            <el-icon><Plus /></el-icon>
          </div>
          <div class="action-label">新建企业</div>
        </div>

        <div class="action-card blue" @click="handleAction('enterprise-management')">
          <div class="action-icon">
            <el-icon><Document /></el-icon>
          </div>
          <div class="action-label">企业管理</div>
        </div>

        <div class="action-card green" @click="handleAction('finance')">
          <div class="action-icon">
            <el-icon><Document /></el-icon>
          </div>
          <div class="action-label">财务管理</div>
        </div>

        <div class="action-card orange" @click="handleAction('pricing')">
          <div class="action-icon">
            <el-icon><PriceTag /></el-icon>
          </div>
          <div class="action-label">价格管理</div>
        </div>

        <div class="action-card purple" @click="handleAction('users')">
          <div class="action-icon">
            <el-icon><User /></el-icon>
          </div>
          <div class="action-label">用户数据</div>
        </div>

        <div class="action-card gray" @click="handleAction('settings')">
          <div class="action-icon">
            <el-icon><Setting /></el-icon>
          </div>
          <div class="action-label">系统设置</div>
        </div>
      </div>
    </div>

    <!-- 测试趋势折线图 -->
    <div class="trend-section">
      <div class="section-header">
        <h2 class="section-title">
          <el-icon class="title-icon"><TrendCharts /></el-icon>
          测试趋势
        </h2>
        <p class="section-subtitle">最近 14 天人脸分析、MBTI、PDP、DISC 等关键测试的完成情况</p>
      </div>

      <div class="trend-chart-wrapper" v-if="testTrends.length">
        <VChart class="trend-chart-echarts" :option="chartOption" autoresize />
      </div>

      <div class="empty-placeholder" v-else>
        <el-icon class="empty-icon"><TrendCharts /></el-icon>
        <p class="empty-text">暂无测试趋势数据</p>
      </div>
    </div>

    <!-- 底部两列布局 -->
    <div class="bottom-section">
      <!-- 最近动态 -->
      <div class="recent-dynamics-section">
        <div class="section-header">
          <h2 class="section-title">
            <el-icon class="title-icon"><TrendCharts /></el-icon>
            最近动态
          </h2>
        </div>

        <div class="dynamics-list" v-if="recentDynamics.length > 0">
            <div
            v-for="(item, index) in recentDynamics"
            :key="index"
            class="dynamics-item"
          >
            <div class="dynamics-icon">
              <el-icon><component :is="iconMap[item.icon] || Document" /></el-icon>
            </div>
            <div class="dynamics-content">
              <div class="dynamics-text">{{ item.text }}</div>
            </div>
            <div class="dynamics-time">{{ item.time }}</div>
          </div>
        </div>
        <!-- 空数据占位图 -->
        <div v-else class="empty-placeholder">
          <el-icon class="empty-icon"><Document /></el-icon>
          <p class="empty-text">暂无最近动态</p>
        </div>
      </div>

      <!-- 企业活跃排行 -->
      <div class="ranking-section">
        <div class="section-header">
          <h2 class="section-title">
            <el-icon class="title-icon"><TrendCharts /></el-icon>
            企业活跃排行
          </h2>
          <el-button link class="view-all-btn" @click="handleViewAll">查看全部</el-button>
        </div>

        <div class="ranking-list" v-if="enterpriseRanking.length > 0">
          <div
            v-for="(item, index) in enterpriseRanking"
            :key="index"
            class="ranking-item"
          >
            <div class="ranking-number" :class="{ 'top-one': index === 0 }">
              {{ index + 1 }}
            </div>
            <div class="ranking-content">
              <div class="ranking-name">{{ item.name }}</div>
              <div class="ranking-stats">
                <span class="test-count">{{ item.tests }}次测试</span>
                <span class="amount">{{ formatMoneyYuan(fenToYuan(item.amount)) }}</span>
              </div>
            </div>
          </div>
        </div>
        <!-- 空数据占位图 -->
        <div v-else class="empty-placeholder">
          <el-icon class="empty-icon"><TrendCharts /></el-icon>
          <p class="empty-text">暂无企业排行数据</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import {
  User,
  Document,
  TrendCharts,
  ArrowUp,
  Lightning,
  Plus,
  PriceTag,
  Setting
} from '@element-plus/icons-vue'
import { request } from '@/utils/request'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { LineChart } from 'echarts/charts'
import { GridComponent, TooltipComponent, LegendComponent } from 'echarts/components'
import VChart from 'vue-echarts'
import { formatMoneyYuan } from '@/utils/format'

use([CanvasRenderer, LineChart, GridComponent, TooltipComponent, LegendComponent])

const router = useRouter()
const loading = ref(false)

// 金额为分，转元展示
function fenToYuan(fen: number | undefined | null): number {
  if (fen == null || Number.isNaN(Number(fen))) return 0
  return Number(fen) / 100
}

const stats = reactive({
  totalEnterprises: 0,
  newEnterprises: 0,
  totalRegisteredUsers: 0,
  totalUsers: 0,
  newUsers: 0,
  totalRevenue: 0,
  monthRevenue: 0,
  revenueGrowth: 0,
  paidOrderCount: 0,
  totalTests: 0,
  newTests: 0
})

interface DynamicItem { icon: string; text: string; time: string }
interface RankingItem { name: string; tests: number; amount: number }

const recentDynamics = ref<DynamicItem[]>([])
const enterpriseRanking = ref<RankingItem[]>([])

type TrendPoint = { date: string; face: number; mbti: number; pdp: number; disc: number; total: number }
const testTrends = ref<TrendPoint[]>([])

const chartOption = computed(() => {
  const dates = testTrends.value.map(d => d.date.slice(5))
  return {
    tooltip: { trigger: 'axis' },
    legend: {
      data: ['人脸分析', 'MBTI', 'PDP', 'DISC'],
      bottom: 0
    },
    grid: {
      left: 40,
      right: 20,
      top: 30,
      bottom: 40
    },
    xAxis: {
      type: 'category',
      data: dates,
      boundaryGap: false,
      axisLine: { lineStyle: { color: '#e5e7eb' } },
      axisLabel: { color: '#6b7280' }
    },
    yAxis: {
      type: 'value',
      minInterval: 1,
      axisLine: { lineStyle: { color: '#e5e7eb' } },
      splitLine: { lineStyle: { color: '#f3f4f6' } },
      axisLabel: { color: '#6b7280' }
    },
    series: [
      {
        name: '人脸分析',
        type: 'line',
        smooth: true,
        showSymbol: false,
        itemStyle: { color: '#22c55e' },
        data: testTrends.value.map(d => d.face)
      },
      {
        name: 'MBTI',
        type: 'line',
        smooth: true,
        showSymbol: false,
        itemStyle: { color: '#3b82f6' },
        data: testTrends.value.map(d => d.mbti)
      },
      {
        name: 'PDP',
        type: 'line',
        smooth: true,
        showSymbol: false,
        itemStyle: { color: '#f97316' },
        data: testTrends.value.map(d => d.pdp)
      },
      {
        name: 'DISC',
        type: 'line',
        smooth: true,
        showSymbol: false,
        itemStyle: { color: '#6366f1' },
        data: testTrends.value.map(d => d.disc)
      }
    ]
  }
})

// 图标映射
const iconMap: Record<string, any> = {
  Document,
  User,
  TrendCharts
}

// 加载数据概览
const loadOverview = async () => {
  loading.value = true
  try {
    const response: any = await request.get('/superadmin/overview')
    if (response.code === 200 && response.data) {
      Object.assign(stats, response.data)
    }
  } catch (error: any) {
    console.error('加载数据概览失败:', error)
  } finally {
    loading.value = false
  }
}

// 加载最近动态
const loadRecentDynamics = async () => {
  try {
    const response: any = await request.get('/superadmin/overview/recent-dynamics', {
      params: { limit: 10 }
    })
    if (response.code === 200 && response.data) {
      recentDynamics.value = response.data
    }
  } catch (error: any) {
    console.error('加载最近动态失败:', error)
  }
}

// 加载企业排行
const loadEnterpriseRanking = async () => {
  try {
    const response: any = await request.get('/superadmin/overview/enterprise-ranking', {
      params: { limit: 5 }
    })
    if (response.code === 200 && response.data) {
      enterpriseRanking.value = response.data.map((item: any) => ({
        name: item.name,
        tests: item.tests,
        amount: item.amount
      }))
    }
  } catch (error: any) {
    console.error('加载企业排行失败:', error)
  }
}

// 加载测试趋势
const loadTestTrends = async () => {
  try {
    const response: any = await request.get('/superadmin/overview/test-trends', {
      params: { days: 14 }
    })
    if (response.code === 200 && response.data) {
      testTrends.value = response.data
    }
  } catch (error: any) {
    console.error('加载测试趋势失败:', error)
  }
}

const handleAction = (action: string) => {
  const routes: Record<string, string> = {
    'create-enterprise': '/superadmin/enterprises',
    'enterprise-management': '/superadmin/enterprises',
    'finance': '/superadmin/finance',
    'pricing': '/superadmin/pricing',
    'users': '/superadmin/users',
    'settings': '/superadmin/settings'
  }
  if (routes[action]) {
    router.push(routes[action])
  }
}

const handleViewAll = () => {
  router.push('/superadmin/enterprises')
}

onMounted(() => {
  loadOverview()
  loadRecentDynamics()
  loadEnterpriseRanking()
  loadTestTrends()
})
</script>

<style scoped lang="scss">
.dashboard-container {
  padding: 24px;
  background-color: #f9fafb;
  min-height: 100vh;
}

.section-header {
  margin-bottom: 20px;

  .section-title {
    font-size: 18px;
    font-weight: 700;
    color: #111827;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;

    .title-icon {
      font-size: 20px;
      color: #6b7280;
    }
  }

  .section-subtitle {
    font-size: 13px;
    color: #6b7280;
    margin: 4px 0 0 0;
  }
}

.overview-section {
  margin-bottom: 28px;

  .stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
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
    }
  }
}

.quick-actions-section {
  margin-bottom: 28px;

  .actions-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 16px;
  }

  .action-card {
    background: #fff;
    border-radius: 10px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid #f3f4f6;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);

    &:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px -2px rgba(0, 0, 0, 0.1);
    }

    .action-icon {
      width: 48px;
      height: 48px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      color: #fff;
    }

    .action-label {
      font-size: 13px;
      font-weight: 500;
      color: #111827;
      text-align: center;
    }

    &.blue .action-icon {
      background-color: #3b82f6;
    }

    &.green .action-icon {
      background-color: #22c55e;
    }

    &.orange .action-icon {
      background-color: #f97316;
    }

    &.purple .action-icon {
      background-color: #a855f7;
    }

    &.gray .action-icon {
      background-color: #6b7280;
    }
  }
}

.trend-section {
  margin-bottom: 28px;
  background: #fff;
  border-radius: 10px;
  padding: 20px 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  border: 1px solid #f3f4f6;

  .trend-chart-wrapper {
    margin-top: 8px;
  }

  .trend-chart-echarts {
    width: 100%;
    height: 260px;
  }
}

.bottom-section {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.recent-dynamics-section,
.ranking-section {
  background: #fff;
  border-radius: 10px;
  padding: 20px;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);

  .section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #f3f4f6;

    .view-all-btn {
      font-size: 13px;
      color: #6b7280;
      padding: 0;

      &:hover {
        color: #ef4444;
      }
    }
  }
}

.dynamics-list {
  .dynamics-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;

    &:last-child {
      border-bottom: none;
    }

    .dynamics-icon {
      width: 32px;
      height: 32px;
      border-radius: 6px;
      background-color: #f3f4f6;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #6b7280;
      font-size: 16px;
      flex-shrink: 0;
    }

    .dynamics-content {
      flex: 1;

      .dynamics-text {
        font-size: 14px;
        color: #111827;
        line-height: 1.5;
      }
    }

    .dynamics-time {
      font-size: 12px;
      color: #9ca3af;
      flex-shrink: 0;
    }
  }
}

.empty-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  color: #9ca3af;

  .empty-icon {
    font-size: 64px;
    color: #d1d5db;
    margin-bottom: 16px;
  }

  .empty-text {
    font-size: 14px;
    color: #9ca3af;
    margin: 0;
  }
}

.ranking-list {
  .ranking-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;

    &:last-child {
      border-bottom: none;
    }

    .ranking-number {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background-color: #f3f4f6;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      font-weight: 600;
      color: #6b7280;
      flex-shrink: 0;

      &.top-one {
        background-color: #fef3c7;
        color: #f59e0b;
      }
    }

    .ranking-content {
      flex: 1;

      .ranking-name {
        font-size: 14px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 4px;
      }

      .ranking-stats {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 12px;
        color: #6b7280;

        .test-count {
          color: #6b7280;
        }

        .amount {
          color: #111827;
          font-weight: 600;
        }
      }
    }
  }
}

@media (max-width: 1400px) {
  .quick-actions-section .actions-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 1200px) {
  .overview-section .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .bottom-section {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .overview-section .stats-grid,
  .quick-actions-section .actions-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
