<template>
  <div class="profile-page">
    <!-- 用户信息卡 -->
    <div class="user-card">
      <div class="user-card-bg"></div>
      <div class="user-card-content">
        <div class="avatar-wrap">
          <img v-if="userInfo?.avatar" :src="userInfo.avatar" class="avatar-img" alt="头像" />
          <div v-else class="avatar-placeholder" :style="{ background: avatarBgColor }">
            {{ avatarLetter }}
          </div>
          <div class="avatar-verified" v-if="userInfo?.phone">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="white">
              <path d="M20 6L9 17l-5-5" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </div>
        <div class="user-info">
          <div class="user-name">{{ displayName }}</div>
          <div class="user-phone" v-if="userInfo?.phone">{{ maskedPhone }}</div>
          <div class="user-tags">
            <span class="tag" v-if="recentData.mbtiType">{{ recentData.mbtiType }}</span>
            <span class="tag disc" v-if="recentData.discType">{{ recentData.discType }}</span>
            <span class="tag pdp" v-if="recentData.pdpType">{{ recentData.pdpType }}</span>
            <span class="tag-empty" v-if="!recentData.mbtiType && !recentData.discType && !recentData.pdpType">
              完成测评，解锁性格标签
            </span>
          </div>
        </div>
      </div>
      <!-- 数据条 -->
      <div class="stats-row">
        <div class="stat-item">
          <div class="stat-num">{{ recentData.testCount }}</div>
          <div class="stat-label">已完成测评</div>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <div class="stat-num">{{ balance }}</div>
          <div class="stat-label">余额（元）</div>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <div class="stat-num">{{ promoData.totalInvite }}</div>
          <div class="stat-label">已邀好友</div>
        </div>
      </div>
    </div>

    <!-- 最近测评横滑 -->
    <div class="section" v-if="recentData.testCount > 0">
      <div class="section-header">
        <span class="section-title">我的测评</span>
        <span class="section-more" @click="$router.push('/test-select')">查看全部 ›</span>
      </div>
      <div class="recent-scroll">
        <div class="recent-cards">
          <div class="recent-card" v-if="recentData.mbtiType" @click="$router.push('/result?type=mbti')">
            <div class="recent-card-icon mbti-icon">M</div>
            <div class="recent-card-info">
              <div class="recent-card-type">MBTI</div>
              <div class="recent-card-result">{{ recentData.mbtiType }}</div>
              <div class="recent-card-time">{{ recentData.mbtiTime }}</div>
            </div>
            <div class="recent-card-arrow">›</div>
          </div>
          <div class="recent-card" v-if="recentData.discType" @click="$router.push('/result?type=disc')">
            <div class="recent-card-icon disc-icon">D</div>
            <div class="recent-card-info">
              <div class="recent-card-type">DISC</div>
              <div class="recent-card-result">{{ recentData.discType }}</div>
              <div class="recent-card-time">{{ recentData.discTime }}</div>
            </div>
            <div class="recent-card-arrow">›</div>
          </div>
          <div class="recent-card" v-if="recentData.pdpType" @click="$router.push('/result?type=pdp')">
            <div class="recent-card-icon pdp-icon">P</div>
            <div class="recent-card-info">
              <div class="recent-card-type">PDP</div>
              <div class="recent-card-result">{{ recentData.pdpType }}</div>
              <div class="recent-card-time">{{ recentData.pdpTime }}</div>
            </div>
            <div class="recent-card-arrow">›</div>
          </div>
          <div class="recent-card" v-if="recentData.aiType" @click="$router.push('/face-result')">
            <div class="recent-card-icon ai-icon">AI</div>
            <div class="recent-card-info">
              <div class="recent-card-type">面相分析</div>
              <div class="recent-card-result">{{ recentData.aiType }}</div>
              <div class="recent-card-time">{{ recentData.aiTime }}</div>
            </div>
            <div class="recent-card-arrow">›</div>
          </div>
        </div>
      </div>
    </div>

    <!-- 快捷功能网格 -->
    <div class="section">
      <div class="quick-grid">
        <div class="quick-item" @click="$router.push('/test-select')">
          <div class="quick-icon test-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <span>性格测试</span>
        </div>
        <div class="quick-item" @click="$router.push('/purchase')">
          <div class="quick-icon order-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
              <line x1="3" y1="6" x2="21" y2="6" stroke="currentColor" stroke-width="1.75"/>
              <path d="M16 10a4 4 0 01-8 0" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
            </svg>
          </div>
          <span>我的订单</span>
        </div>
        <div class="quick-item" @click="$router.push('/purchase')">
          <div class="quick-icon deep-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75"/>
              <path d="M12 8v4l3 3" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
            </svg>
          </div>
          <span>了解自己</span>
        </div>
        <div class="quick-item" @click="$router.push('/promo')">
          <div class="quick-icon promo-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
              <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.75"/>
              <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
            </svg>
          </div>
          <span>推广中心</span>
        </div>
      </div>
    </div>

    <!-- 推广邀请码 -->
    <div class="section invite-strip" @click="copyInviteCode" v-if="inviteCode">
      <div class="invite-left">
        <div class="invite-title">我的邀请码</div>
        <div class="invite-code">{{ inviteCode }}</div>
      </div>
      <div class="invite-right">
        <span>复制推广</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <polyline points="9 18 15 12 9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
    </div>

    <!-- 推广数据 -->
    <div class="section promo-card" v-if="promoData.totalEarned !== '0.00'">
      <div class="section-header">
        <span class="section-title">推广收益</span>
        <span class="section-more" @click="$router.push('/promo')">推广中心 ›</span>
      </div>
      <div class="promo-stats">
        <div class="promo-stat">
          <div class="promo-num">{{ promoData.totalInvite }}</div>
          <div class="promo-label">邀请好友</div>
        </div>
        <div class="promo-stat">
          <div class="promo-num">¥{{ promoData.totalEarned }}</div>
          <div class="promo-label">累计收益</div>
        </div>
        <div class="promo-stat">
          <div class="promo-num green">¥{{ promoData.withdrawable }}</div>
          <div class="promo-label">可提现</div>
        </div>
      </div>
    </div>

    <div style="height: 80px;"></div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { request } from '@/utils/request'

