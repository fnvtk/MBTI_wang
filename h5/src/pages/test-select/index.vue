<template>
  <div class="test-select">
    <div class="test-select__hero gradient-bg safe-top">
      <AppNavBar title="选择测评" :show-back="true" :dark="true" />
      <div class="test-select__hero-body">
        <h2>发现你的性格密码</h2>
        <p>多维度测评，全面洞察自我</p>
      </div>
    </div>

    <div class="test-select__body">
      <!-- 测评卡片列表 -->
      <div class="test-cards">
        <div
          v-for="item in tests"
          :key="item.key"
          class="tcard"
          :style="{ '--tc': item.color }"
          @click="go(item)"
        >
          <div class="tcard__left">
            <div class="tcard__icon-wrap">
              <span class="tcard__emoji">{{ item.emoji }}</span>
            </div>
          </div>
          <div class="tcard__mid">
            <div class="tcard__name">{{ item.name }}</div>
            <div class="tcard__desc">{{ item.desc }}</div>
            <div class="tcard__meta">
              <span class="tcard__q">{{ item.questions }}题</span>
              <span class="tcard__time">约 {{ item.minutes }} 分钟</span>
            </div>
          </div>
          <div class="tcard__right">
            <div v-if="getResult(item.key)" class="tcard__done">
              <span>{{ getResult(item.key) }}</span>
              <p>已完成</p>
            </div>
            <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path d="M9 18l6-6-6-6" stroke="#C4B5FD" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
          </div>
        </div>
      </div>

      <!-- 高考版入口（特殊样式） -->
      <div class="gaokao-banner" @click="$router.push('/gaokao')">
        <div class="gaokao-banner__left">
          <div class="gaokao-banner__icon">🎓</div>
          <div>
            <div class="gaokao-banner__title">高考志愿规划</div>
            <div class="gaokao-banner__sub">面相 × 成绩 × AI = 最佳志愿方案</div>
          </div>
        </div>
        <div class="gaokao-banner__badge">NEW</div>
      </div>

      <!-- AI 对话入口 -->
      <div class="ai-banner" @click="$router.push('/ai-chat')">
        <div class="ai-banner__content">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" style="flex-shrink:0">
            <rect width="24" height="24" rx="12" fill="rgba(108,62,246,0.15)"/>
            <path d="M8 10h8M8 13h5" stroke="#6C3EF6" stroke-width="1.75" stroke-linecap="round"/>
            <circle cx="17" cy="13" r="2" fill="#6C3EF6"/>
          </svg>
          <div>
            <div class="ai-banner__title">AI 性格解读对话</div>
            <div class="ai-banner__sub">基于你的测评结果，深度对话分析</div>
          </div>
        </div>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M9 18l6-6-6-6" stroke="#6C3EF6" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
      </div>
    </div>

    <div style="height:80px" />
    <TabBar />
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import AppNavBar from '@/components/AppNavBar.vue'
import TabBar from '@/components/TabBar.vue'

const router = useRouter()

const tests = [
  { key: 'mbti', name: 'MBTI 人格测试', desc: '探索16种人格类型，了解你的思维方式与行为模式', questions: 93, minutes: 15, color: '#6C3EF6', emoji: '🧠' },
  { key: 'disc', name: 'DISC 行为风格', desc: '职场行为与沟通风格的四维度深度分析', questions: 28, minutes: 8, color: '#06B6D4', emoji: '📊' },
  { key: 'pdp', name: 'PDP 动力测评', desc: '发现驱动你前进的内在动力与领导风格', questions: 40, minutes: 10, color: '#10B981', emoji: '⚡' },
  { key: 'sbti', name: '商业人格测试', desc: '评估创业潜力、商业直觉与决策风格', questions: 36, minutes: 10, color: '#F59E0B', emoji: '💼' },
]

const getResult = (key: string) => {
  try {
    const raw = localStorage.getItem(`${key}Result`)
    if (!raw) return null
    const d = JSON.parse(raw)
    return d.type || d.mbtiType || d.dominantType || null
  } catch { return null }
}

const go = (test: any) => {
  router.push(`/test/${test.key}`)
}
</script>

<style scoped>
.test-select { background: var(--bg); min-height: 100vh; }
.test-select__hero { border-radius: 0 0 28px 28px; }
.test-select__hero-body {
  padding: 16px 24px 28px;
  h2 { font-size: 22px; font-weight: 800; color: white; margin-bottom: 6px; }
  p { color: rgba(255,255,255,0.75); font-size: 14px; }
}

.test-select__body { padding: 20px 16px; display: flex; flex-direction: column; gap: 12px; }

.test-cards { display: flex; flex-direction: column; gap: 10px; }

.tcard {
  display: flex; align-items: center; gap: 14px;
  background: white; border-radius: 18px; padding: 16px;
  border: 1.5px solid rgba(0,0,0,0.06);
  box-shadow: 0 2px 12px rgba(108,62,246,0.06);
  cursor: pointer;
  transition: transform 0.15s, box-shadow 0.15s;
  &:active { transform: scale(0.98); }

  &__left { flex-shrink: 0; }
  &__icon-wrap {
    width: 52px; height: 52px; border-radius: 16px;
    background: color-mix(in srgb, var(--tc) 12%, white);
    display: flex; align-items: center; justify-content: center;
  }
  &__emoji { font-size: 24px; }

  &__mid { flex: 1; min-width: 0; }
  &__name { font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 4px; }
  &__desc { font-size: 12.5px; color: var(--text-secondary); line-height: 1.5; margin-bottom: 8px; }
  &__meta { display: flex; gap: 10px; }
  &__q, &__time {
    font-size: 11.5px; font-weight: 500;
    background: #F3F4F6; color: var(--text-secondary);
    padding: 2px 8px; border-radius: 10px;
  }

  &__right { flex-shrink: 0; }
  &__done {
    text-align: center;
    span { font-size: 15px; font-weight: 800; color: var(--tc); }
    p { font-size: 10px; color: var(--success); margin-top: 2px; }
  }
}

.gaokao-banner {
  display: flex; align-items: center; justify-content: space-between;
  background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
  border-radius: 18px; padding: 18px 16px;
  cursor: pointer; box-shadow: 0 4px 20px rgba(255,107,107,0.3);
  transition: transform 0.15s;
  &:active { transform: scale(0.98); }
  &__left { display: flex; align-items: center; gap: 14px; }
  &__icon { font-size: 28px; }
  &__title { font-size: 15px; font-weight: 700; color: white; margin-bottom: 3px; }
  &__sub { font-size: 12px; color: rgba(255,255,255,0.85); }
  &__badge {
    background: white; color: #FF6B6B; font-size: 11px; font-weight: 800;
    padding: 3px 8px; border-radius: 10px;
  }
}

.ai-banner {
  display: flex; align-items: center; justify-content: space-between;
  background: white; border-radius: 16px; padding: 16px;
  border: 2px solid rgba(108,62,246,0.15);
  cursor: pointer;
  &:active { background: var(--primary-light); }
  &__content { display: flex; align-items: center; gap: 12px; }
  &__title { font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 3px; }
  &__sub { font-size: 12.5px; color: var(--text-secondary); }
}
</style>
