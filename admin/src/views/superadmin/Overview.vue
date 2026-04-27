<template>
  <div class="dashboard-container" v-loading="loading">
    <!-- 数据概览 -->
    <div class="overview-section">
      <div class="section-header">
        <h2 class="section-title">数据概览</h2>
        <p class="section-subtitle">对照「普通管理后台」经营结果的平台级指标（非企业日常操作入口）</p>
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
            <!-- 楼宇图标 -->
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <path d="M3 21V7l9-4 9 4v14" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M9 21V15h6v6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M9 9h.01M12 9h.01M15 9h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
            </svg>
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
          <div class="stat-icon blue">
            <!-- 人群图标 -->
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
              <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.75"/>
              <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
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
            <!-- 钱包图标 -->
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="1.75"/>
              <path d="M2 10h20" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
              <circle cx="16.5" cy="15" r="1.5" fill="currentColor"/>
            </svg>
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
          <div class="stat-icon teal">
            <!-- 购物袋图标 -->
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
              <line x1="3" y1="6" x2="21" y2="6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
              <path d="M16 10a4 4 0 01-8 0" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
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
            <!-- 折线趋势图标 -->
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- 快捷操作 -->
    <div class="quick-actions-section">
      <div class="section-header">
        <h2 class="section-title">
          <span class="title-svg-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          快捷操作
        </h2>
      </div>

      <div class="actions-grid">
        <div class="action-card blue" @click="handleAction('create-enterprise')">
          <div class="action-icon">
            <!-- 新建企业：加号+楼宇 -->
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="action-label">新建企业</div>
        </div>

        <div class="action-card blue" @click="handleAction('enterprise-management')">
          <div class="action-icon">
            <!-- 企业管理：楼宇 -->
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <path d="M3 21V7l9-4 9 4v14" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M9 21V15h6v6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M9 9h.01M12 9h.01M15 9h.01M9 12h.01M12 12h.01M15 12h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="action-label">企业管理</div>
        </div>

        <div class="action-card green" @click="handleAction('finance')">
          <div class="action-icon">
            <!-- 财务管理：钱包+趋势 -->
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="1.75"/>
              <path d="M2 10h20" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
              <circle cx="16" cy="15" r="1.5" fill="currentColor"/>
            </svg>
          </div>
          <div class="action-label">财务管理</div>
        </div>

        <div class="action-card orange" @click="handleAction('pricing')">
          <div class="action-icon">
            <!-- 价格管理：标签 -->
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
              <line x1="7" y1="7" x2="7.01" y2="7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="action-label">价格管理</div>
        </div>

        <div class="action-card purple" @click="handleAction('users')">
          <div class="action-icon">
            <!-- 用户数据：人群 -->
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
              <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.75"/>
              <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <div class="action-label">用户数据</div>
        </div>

        <div class="action-card gray" @click="handleAction('settings')">
          <div class="action-icon">
            <!-- 系统设置：齿轮 -->
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.75"/>
              <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z" stroke="currentColor" stroke-width="1.75"/>
            </svg>
          </div>
          <div class="action-label">系统设置</div>
        </div>
      </div>
    </div>

    <!-- 邀请小程序码（与企业后台一致：企业版 + 个人版） -->
    <div class="invite-section">
      <div class="section-header invite-header">
        <div>
          <h2 class="section-title">邀请小程序码</h2>
          <p class="section-subtitle">企业版进企业测评，个人版进小程序首页；企业版太阳码对应下方所选企业</p>
        </div>
        <div class="invite-header-actions">
          <el-select
            v-model="inviteSelectedEnterpriseId"
            placeholder="选择企业（生成企业版码）"
            filterable
            clearable
            style="width: 220px"
            size="small"
            @change="loadInviteQrcode"
          >
            <el-option
              v-for="opt in inviteEnterpriseOptions"
              :key="opt.id"
              :label="opt.name"
              :value="opt.id"
            />
          </el-select>
          <el-button type="primary" size="small" :loading="inviteLoading" @click="loadInviteQrcode">
            {{ inviteQrcodeEnterprise || inviteQrcodePersonal ? '刷新' : '生成' }}
          </el-button>
        </div>
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
        <span v-else class="invite-placeholder">{{ inviteLoadError || '选择企业后点击生成，或未选企业时使用系统默认企业' }}</span>
      </div>
    </div>

    <!-- 测试趋势折线图 -->
    <div class="trend-section">
      <div class="section-header">
        <h2 class="section-title">
          <span class="title-svg-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          测试趋势
        </h2>
        <p class="section-subtitle">最近 14 天人脸分析、MBTI、PDP、DISC 等关键测试的完成情况</p>
      </div>

      <div class="trend-chart-wrapper" v-if="testTrends.length">
        <VChart class="trend-chart-echarts" :option="chartOption" autoresize />
      </div>

      <div class="empty-placeholder" v-else>
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" style="color:#CBD5E1">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <p class="empty-text">暂无测试趋势数据</p>
      </div>
    </div>

    <!-- 底部两列布局 -->
    <div class="bottom-section">
      <!-- 最近动态 -->
      <div class="recent-dynamics-section">
        <div class="section-header">
          <h2 class="section-title">
            <span class="title-svg-icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" stroke="currentColor" stroke-width="1.75"/>
                <path d="M12 6v6l4 2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
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
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" style="color:#CBD5E1">
            <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" stroke="currentColor" stroke-width="1.5"/>
            <path d="M12 6v6l4 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
          <p class="empty-text">暂无最近动态</p>
        </div>
      </div>

      <!-- 企业活跃排行 -->
      <div class="ranking-section">
        <div class="section-header">
          <h2 class="section-title">
            <span class="title-svg-icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M18 20V10M12 20V4M6 20v-6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
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
            <div class="ranking-number" :class="{ 'top-one': index === 0, 'top-two': index === 1, 'top-three': index === 2 }">
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
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" style="color:#CBD5E1">
            <path d="M18 20V10M12 20V4M6 20v-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
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
  ArrowUp
} from '@element-plus/icons-vue'
import { request } from '@/utils/request'
import { ElMessage } from 'element-plus'
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
interface InviteEnterpriseOption { id: number; name: string }

