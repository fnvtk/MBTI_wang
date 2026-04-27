<template>
  <div class="journey-panel">
    <!-- 头部 -->
    <div class="journey-header">
      <div class="journey-header-left">
        <h3 class="journey-title">用户旅程漏斗</h3>
        <p class="journey-desc">注册 → 绑手机 → 完成测评 → 解锁报告 → 分享 → 付费 → 复测</p>
      </div>
      <div class="journey-header-right">
        <span class="journey-beta-tag">前端占位版</span>
        <button class="refresh-btn" @click="refresh">
          <el-icon><Refresh /></el-icon>
          刷新
        </button>
      </div>
    </div>

    <!-- 简历完整度快览卡 -->
    <div class="profile-quality-section">
      <div class="pq-title">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
          <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M16 13H8M16 17H8M10 9H8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        简历完整度分布
      </div>
      <div class="pq-cards">
        <div v-for="card in profileQualityCards" :key="card.key" :class="['pq-card', 'pq-card--' + card.tone]">
          <div class="pq-card-icon">
            <component :is="card.icon" />
          </div>
          <div class="pq-card-body">
            <div class="pq-count">{{ card.count }}</div>
            <div class="pq-label">{{ card.label }}</div>
          </div>
          <div v-if="card.highlight" class="pq-highlight-badge">重点</div>
        </div>
      </div>
      <div class="pq-note">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
          <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        「有简历」= 手机号已绑定 + 至少完成 1 项测评。后端接口上线后自动切换真实数据。
      </div>
    </div>

    <!-- 旅程漏斗 -->
    <div class="funnel-wrap">
      <div
        v-for="(stage, idx) in stages"
        :key="stage.key"
        class="funnel-row"
        :style="{ animationDelay: `${idx * 40}ms` }"
      >
        <div class="funnel-meta">
          <div class="funnel-idx">{{ idx + 1 }}</div>
          <div class="funnel-meta-body">
            <span class="funnel-label">{{ stage.label }}</span>
            <span class="funnel-note">{{ stage.note }}</span>
          </div>
        </div>
        <div class="funnel-bar-track">
          <div
            class="funnel-bar-fill"
            :style="{
              width: barWidth(stage.count),
              background: stageColors[idx % stageColors.length]
            }"
          ></div>
        </div>
        <div class="funnel-num">
          <span class="funnel-count">{{ stage.count.toLocaleString() }}</span>
          <span class="funnel-percent" v-if="idx > 0 && stages[0].count > 0">
            {{ ((stage.count / stages[0].count) * 100).toFixed(1) }}%
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Refresh, Document, Phone, User, StarFilled } from '@element-plus/icons-vue'

interface StageRow {
  key: string
  label: string
  note: string
  count: number
}

const stageColors = [
  'linear-gradient(90deg, #4F46E5, #818CF8)',
  'linear-gradient(90deg, #7C3AED, #A78BFA)',
  'linear-gradient(90deg, #0EA5E9, #38BDF8)',
  'linear-gradient(90deg, #10B981, #34D399)',
  'linear-gradient(90deg, #F59E0B, #FCD34D)',
  'linear-gradient(90deg, #EF4444, #FC8181)',
  'linear-gradient(90deg, #8B5CF6, #C4B5FD)',
]

const mock = (): StageRow[] => [
  { key: 'register',    label: '注册',           note: '进入小程序并静默登录',     count: 1280 },
  { key: 'bind_phone',  label: '授权手机号',     note: 'getPhoneNumber 成功',      count: 842 },
  { key: 'tested',      label: '完成任意测评',   note: 'MBTI / DISC / PDP / SBTI', count: 716 },
  { key: 'viewed_full', label: '解锁完整报告',   note: '手机号登录查看结果',       count: 498 },
  { key: 'shared',      label: '发起分享',       note: '好友 / 朋友圈',            count: 263 },
  { key: 'paid',        label: '付费解锁',       note: '人脸或其他收费项',         count: 134 },
  { key: 'repeat',      label: '复测',           note: '>= 2 次完成记录',          count: 86 }
]

const stages = ref<StageRow[]>(mock())

const topCount = computed(() => Math.max(1, ...stages.value.map(s => s.count)))

function barWidth(count: number) {
  return `${Math.round((count / topCount.value) * 100)}%`
}

// 简历完整度分布（mock，后端上线后替换）
const profileQualityCards = computed(() => {
  const tested = stages.value.find(s => s.key === 'tested')?.count || 716
  const bindPhone = stages.value.find(s => s.key === 'bind_phone')?.count || 842
  const total = stages.value.find(s => s.key === 'register')?.count || 1280
  // 有简历 = 绑定手机 + 至少1项测评
  const withResume = Math.min(tested, bindPhone)
  const phoneOnly = bindPhone - withResume
  const noData = total - bindPhone
  return [
    {
      key: 'with_resume',
      label: '有简历（绑号+测评）',
      count: withResume,
      icon: StarFilled,
      tone: 'green',
      highlight: true
    },
    {
      key: 'phone_only',
      label: '绑号未测评',
      count: phoneOnly,
      icon: Phone,
      tone: 'blue',
      highlight: false
    },
    {
      key: 'no_data',
      label: '无手机号',
      count: noData,
      icon: User,
      tone: 'gray',
      highlight: false
    },
  ]
})

