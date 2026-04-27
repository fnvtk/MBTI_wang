<template>
  <div class="promo-page">
    <AppNavBar title="推广中心" />

    <!-- 收益卡 -->
    <div class="earnings-card">
      <div class="earnings-main">
        <div class="earnings-label">可提现余额（元）</div>
        <div class="earnings-amount">{{ promoData.withdrawable }}</div>
        <button class="withdraw-btn" @click="handleWithdraw" :disabled="withdrawing">
          {{ withdrawing ? '处理中...' : '申请提现' }}
        </button>
      </div>
      <div class="earnings-stats">
        <div class="e-stat">
          <div class="e-num">{{ promoData.totalInvite }}</div>
          <div class="e-label">邀请好友</div>
        </div>
        <div class="e-divider"></div>
        <div class="e-stat">
          <div class="e-num">¥{{ promoData.totalEarned }}</div>
          <div class="e-label">累计收益</div>
        </div>
        <div class="e-divider"></div>
        <div class="e-stat">
          <div class="e-num">{{ promoData.successOrders }}</div>
          <div class="e-label">成功订单</div>
        </div>
      </div>
    </div>

    <!-- 我的邀请码 -->
    <div class="section">
      <div class="section-title">我的邀请码</div>
      <div class="invite-box">
        <div class="invite-code-display">{{ inviteCode || '加载中...' }}</div>
        <button class="copy-btn" @click="copyCode">复制</button>
      </div>
      <p class="invite-hint">分享邀请码给好友，好友完成购买后您可获得推广奖励</p>
    </div>

    <!-- 分享海报按钮 -->
    <div class="section">
      <button class="share-poster-btn" @click="shareApp">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8M16 6l-4-4-4 4M12 2v13" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        生成专属推广海报
      </button>
    </div>

    <!-- 邀请记录 -->
    <div class="section">
      <div class="section-header">
        <span class="section-title">邀请记录</span>
      </div>
      <div class="invite-list" v-if="inviteList.length">
        <div class="invite-item" v-for="(item, idx) in inviteList" :key="idx">
          <div class="invite-item-avatar" :style="{ background: getAvatarColor(item.nickname) }">
            {{ (item.nickname || '用').charAt(0) }}
          </div>
          <div class="invite-item-info">
            <div class="invite-item-name">{{ item.nickname || '微信用户' }}</div>
            <div class="invite-item-time">{{ item.time }}</div>
          </div>
          <div class="invite-item-reward" v-if="item.reward">
            <span class="reward-badge">+¥{{ item.reward }}</span>
          </div>
          <div class="invite-item-status" :class="item.status">
            {{ statusLabel(item.status) }}
          </div>
        </div>
      </div>
      <div class="empty-state" v-else-if="!loading">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" style="color:#E5E7EB">
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.5"/>
          <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <p>暂无邀请记录</p>
        <p style="font-size:12px; color:#9CA3AF;">快去邀请好友，共同获得推广奖励</p>
      </div>
    </div>

    <div style="height: 40px;"></div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppNavBar from '@/components/AppNavBar.vue'
import { request } from '@/utils/request'

const loading = ref(true)
const withdrawing = ref(false)
const inviteCode = ref('')

const promoData = ref({
  totalInvite: 0,
  totalEarned: '0.00',
  withdrawable: '0.00',
  successOrders: 0
})

const inviteList = ref<any[]>([])

const avatarColors = ['#6366F1', '#10B981', '#F59E0B', '#EC4899', '#3B82F6', '#8B5CF6']
function getAvatarColor(name: string) {
  let hash = 0
  for (let i = 0; i < (name || '').length; i++) hash += (name || '').charCodeAt(i)
  return avatarColors[Math.abs(hash) % avatarColors.length]
}

function statusLabel(s: string) {
  const map: Record<string, string> = { paid: '已购买', registered: '已注册', invited: '已邀请' }
  return map[s] || s
}

async function loadData() {
  loading.value = true
  try {
    const [statsRes, codeRes, listRes] = await Promise.all([
      request({ url: '/api/distribution/stats', method: 'GET' }),
      request({ url: '/api/distribution/my-invite-code', method: 'GET' }),
      request({ url: '/api/distribution/invite-list', method: 'GET' })
    ])
    if (statsRes.code === 200 && statsRes.data) {
      promoData.value = {
        totalInvite: statsRes.data.totalInvite || 0,
        totalEarned: statsRes.data.totalEarned || '0.00',
        withdrawable: statsRes.data.walletBalance || '0.00',
        successOrders: statsRes.data.successOrders || 0
      }
    }
    if (codeRes.code === 200 && codeRes.data?.code) {
      inviteCode.value = String(codeRes.data.code).toUpperCase()
    }
    if (listRes.code === 200 && Array.isArray(listRes.data?.list)) {
      inviteList.value = listRes.data.list
    }
  } catch (e) {}
  finally { loading.value = false }
}