const recentDynamics = ref<DynamicItem[]>([])
const enterpriseRanking = ref<RankingItem[]>([])

const inviteLoading = ref(false)
const inviteQrcodeEnterprise = ref<string>('')
const inviteQrcodePersonal = ref<string>('')
const inviteLoadError = ref<string>('')
const inviteEnterpriseOptions = ref<InviteEnterpriseOption[]>([])
const inviteSelectedEnterpriseId = ref<number | null>(null)

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
    'pricing': '/superadmin/commerce?tab=pricing',
    'users': '/superadmin/enterprises?tab=users',
    'settings': '/superadmin/settings'
  }
  if (routes[action]) {
    router.push(routes[action])
  }
}

const handleViewAll = () => {
  router.push('/superadmin/enterprises')
}

const loadInviteEnterprisesAndSettings = async () => {
  try {
    const [settingsRes, entRes]: any[] = await Promise.all([
      request.get('/superadmin/settings'),
      request.get('/superadmin/enterprises', { params: { page: 1, pageSize: 500 } })
    ])
    const list = entRes?.data?.list ?? []
    inviteEnterpriseOptions.value = list.map((r: any) => ({
      id: Number(r.id),
      name: r.name ? String(r.name) : `企业#${r.id}`
    }))
    const defRaw = settingsRes?.data?.system?.defaultEnterpriseId
    const defNum =
      defRaw != null && defRaw !== '' && !Number.isNaN(Number(defRaw)) ? Number(defRaw) : null
    const idSet = new Set(inviteEnterpriseOptions.value.map(o => o.id))
    if (defNum != null && defNum > 0 && idSet.has(defNum)) {
      inviteSelectedEnterpriseId.value = defNum
    } else if (inviteEnterpriseOptions.value.length > 0) {
      inviteSelectedEnterpriseId.value = inviteEnterpriseOptions.value[0].id
    } else {
      inviteSelectedEnterpriseId.value = null
    }
  } catch (e) {
    console.error('加载邀请码企业列表失败:', e)
    inviteEnterpriseOptions.value = []
    inviteSelectedEnterpriseId.value = null
  }
}

