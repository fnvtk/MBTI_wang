<template>
  <div class="finance-page" :class="{ 'is-embedded': embedded }">
    <!-- 嵌入系统设置「企业余额」时单独展示操作区（独立页仍有完整 page-header） -->
    <div v-if="embedded" class="embedded-toolbar">
      <div class="embedded-toolbar-actions">
        <el-button @click="loadAll" :loading="loading">刷新</el-button>
        <el-button type="primary" color="#7c3aed" @click="openRechargeDialog">生成充值码</el-button>
      </div>
    </div>

    <div v-if="!embedded" class="page-header">
      <div>
        <h2>企业余额</h2>
        <p class="subtitle">{{ overview.enterpriseName || '当前企业' }}的余额、测试收入和充值流水</p>
      </div>
      <div class="header-actions">
        <el-button @click="loadAll" :loading="loading">刷新</el-button>
        <el-button type="primary" color="#7c3aed" @click="openRechargeDialog">生成充值码</el-button>
      </div>
    </div>

    <div class="stats-grid" v-loading="loading">
      <div class="stat-card">
        <div class="stat-label">当前余额</div>
        <div class="stat-value">¥{{ fenToYuan(overview.balanceFen) }}</div>
        <div class="stat-desc">可用于企业佣金结算</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">今日测试收入</div>
        <div class="stat-value">¥{{ fenToYuan(overview.todayIncomeFen) }}</div>
        <div class="stat-desc">仅统计 face / MBTI / DISC / PDP</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">本月测试收入</div>
        <div class="stat-value">¥{{ fenToYuan(overview.monthIncomeFen) }}</div>
        <div class="stat-desc">企业用户支付后自动入账</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">冻结佣金</div>
        <div class="stat-value">¥{{ fenToYuan(overview.frozenCommissionFen) }}</div>
        <div class="stat-desc">补余额后会自动解冻</div>
      </div>
    </div>

    <div class="content-card">
      <div class="content-header">
        <h3>财务流水</h3>
        <span class="content-tip">测试收入会自动进入企业余额，手动充值也会记录在这里</span>
      </div>

      <el-table :data="records" v-loading="recordsLoading" class="custom-table">
        <el-table-column prop="typeLabel" label="类型" width="120" />
        <el-table-column label="金额" width="130" align="right">
          <template #default="{ row }">
            <span :class="row.direction === 'out' ? 'amount-out' : 'amount-in'">
              {{ row.direction === 'out' ? '-' : '+' }}¥{{ fenToYuan(row.amountFen) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="变动前余额" width="140" align="right">
          <template #default="{ row }">¥{{ fenToYuan(row.balanceBeforeFen) }}</template>
        </el-table-column>
        <el-table-column label="变动后余额" width="140" align="right">
          <template #default="{ row }">¥{{ fenToYuan(row.balanceAfterFen) }}</template>
        </el-table-column>
        <el-table-column prop="description" label="说明" min-width="220" />
        <el-table-column label="时间" width="180">
          <template #default="{ row }">{{ formatTime(row.createdAt) }}</template>
        </el-table-column>
      </el-table>

      <div class="empty-state" v-if="!recordsLoading && records.length === 0">暂无财务流水</div>

      <div class="pagination-wrap" v-if="total > pageSize">
        <el-pagination
          v-model:current-page="page"
          :page-size="pageSize"
          :total="total"
          layout="prev, pager, next, total"
          @current-change="loadRecords"
        />
      </div>
    </div>

    <el-dialog v-model="rechargeDialogVisible" title="企业余额充值" width="460px">
      <el-form label-position="top">
        <el-form-item label="充值金额（元）" required>
          <el-input-number v-model="rechargeForm.amountYuan" :min="0.01" :step="100" :precision="2" class="w-full" />
        </el-form-item>
        <el-form-item label="说明">
          <el-input v-model="rechargeForm.remark" type="textarea" :rows="2" placeholder="可选，仅用于当前页提示，不会直接入账" />
        </el-form-item>
        <div v-if="rechargeQrcode" class="recharge-qrcode">
          <img :src="rechargeQrcode" alt="充值二维码" class="qrcode-image" />
          <p class="qrcode-tip">请使用微信扫码，在小程序内完成支付充值</p>
          <p class="qrcode-amount">本次充值：¥{{ rechargeForm.amountYuan.toFixed(2) }}</p>
        </div>
      </el-form>
      <template #footer>
        <el-button @click="rechargeDialogVisible = false">取消</el-button>
        <el-button type="primary" color="#7c3aed" :loading="rechargeLoading" @click="generateRechargeQrcode">生成小程序码</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { request } from '@/utils/request'

withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false })

