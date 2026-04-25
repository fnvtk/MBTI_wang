<template>
  <el-drawer
    v-model="visible"
    :title="drawerTitle"
    direction="rtl"
    size="480px"
    :destroy-on-close="true"
  >
    <div v-if="order" class="timeline-drawer">
      <div class="summary-card">
        <div class="summary-row">
          <span class="label">订单号</span>
          <span class="value monospaced">{{ order.orderNo || '—' }}</span>
        </div>
        <div class="summary-row">
          <span class="label">用户</span>
          <span class="value">{{ order.userName || '—' }} <em>{{ formatPhone(order.userPhone) }}</em></span>
        </div>
        <div class="summary-row">
          <span class="label">产品</span>
          <span class="value">{{ order.productTitle || productTypeLabel(order.productType) }}</span>
        </div>
        <div class="summary-row">
          <span class="label">金额</span>
          <span class="value amount">{{ formatAmount(order.amount) }}</span>
        </div>
        <div class="summary-row">
          <span class="label">状态</span>
          <span :class="['status-badge', 'status-' + (order.status || 'pending')]">{{ statusLabel(order.status) }}</span>
        </div>
      </div>

      <h4 class="section-title">订单时间线</h4>
      <ul class="timeline">
        <li v-for="step in timelineSteps" :key="step.key" :class="['timeline-step', step.active ? 'active' : 'inactive']">
          <span class="dot" />
          <div class="step-body">
            <div class="step-title">{{ step.title }}</div>
            <div class="step-time">{{ step.active ? formatDateTime(step.time) : '—' }}</div>
            <div class="step-desc" v-if="step.desc">{{ step.desc }}</div>
          </div>
        </li>
      </ul>

      <template v-if="commissionList.length">
        <h4 class="section-title">分销分润</h4>
        <div class="commission-list">
          <div v-for="cr in commissionList" :key="cr.id" class="commission-card">
            <div class="commission-top">
              <span class="inviter">{{ cr.inviterName || cr.inviterNickname || ('#' + cr.inviterId) }}</span>
              <span :class="['status-badge', 'status-' + mapCommissionStatus(cr.status)]">{{ commissionStatusLabel(cr.status) }}</span>
            </div>
            <div class="commission-meta">
              <span>佣金 <strong>{{ formatAmount(cr.commissionFen) }}</strong></span>
              <span v-if="cr.rate != null">比例 {{ Number(cr.rate * 100).toFixed(1) }}%</span>
              <span v-if="cr.paidAt">结算 {{ formatDateTime(cr.paidAt) }}</span>
            </div>
          </div>
        </div>
      </template>
      <div v-else class="empty-commission">无分销分润记录</div>
    </div>
  </el-drawer>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface OrderInput {
  orderNo?: string
  userName?: string
  userPhone?: string
  userId?: number
  productType?: string
  productTitle?: string
  amount?: number
  status?: string
  createdAt?: number | null
  payTime?: number | null
  refundTime?: number | null
  cancelTime?: number | null
  commissions?: any[]
}

const props = defineProps<{
  modelValue: boolean
  order: OrderInput | null
  commissions?: any[]
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
}>()

const visible = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit('update:modelValue', v),
})

const drawerTitle = computed(() => `订单详情 · ${props.order?.orderNo || ''}`)

const commissionList = computed(() => {
  const inline = Array.isArray(props.order?.commissions) ? props.order!.commissions! : []
  const extra = Array.isArray(props.commissions) ? props.commissions : []
  return [...inline, ...extra]
})

const timelineSteps = computed(() => {
  const order = props.order
  if (!order) return []
  const status = order.status || 'pending'
  const commissionPaidAt = commissionList.value.find(c => c.paidAt)?.paidAt ?? null
  const createdActive = !!order.createdAt
  const paidActive = !!order.payTime || ['paid', 'completed', 'refunded'].includes(status)
  const settledActive = !!commissionPaidAt || status === 'completed'
  const refundActive = status === 'refunded' && !!(order.refundTime || order.payTime)

  return [
    {
      key: 'created',
      title: '下单',
      time: order.createdAt,
      active: createdActive,
      desc: '用户发起订单',
    },
    {
      key: 'paid',
      title: '付款',
      time: order.payTime,
      active: paidActive,
      desc: paidActive ? '支付已到账' : '等待支付',
    },
    {
      key: 'settled',
      title: '分销结算',
      time: commissionPaidAt,
      active: settledActive,
      desc: settledActive
        ? (commissionList.value.length ? '佣金按规则结算' : '无分销，直接完成')
        : '尚未结算',
    },
    ...(refundActive
      ? [{ key: 'refunded', title: '退款', time: order.refundTime || order.payTime, active: true, desc: '已发起退款' }]
      : []),
  ]
})

