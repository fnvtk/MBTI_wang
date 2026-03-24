<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h2>分销管理</h2>
        <p class="subtitle">管理分销商、佣金和分销设置</p>
      </div>
      <div class="header-actions">
        <el-button @click="refresh" class="refresh-btn">
          <el-icon><Refresh /></el-icon>
          <span>刷新</span>
        </el-button>
      </div>
    </div>

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

    <div class="tab-content">
      <div v-if="activeTab === 'overview'" class="overview-section">
        <!-- 核心指标 -->
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-info">
              <div class="stat-label">分销商总数</div>
              <div class="stat-value">{{ overview.totalAgents }}</div>
              <div class="trend-tag up">
                <el-icon><CaretTop /></el-icon>今日+{{ overview.todayAgents }}
              </div>
            </div>
            <div class="stat-icon purple"><el-icon><User /></el-icon></div>
          </div>
          <div class="stat-card">
            <div class="stat-info">
              <div class="stat-label">累计佣金</div>
              <div class="stat-value">{{ formatCurrency(parseFloat(overview.totalCommission)) }}</div>
              <div class="trend-tag up">
                <el-icon><CaretTop /></el-icon>今日+{{ formatCurrency(parseFloat(overview.todayCommission)) }}
              </div>
            </div>
            <div class="stat-icon green"><el-icon><Money /></el-icon></div>
          </div>
          <div class="stat-card">
            <div class="stat-info">
              <div class="stat-label">待结算佣金</div>
              <div class="stat-value">{{ formatCurrency(parseFloat(overview.pendingCommission)) }}</div>
              <div class="trend-tag warning">{{ overview.pendingCount }}笔</div>
            </div>
            <div class="stat-icon orange"><el-icon><Clock /></el-icon></div>
          </div>
          <div class="stat-card">
            <div class="stat-info">
              <div class="stat-label">绑定关系</div>
              <div class="stat-value">{{ overview.bindingCount }}</div>
              <div class="trend-tag neutral">有效绑定</div>
            </div>
            <div class="stat-icon blue"><el-icon><Connection /></el-icon></div>
          </div>
        </div>

        <!-- 图表区域 -->
        <div class="charts-grid">
          <div class="chart-card">
            <div class="card-header">
              <el-icon><TrendCharts /></el-icon>
              <span>近7天佣金趋势</span>
            </div>
            <VChart v-if="hasCommissionTrend" class="trend-chart-echarts" :option="commissionTrendOption" autoresize />
            <div v-else class="empty-chart">暂无趋势数据</div>
          </div>
          <div class="chart-card">
            <div class="card-header">
              <el-icon><PieChart /></el-icon>
              <span>产品佣金分布</span>
            </div>
            <VChart v-if="hasProductSeries" class="trend-chart-echarts" :option="productSeriesOption" autoresize />
            <div v-else class="empty-chart">暂无产品数据</div>
          </div>
          <div class="chart-card">
            <div class="card-header">
              <el-icon><UserFilled /></el-icon>
              <span>分销商收益排行</span>
            </div>
            <div v-if="overviewTopDistributors.length" class="overview-list">
              <div v-for="item in overviewTopDistributors" :key="item.id" class="overview-list-item">
                <div class="overview-main">
                  <div class="agent-cell">
                    <el-avatar :size="28" :src="item.avatar" class="agent-avatar">
                      {{ item.agentName ? item.agentName[0] : '?' }}
                    </el-avatar>
                    <div class="overview-meta">
                      <strong>{{ item.agentName || '-' }}</strong>
                      <span>团队人数：{{ item.teamCount || 0 }}人</span>
                    </div>
                  </div>
                </div>
                <div class="overview-side">
                  <strong>{{ formatCurrency(parseFloat(item.totalCommission || 0)) }}</strong>
                  <span>累计收益</span>
                </div>
              </div>
            </div>
            <div v-else class="empty-chart">暂无分销商数据</div>
          </div>
          <div class="chart-card">
            <div class="card-header">
              <el-icon><List /></el-icon>
              <span>佣金统计</span>
            </div>
            <div class="withdraw-stats">
              <div class="stat-item green"><span>累计佣金</span><strong>{{ formatCurrency(parseFloat(overview.totalCommission)) }}</strong></div>
              <div class="stat-item orange"><span>今日新增</span><strong>{{ formatCurrency(parseFloat(overview.todayCommission)) }}</strong></div>
              <div class="stat-item blue"><span>待结算佣金</span><strong>{{ formatCurrency(parseFloat(overview.pendingCommission)) }}</strong></div>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="activeTab === 'distributors'" class="table-section">
        <div class="content-card">
          <div class="toolbar">
            <el-input v-model="distSearch" placeholder="搜索用户名..." class="search-input">
              <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-button type="primary" color="#7c3aed" @click="loadDistributors">搜索</el-button>
          </div>
          <el-table :data="distributors" style="width: 100%" class="custom-table" v-loading="loading">
            <el-table-column label="分销商" min-width="160">
              <template #default="{ row }">
                <div class="agent-cell">
                  <el-avatar :size="36" :src="row.avatar" class="agent-avatar">
                    {{ row.agentName ? row.agentName[0] : '?' }}
                  </el-avatar>
                  <span class="agent-name">{{ row.agentName || '-' }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="累计收益" align="right">
              <template #default="{ row }">
                <el-button link type="primary" @click="openCommDetail(row)">
                  {{ formatCurrency(parseFloat(row.totalCommission || 0)) }}
                </el-button>
              </template>
            </el-table-column>
            <el-table-column label="可提现" align="right">
              <template #default="{ row }">
                {{ formatCurrency(parseFloat(row.availableCommission || 0)) }}
              </template>
            </el-table-column>
            <el-table-column label="团队人数" align="center">
              <template #default="{ row }">
                <el-button link type="primary" @click="openTeamDetail(row)">
                  {{ row.teamCount }}人
                </el-button>
              </template>
            </el-table-column>
            <el-table-column label="加入时间" min-width="160">
              <template #default="{ row }">
                {{ row.createdAt ? new Date(row.createdAt * 1000).toLocaleString() : '-' }}
              </template>
            </el-table-column>
          </el-table>
          <div v-if="distributors.length === 0 && !loading" class="empty-placeholder">暂无分销商数据</div>
        </div>
      </div>

      <div v-else-if="activeTab === 'commissions'" class="table-section">
        <div class="content-card">
          <div class="toolbar justify-between">
            <div class="filter-group">
              <div
                v-for="item in commOptions"
                :key="item.value"
                :class="['filter-item', { active: commFilter === item.value }]"
                @click="commFilter = item.value"
              >
                {{ item.label }}
              </div>
            </div>
            <el-button class="action-btn">批量结算</el-button>
          </div>
          <el-table :data="commissions" style="width: 100%" class="custom-table" v-loading="loading">
            <el-table-column label="佣金ID" prop="id" />
            <el-table-column label="分销商" min-width="160">
              <template #default="{ row }">
                <div class="agent-cell">
                  <el-avatar :size="30" :src="row.agentAvatar" class="agent-avatar">
                    {{ row.agentName ? row.agentName[0] : '?' }}
                  </el-avatar>
                  <span class="agent-name">{{ row.agentName || '-' }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="购买者" min-width="160">
              <template #default="{ row }">
                <div class="agent-cell">
                  <el-avatar :size="30" :src="row.buyerAvatar" class="agent-avatar">
                    {{ row.buyerName ? row.buyerName[0] : '?' }}
                  </el-avatar>
                  <span class="agent-name">{{ row.buyerName || '-' }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="类型" align="center" width="110">
              <template #default="{ row }">
                <el-tag size="small" effect="plain" class="type-tag">
                  {{ row.testTypeLabel || '-' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="订单金额" align="right">
              <template #default="{ row }">
                {{ formatCurrency(parseFloat(row.orderAmount || 0)) }}
              </template>
            </el-table-column>
            <el-table-column label="比例" align="center">
              <template #default="{ row }">
                {{ row.commissionRate || 0 }}%
              </template>
            </el-table-column>
            <el-table-column label="佣金" align="right">
              <template #default="{ row }">
                {{ formatCurrency(parseFloat(row.commissionAmount || 0)) }}
              </template>
            </el-table-column>
            <el-table-column label="状态" align="center">
              <template #default="{ row }">
                <el-tag :type="row.status === 'paid' ? 'success' : row.status === 'pending' ? 'warning' : 'info'" size="small">
                  {{ row.status === 'paid' ? '已结算' : row.status === 'pending' ? '待结算' : '已取消' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="时间">
              <template #default="{ row }">
                {{ row.createdAt ? new Date(row.createdAt * 1000).toLocaleString() : '-' }}
              </template>
            </el-table-column>
          </el-table>
          <div v-if="commissions.length === 0 && !loading" class="empty-placeholder">暂无佣金记录</div>
        </div>
      </div>

      <div v-else-if="activeTab === 'settings'" class="settings-section">
        <div class="settings-grid">
          <!-- 基本设置 -->
          <div class="settings-card">
            <div class="card-header">基本设置</div>
            <div class="setting-list">
              <div class="setting-row">
                <div class="info">
                  <p class="title">启用分销功能</p>
                  <p class="desc">关闭后新订单不再产生佣金，小程序个人中心推广中心卡片将隐藏</p>
                </div>
                <el-switch v-model="distEnabled" />
              </div>
              <div class="setting-row">
                <div class="info">
                  <p class="title">推广中心标题</p>
                  <p class="desc">小程序个人中心推广中心卡片的显示文字，可自定义</p>
                </div>
                <el-input v-model="promoCenterTitle" placeholder="推广中心" maxlength="20" show-word-limit class="promo-title-input" />
              </div>
            </div>
          </div>

          <!-- 测试佣金配置（各测试类型独立） -->
          <div class="settings-card full-width-card">
            <div class="card-header">测试佣金配置</div>
            <p class="card-desc">为每种测试类型独立设置佣金比例或固定金额，并可开启「无需付款」让用户完成测试即触发佣金。</p>
            <div class="ts-grid">
              <div v-for="item in testTypeItems" :key="item.key" class="ts-card">
                <div class="ts-head">
                  <span class="ts-name">{{ item.label }}</span>
                  <el-switch v-model="testSettings[item.key].enabled" size="small" />
                </div>
                <template v-if="testSettings[item.key].enabled">
                  <div class="ts-row">
                    <label>佣金类型</label>
                    <el-radio-group v-model="testSettings[item.key].commissionType" size="small">
                      <el-radio-button value="ratio">比例</el-radio-button>
                      <el-radio-button value="amount">固定金额</el-radio-button>
                    </el-radio-group>
                  </div>
                  <div class="ts-row">
                    <label>{{ testSettings[item.key].commissionType === 'ratio' ? '佣金比例 (%)' : '固定金额 (元)' }}</label>
                    <el-input-number
                      v-if="testSettings[item.key].commissionType === 'ratio'"
                      v-model="testSettings[item.key].commissionRate"
                      :min="0" :max="100" class="w-full"
                    />
                    <el-input-number
                      v-else
                      v-model="testSettings[item.key].commissionAmount"
                      :min="0" :precision="2" :step="0.1" class="w-full"
                    />
                  </div>
                  <div class="ts-nopay">
                    <div class="ts-nopay-info">
                      <p class="ts-nopay-title">无需付款触发</p>
                      <p class="ts-nopay-desc">用户完成测试即发放佣金，无需付款</p>
                    </div>
                    <el-switch v-model="testSettings[item.key].noPayment" size="small" />
                  </div>
                </template>
                <div v-else class="ts-disabled">已关闭，该类型不产生佣金</div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="save-actions">
          <el-button type="primary" color="#7c3aed" class="save-btn" @click="saveSettings" :loading="loading">保存配置</el-button>
        </div>
      </div>
    </div>
  </div>

  <!-- 累计收益明细弹窗 -->
  <el-dialog v-model="commDetailVisible" :title="`${commDetailAgent} 的佣金记录`" width="700px" destroy-on-close>
    <el-table :data="commDetailList" v-loading="commDetailLoading" style="width:100%">
      <el-table-column label="购买者" prop="inviteeName" />
      <el-table-column label="订单金额" align="right">
        <template #default="{ row }">¥{{ row.orderYuan }}</template>
      </el-table-column>
      <el-table-column label="佣金比例" align="center">
        <template #default="{ row }">{{ row.commissionRate || 0 }}%</template>
      </el-table-column>
      <el-table-column label="佣金" align="right">
        <template #default="{ row }">¥{{ row.commissionYuan }}</template>
      </el-table-column>
      <el-table-column label="状态" align="center">
        <template #default="{ row }">
          <el-tag :type="row.status === 'paid' ? 'success' : row.status === 'pending' ? 'warning' : 'info'" size="small">
            {{ row.status === 'paid' ? '已结算' : row.status === 'pending' ? '待结算' : '已取消' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="时间" min-width="150">
        <template #default="{ row }">
          {{ row.createdAt ? new Date(row.createdAt * 1000).toLocaleString() : '-' }}
        </template>
      </el-table-column>
    </el-table>
    <div v-if="commDetailList.length === 0 && !commDetailLoading" class="empty-placeholder" style="padding:40px">暂无佣金记录</div>
  </el-dialog>

  <!-- 团队列表弹窗 -->
  <el-dialog v-model="teamDetailVisible" :title="`${teamDetailAgent} 的团队成员`" width="600px" destroy-on-close>
    <el-table :data="teamDetailList" v-loading="teamDetailLoading" style="width:100%">
      <el-table-column label="成员">
        <template #default="{ row }">
          <div class="agent-cell">
            <el-avatar :size="30" :src="row.inviteeAvatar">{{ row.inviteeName ? row.inviteeName[0] : '?' }}</el-avatar>
            <span style="margin-left:8px">{{ row.inviteeName || '未知' }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="状态" align="center">
        <template #default="{ row }">
          <el-tag :type="row.status === 'active' ? 'success' : row.status === 'overridden' ? 'warning' : 'info'" size="small">
            {{ row.status === 'active' ? '绑定中' : row.status === 'overridden' ? '已替换' : '已过期' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="剩余天数" align="center">
        <template #default="{ row }">
          {{ row.status === 'active' ? `${row.remainDays}天` : '-' }}
        </template>
      </el-table-column>
      <el-table-column label="绑定时间" min-width="150">
        <template #default="{ row }">
          {{ row.createdAt ? new Date(row.createdAt * 1000).toLocaleString() : '-' }}
        </template>
      </el-table-column>
    </el-table>
    <div v-if="teamDetailList.length === 0 && !teamDetailLoading" class="empty-placeholder" style="padding:40px">暂无团队成员</div>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch, computed } from 'vue'
import {
  Refresh, User, Money, Clock, Connection, Search,
  CaretTop, TrendCharts, PieChart, UserFilled, List
} from '@element-plus/icons-vue'
import { request } from '@/utils/request'
import { ElMessage } from 'element-plus'
import { formatCurrency } from '@/utils/format'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { LineChart, PieChart as EchartsPieChart } from 'echarts/charts'
import { GridComponent, TooltipComponent, LegendComponent } from 'echarts/components'
import VChart from 'vue-echarts'

use([CanvasRenderer, LineChart, EchartsPieChart, GridComponent, TooltipComponent, LegendComponent])

const activeTab = ref('overview')
const distSearch = ref('')
const commFilter = ref('')
const loading = ref(false)

const tabs = [
  { label: '数据概览', value: 'overview' },
  { label: '分销商', value: 'distributors' },
  { label: '佣金记录', value: 'commissions' },
  { label: '分销设置', value: 'settings' }
]

const commOptions = [
  { label: '全部', value: '' },
  { label: '待结算', value: 'pending' },
  { label: '已结算', value: 'paid' },
  { label: '已取消', value: 'cancelled' }
]

// 数据概览
const overview = reactive({
  totalAgents: 0,
  todayAgents: 0,
  totalCommission: '0.00',
  todayCommission: '0.00',
  pendingCommission: '0.00',
  pendingCount: 0,
  bindingCount: 0
})
const commissionTrend = ref<Array<{ date: string; amount: number }>>([])
const productCommissionSeries = ref<Array<{ label: string; value: number }>>([])
const overviewTopDistributors = ref<any[]>([])

const hasCommissionTrend = computed(() =>
  commissionTrend.value.some(item => Number(item.amount || 0) > 0)
)

const hasProductSeries = computed(() =>
  productCommissionSeries.value.some(item => Number(item.value || 0) > 0)
)

const commissionTrendOption = computed(() => ({
  tooltip: { trigger: 'axis' },
  grid: { left: 40, right: 20, top: 24, bottom: 30 },
  xAxis: {
    type: 'category',
    boundaryGap: false,
    data: commissionTrend.value.map(item => item.date.slice(5)),
    axisLine: { lineStyle: { color: '#e5e7eb' } },
    axisLabel: { color: '#6b7280' }
  },
  yAxis: {
    type: 'value',
    axisLine: { lineStyle: { color: '#e5e7eb' } },
    splitLine: { lineStyle: { color: '#f3f4f6' } },
    axisLabel: {
      color: '#6b7280',
      formatter: (value: number) => `¥${value}`
    }
  },
  series: [
    {
      name: '佣金',
      type: 'line',
      smooth: true,
      showSymbol: true,
      symbolSize: 6,
      itemStyle: { color: '#7c3aed' },
      areaStyle: { color: 'rgba(124, 58, 237, 0.08)' },
      data: commissionTrend.value.map(item => Number(item.amount || 0))
    }
  ]
}))

const productSeriesOption = computed(() => ({
  tooltip: {
    trigger: 'item',
    formatter: ({ name, value, percent }: { name: string; value: number; percent: number }) =>
      `${name}<br/>佣金：¥${value}<br/>占比：${percent}%`
  },
  legend: {
    bottom: 0,
    left: 'center',
    icon: 'circle',
    textStyle: { color: '#6b7280', fontSize: 12 }
  },
  series: [
    {
      name: '产品佣金',
      type: 'pie',
      radius: ['45%', '70%'],
      center: ['50%', '42%'],
      avoidLabelOverlap: true,
      itemStyle: {
        borderRadius: 8,
        borderColor: '#fff',
        borderWidth: 2
      },
      label: {
        show: true,
        formatter: '{b}\n¥{c}',
        color: '#374151',
        fontSize: 12
      },
      labelLine: {
        length: 12,
        length2: 10
      },
      data: productCommissionSeries.value.map(item => ({
        name: item.label,
        value: Number(item.value || 0)
      })),
      color: ['#7c3aed', '#22c55e', '#3b82f6', '#f97316', '#94a3b8']
    }
  ]
}))

const distributors = ref<any[]>([])
const commissions = ref<any[]>([])

// 累计收益明细弹窗
const commDetailVisible = ref(false)
const commDetailLoading = ref(false)
const commDetailAgent = ref('')
const commDetailList = ref<any[]>([])

// 团队列表弹窗
const teamDetailVisible = ref(false)
const teamDetailLoading = ref(false)
const teamDetailAgent = ref('')
const teamDetailList = ref<any[]>([])

const distEnabled = ref(true)
const promoCenterTitle = ref('推广中心')

const testTypeItems = [
  { key: 'face',   label: '人脸分析' },
  { key: 'mbti',   label: 'MBTI 测试' },
  { key: 'disc',   label: 'DISC 测试' },
  { key: 'pdp',    label: 'PDP 测试' },
]
type TestSetting = { enabled: boolean; commissionType: 'ratio' | 'amount'; commissionRate: number; commissionAmount: number; noPayment: boolean }
const makeDefaultTs = (): TestSetting => ({ enabled: true, commissionType: 'ratio', commissionRate: 90, commissionAmount: 0, noPayment: false })
const testSettings = reactive<Record<string, TestSetting>>({
  face:   makeDefaultTs(),
  mbti:   makeDefaultTs(),
  disc:   makeDefaultTs(),
  pdp:   makeDefaultTs(),
})

// 加载数据概览
const loadOverview = async () => {
  try {
    const [overviewRes, distributorsRes] = await Promise.all([
      request.get('/admin/distribution/overview'),
      request.get('/admin/distribution/distributors', { params: { pageSize: 100 } })
    ])

    if (overviewRes.code === 200 && overviewRes.data) {
      Object.assign(overview, overviewRes.data)
      commissionTrend.value = overviewRes.data.commissionTrend || []
      productCommissionSeries.value = overviewRes.data.productCommissionSeries || []
    } else {
      commissionTrend.value = []
      productCommissionSeries.value = []
    }

    if (distributorsRes.code === 200 && distributorsRes.data) {
      overviewTopDistributors.value = [...(distributorsRes.data.list || [])]
        .sort((a: any, b: any) => parseFloat(b.totalCommission || 0) - parseFloat(a.totalCommission || 0))
        .slice(0, 5)
    } else {
      overviewTopDistributors.value = []
    }
  } catch (error: any) {
    console.error('加载数据概览失败:', error)
    commissionTrend.value = []
    productCommissionSeries.value = []
    overviewTopDistributors.value = []
  }
}

// 加载分销商列表
const loadDistributors = async () => {
  loading.value = true
  try {
    const response: any = await request.get('/admin/distribution/distributors', {
      params: {
        search: distSearch.value
      }
    })
    if (response.code === 200 && response.data) {
      distributors.value = response.data.list || []
    }
  } catch (error: any) {
    console.error('加载分销商列表失败:', error)
  } finally {
    loading.value = false
  }
}

// 打开某分销商的佣金明细
const openCommDetail = async (row: any) => {
  commDetailAgent.value = row.agentName || '-'
  commDetailList.value = []
  commDetailVisible.value = true
  commDetailLoading.value = true
  try {
    const res: any = await request.get('/admin/distribution/commissions', { params: { inviterId: row.id, pageSize: 100 } })
    if (res.code === 200 && res.data) commDetailList.value = res.data.list || []
  } catch (e) {}
  commDetailLoading.value = false
}

// 打开某分销商的团队成员
const openTeamDetail = async (row: any) => {
  teamDetailAgent.value = row.agentName || '-'
  teamDetailList.value = []
  teamDetailVisible.value = true
  teamDetailLoading.value = true
  try {
    const res: any = await request.get('/admin/distribution/bindings', { params: { inviterId: row.id, pageSize: 100 } })
    if (res.code === 200 && res.data) teamDetailList.value = res.data.list || []
  } catch (e) {}
  teamDetailLoading.value = false
}

// 加载佣金记录
const loadCommissions = async () => {
  loading.value = true
  try {
    const response: any = await request.get('/admin/distribution/commissions', {
      params: {
        status: commFilter.value
      }
    })
    if (response.code === 200 && response.data) {
      commissions.value = (response.data.list || []).map((item: any) => ({
        ...item,
        agentName: item.inviterName || `用户${item.inviterId || ''}`,
        agentAvatar: item.inviterAvatar || '',
        buyerName: item.inviteeName || `用户${item.inviteeId || ''}`,
        buyerAvatar: item.inviteeAvatar || '',
        testType: item.testType || 'other',
        testTypeLabel: item.testTypeLabel || '其他',
        orderAmount: item.orderYuan ?? item.orderAmount ?? '0.00',
        commissionAmount: item.commissionYuan ?? item.commissionAmount ?? '0.00'
      }))
    }
  } catch (error: any) {
    console.error('加载佣金记录失败:', error)
  } finally {
    loading.value = false
  }
}

// 加载分销设置
const loadSettings = async () => {
  try {
    const response: any = await request.get('/admin/distribution/settings')
    if (response.code === 200 && response.data) {
      distEnabled.value = response.data.enabled ?? true
      promoCenterTitle.value = response.data.promoCenterTitle ?? '推广中心'
      const ts = response.data.testSettings ?? {}
      testTypeItems.forEach(({ key }) => {
        const s = ts[key] ?? {}
        testSettings[key] = {
          enabled:        s.enabled        !== false,
          commissionType: s.commissionType ?? 'ratio',
          commissionRate: s.commissionRate  ?? 90,
          commissionAmount: s.commissionAmount ?? 0,
          noPayment:      s.noPayment      ?? false,
        }
      })
    }
  } catch (error: any) {
    console.error('加载分销设置失败:', error)
  }
}

// 保存分销设置
const saveSettings = async () => {
  loading.value = true
  try {
    const response: any = await request.put('/admin/distribution/settings', {
      enabled: distEnabled.value,
      promoCenterTitle: promoCenterTitle.value || '推广中心',
      testSettings: Object.fromEntries(
        testTypeItems.map(({ key }) => [key, testSettings[key]])
      ),
    })
    if (response.code === 200) {
      ElMessage.success('分销设置已保存')
    }
  } catch (error: any) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    loading.value = false
  }
}

// 监听tab切换
watch(activeTab, (newTab) => {
  if (newTab === 'overview') {
    loadOverview()
  } else if (newTab === 'distributors') {
    loadDistributors()
  } else if (newTab === 'commissions') {
    loadCommissions()
  } else if (newTab === 'settings') {
    loadSettings()
  }
})

watch(commFilter, () => {
  if (activeTab.value === 'commissions') {
    loadCommissions()
  }
})

const refresh = async () => {
  if (activeTab.value === 'overview') {
    await loadOverview()
  } else if (activeTab.value === 'distributors') {
    await loadDistributors()
  } else if (activeTab.value === 'commissions') {
    await loadCommissions()
  }
  ElMessage.success('数据已刷新')
}

onMounted(() => {
  loadOverview()
})
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

.custom-tabs-container {
  background-color: #f3f4f6;
  padding: 4px;
  border-radius: 8px;
  display: flex;
  margin-bottom: 24px;
  width: 100%;

  .custom-tabs {
    display: flex;
    gap: 4px;
    width: 100%;

    .tab-item {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 8px 0;
      font-size: 13px;
      color: #6b7280;
      cursor: pointer;
      border-radius: 6px;
      transition: all 0.2s;
      white-space: nowrap;

      &:hover {
        color: #111827;
      }

      &.active {
        background-color: #fff;
        color: #111827;
        font-weight: 600;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
      }
    }
  }
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
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
    .trend-tag {
      display: flex;
      align-items: center;
      gap: 2px;
      font-size: 12px;
      margin-top: 8px;
      font-weight: 500;
      
      &.up { color: #22c55e; }
      &.warning { color: #f59e0b; }
      &.danger { color: #ef4444; }
      &.neutral { color: #3b82f6; }
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

    &.purple { background-color: #faf5ff; color: #a855f7; }
    &.green { background-color: #f0fdf4; color: #22c55e; }
    &.orange { background-color: #fffbeb; color: #f59e0b; }
    &.blue { background-color: #eff6ff; color: #3b82f6; }
    &.red { background-color: #fef2f2; color: #ef4444; }
  }
}

.charts-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

.chart-card {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #f3f4f6;
  padding: 20px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);

  .card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 15px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 20px;
    
    .el-icon {
      color: #6b7280;
      font-size: 18px;
    }
  }

  .empty-chart {
    height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    font-size: 13px;
    background-color: #f9fafb;
    border-radius: 8px;
  }
}

.trend-chart-echarts {
  height: 180px;
}

.overview-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-height: 180px;
}

.overview-list-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 14px;
  border-radius: 8px;
  background-color: #f9fafb;
  border: 1px solid #f3f4f6;
}

.overview-main {
  min-width: 0;
  flex: 1;
}

.overview-side {
  min-width: 120px;
  text-align: right;
  display: flex;
  flex-direction: column;
  gap: 4px;

  strong {
    font-size: 14px;
    color: #111827;
  }

  span {
    font-size: 12px;
    color: #6b7280;
  }
}

.overview-meta {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;

  strong {
    font-size: 13px;
    color: #111827;
    font-weight: 600;
  }

  span {
    font-size: 12px;
    color: #6b7280;
  }
}

.overview-pair {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.pair-arrow {
  font-size: 12px;
  color: #9ca3af;
}

.withdraw-stats {
  display: flex;
  flex-direction: column;
  gap: 12px;
  
  .stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-radius: 8px;
    
    span { font-size: 13px; }
    strong { font-size: 16px; font-weight: 700; }
    
    &.green { background-color: #f0fdf4; color: #166534; }
    &.orange { background-color: #fffbeb; color: #92400e; }
    &.blue { background-color: #eff6ff; color: #1e40af; }
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

  .search-input {
    max-width: 320px;
    :deep(.el-input__wrapper) {
      border-radius: 6px;
      background-color: #f9fafb;
      box-shadow: none;
      border: 1px solid #e5e7eb;
    }
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

      &.active {
        background-color: #7c3aed;
        color: #fff;
        font-weight: 500;
      }
    }
  }

  .action-btn {
    font-size: 13px;
    border-radius: 6px;
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
}

.agent-cell {
  display: flex;
  align-items: center;
  gap: 10px;

  .agent-avatar {
    flex-shrink: 0;
    font-size: 14px;
    background-color: #ede9fe;
    color: #7c3aed;
  }

  .agent-name {
    font-size: 13px;
    font-weight: 500;
    color: #111827;
  }
}

.settings-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
  width: 100%;
}

.settings-card {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #f3f4f6;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);

  &.full-width-card { grid-column: 1 / -1; }

  .card-header {
    font-size: 16px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 20px;
  }

  .card-header-row {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;
    .card-header { margin-bottom: 0; }
  }
  .card-desc { font-size: 12px; color: #6b7280; margin: 0 0 20px; }

  .ts-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;
    .ts-card {
      background: #f9fafb; border-radius: 8px; padding: 16px; display: flex; flex-direction: column; gap: 12px;
      .ts-head {
        display: flex; justify-content: space-between; align-items: center;
        .ts-name { font-size: 13px; font-weight: 600; color: #374151; }
      }
      .ts-row {
        display: flex; flex-direction: column; gap: 6px;
        label { font-size: 12px; color: #6b7280; }
      }
      .ts-nopay {
        display: flex; justify-content: space-between; align-items: center;
        padding: 10px 12px; background: #ede9fe; border-radius: 6px; margin-top: 4px;
        .ts-nopay-info {
          .ts-nopay-title { font-size: 12px; font-weight: 600; color: #5b21b6; margin: 0; }
          .ts-nopay-desc  { font-size: 11px; color: #7c3aed; margin: 2px 0 0; }
        }
      }
      .ts-disabled { font-size: 12px; color: #9ca3af; text-align: center; padding: 16px 0; }
    }
  }

  .setting-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .setting-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    background-color: #f9fafb;
    border-radius: 8px;

    .info {
      .title { font-size: 14px; font-weight: 600; color: #111827; margin: 0; }
      .desc { font-size: 12px; color: #6b7280; margin: 4px 0 0; }
    }

    .promo-title-input {
      width: 180px;
      flex-shrink: 0;
    }
  }

  .form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    
    &.three-cols { grid-template-columns: repeat(2, 1fr); }

    .form-item {
      display: flex;
      flex-direction: column;
      gap: 8px;
      
      label { font-size: 13px; font-weight: 500; color: #374151; }
    }

    .form-hint {
      font-size: 12px;
      color: #9ca3af;
      margin: 8px 0 0;
      line-height: 1.4;
    }
  }
}

.save-actions {
  margin-top: 24px;
  .save-btn {
    height: 42px;
    padding: 0 40px;
    border-radius: 8px;
    font-weight: 600;
  }
}

.empty-placeholder {
  padding: 60px;
  text-align: center;
  color: #9ca3af;
  font-size: 14px;
}

.w-full { width: 100%; }

@media (max-width: 1200px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .charts-grid { grid-template-columns: 1fr; }
  .settings-card .form-grid.three-cols { grid-template-columns: 1fr; }
}
</style>
