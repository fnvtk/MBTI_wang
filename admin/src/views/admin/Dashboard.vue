<template>
  <div class="dashboard-container" v-loading="loading">
    <!-- 统计卡片 -->
    <div class="stats-grid">
      <div class="stat-card">
            <div class="stat-info">
              <div class="stat-label">总用户数</div>
              <div class="stat-value">{{ stats.totalUsers }}</div>
            </div>
            <div class="stat-icon blue">
              <el-icon><User /></el-icon>
            </div>
          </div>

      <div class="stat-card">
            <div class="stat-info">
              <div class="stat-label">已完成测试</div>
              <div class="stat-value">{{ stats.testsCompleted }}</div>
            </div>
            <div class="stat-icon green">
              <el-icon><Document /></el-icon>
            </div>
          </div>

      <div class="stat-card">
            <div class="stat-info">
              <div class="stat-label">今日活跃</div>
              <div class="stat-value">{{ stats.activeToday }}</div>
            </div>
            <div class="stat-icon purple">
              <el-icon><TrendCharts /></el-icon>
            </div>
          </div>

      <div class="stat-card">
            <div class="stat-info">
              <div class="stat-label">待审核</div>
              <div class="stat-value">{{ stats.pendingReviews }}</div>
            </div>
            <div class="stat-icon orange">
              <el-icon><User /></el-icon>
            </div>
          </div>
    </div>

    <!-- 测试趋势折线图 -->
    <div class="activity-section">
      <div class="section-header">
        <h2 class="section-title">测试趋势</h2>
        <p class="section-subtitle">最近 14 天人脸分析、MBTI、PDP、DISC 等关键测试的完成情况</p>
      </div>

      <div class="trend-chart-wrapper" v-if="testTrends.length">
        <VChart class="trend-chart-echarts" :option="chartOption" autoresize />
      </div>

      <div class="empty-state" v-else>
        <span>暂无测试趋势数据</span>
      </div>
    </div>

    <!-- 邀请二维码 -->
    <div class="activity-section invite-section">
      <div class="section-header">
        <h2 class="section-title">专属邀请小程序码</h2>
        <p class="section-subtitle">生成专属邀请二维码，员工/客户扫码即可进入小程序完成测试</p>
        <el-button size="small" type="primary" @click="loadInviteQrcode" :loading="inviteLoading">
          重新生成
        </el-button>
      </div>

      <div class="invite-content">
        <div v-if="inviteQrcode">
          <img :src="inviteQrcode" alt="邀请小程序码" class="invite-qrcode" />
          <div class="invite-tip">右键/长按保存二维码，用于宣传物料或群内邀请</div>
        </div>
        <div v-else class="empty-state">
          <el-button type="primary" size="small" @click="loadInviteQrcode" :loading="inviteLoading">
            生成邀请二维码
          </el-button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import {
  User,
  Document,
  TrendCharts
} from '@element-plus/icons-vue'
import { request } from '@/utils/request'
import { ElMessage } from 'element-plus'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { LineChart } from 'echarts/charts'
import { GridComponent, TooltipComponent, LegendComponent } from 'echarts/components'
import VChart from 'vue-echarts'

use([CanvasRenderer, LineChart, GridComponent, TooltipComponent, LegendComponent])

const stats = reactive({
  totalUsers: 0,
  testsCompleted: 0,
  activeToday: 0,
  pendingReviews: 0
})

const testTrends = ref<
  Array<{ date: string; face: number; mbti: number; pdp: number; disc: number; total: number }>
>([])
const loading = ref(false)
const inviteLoading = ref(false)
const inviteQrcode = ref<string>('')

const chartOption = computed(() => {
  const dates = testTrends.value.map(d => d.date.slice(5))
  return {
    tooltip: {
      trigger: 'axis'
    },
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

// 加载数据
const loadData = async () => {
  loading.value = true
  try {
    const response: any = await request.get('/admin/dashboard')
    if (response.code === 200 && response.data) {
      stats.totalUsers = response.data.totalUsers || 0
      stats.testsCompleted = response.data.testsCompleted || 0
      stats.activeToday = response.data.activeToday || 0
      stats.pendingReviews = response.data.pendingReviews || 0
      testTrends.value = response.data.testTrends || []
    }
  } catch (error: any) {
    console.error('加载数据失败:', error)
    ElMessage.error(error.message || '加载数据失败')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadData()
})

// 加载邀请小程序码
const loadInviteQrcode = async () => {
  if (inviteLoading.value) return
  inviteLoading.value = true
  try {
    const res: any = await request.get('/admin/invite/qrcode')
    const data = res.data ?? res
    if (data && data.qrcode) {
      inviteQrcode.value = data.qrcode
    } else if (data.code === 200 && data.data?.qrcode) {
      inviteQrcode.value = data.data.qrcode
    } else {
      ElMessage.error('生成邀请二维码失败')
    }
  } catch (error: any) {
    console.error('生成邀请二维码失败:', error)
    ElMessage.error(error?.message || '生成邀请二维码失败')
  } finally {
    inviteLoading.value = false
  }
}
</script>

<style scoped lang="scss">
.dashboard-container {
  padding: 24px;
  background-color: #f9fafb;
  min-height: 100vh;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 20px;
  margin-bottom: 28px;
}

.stat-card {
  background: #fff;
  border-radius: 10px;
  padding: 20px 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  border: 1px solid #f3f4f6;
  transition: transform 0.2s, box-shadow 0.2s;

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px -2px rgba(0, 0, 0, 0.1);
  }

    .stat-info {
      .stat-label {
        font-size: 14px;
      color: #6b7280;
      margin-bottom: 6px;
      }

      .stat-value {
      font-size: 28px;
        font-weight: 700;
        color: #111827;
      line-height: 1;
      }
    }

    .stat-icon {
    width: 42px;
    height: 42px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;

      &.blue {
        background-color: #eff6ff;
        color: #3b82f6;
      }

      &.green {
        background-color: #f0fdf4;
        color: #22c55e;
      }

      &.purple {
        background-color: #faf5ff;
        color: #a855f7;
      }

      &.orange {
        background-color: #fffbeb;
        color: #f59e0b;
      }
  }
}

.activity-section {
  background: #fff;
  border-radius: 10px;
  padding: 28px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  border: 1px solid #f3f4f6;

  .section-header {
    margin-bottom: 24px;

    .section-title {
      font-size: 20px;
      font-weight: 700;
      color: #111827;
      margin: 0 0 6px 0;
    }

    .section-subtitle {
      font-size: 13px;
      color: #6b7280;
      margin: 0;
    }
  }

  .trend-chart-wrapper {
    margin-top: 12px;
  }

  .trend-chart-echarts {
    width: 100%;
    height: 260px;
  }
}

.invite-section {
  margin-top: 24px;

  .section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    .section-title {
      margin-bottom: 0;
    }
  }

  .invite-content {
    display: flex;
    align-items: center;
    gap: 24px;
    margin-top: 8px;
  }

  .invite-qrcode {
    width: 160px;
    height: 160px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #f9fafb;
  }

  .invite-tip {
    font-size: 13px;
    color: #6b7280;
    margin-top: 8px;
  }
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .activity-section {
    padding: 20px;
      }
    }

@media (max-width: 480px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>