function formatPhone(phone?: string) {
  if (!phone) return ''
  if (phone.length === 11) return phone.substring(0, 3) + '****' + phone.substring(7)
  return phone
}

function formatDateTime(ts?: number | null) {
  if (ts == null) return '—'
  const d = new Date(Number(ts) * 1000)
  return (
    d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') + ' ' +
    String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0')
  )
}

function formatAmount(amountFen?: number | string | null) {
  if (amountFen == null) return '¥0.00'
  const n = typeof amountFen === 'number' ? amountFen : Number(amountFen)
  if (!Number.isFinite(n)) return '¥0.00'
  return '¥' + (n / 100).toFixed(2)
}

function productTypeLabel(type?: string) {
  const map: Record<string, string> = {
    face: 'AI人脸分析',
    mbti: 'MBTI',
    disc: 'DISC',
    pdp: 'PDP',
    sbti: 'SBTI',
    report: '完整报告',
  }
  return map[type || ''] || type || '—'
}

function statusLabel(status?: string) {
  const map: Record<string, string> = {
    pending: '待支付',
    paid: '已支付',
    completed: '已完成',
    cancelled: '已取消',
    refunded: '已退款',
    failed: '失败',
  }
  return map[status || ''] || status || '—'
}

function mapCommissionStatus(s: string) {
  if (s === 'paid' || s === 'settled') return 'completed'
  if (s === 'frozen') return 'frozen'
  if (s === 'cancelled') return 'cancelled'
  return 'pending'
}

function commissionStatusLabel(s: string) {
  const map: Record<string, string> = {
    frozen: '冻结中',
    pending: '待结算',
    paid: '已结算',
    settled: '已结算',
    cancelled: '已撤销',
  }
  return map[s] || s || '—'
}
</script>

<style scoped lang="scss">
.timeline-drawer { padding: 8px 4px; }

.summary-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 6px;

  .summary-row {
    display: flex;
    align-items: baseline;
    gap: 12px;
    font-size: 13px;

    .label { color: #64748b; flex-shrink: 0; width: 56px; }
    .value { color: #0f172a; font-weight: 500; }
    .value.amount { color: #4f46e5; font-weight: 700; }
    .value.monospaced { font-family: 'SF Mono', Menlo, monospace; font-weight: 500; }
    em { color: #94a3b8; font-style: normal; margin-left: 6px; font-size: 12px; }
  }
}

.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
  margin: 20px 0 10px;
}

.timeline {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.timeline-step {
  position: relative;
  padding-left: 22px;

  .dot {
    position: absolute;
    left: 4px;
    top: 4px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #cbd5e1;
  }

  &::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 14px;
    bottom: -10px;
    width: 2px;
    background: #e2e8f0;
  }

  &:last-child::before { display: none; }

  &.active .dot { background: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15); }

  .step-title { font-size: 13px; font-weight: 600; color: #0f172a; }
  .step-time { font-size: 12px; color: #64748b; margin-top: 2px; }
  .step-desc { font-size: 12px; color: #94a3b8; margin-top: 2px; }

  &.inactive {
    .step-title, .step-time, .step-desc { color: #94a3b8; }
  }
}

.commission-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.commission-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px 12px;

  .commission-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;

    .inviter { font-size: 13px; color: #0f172a; font-weight: 600; }
  }

  .commission-meta {
    display: flex;
    gap: 12px;
    font-size: 12px;
    color: #64748b;
    flex-wrap: wrap;

    strong { color: #4f46e5; font-weight: 600; }
  }
}

.empty-commission {
  font-size: 12px;
  color: #94a3b8;
  padding: 8px 0;
}
</style>
