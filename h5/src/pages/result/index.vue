<template>
  <div class="result-page">
    <!-- 英雄区 -->
    <div class="result-hero" :style="{ background: `linear-gradient(135deg, ${typeColor} 0%, ${typeDark} 100%)` }">
      <AppNavBar :title="testName + ' 结果'" :show-back="true" :dark="true" />
      <div class="result-hero__body safe-top">
        <div class="result-hero__type-badge">{{ result?.type || result?.mbtiType || result?.dominantType || '--' }}</div>
        <h2 class="result-hero__title">{{ result?.typeName || typeName }}</h2>
        <p class="result-hero__subtitle">{{ result?.summary || typeSummary }}</p>
        <div class="result-hero__tags">
          <span v-for="tag in typeTags" :key="tag">{{ tag }}</span>
        </div>
      </div>
    </div>

    <div class="result-body">
      <!-- 核心维度 -->
      <section class="result-section" v-if="dimensions.length">
        <h3 class="rs-title">核心维度</h3>
        <div class="dims-grid">
          <div v-for="dim in dimensions" :key="dim.label" class="dim-card">
            <div class="dim-card__row">
              <span class="dim-card__label">{{ dim.label }}</span>
              <span class="dim-card__val" :style="{ color: typeColor }">{{ dim.value }}</span>
            </div>
            <div class="dim-bar">
              <div class="dim-bar__fill" :style="{ width: dim.pct + '%', background: typeColor }"/>
            </div>
            <div class="dim-card__desc">{{ dim.desc }}</div>
          </div>
        </div>
      </section>

      <!-- 性格分析 -->
      <section class="result-section">
        <h3 class="rs-title">性格深度解读</h3>
        <div class="analysis-cards">
          <div v-for="item in analysisBlocks" :key="item.title" class="analysis-card card">
            <div class="analysis-card__head" :style="{ background: item.bg }">
              <span class="analysis-card__icon">{{ item.icon }}</span>
              <span class="analysis-card__title">{{ item.title }}</span>
            </div>
            <div class="analysis-card__body">{{ item.content }}</div>
          </div>
        </div>
      </section>

      <!-- 团队匹配 -->
      <section class="result-section">
        <h3 class="rs-title">最佳团队搭档</h3>
        <div class="match-grid">
          <div v-for="m in bestMatches" :key="m.type" class="match-card">
            <div class="match-card__type" :style="{ background: m.color + '18', color: m.color }">{{ m.type }}</div>
            <div class="match-card__name">{{ m.name }}</div>
            <div class="match-card__reason">{{ m.reason }}</div>
          </div>
        </div>
      </section>

      <!-- 操作区 -->
      <div class="result-cta">
        <button class="btn-primary" :style="{ background: `linear-gradient(135deg, ${typeColor}, ${typeDark})` }" @click="goAIChat">
          AI 深度解读我的 {{ typePretty }}
        </button>
        <div class="result-cta__row">
          <button class="result-cta__btn" @click="$router.push('/test-select')">
            继续做其他测评
          </button>
          <button class="result-cta__btn result-cta__btn--share">
            分享结果
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppNavBar from '@/components/AppNavBar.vue'

const route = useRoute()
const router = useRouter()
const testType = route.params.type as string

const testMeta: Record<string, any> = {
  mbti: { name: 'MBTI', color: '#6C3EF6', dark: '#4C1D95', storageKey: 'mbtiResult' },
  disc: { name: 'DISC', color: '#06B6D4', dark: '#0284C7', storageKey: 'discResult' },
  pdp:  { name: 'PDP',  color: '#10B981', dark: '#059669', storageKey: 'pdpResult' },
  sbti: { name: '商业人格', color: '#F59E0B', dark: '#B45309', storageKey: 'sbtiResult' },
}

const meta = testMeta[testType] || testMeta.mbti
const testName = meta.name
const typeColor = meta.color
const typeDark = meta.dark

const result = computed(() => {
  try { return JSON.parse(localStorage.getItem(meta.storageKey) || '') } catch { return null }
})

const typePretty = computed(() => result.value?.type || result.value?.mbtiType || result.value?.dominantType || testName)
const typeName = computed(() => result.value?.typeName || '详细分析')
const typeSummary = computed(() => result.value?.summary || '你已完成测评，以下是你的专属分析报告')
const typeTags = computed<string[]>(() => result.value?.tags || ['独立思考', '目标导向', '系统规划'])

const dimensions = computed(() => {
  const r = result.value
  if (!r) return []
  if (testType === 'mbti') {
    return [
      { label: '外向 E - 内向 I', value: r.ei || 'I', pct: r.eiPct || 65, desc: r.eiDesc || '更倾向于内向，从独处中汲取能量' },
      { label: '直觉 N - 实感 S', value: r.ns || 'N', pct: r.nsPct || 72, desc: r.nsDesc || '偏好抽象思维，善于洞察可能性' },
      { label: '思考 T - 情感 F', value: r.tf || 'T', pct: r.tfPct || 58, desc: r.tfDesc || '决策时更注重客观逻辑' },
      { label: '判断 J - 感知 P', value: r.jp || 'J', pct: r.jpPct || 80, desc: r.jpDesc || '喜欢有计划、有条理的生活方式' },
    ]
  }
  return r.dimensions || []
})