const loading = ref(false)
const recordsLoading = ref(false)
const rechargeLoading = ref(false)
const rechargeDialogVisible = ref(false)
const rechargeQrcode = ref('')

const overview = reactive({
  enterpriseId: 0,
  enterpriseName: '',
  balanceFen: 0,
  todayIncomeFen: 0,
  monthIncomeFen: 0,
  totalIncomeFen: 0,
  manualRechargeFen: 0,
  frozenCommissionFen: 0,
  paidOrderCount: 0
})

const records = ref<any[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)

const rechargeForm = reactive({
  amountYuan: 100,
  remark: ''
})

const fenToYuan = (fen: number) => (Number(fen || 0) / 100).toFixed(2)

const formatTime = (timestamp: number) => {
  if (!timestamp) return '-'
  const date = new Date(timestamp * 1000)
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

const loadOverview = async () => {
  const res: any = await request.get('/admin/finance/overview')
  Object.assign(overview, res.data || {})
}

const loadRecords = async () => {
  recordsLoading.value = true
  try {
    const res: any = await request.get('/admin/finance/records', {
      params: {
        page: page.value,
        pageSize: pageSize.value
      }
    })
    records.value = res.data?.list || []
    total.value = res.data?.total || 0
  } finally {
    recordsLoading.value = false
  }
}

const loadAll = async () => {
  loading.value = true
  try {
    await loadOverview()
    await loadRecords()
  } catch (error: any) {
    ElMessage.error(error?.message || '加载企业财务数据失败')
  } finally {
    loading.value = false
  }
}

const openRechargeDialog = () => {
  rechargeQrcode.value = ''
  rechargeDialogVisible.value = true
}

const generateRechargeQrcode = async () => {
  if (!rechargeForm.amountYuan || rechargeForm.amountYuan <= 0) {
    ElMessage.warning('请输入正确的充值金额')
    return
  }

  const amountFen = Math.round(Number(rechargeForm.amountYuan) * 100)
  if (amountFen <= 0) {
    ElMessage.warning('请输入正确的充值金额')
    return
  }

  rechargeLoading.value = true
  try {
    const res: any = await request.post('/admin/finance/recharge-qrcode', {
      amountFen,
      remark: rechargeForm.remark
    })
    rechargeQrcode.value = res.data?.qrcode || ''
    ElMessage.success('充值码已生成')
  } catch (error: any) {
    ElMessage.error(error?.message || '生成充值码失败')
  } finally {
    rechargeLoading.value = false
  }
}

onMounted(() => {
  loadAll()
})
</script>

<style scoped lang="scss">
.finance-page {
  min-height: 100vh;
  padding: 24px;
  background: #f9fafb;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  gap: 16px;

  h2 {
    margin: 0 0 6px;
    font-size: 26px;
    color: #111827;
  }

  .subtitle {
    margin: 0;
    color: #6b7280;
    font-size: 14px;
  }
}

.header-actions {
  display: flex;
  gap: 12px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.stat-card,
.content-card {
  background: #fff;
  border-radius: 18px;
  border: 1px solid #eef2f7;
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
}

.stat-card {
  padding: 20px;
}

.stat-label {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 8px;
}

.stat-value {
  font-size: 30px;
  font-weight: 700;
  color: #111827;
  line-height: 1.2;
}

.stat-desc {
  margin-top: 10px;
  font-size: 13px;
  color: #94a3b8;
}

.content-card {
  padding: 20px;
}

.content-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;

  h3 {
    margin: 0;
    color: #111827;
  }
}

.content-tip {
  color: #6b7280;
  font-size: 13px;
}

.amount-in {
  color: #16a34a;
  font-weight: 600;
}

.amount-out {
  color: #dc2626;
  font-weight: 600;
}

.empty-state {
  padding: 24px 0 8px;
  text-align: center;
  color: #94a3b8;
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 18px;
}

.recharge-qrcode {
  margin-top: 12px;
  padding: 18px;
  border-radius: 16px;
  background: #f8fafc;
  text-align: center;
}

.qrcode-image {
  width: 220px;
  height: 220px;
  object-fit: contain;
  border-radius: 12px;
  background: #fff;
  padding: 10px;
}

.qrcode-tip {
  margin: 12px 0 4px;
  color: #6b7280;
  font-size: 13px;
}

.qrcode-amount {
  margin: 0;
  color: #111827;
  font-size: 14px;
  font-weight: 600;
}

.w-full {
  width: 100%;
}

@media (max-width: 1200px) {
  .stats-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .finance-page {
    padding: 16px;
  }

  .page-header,
  .content-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }
}

.finance-page.is-embedded {
  padding: 0;
  min-height: auto;
}

.embedded-toolbar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 16px;
}

.embedded-toolbar-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
}
</style>