const loadInviteQrcode = async () => {
  if (inviteLoading.value) return
  inviteLoading.value = true
  inviteLoadError.value = ''
  try {
    const params: Record<string, number> = {}
    if (inviteSelectedEnterpriseId.value != null && inviteSelectedEnterpriseId.value > 0) {
      params.enterpriseId = inviteSelectedEnterpriseId.value
    }
    const res: any = await request.get('/superadmin/invite/qrcode', {
      params: Object.keys(params).length ? params : undefined
    })
    const d = res?.data
    const ent = d?.enterprise?.qrcode ?? d?.qrcode
    const per = d?.personal?.qrcode
    if (typeof ent === 'string' && ent) inviteQrcodeEnterprise.value = ent
    else inviteQrcodeEnterprise.value = ''
    if (typeof per === 'string' && per) inviteQrcodePersonal.value = per
    else inviteQrcodePersonal.value = ''

    if (!inviteQrcodeEnterprise.value && !inviteQrcodePersonal.value) {
      const msg = res?.message || res?.msg || '生成失败，请确认小程序配置与企业'
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

onMounted(async () => {
  loadOverview()
  loadRecentDynamics()
  loadEnterpriseRanking()
  loadTestTrends()
  await loadInviteEnterprisesAndSettings()
  loadInviteQrcode()
})
</script>

<style scoped lang="scss">
.dashboard-container {
  padding: 20px 24px 28px;
  background-color: #F1F5F9;
  min-height: 100%;
  box-sizing: border-box;
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
    border-radius: 14px;
    padding: 18px 20px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(16,24,40,0.04), 0 4px 12px rgba(16,24,40,0.03);
    transition: transform 0.18s, box-shadow 0.18s;
    &:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(30,64,175,0.1); }

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
        background-color: #E0E7FF;
        color: #1E40AF;
      }

      &.green {
        background-color: #D1FAE5;
        color: #059669;
      }

      &.orange {
        background-color: #FEF3C7;
        color: #D97706;
      }

      &.blue {
        background-color: #DBEAFE;
        color: #1D4ED8;
      }

      &.teal {
        background-color: #CCFBF1;
        color: #0D9488;
      }
    }
  }
}

/* 标题内联 SVG 图标 */
.title-svg-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 6px;
  background: #EEF2FF;
  color: #4338CA;
  flex-shrink: 0;
}

.quick-actions-section {
  margin-bottom: 24px;

  .actions-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 12px;

    @media (max-width: 1100px) { grid-template-columns: repeat(3, 1fr); }
    @media (max-width: 600px)  { grid-template-columns: repeat(2, 1fr); }
  }

  .action-card {
    background: #fff;
    border-radius: 14px;
    padding: 20px 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(16,24,40,0.04), 0 4px 12px rgba(16,24,40,0.03);

    &:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 24px rgba(30,64,175,0.12);
      border-color: #BFDBFE;
    }

    .action-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
    }

    .action-label {
      font-size: 12.5px;
      font-weight: 600;
      color: #374151;
      text-align: center;
      line-height: 1.3;
    }

    &.blue .action-icon   { background: linear-gradient(135deg, #1E40AF 0%, #1D4ED8 100%); }
    &.green .action-icon  { background: linear-gradient(135deg, #059669 0%, #0D9488 100%); }
    &.orange .action-icon { background: linear-gradient(135deg, #D97706 0%, #DC2626 100%); }
    &.purple .action-icon { background: linear-gradient(135deg, #4338CA 0%, #7C3AED 100%); }
    &.gray .action-icon   { background: linear-gradient(135deg, #475569 0%, #334155 100%); }
  }
}

.invite-section {
  margin-bottom: 20px;
  background: #fff;
  border-radius: 16px;
  padding: 20px 24px;
  box-shadow: 0 1px 3px rgba(16,24,40,0.04), 0 4px 12px rgba(16,24,40,0.03);
  border: 1px solid #E2E8F0;

  .invite-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 16px;
  }

  .invite-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
  }

  .invite-body {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: center;
    gap: 16px;
    min-height: 112px;
    padding: 8px 0;
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
}

.trend-section {
  margin-bottom: 20px;
  background: #fff;
  border-radius: 16px;
  padding: 20px 24px;
  box-shadow: 0 1px 3px rgba(16,24,40,0.04), 0 4px 12px rgba(16,24,40,0.03);
  border: 1px solid #E2E8F0;

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
  border-radius: 16px;
  padding: 20px;
  border: 1px solid #E2E8F0;
  box-shadow: 0 1px 3px rgba(16,24,40,0.04), 0 4px 12px rgba(16,24,40,0.03);

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
        color: #1E40AF;
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
        background-color: #FEF3C7;
        color: #D97706;
        font-weight: 800;
      }

      &.top-two {
        background-color: #F1F5F9;
        color: #475569;
        font-weight: 700;
      }

      &.top-three {
        background-color: #FFF7ED;
        color: #C2410C;
        font-weight: 700;
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
