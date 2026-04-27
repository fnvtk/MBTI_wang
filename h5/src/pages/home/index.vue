<template>
  <div class="home">
    <!-- 顶部渐变英雄区 -->
    <div class="home__hero gradient-bg safe-top">
      <div class="home__hero-nav">
        <div class="home__logo">
          <svg width="28" height="28" viewBox="0 0 32 32" fill="none">
            <rect width="32" height="32" rx="10" fill="rgba(255,255,255,0.2)"/>
            <path d="M8 8h6v6H8zM18 8h6v6h-6zM8 18h6v6H8zM18 18h6v6h-6z" fill="white"/>
          </svg>
          <span>神仙团队</span>
        </div>
        <button class="home__vip-btn" @click="$router.push('/purchase')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="#F59E0B"/>
          </svg>
          <span>开通VIP</span>
        </button>
      </div>

      <div class="home__hero-content">
        <h1>AI 性格深度测评</h1>
        <p>结合面相 · MBTI · DISC · PDP<br>全方位解读你的性格密码</p>
        <div class="home__hero-tags">
          <span v-for="tag in heroTags" :key="tag" class="hero-tag">{{ tag }}</span>
        </div>
      </div>

      <!-- 4格 MBTI 类型展示 -->
      <div class="home__type-grid">
        <div v-for="t in topTypes" :key="t.type" class="type-cell" :style="{ background: t.color }">
          <span class="type-cell__type">{{ t.type }}</span>
          <span class="type-cell__name">{{ t.name }}</span>
        </div>
      </div>
    </div>

    <!-- 主入口按钮 -->
    <div class="home__actions">
      <button class="btn-primary home__main-btn" @click="$router.push('/camera')">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="margin-right:8px">
          <path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z" stroke="white" stroke-width="2" stroke-linejoin="round"/>
          <circle cx="12" cy="13" r="4" stroke="white" stroke-width="2"/>
        </svg>
        拍照测面相 · 开始测评
      </button>
      <button class="btn-secondary home__sub-btn" @click="$router.push('/test-select')">
        跳过拍照，直接做测试
      </button>
    </div>

    <!-- 测评功能卡片 -->
    <div class="home__section">
      <h2 class="home__section-title">选择你的测评</h2>
      <div class="home__tests">
        <div
          v-for="test in tests"
          :key="test.key"
          class="test-card"
          :style="{ '--card-color': test.color }"
          @click="goTest(test)"
        >
          <div class="test-card__icon">{{ test.icon }}</div>
          <div class="test-card__info">
            <div class="test-card__name">{{ test.name }}</div>
            <div class="test-card__desc">{{ test.desc }}</div>
          </div>
          <div class="test-card__count">{{ test.questions }}题</div>
        </div>
      </div>
    </div>

    <!-- 功能入口网格 -->
    <div class="home__section">
      <h2 class="home__section-title">发现更多</h2>
      <div class="home__discover">
        <div
          v-for="item in discoverItems"
          :key="item.path"
          class="discover-card"
          @click="$router.push(item.path)"
        >
          <div class="discover-card__icon" :style="{ background: item.color }" v-html="item.svg"></div>
          <span class="discover-card__label">{{ item.label }}</span>
        </div>
      </div>
    </div>

    <!-- 已完成测评结果展示 -->
    <div v-if="hasResults" class="home__section">
      <h2 class="home__section-title">我的测评结果</h2>
      <div class="home__results">
        <div
          v-for="result in myResults"
          :key="result.type"
          class="result-chip"
          @click="$router.push(`/result/${result.type}`)"
        >
          <span class="result-chip__label">{{ result.name }}</span>
          <span class="result-chip__value" :style="{ color: result.color }">{{ result.value }}</span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round"/></svg>
        </div>
      </div>
    </div>

    <div style="height: 80px" />
    <TabBar />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import TabBar from '@/components/TabBar.vue'

const router = useRouter()

const heroTags = ['精准面相', 'MBTI人格', '团队匹配', 'AI解析']

const topTypes = [
  { type: 'INTJ', name: '建筑师', color: 'rgba(255,255,255,0.12)' },
  { type: 'ENFP', name: '活动家', color: 'rgba(255,255,255,0.08)' },
  { type: 'ISTP', name: '鉴赏家', color: 'rgba(255,255,255,0.08)' },
  { type: 'ESFJ', name: '执政官', color: 'rgba(255,255,255,0.12)' },
]

const tests = [
  { key: 'mbti', name: 'MBTI 人格测试', desc: '16种人格类型深度分析', questions: 93, color: '#6C3EF6', icon: '🧠' },
  { key: 'disc', name: 'DISC 行为测评', desc: '职场行为风格洞察', questions: 28, color: '#06B6D4', icon: '📊' },
  { key: 'pdp', name: 'PDP 动力测评', desc: '工作动力与领导风格', questions: 40, color: '#10B981', icon: '⚡' },
  { key: 'sbti', name: '商业人格', desc: '创业/商业适应性分析', questions: 36, color: '#F59E0B', icon: '💼' },
]

