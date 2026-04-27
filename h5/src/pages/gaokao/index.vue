<template>
  <div class="gaokao-home">
    <!-- 顶部英雄区 -->
    <div class="gaokao-hero">
      <AppNavBar title="高考志愿规划" :show-back="true" :dark="true" />
      <div class="gaokao-hero__body">
        <div class="gaokao-hero__icon">🎓</div>
        <h1>AI 高考志愿规划</h1>
        <p>三步完成个性化志愿分析<br>面相 × 成绩 × 性格 = 最优方案</p>
      </div>
    </div>

    <!-- 流程步骤 -->
    <div class="gaokao-steps">
      <div v-for="(step, i) in steps" :key="step.id" class="gstep" :class="{ 'gstep--done': completedSteps.includes(step.id), 'gstep--active': currentStep === i }">
        <div class="gstep__line" v-if="i < steps.length - 1" />
        <div class="gstep__icon" :style="{ background: completedSteps.includes(step.id) ? '#10B981' : step.color }">
          <span v-if="completedSteps.includes(step.id)">✓</span>
          <span v-else>{{ step.num }}</span>
        </div>
        <div class="gstep__content">
          <div class="gstep__title">{{ step.title }}</div>
          <div class="gstep__desc">{{ step.desc }}</div>
          <div v-if="completedSteps.includes(step.id)" class="gstep__done-badge">已完成</div>
        </div>
      </div>
    </div>

    <!-- 说明卡片 -->
    <div class="gaokao-intro card">
      <h3>为什么选择 AI 规划志愿？</h3>
      <div class="intro-points">
        <div v-for="pt in points" :key="pt.title" class="intro-point">
          <div class="intro-point__icon" :style="{ background: pt.color }">{{ pt.icon }}</div>
          <div>
            <div class="intro-point__title">{{ pt.title }}</div>
            <div class="intro-point__desc">{{ pt.desc }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- 开始按钮 -->
    <div class="gaokao-cta">
      <button class="btn-primary gaokao-cta__btn" style="background:linear-gradient(135deg,#FF6B6B,#FF8E53);box-shadow:0 4px 20px rgba(255,107,107,0.4)" @click="startFlow">
        {{ ctaText }}
      </button>
      <p class="gaokao-cta__sub">仅需 3 步，约 10 分钟完成</p>
    </div>

    <div style="height:80px"/>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import AppNavBar from '@/components/AppNavBar.vue'

const router = useRouter()

const steps = [
  { id: 'face',  num: '1', title: '面相拍摄', desc: '拍摄正面照片，AI 分析性格特质', color: '#6C3EF6' },
  { id: 'score', num: '2', title: '成绩上传', desc: '上传模考成绩截图或填写分数段', color: '#FF6B6B' },
  { id: 'mbti',  num: '3', title: '性格测评', desc: '完成 MBTI 问卷，深度性格建模', color: '#06B6D4' },
]

const completedSteps = computed(() => {
  const done: string[] = []
  if (localStorage.getItem('aiResult')) done.push('face')
  if (localStorage.getItem('gaokaoScore')) done.push('score')
  if (localStorage.getItem('mbtiResult')) done.push('mbti')
  return done
})

const currentStep = computed(() => {
  if (!completedSteps.value.includes('face')) return 0
  if (!completedSteps.value.includes('score')) return 1
  if (!completedSteps.value.includes('mbti')) return 2
  return 3
})

const ctaText = computed(() => {
  if (completedSteps.value.length === 3) return '查看完整分析报告'
  if (completedSteps.value.length === 0) return '立即开始 · 第一步拍照'
  return '继续未完成的步骤'
})

const points = [
  { icon: '🧬', color: '#EDE9FE', title: '面相性格建模', desc: '从面相特征推断先天性格优势' },
  { icon: '📊', color: '#CCFBF1', title: '数据驱动决策', desc: '结合历年录取数据精准匹配' },
  { icon: '🎯', color: '#FEF3C7', title: '个性化推荐', desc: '基于性格特质推荐最适合的专业方向' },
]

const startFlow = () => {
  if (completedSteps.value.length === 3) {
    router.push('/gaokao/result')
  } else if (!completedSteps.value.includes('face')) {
    router.push('/gaokao/camera')
  } else if (!completedSteps.value.includes('score')) {
    router.push('/gaokao/score')
  } else {
    router.push('/test/mbti')
  }
}
</script>

<style scoped>
.gaokao-home { min-height: 100vh; background: var(--bg); }

.gaokao-hero {
  background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 60%, #F59E0B 100%);
  border-radius: 0 0 32px 32px; overflow: hidden;
}
.gaokao-hero__body {
  padding: 16px 24px 36px; text-align: center;
  .gaokao-hero__icon { font-size: 48px; margin-bottom: 12px; }
  h1 { color: white; font-size: 24px; font-weight: 900; margin-bottom: 10px; }
  p { color: rgba(255,255,255,0.88); font-size: 14px; line-height: 1.7; }
}

.gaokao-steps {
  padding: 24px 20px; display: flex; flex-direction: column; gap: 0; position: relative;
}

.gstep {
  display: flex; gap: 16px; position: relative; padding-bottom: 24px;
  &:last-child { padding-bottom: 0; }

  &__line {
    position: absolute; left: 19px; top: 40px; bottom: 0;
    width: 2px; background: #E5E7EB; z-index: 0;
  }

  &--done &__line { background: #A7F3D0; }

  &__icon {
    width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 16px; font-weight: 800; z-index: 1;
  }

  &__content { flex: 1; padding-top: 8px; }
  &__title { font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 3px; }
  &__desc { font-size: 13px; color: var(--text-secondary); }
  &__done-badge {
    display: inline-block; margin-top: 4px;
    font-size: 11px; font-weight: 700; color: #059669;
    background: #ECFDF5; padding: 2px 8px; border-radius: 8px;
  }

  &--active &__content &__title { color: #FF6B6B; }
}

.gaokao-intro {
  margin: 0 16px 20px; padding: 20px;
  h3 { font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 16px; }
}
.intro-points { display: flex; flex-direction: column; gap: 14px; }
.intro-point {
  display: flex; gap: 14px; align-items: flex-start;
  &__icon {
    width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 18px;
  }
  &__title { font-size: 14px; font-weight: 600; color: var(--text); margin-bottom: 2px; }
  &__desc { font-size: 12.5px; color: var(--text-secondary); }
}

.gaokao-cta {
  padding: 0 16px 16px; text-align: center;
  &__btn { border-radius: 28px; }
  &__sub { font-size: 12.5px; color: var(--text-secondary); margin-top: 10px; }
}
</style>