function copyCode() {
  if (!inviteCode.value) return
  navigator.clipboard.writeText(inviteCode.value)
    .then(() => alert('邀请码已复制：' + inviteCode.value))
    .catch(() => prompt('复制邀请码', inviteCode.value))
}

function shareApp() {
  const url = `${window.location.origin}/?invite=${inviteCode.value}`
  if (navigator.share) {
    navigator.share({ title: '神仙团队MBTI测评', text: '快来测测你的性格类型！', url })
  } else {
    navigator.clipboard.writeText(url).then(() => alert('推广链接已复制！'))
  }
}

async function handleWithdraw() {
  if (withdrawing.value) return
  const amt = parseFloat(promoData.value.withdrawable)
  if (!amt || amt <= 0) { alert('暂无可提现余额'); return }
  withdrawing.value = true
  try {
    const res = await request({ url: '/api/distribution/withdraw', method: 'POST', data: { amount: amt } })
    if (res.code === 200) {
      alert('提现申请已提交，预计1-3个工作日到账')
      loadData()
    } else {
      alert(res.message || '提现失败，请稍后重试')
    }
  } catch { alert('网络异常，请稍后重试') }
  finally { withdrawing.value = false }
}

onMounted(loadData)
</script>

<style scoped>
.promo-page { min-height: 100vh; background: #F4F6FB; }

.earnings-card {
  background: linear-gradient(135deg, #1E40AF, #4338CA);
  margin: 16px; border-radius: 20px; overflow: hidden;
  box-shadow: 0 8px 32px rgba(30,64,175,0.3); color: white;
}
.earnings-main { padding: 28px 24px 20px; text-align: center; }
.earnings-label { font-size: 12px; color: rgba(255,255,255,0.7); }
.earnings-amount { font-size: 48px; font-weight: 900; letter-spacing: -2px; margin: 4px 0 16px; }
.withdraw-btn {
  background: rgba(255,255,255,0.2); color: white;
  border: 1.5px solid rgba(255,255,255,0.4);
  border-radius: 25px; padding: 10px 32px;
  font-size: 14px; font-weight: 600; cursor: pointer;
  backdrop-filter: blur(8px); transition: all 0.18s;
}
.withdraw-btn:disabled { opacity: 0.6; }
.earnings-stats {
  display: flex; border-top: 1px solid rgba(255,255,255,0.15);
  padding: 16px 0;
}
.e-stat { flex: 1; text-align: center; }
.e-num { font-size: 18px; font-weight: 800; color: white; }
.e-label { font-size: 11px; color: rgba(255,255,255,0.6); margin-top: 2px; }
.e-divider { width: 1px; background: rgba(255,255,255,0.15); }

.section {
  background: white; margin: 12px 16px 0;
  border-radius: 16px; padding: 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.section-title { font-size: 15px; font-weight: 700; color: #111827; margin-bottom: 12px; display: block; }

.invite-box {
  display: flex; align-items: center; gap: 12px;
  background: #F3F4F6; border-radius: 12px; padding: 12px 16px;
}
.invite-code-display { flex: 1; font-size: 24px; font-weight: 900; color: #4338CA; letter-spacing: 4px; }
.copy-btn {
  background: #4338CA; color: white; border: none;
  border-radius: 8px; padding: 8px 16px;
  font-size: 13px; font-weight: 600; cursor: pointer;
}
.invite-hint { font-size: 12px; color: #9CA3AF; margin: 8px 0 0; line-height: 1.5; }

.share-poster-btn {
  width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;
  background: linear-gradient(90deg, #F59E0B, #EF4444);
  color: white; border: none; border-radius: 12px;
  padding: 14px; font-size: 15px; font-weight: 700; cursor: pointer;
}

.invite-list { display: flex; flex-direction: column; gap: 12px; }
.invite-item {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 0; border-bottom: 1px solid #F3F4F6;
}
.invite-item:last-child { border-bottom: none; }
.invite-item-avatar {
  width: 40px; height: 40px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 15px; font-weight: 700; color: white; flex-shrink: 0;
}
.invite-item-info { flex: 1; min-width: 0; }
.invite-item-name { font-size: 14px; font-weight: 600; color: #111827; }
.invite-item-time { font-size: 12px; color: #9CA3AF; }
.reward-badge {
  background: #ECFDF5; color: #10B981;
  font-size: 12px; font-weight: 700; border-radius: 4px; padding: 2px 8px;
}
.invite-item-status {
  font-size: 12px; font-weight: 500;
  padding: 3px 10px; border-radius: 20px; flex-shrink: 0;
  background: #F3F4F6; color: #6B7280;
}
.invite-item-status.paid { background: #ECFDF5; color: #10B981; }

.empty-state {
  display: flex; flex-direction: column; align-items: center;
  padding: 32px 0; gap: 8px; color: #9CA3AF; font-size: 13px;
}
</style>