const discoverItems = [
  {
    path: '/gaokao', label: '高考规划', color: 'linear-gradient(135deg,#FF6B6B,#FF8E53)',
    svg: `<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M22 10v6M2 10l10-5 10 5-10 5-10-5z" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 12v5c3 3 9 3 12 0v-5" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>`
  },
  {
    path: '/ai-chat', label: 'AI 对话', color: 'linear-gradient(135deg,#6C3EF6,#4C1D95)',
    svg: `<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" stroke="white" stroke-width="1.75" stroke-linejoin="round"/></svg>`
  },
  {
    path: '/promo', label: '邀请赚钱', color: 'linear-gradient(135deg,#10B981,#059669)',
    svg: `<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" stroke="white" stroke-width="1.75" stroke-linecap="round"/><circle cx="9" cy="7" r="4" stroke="white" stroke-width="1.75"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="white" stroke-width="1.75" stroke-linecap="round"/></svg>`
  },
  {
    path: '/purchase', label: 'VIP 特权', color: 'linear-gradient(135deg,#F59E0B,#D97706)',
    svg: `<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" stroke="white" stroke-width="1.75" stroke-linejoin="round" fill="white"/></svg>`
  },
]

const mbtiResult = localStorage.getItem('mbtiResult')
const discResult = localStorage.getItem('discResult')
const pdpResult = localStorage.getItem('pdpResult')

const hasResults = computed(() => !!(mbtiResult || discResult || pdpResult))

const myResults = computed(() => {
  const r = []
  if (mbtiResult) {
    try {
      const d = JSON.parse(mbtiResult)
      r.push({ type: 'mbti', name: 'MBTI', value: d.type || d.mbtiType, color: '#6C3EF6' })
    } catch (e) {}
  }
  if (discResult) {
    try {
      const d = JSON.parse(discResult)
      r.push({ type: 'disc', name: 'DISC', value: d.dominantType || d.type, color: '#06B6D4' })
    } catch (e) {}
  }
  if (pdpResult) {
    try {
      const d = JSON.parse(pdpResult)
      r.push({ type: 'pdp', name: 'PDP', value: d.dominantType || d.type, color: '#10B981' })
    } catch (e) {}
  }
  return r
})

const goTest = (test: any) => {
  router.push(`/test/${test.key}`)
}
</script>

<style scoped>
.home { background: var(--bg); min-height: 100vh; }

/* Hero */
.home__hero {
  padding-bottom: 24px;
  border-radius: 0 0 32px 32px;
  overflow: hidden;
}

.home__hero-nav {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px 0;
}
.home__logo {
  display: flex; align-items: center; gap: 8px;
  color: white; font-size: 16px; font-weight: 700;
}
.home__vip-btn {
  display: flex; align-items: center; gap: 5px;
  padding: 6px 14px; border-radius: 20px;
  background: rgba(255,255,255,0.18); border: 1.5px solid rgba(255,255,255,0.35);
  color: white; font-size: 12.5px; font-weight: 600; cursor: pointer;
}

.home__hero-content {
  padding: 24px 20px 16px;
  h1 { font-size: 26px; font-weight: 900; color: white; margin-bottom: 8px; }
  p { font-size: 14px; color: rgba(255,255,255,0.8); line-height: 1.6; }
}

.home__hero-tags {
  display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px;
}
.hero-tag {
  padding: 4px 12px; background: rgba(255,255,255,0.18);
  border-radius: 20px; color: white; font-size: 12px; font-weight: 500;
  border: 1px solid rgba(255,255,255,0.3);
}

.home__type-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 8px; margin: 16px 20px 0;
}
.type-cell {
  border-radius: 14px; padding: 14px 16px;
  border: 1px solid rgba(255,255,255,0.2);
  display: flex; flex-direction: column; gap: 4px;
  cursor: pointer;
  &__type { color: white; font-size: 18px; font-weight: 800; }
  &__name { color: rgba(255,255,255,0.75); font-size: 12px; }
}

/* Actions */
.home__actions { padding: 20px 20px 0; display: flex; flex-direction: column; gap: 10px; }
.home__main-btn { border-radius: 28px; }
.home__sub-btn { height: 44px; border-radius: 22px; font-size: 14px; }

/* Section */
.home__section { padding: 24px 20px 0; }
.home__section-title {
  font-size: 17px; font-weight: 800; color: var(--text); margin-bottom: 14px;
}

/* 测试卡片 */
.home__tests { display: flex; flex-direction: column; gap: 10px; }
.test-card {
  display: flex; align-items: center; gap: 14px;
  background: white; border-radius: 16px; padding: 16px;
  border: 1.5px solid var(--border);
  box-shadow: var(--shadow-sm);
  cursor: pointer;
  transition: transform 0.15s, box-shadow 0.15s;
  &:active { transform: scale(0.98); }
  &__icon {
    width: 48px; height: 48px; border-radius: 14px;
    background: var(--card-color, #6C3EF6);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; flex-shrink: 0;
    opacity: 0.9;
  }
  &__info { flex: 1; min-width: 0; }
  &__name { font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 2px; }
  &__desc { font-size: 12.5px; color: var(--text-secondary); }
  &__count {
    font-size: 12px; font-weight: 600; color: var(--text-light);
    background: var(--bg); padding: 4px 10px; border-radius: 20px;
    flex-shrink: 0;
  }
}

/* 发现网格 */
.home__discover {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;
}
.discover-card {
  display: flex; flex-direction: column; align-items: center; gap: 8px; cursor: pointer;
  &__icon {
    width: 52px; height: 52px; border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
  }
  &__label { font-size: 12px; font-weight: 500; color: var(--text-secondary); }
}

/* 结果条目 */
.home__results { display: flex; flex-direction: column; gap: 8px; }
.result-chip {
  display: flex; align-items: center; gap: 12px;
  background: white; border-radius: 12px; padding: 14px 16px;
  border: 1px solid var(--border); cursor: pointer;
  &__label { color: var(--text-secondary); font-size: 13px; flex: 1; }
  &__value { font-size: 16px; font-weight: 800; }
}
</style>
