<template>
  <div class="page-container" :class="{ 'is-embedded': embedded }">
    <div v-if="!embedded" class="page-header">
      <div class="header-left">
        <h2>分销管理</h2>
        <p class="subtitle">全平台分销商、佣金、提现审核与分销设置</p>
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
      <!-- 数据概览 -->
      <div v-if="activeTab === 'overview'" class="overview-section">
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-info">
              <div class="stat-label">累计佣金（全平台）</div>
              <div class="stat-value">¥{{ overview.totalCommission }}</div>
            </div>
            <div class="stat-icon purple"><el-icon><Money /></el-icon></div>
          </div>
          <div class="stat-card">
            <div class="stat-info">
              <div class="stat-label">已结算佣金</div>
              <div class="stat-value">¥{{ overview.paidCommission }}</div>
            </div>
            <div class="stat-icon green"><el-icon><Money /></el-icon></div>
          </div>
          <div class="stat-card">
            <div class="stat-info">
              <div class="stat-label">待审核提现</div>
              <div class="stat-value">¥{{ overview.pendingWithdraw }}</div>
            </div>
            <div class="stat-icon orange"><el-icon><Clock /></el-icon></div>
          </div>
          <div class="stat-card">
            <div class="stat-info">
              <div class="stat-label">有效绑定人数</div>
              <div class="stat-value">{{ overview.bindingCount }}</div>
            </div>
            <div class="stat-icon blue"><el-icon><User /></el-icon></div>
          </div>
        </div>
        <div class="two-cols">
          <div class="content-card small-card">
            <div class="card-title">佣金来源分布</div>
            <div class="dist-row">
              <span>个人版</span><strong>¥{{ overview.personalCommission }}</strong>
            </div>
            <div class="dist-row">
              <span>企业版</span><strong>¥{{ overview.enterpriseCommission }}</strong>
            </div>
          </div>
          <div class="content-card small-card">
            <div class="card-title">今日数据</div>
            <div class="dist-row">
              <span>今日结算佣金</span><strong>¥{{ overview.todayCommission }}</strong>
            </div>
            <div class="dist-row">
              <span>全部订单数</span><strong>{{ overview.totalOrders }}</strong>
            </div>
          </div>
        </div>
      </div>

      <!-- 提现管理 -->
      <div v-else-if="activeTab === 'withdrawals'" class="table-section">
        <div class="content-card">
          <div class="toolbar">
            <div class="filter-group">
              <div
                v-for="item in withOptions"
                :key="item.value"
                :class="['filter-item', { active: withFilter === item.value }]"
                @click="withFilter = item.value"
              >{{ item.label }}</div>
            </div>
          </div>
          <el-table :data="withdrawals" style="width:100%" class="custom-table" v-loading="loading">
            <el-table-column label="用户" min-width="140">
              <template #default="{ row }">
                <div class="agent-cell">
                  <el-avatar :size="32" :src="row.avatar">{{ row.nickname ? row.nickname[0] : '?' }}</el-avatar>
                  <span class="agent-name">{{ row.nickname }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="申请金额" align="right">
              <template #default="{ row }">¥{{ row.amountYuan }}</template>
            </el-table-column>
            <el-table-column label="状态" align="center">
              <template #default="{ row }">
                <el-tag :type="statusTagType(row.status)" size="small">
                  {{ statusLabel(row.status) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="备注" prop="remark" />
            <el-table-column label="申请时间" min-width="160">
              <template #default="{ row }">{{ fmtTime(row.createdAt) }}</template>
            </el-table-column>
            <el-table-column label="操作" align="center" width="160">
              <template #default="{ row }">
                <template v-if="Number(row.status) === 0">
                  <el-button size="small" type="success" @click="approve(row)">通过</el-button>
                  <el-button size="small" type="danger"  @click="openReject(row)">拒绝</el-button>
                </template>
                <span v-else style="color:#9ca3af;font-size:12px">已处理</span>
              </template>
            </el-table-column>
          </el-table>
          <div v-if="withdrawals.length === 0 && !loading" class="empty-placeholder">暂无提现申请</div>
        </div>
      </div>

      <!-- 佣金记录 -->
      <div v-else-if="activeTab === 'commissions'" class="table-section">
        <div class="content-card">
          <div class="toolbar">
            <div class="filter-group">
              <div
                v-for="item in commOptions"
                :key="item.value"
                :class="['filter-item', { active: commFilter === item.value }]"
                @click="commFilter = item.value"
              >{{ item.label }}</div>
            </div>
          </div>
          <el-table :data="commissions" style="width:100%" class="custom-table" v-loading="loading">
            <el-table-column label="推荐人" prop="inviterName" />
            <el-table-column label="购买者" prop="inviteeName" />
            <el-table-column label="企业" prop="enterpriseName" />
            <el-table-column label="订单金额" align="right">
              <template #default="{ row }">¥{{ row.orderYuan }}</template>
            </el-table-column>
            <el-table-column label="比例" align="center">
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
            <el-table-column label="时间" min-width="160">
              <template #default="{ row }">{{ fmtTime(row.createdAt) }}</template>
            </el-table-column>
          </el-table>
          <div v-if="commissions.length === 0 && !loading" class="empty-placeholder">暂无佣金记录</div>
        </div>
      </div>

      <!-- 分销设置 -->
      <div v-else-if="activeTab === 'settings'" class="settings-section">
        <div class="settings-grid">
          <div class="settings-card">
            <div class="card-header">基本设置</div>
            <div class="setting-list">
              <div class="setting-row">
                <div class="info">
                  <p class="title">启用分销功能（个人版全局）</p>
                  <p class="desc">关闭后新订单不再产生佣金</p>
                </div>
                <el-switch v-model="distEnabled" />
              </div>
              <div class="setting-row">
                <div class="info">
                  <p class="title">推广中心标题</p>
                  <p class="desc">小程序个人中心推广卡片的显示文字</p>
                </div>
                <el-input v-model="promoCenterTitle" placeholder="推广中心" maxlength="20" show-word-limit class="promo-title-input" />
              </div>
            </div>
          </div>
          <div class="settings-card">
            <div class="card-header">提现设置</div>
            <div class="setting-list" style="margin-bottom:16px">
              <div class="setting-row">
                <div class="info">
                  <p class="title">开启提现审核</p>
                  <p class="desc">开启后每笔提现申请需管理员人工审核才可打款</p>
                </div>
                <el-switch v-model="requireAudit" />
              </div>
            </div>
            <div class="form-grid">
              <div class="form-item">
                <label>最低提现金额 (元)</label>
                <el-input-number v-model="minWithdraw" :min="1" :max="200" :precision="2" class="w-full" />
              </div>
              <div class="form-item">
                <label>最高提现金额 (元)</label>
                <el-input-number v-model="maxWithdraw" :min="0" :max="200" :precision="2" class="w-full" />
              </div>
              <div class="form-item">
                <label>提现手续费 (%)</label>
                <el-input-number v-model="withdrawFee" :min="0" :max="100" class="w-full" />
              </div>
            </div>
          </div>

          <!-- 测试佣金配置 -->
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

  <!-- 拒绝理由弹窗 -->
  <el-dialog v-model="rejectVisible" title="拒绝提现" width="400px" destroy-on-close>
    <el-input v-model="rejectNote" type="textarea" :rows="3" placeholder="请输入拒绝理由（可选）" />
    <template #footer>
      <el-button @click="rejectVisible = false">取消</el-button>
      <el-button type="danger" @click="confirmReject" :loading="loading">确认拒绝</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue'
import { Refresh, User, Money, Clock } from '@element-plus/icons-vue'
import { request } from '@/utils/request'
import { ElMessage } from 'element-plus'

withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false })

const activeTab = ref('overview')
const loading = ref(false)
const commFilter = ref('')
const withFilter = ref('')

const tabs = [
  { label: '数据概览', value: 'overview' },
  { label: '提现管理', value: 'withdrawals' },
  { label: '佣金记录', value: 'commissions' },
  { label: '分销设置', value: 'settings' },
]

const commOptions = [
  { label: '全部', value: '' },
  { label: '待结算', value: 'pending' },
  { label: '已结算', value: 'paid' },
  { label: '已取消', value: 'cancelled' },
]

const withOptions = [
  { label: '全部', value: '' },
  { label: '待审核', value: 'pending' },
  { label: '已通过', value: 'approved' },
  { label: '已拒绝', value: 'rejected' },
  { label: '已完成', value: 'completed' },
]

const overview = reactive({
  totalCommission: '0.00',
  paidCommission: '0.00',
  frozenCommission: '0.00',
  personalCommission: '0.00',
  enterpriseCommission: '0.00',
  totalOrders: 0,
  bindingCount: 0,
  pendingWithdraw: '0.00',
  todayCommission: '0.00',
})

const withdrawals = ref<any[]>([])
const commissions = ref<any[]>([])

const distEnabled = ref(true)
const promoCenterTitle = ref('推广中心')
const minWithdraw = ref(1)
const maxWithdraw = ref(0)
const requireAudit = ref(true)
const withdrawFee = ref(0)

const testTypeItems = [
  { key: 'face',   label: '人脸分析' },
  { key: 'mbti',   label: 'MBTI 测试' },
  { key: 'sbti',   label: 'SBTI 测试' },
  { key: 'disc',   label: 'DISC 测试' },
  { key: 'pdp',    label: 'PDP 测试' },
]
type TestSetting = { enabled: boolean; commissionType: 'ratio' | 'amount'; commissionRate: number; commissionAmount: number; noPayment: boolean }
const makeDefaultTs = (): TestSetting => ({ enabled: true, commissionType: 'ratio', commissionRate: 90, commissionAmount: 0, noPayment: false })
const testSettings = reactive<Record<string, TestSetting>>({
  face:  makeDefaultTs(),
  mbti:  makeDefaultTs(),
  sbti:  makeDefaultTs(),
  disc:  makeDefaultTs(),
  pdp:   makeDefaultTs(),
})

// 拒绝弹窗
const rejectVisible = ref(false)
const rejectNote = ref('')
const rejectTarget = ref<any>(null)

const fmtTime = (ts: number) => ts ? new Date(ts * 1000).toLocaleString() : '-'
// 提现状态：0审核中、1已驳回、2待收款、3已收款、4已过期
const statusLabel = (s: any) => {
  const code = Number(s)
  switch (code) {
    case 0: return '审核中'
    case 1: return '已驳回'
    case 2: return '待收款'
    case 3: return '已收款'
    case 4: return '已过期'
    default: return '未知'
  }
}
const statusTagType = (s: any) => {
  const code = Number(s)
  switch (code) {
    case 0: return 'warning' // 审核中
    case 1: return 'danger'  // 已驳回
    case 2: return 'info'    // 待收款
    case 3: return 'success' // 已收款
    case 4: return 'info'    // 已过期
    default: return 'info'
  }
}

// ── 数据概览
const loadOverview = async () => {
  try {
    const res: any = await request.get('/superadmin/distribution/overview')
    if (res.code === 200 && res.data) Object.assign(overview, res.data)
  } catch (e) {}
}

// ── 提现列表
const loadWithdrawals = async () => {
  loading.value = true
  try {
    const res: any = await request.get('/superadmin/distribution/withdrawals', {
      params: { status: withFilter.value, pageSize: 100 }
    })
    if (res.code === 200 && res.data) withdrawals.value = res.data.list || []
  } catch (e) {}
  loading.value = false
}

// ── 审核通过
const approve = async (row: any) => {
  loading.value = true
  try {
    const res: any = await request.post(`/superadmin/distribution/withdrawals/${row.id}/approve`)
    if (res.code === 200) {
      ElMessage.success('已通过')
      loadWithdrawals()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e: any) { ElMessage.error(e.message || '操作失败') }
  loading.value = false
}

// ── 打开拒绝弹窗
const openReject = (row: any) => {
  rejectTarget.value = row
  rejectNote.value = ''
  rejectVisible.value = true
}

// ── 确认拒绝
const confirmReject = async () => {
  if (!rejectTarget.value) return
  loading.value = true
  try {
    const res: any = await request.post(
      `/superadmin/distribution/withdrawals/${rejectTarget.value.id}/reject`,
      { note: rejectNote.value }
    )
    if (res.code === 200) {
      ElMessage.success('已拒绝')
      rejectVisible.value = false
      loadWithdrawals()
    } else {
      ElMessage.error(res.message || '操作失败')
    }
  } catch (e: any) { ElMessage.error(e.message || '操作失败') }
  loading.value = false
}

// ── 佣金记录
const loadCommissions = async () => {
  loading.value = true
  try {
    const res: any = await request.get('/superadmin/distribution/commissions', {
      params: { status: commFilter.value, pageSize: 100 }
    })
    if (res.code === 200 && res.data) commissions.value = res.data.list || []
  } catch (e) {}
  loading.value = false
}

// ── 加载设置
const loadSettings = async () => {
  try {
    const res: any = await request.get('/superadmin/distribution/settings')
    if (res.code === 200 && res.data) {
      const d = res.data
      distEnabled.value      = d.enabled ?? true
      promoCenterTitle.value = d.promoCenterTitle ?? '推广中心'
      minWithdraw.value      = d.minWithdraw ?? 1
      maxWithdraw.value      = d.maxWithdraw ?? 0
      requireAudit.value     = d.requireAudit !== false
      withdrawFee.value      = d.withdrawFee ?? 0
      const ts = d.testSettings ?? {}
      testTypeItems.forEach(({ key }) => {
        const s = ts[key] ?? {}
        testSettings[key] = {
          enabled:          s.enabled          !== false,
          commissionType:   s.commissionType   ?? 'ratio',
          commissionRate:   s.commissionRate    ?? 90,
          commissionAmount: s.commissionAmount  ?? 0,
          noPayment:        s.noPayment        ?? false,
        }
      })
    }
  } catch (e) {}
}

// ── 保存设置
const saveSettings = async () => {
  loading.value = true
  try {
    const res: any = await request.put('/superadmin/distribution/settings', {
      enabled: distEnabled.value,
      promoCenterTitle: promoCenterTitle.value || '推广中心',
      minWithdraw: minWithdraw.value,
      maxWithdraw: maxWithdraw.value,
      requireAudit: requireAudit.value,
      withdrawFee: withdrawFee.value,
      testSettings: Object.fromEntries(
        testTypeItems.map(({ key }) => [key, testSettings[key]])
      ),
    })
    if (res.code === 200) ElMessage.success('配置已保存')
  } catch (e: any) { ElMessage.error(e.message || '保存失败') }
  loading.value = false
}

watch(activeTab, (t) => {
  if (t === 'overview')     loadOverview()
  else if (t === 'withdrawals') loadWithdrawals()
  else if (t === 'commissions') loadCommissions()
  else if (t === 'settings')    loadSettings()
})

watch(withFilter,  () => { if (activeTab.value === 'withdrawals')  loadWithdrawals() })
watch(commFilter,  () => { if (activeTab.value === 'commissions')  loadCommissions() })

const refresh = async () => {
  if (activeTab.value === 'overview')     await loadOverview()
  else if (activeTab.value === 'withdrawals') await loadWithdrawals()
  else if (activeTab.value === 'commissions') await loadCommissions()
  ElMessage.success('数据已刷新')
}

onMounted(() => { loadOverview() })
</script>

<style scoped lang="scss">
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
  .header-left {
    h2 { font-size: 22px; font-weight: 700; color: #111827; margin: 0 0 4px 0; }
    .subtitle { font-size: 13px; color: #6b7280; margin: 0; }
  }
  .refresh-btn {
    border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 12px;
    font-size: 13px; color: #374151; height: 34px;
    display: flex; align-items: center; gap: 6px;
    &:hover { background-color: #f9fafb; }
  }
}

.custom-tabs-container {
  background-color: #f3f4f6; padding: 4px;
  border-radius: 8px; display: flex; margin-bottom: 24px; width: 100%;
  .custom-tabs {
    display: flex; gap: 4px; width: 100%;
    .tab-item {
      flex: 1; display: flex; align-items: center; justify-content: center;
      padding: 8px 0; font-size: 13px; color: #6b7280;
      cursor: pointer; border-radius: 6px; transition: all 0.2s;
      white-space: nowrap;
      &:hover { color: #111827; }
      &.active { background: #fff; color: #111827; font-weight: 600; box-shadow: 0 1px 2px rgba(0,0,0,.05); }
    }
  }
}

.overview-section {
  .stats-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;
  }
  .stat-card {
    background: #fff; border-radius: 10px; padding: 20px;
    display: flex; justify-content: space-between; align-items: center;
    border: 1px solid #f3f4f6; box-shadow: 0 1px 2px rgba(0,0,0,.05);
    .stat-label { font-size: 13px; color: #6b7280; margin-bottom: 6px; }
    .stat-value { font-size: 24px; font-weight: 700; color: #111827; }
    .stat-icon {
      width: 48px; height: 48px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px;
      &.purple { background: #ede9fe; color: #7c3aed; }
      &.green  { background: #d1fae5; color: #059669; }
      &.orange { background: #fef3c7; color: #d97706; }
      &.blue   { background: #dbeafe; color: #2563eb; }
    }
  }
  .two-cols {
    display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
  }
  .small-card {
    padding: 20px;
    .card-title { font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 16px; }
    .dist-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 10px 0; border-bottom: 1px solid #f3f4f6;
      span { font-size: 13px; color: #6b7280; }
      strong { font-size: 15px; font-weight: 700; color: #111827; }
      &:last-child { border-bottom: none; }
    }
  }
}

.table-section { .content-card { overflow: hidden; } }

.content-card {
  background: #fff; border-radius: 10px;
  border: 1px solid #f3f4f6; box-shadow: 0 1px 2px rgba(0,0,0,.05);
}

.toolbar {
  padding: 16px 20px;
  display: flex; align-items: center; gap: 16px;
  border-bottom: 1px solid #f3f4f6;
  .filter-group {
    display: flex; background: #f3f4f6; padding: 3px; border-radius: 6px; gap: 2px;
    .filter-item {
      padding: 4px 12px; font-size: 12px; color: #6b7280;
      cursor: pointer; border-radius: 4px; transition: all .2s;
      &.active { background: #7c3aed; color: #fff; font-weight: 500; }
    }
  }
}

.custom-table {
  :deep(.el-table__header) th {
    background: #f9fafb; color: #6b7280; font-weight: 500; font-size: 13px; padding: 12px 0;
  }
}

.agent-cell {
  display: flex; align-items: center; gap: 10px;
  .agent-name { font-size: 13px; font-weight: 500; color: #111827; }
}

.empty-placeholder { padding: 60px; text-align: center; color: #9ca3af; font-size: 14px; }

/* 设置区 */
.settings-section { }
.settings-grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
.settings-card {
  background: #fff; border-radius: 10px; border: 1px solid #f3f4f6; padding: 24px;
  .card-header { font-size: 15px; font-weight: 600; color: #111827; margin-bottom: 20px; }
  .card-header-row {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;
    .card-header { margin-bottom: 0; }
  }
  .card-desc { font-size: 12px; color: #6b7280; margin: 0 0 20px; }
  &.full-width-card { grid-column: 1 / -1; }
  .setting-list { display: flex; flex-direction: column; gap: 12px; }
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
  .setting-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px; background: #f9fafb; border-radius: 8px;
    .info {
      .title { font-size: 14px; font-weight: 600; color: #111827; margin: 0; }
      .desc  { font-size: 12px; color: #6b7280; margin: 4px 0 0; }
    }
    .promo-title-input { width: 180px; flex-shrink: 0; }
  }
  .form-grid {
    display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;
    .form-item {
      display: flex; flex-direction: column; gap: 8px;
      label { font-size: 13px; font-weight: 500; color: #374151; }
    }
    .form-hint { font-size: 12px; color: #9ca3af; margin: 8px 0 0; }
  }
}
.save-actions {
  margin-top: 24px;
  .save-btn { height: 42px; padding: 0 40px; border-radius: 8px; font-weight: 600; }
}
.w-full { width: 100%; }

@media (max-width: 1200px) {
  .overview-section .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .overview-section .two-cols { grid-template-columns: 1fr; }
}

.page-container.is-embedded {
  min-height: auto;
}
</style>