const router = useRouter()

const userInfo = ref<any>(null)
const balance = ref('0.00')
const inviteCode = ref('')
const recentData = ref({
  testCount: 0,
  mbtiType: '', mbtiTime: '',
  discType: '', discTime: '',
  pdpType: '', pdpTime: '',
  aiType: '', aiTime: ''
})
const promoData = ref({ totalInvite: 0, totalEarned: '0.00', withdrawable: '0.00' })

const avatarBgColors = ['#6366f1', '#8b5cf6', '#ec4899', '#14b8a6', '#0ea5e9']
const avatarBgColor = computed(() => {
  const name = displayName.value
  let hash = 0
  for (let i = 0; i < name.length; i++) hash += name.charCodeAt(i)
  return avatarBgColors[Math.abs(hash) % avatarBgColors.length]
})
const avatarLetter = computed(() => (displayName.value || '我').charAt(0).toUpperCase())
const displayName = computed(() => {
  if (!userInfo.value) return '点击登录'
  return userInfo.value.nickname || userInfo.value.nickName || '微信用户'
})
const maskedPhone = computed(() => {
  const p = userInfo.value?.phone
  if (!p) return ''
  return p.replace(/(\d{3})\d{4}(\d{4})/, '$1****$2')
})

async function loadData() {
  const token = localStorage.getItem('token')
  if (!token) return
  const stored = localStorage.getItem('userInfo')
  if (stored) userInfo.value = JSON.parse(stored)

  try {
    const res = await request({ url: '/api/test/recent?scope=all', method: 'GET' })
    if (res.code === 200 && res.data?.records) {
      const r = res.data.records
      recentData.value = {
        testCount: res.data.totalCount || 0,
        mbtiType: r.mbti?.resultText || '',
        mbtiTime: r.mbti?.testTime?.slice(0, 10) || '',
        discType: r.disc?.resultText || '',
        discTime: r.disc?.testTime?.slice(0, 10) || '',
        pdpType: r.pdp?.resultText || '',
        pdpTime: r.pdp?.testTime?.slice(0, 10) || '',
        aiType: r.ai?.resultText || '',
        aiTime: r.ai?.testTime?.slice(0, 10) || ''
      }
    }
  } catch {}

  try {
    const res2 = await request({ url: '/api/distribution/stats', method: 'GET' })
    if (res2.code === 200 && res2.data) {
      promoData.value = {
        totalInvite: res2.data.totalInvite || 0,
        totalEarned: res2.data.totalEarned || '0.00',
        withdrawable: res2.data.walletBalance || '0.00'
      }
    }
  } catch {}

  try {
    const res3 = await request({ url: '/api/distribution/my-invite-code', method: 'GET' })
    if (res3.code === 200 && res3.data?.code) {
      inviteCode.value = String(res3.data.code).toUpperCase()
    }
  } catch {}
}