async function refresh() {
  stages.value = mock()
}
</script>

<style scoped lang="scss">
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: translateY(0); }
}

.journey-panel {
  padding: 20px 24px 24px;
  background: #F4F6FB;
}

/* ── 头部 ── */
.journey-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.journey-header-left {}
.journey-header-right {
  display: flex;
  align-items: center;
  gap: 8px;
}
.journey-title {
  margin: 0 0 4px;
  font-size: 18px;
  font-weight: 800;
  color: #111827;
}
.journey-desc {
  margin: 0;
  color: #6B7280;
  font-size: 12.5px;
}
.journey-beta-tag {
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 600;
  color: #B45309;
  background: #FFFBEB;
  border: 1px solid #FDE68A;
}
.refresh-btn {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 6px 14px;
  border: 1px solid #E5E7EB;
  background: #fff;
  color: #6B7280;
  border-radius: 8px;
  font-size: 12.5px;
  cursor: pointer;
  transition: all 0.15s;

  &:hover { background: #F9FAFB; color: #374151; }
}

/* ── 简历完整度 ── */
.profile-quality-section {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #E5E7EB;
  padding: 16px 18px;
  margin-bottom: 16px;
  box-shadow: 0 1px 3px rgba(16,24,40,0.04);
}
.pq-title {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 13px;
  font-weight: 700;
  color: #374151;
  margin-bottom: 14px;
  color: #4F46E5;
}
.pq-cards {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 12px;
}
.pq-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 12px;
  position: relative;
  border: 1.5px solid transparent;

  .pq-card-icon {
    width: 36px; height: 36px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
  }

  &--green {
    background: linear-gradient(135deg, #F0FDF4, #ECFDF5);
    border-color: #A7F3D0;
    .pq-card-icon { background: #10B981; color: #fff; }
    .pq-count { color: #059669; }
  }
  &--blue {
    background: #EFF6FF;
    border-color: #BFDBFE;
    .pq-card-icon { background: #3B82F6; color: #fff; }
    .pq-count { color: #2563EB; }
  }
  &--gray {
    background: #F9FAFB;
    border-color: #E5E7EB;
    .pq-card-icon { background: #9CA3AF; color: #fff; }
    .pq-count { color: #6B7280; }
  }
}
.pq-count {
  font-size: 26px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  line-height: 1;
  margin-bottom: 3px;
}
.pq-label {
  font-size: 11.5px;
  color: #6B7280;
  font-weight: 500;
}
.pq-highlight-badge {
  position: absolute;
  top: 8px; right: 8px;
  font-size: 9.5px;
  font-weight: 700;
  background: #10B981;
  color: #fff;
  padding: 2px 7px;
  border-radius: 20px;
}
.pq-note {
  display: flex;
  align-items: flex-start;
  gap: 7px;
  font-size: 11.5px;
  color: #9CA3AF;
  line-height: 1.5;
  padding: 8px 10px;
  background: #F8FAFC;
  border-radius: 8px;
}

/* ── 漏斗 ── */
.funnel-wrap {
  display: flex;
  flex-direction: column;
  gap: 8px;
  background: #fff;
  border-radius: 14px;
  border: 1px solid #E5E7EB;
  padding: 16px 18px;
  box-shadow: 0 1px 3px rgba(16,24,40,0.04);
}

.funnel-row {
  display: grid;
  grid-template-columns: minmax(200px, 240px) minmax(0, 1fr) 96px;
  gap: 14px;
  align-items: center;
  animation: fadeInUp 0.3s ease-out both;
  padding: 6px 0;
  border-bottom: 1px solid #F3F4F6;

  &:last-child { border-bottom: none; }
}

.funnel-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.funnel-idx {
  flex-shrink: 0;
  width: 24px; height: 24px;
  border-radius: 7px;
  background: #EEF2FF;
  color: #4F46E5;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 800;
}

.funnel-meta-body { display: flex; flex-direction: column; min-width: 0; }
.funnel-label { font-size: 13px; font-weight: 600; color: #111827; line-height: 1.2; }
.funnel-note { font-size: 11px; color: #9CA3AF; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.funnel-bar-track {
  height: 10px;
  background: #F3F4F6;
  border-radius: 5px;
  overflow: hidden;
}
.funnel-bar-fill {
  height: 100%;
  border-radius: 5px;
  transition: width 0.5s ease;
}

.funnel-num {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 1px;
}
.funnel-count { font-size: 16px; font-weight: 800; color: #111827; font-variant-numeric: tabular-nums; line-height: 1; }
.funnel-percent { font-size: 11.5px; color: #10B981; font-weight: 700; font-variant-numeric: tabular-nums; }

/* ── 响应式 ── */
@media (max-width: 768px) {
  .journey-panel { padding: 16px; }
  .pq-cards { grid-template-columns: 1fr; }
  .funnel-row {
    grid-template-columns: minmax(130px, 160px) minmax(0, 1fr) 72px;
    gap: 10px;
  }
  .funnel-note { display: none; }
}
</style>