const analysisBlocks = computed(() => result.value?.analysis || [
  { icon: '💪', title: '核心优势', bg: '#EDE9FE', content: '你具备出色的战略思维能力，善于从全局把握问题，能够制定清晰的长期计划并坚定执行。' },
  { icon: '⚠️', title: '成长空间', bg: '#FEF3C7', content: '在人际关系方面可以更加灵活，适当表达情感有助于建立更深厚的信任关系。' },
  { icon: '🎯', title: '职业方向', bg: '#CCFBF1', content: '适合战略咨询、产品管理、研究分析等需要深度思考和系统规划的领域。' },
  { icon: '❤️', title: '感情特质', bg: '#FFE4E6', content: '在感情中注重深度连接，对伴侣忠诚可靠，需要一位理解你内心世界的人。' },
])

const bestMatches = computed(() => result.value?.matches || [
  { type: 'ENFP', name: '活动家', color: '#06B6D4', reason: '互补型搭档，创意与执行完美平衡' },
  { type: 'ENTP', name: '辩论家', color: '#6C3EF6', reason: '智识共鸣，激发彼此最大潜能' },
  { type: 'ESTJ', name: '总经理', color: '#10B981', reason: '目标一致，共同推动卓越成果' },
])

const goAIChat = () => {
  router.push(`/ai-chat?type=${typePretty.value}`)
}
</script>

<style scoped>
.result-page { min-height: 100vh; background: var(--bg); }

.result-hero { overflow: hidden; border-radius: 0 0 32px 32px; }
.result-hero__body { padding: 20px 24px 36px; text-align: center; }

.result-hero__type-badge {
  display: inline-block; padding: 8px 24px;
  background: rgba(255,255,255,0.25); border-radius: 24px;
  color: white; font-size: 26px; font-weight: 900; letter-spacing: 0.08em;
  margin-bottom: 14px;
}

.result-hero__title { color: white; font-size: 22px; font-weight: 800; margin-bottom: 10px; }
.result-hero__subtitle { color: rgba(255,255,255,0.82); font-size: 14px; line-height: 1.7; margin-bottom: 16px; }

.result-hero__tags {
  display: flex; flex-wrap: wrap; gap: 8px; justify-content: center;
  span {
    padding: 5px 14px; background: rgba(255,255,255,0.2);
    border-radius: 20px; color: white; font-size: 12.5px;
    border: 1px solid rgba(255,255,255,0.3);
  }
}

.result-body { padding: 20px 16px; }
.result-section { margin-bottom: 24px; }
.rs-title { font-size: 16px; font-weight: 800; color: var(--text); margin-bottom: 14px; }

/* 维度 */
.dims-grid { display: flex; flex-direction: column; gap: 10px; }
.dim-card {
  background: white; border-radius: 14px; padding: 14px 16px;
  border: 1px solid var(--border);
  &__row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
  &__label { font-size: 13.5px; color: var(--text-secondary); }
  &__val { font-size: 18px; font-weight: 900; }
  &__desc { font-size: 12px; color: var(--text-secondary); margin-top: 6px; }
}
.dim-bar { height: 6px; background: #F3F4F6; border-radius: 3px; overflow: hidden; }
.dim-bar__fill { height: 100%; border-radius: 3px; transition: width 0.8s ease; }

/* 分析卡片 */
.analysis-cards { display: flex; flex-direction: column; gap: 10px; }
.analysis-card {
  overflow: hidden;
  &__head {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px;
  }
  &__icon { font-size: 18px; }
  &__title { font-size: 14px; font-weight: 700; color: var(--text); }
  &__body { padding: 14px 16px; font-size: 13.5px; color: var(--text-secondary); line-height: 1.7; }
}

/* 团队匹配 */
.match-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
.match-card {
  background: white; border-radius: 14px; padding: 14px 12px;
  border: 1px solid var(--border); text-align: center;
  &__type {
    display: inline-block; padding: 4px 10px; border-radius: 10px;
    font-size: 14px; font-weight: 900; margin-bottom: 6px;
  }
  &__name { font-size: 12px; font-weight: 700; color: var(--text); margin-bottom: 6px; }
  &__reason { font-size: 11px; color: var(--text-secondary); line-height: 1.5; }
}

/* CTA */
.result-cta { padding: 8px 0 40px; display: flex; flex-direction: column; gap: 10px; }
.result-cta__row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.result-cta__btn {
  height: 46px; border-radius: 14px; border: 2px solid var(--border);
  background: white; color: var(--text); font-size: 13.5px; font-weight: 600; cursor: pointer;
  &--share { border-color: var(--primary); color: var(--primary); }
}
</style>