function copyInviteCode() {
  if (!inviteCode.value) return
  navigator.clipboard.writeText(inviteCode.value).then(() => {
    alert('邀请码已复制：' + inviteCode.value)
  }).catch(() => {
    prompt('复制邀请码', inviteCode.value)
  })
}

onMounted(loadData)
</script>

<style scoped>
.profile-page {
  min-height: 100vh;
  background: #F4F6FB;
  padding-bottom: 80px;
}

.user-card {
  position: relative;
  margin: 16px 16px 0;
  border-radius: 20px;
  overflow: hidden;
  background: linear-gradient(135deg, #1E40AF 0%, #4338CA 100%);
  color: white;
  box-shadow: 0 8px 32px rgba(30,64,175,0.3);
}

.user-card-bg {
  position: absolute;
  inset: 0;
  background: url("data:image/svg+xml,%3Csvg width='200' height='200' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='160' cy='40' r='80' fill='rgba(255,255,255,0.06)'/%3E%3Ccircle cx='20' cy='160' r='60' fill='rgba(255,255,255,0.04)'/%3E%3C/svg%3E") no-repeat center;
}

.user-card-content {
  position: relative;
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 24px 20px 16px;
}

.avatar-wrap { position: relative; flex-shrink: 0; }
.avatar-img { width: 68px; height: 68px; border-radius: 50%; border: 3px solid rgba(255,255,255,0.4); object-fit: cover; }
.avatar-placeholder {
  width: 68px; height: 68px; border-radius: 50%;
  border: 3px solid rgba(255,255,255,0.4);
  display: flex; align-items: center; justify-content: center;
  font-size: 24px; font-weight: 800; color: white;
}
.avatar-verified {
  position: absolute; bottom: 1px; right: 1px;
  width: 18px; height: 18px; border-radius: 50%;
  background: #10B981; border: 2px solid white;
  display: flex; align-items: center; justify-content: center;
}

.user-info { flex: 1; min-width: 0; }
.user-name { font-size: 18px; font-weight: 800; color: white; margin-bottom: 4px; }
.user-phone { font-size: 12px; color: rgba(255,255,255,0.7); margin-bottom: 8px; }
.user-tags { display: flex; flex-wrap: wrap; gap: 6px; }
.tag {
  font-size: 11px; font-weight: 700;
  background: rgba(255,255,255,0.2);
  color: white; border-radius: 20px;
  padding: 2px 10px; backdrop-filter: blur(4px);
}
.tag.disc { background: rgba(16,185,129,0.3); }
.tag.pdp { background: rgba(245,158,11,0.3); }
.tag-empty { font-size: 11px; color: rgba(255,255,255,0.5); }

.stats-row {
  position: relative;
  display: flex; align-items: center;
  padding: 12px 20px 20px;
  border-top: 1px solid rgba(255,255,255,0.1);
}
.stat-item { flex: 1; text-align: center; }
.stat-num { font-size: 20px; font-weight: 800; color: white; }
.stat-label { font-size: 11px; color: rgba(255,255,255,0.6); margin-top: 2px; }
.stat-divider { width: 1px; height: 30px; background: rgba(255,255,255,0.15); }

.section {
  margin: 16px 16px 0;
  background: white;
  border-radius: 16px;
  padding: 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.section-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 14px;
}
.section-title { font-size: 15px; font-weight: 700; color: #111827; }
.section-more { font-size: 13px; color: #6366F1; cursor: pointer; }

.recent-scroll { overflow-x: auto; margin: 0 -16px; padding: 0 16px; }
.recent-cards { display: flex; gap: 10px; width: max-content; }
.recent-card {
  display: flex; align-items: center; gap: 12px;
  background: #F8F9FF; border-radius: 12px;
  padding: 12px 14px; min-width: 180px;
  cursor: pointer; transition: all 0.18s;
}
.recent-card:active { transform: scale(0.97); }
.recent-card-icon {
  width: 40px; height: 40px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; font-weight: 800; color: white; flex-shrink: 0;
}
.mbti-icon { background: linear-gradient(135deg, #6366F1, #4338CA); }
.disc-icon { background: linear-gradient(135deg, #10B981, #059669); }
.pdp-icon { background: linear-gradient(135deg, #F59E0B, #D97706); }
.ai-icon  { background: linear-gradient(135deg, #EC4899, #BE185D); font-size: 11px; }
.recent-card-info { flex: 1; min-width: 0; }
.recent-card-type { font-size: 11px; color: #9CA3AF; }
.recent-card-result { font-size: 14px; font-weight: 700; color: #111827; margin: 2px 0; }
.recent-card-time { font-size: 11px; color: #D1D5DB; }
.recent-card-arrow { color: #D1D5DB; font-size: 16px; }

.quick-grid {
  display: grid; grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}
.quick-item {
  display: flex; flex-direction: column; align-items: center;
  gap: 8px; padding: 14px 8px; border-radius: 12px;
  background: #F8F9FF; cursor: pointer; transition: all 0.18s;
}
.quick-item:active { transform: scale(0.95); }
.quick-item span { font-size: 11px; color: #374151; font-weight: 500; text-align: center; }
.quick-icon {
  width: 44px; height: 44px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center; color: white;
}
.test-icon  { background: linear-gradient(135deg, #6366F1, #4338CA); }
.order-icon { background: linear-gradient(135deg, #10B981, #059669); }
.deep-icon  { background: linear-gradient(135deg, #F59E0B, #D97706); }
.promo-icon { background: linear-gradient(135deg, #EC4899, #BE185D); }

.invite-strip {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 16px; cursor: pointer;
  background: linear-gradient(90deg, #EEF2FF, #FDF4FF);
  border: 1px solid #E0E7FF;
}
.invite-left .invite-title { font-size: 12px; color: #6B7280; }
.invite-left .invite-code { font-size: 20px; font-weight: 900; color: #4338CA; letter-spacing: 4px; margin-top: 2px; }
.invite-right { display: flex; align-items: center; gap: 4px; font-size: 13px; color: #6366F1; font-weight: 600; }

.promo-card {}
.promo-stats { display: flex; align-items: center; }
.promo-stat { flex: 1; text-align: center; }
.promo-num { font-size: 18px; font-weight: 800; color: #111827; }
.promo-num.green { color: #10B981; }
.promo-label { font-size: 11px; color: #9CA3AF; margin-top: 2px; }
</style>
