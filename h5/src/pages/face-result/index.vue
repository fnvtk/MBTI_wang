<template>
  <div class="face-result">
    <div class="face-result__hero gradient-bg safe-top">
      <AppNavBar title="面相分析结果" :show-back="true" :dark="true" />
      <div class="face-result__score-area">
        <div class="score-ring">
          <svg width="120" height="120" viewBox="0 0 120 120">
            <circle cx="60" cy="60" r="52" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="10"/>
            <circle cx="60" cy="60" r="52" fill="none" stroke="white" stroke-width="10"
              stroke-dasharray="326.7"
              :stroke-dashoffset="326.7 * (1 - (result?.score || 82) / 100)"
              stroke-linecap="round" transform="rotate(-90 60 60)"/>
          </svg>
          <div class="score-ring__num">{{ result?.score || 82 }}</div>
        </div>
        <div class="face-result__summary">
          <h2>{{ result?.level || '睿智型面相' }}</h2>
          <p>{{ result?.summary || '您的面相展现出卓越的智慧与领导气质' }}</p>
        </div>
      </div>
    </div>

    <div class="face-result__body">
      <!-- 特征维度 -->
      <section class="result-section">
        <h3 class="section-title">面相特征分析</h3>
        <div class="traits-grid">
          <div v-for="trait in traits" :key="trait.name" class="trait-card">
            <div class="trait-card__bar-wrap">
              <div class="trait-card__name">{{ trait.name }}</div>
              <div class="trait-card__score-txt">{{ trait.score }}</div>
            </div>
            <div class="trait-card__bar">
              <div class="trait-card__fill" :style="{ width: trait.score + '%', background: trait.color }" />
            </div>
            <div class="trait-card__desc">{{ trait.desc }}</div>
          </div>
        </div>
      </section>

      <!-- AI 分析详情 -->
      <section class="result-section">
        <h3 class="section-title">AI 深度解读</h3>
        <div class="ai-analysis card">
          <div v-for="(item, i) in analysisItems" :key="i" class="analysis-item">
            <div class="analysis-item__icon" :style="{ background: item.color }">
              <span>{{ item.icon }}</span>
            </div>
            <div class="analysis-item__content">
              <div class="analysis-item__title">{{ item.title }}</div>
              <div class="analysis-item__text">{{ item.text }}</div>
            </div>
          </div>
        </div>
      </section>

      <!-- 推荐匹配类型 -->
      <section class="result-section">
        <h3 class="section-title">性格匹配预测</h3>
        <div class="match-types">
          <div v-for="m in matchTypes" :key="m.type" class="match-chip" :style="{ borderColor: m.color, color: m.color }">
            <span class="match-chip__type">{{ m.type }}</span>
            <span class="match-chip__label">{{ m.label }}</span>
          </div>
        </div>
      </section>

      <!-- 继续测试按钮 -->
      <div class="face-result__cta">
        <button class="btn-primary" @click="$router.push('/test-select')">
          继续完成性格测试，解锁完整报告
        </button>
        <button class="btn-secondary" style="margin-top:12px" @click="$router.push('/home')">
          返回首页
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import AppNavBar from '@/components/AppNavBar.vue'

const result = computed(() => {
  try { return JSON.parse(localStorage.getItem('aiResult') || '') } catch { return null }
})

const traits = computed(() => [
  { name: '智慧指数', score: result.value?.wisdom || 88, color: '#6C3EF6', desc: '分析能力强，逻辑清晰' },
  { name: '领导力', score: result.value?.leadership || 76, color: '#06B6D4', desc: '具备较强的统筹协调能力' },
  { name: '亲和力', score: result.value?.affinity || 82, color: '#10B981', desc: '善于与人建立信任关系' },
  { name: '执行力', score: result.value?.execution || 71, color: '#F59E0B', desc: '行动力强，注重结果' },
  { name: '创造力', score: result.value?.creativity || 85, color: '#FF6B6B', desc: '思维活跃，富有创新意识' },
])

const analysisItems = computed(() => [
  { icon: '👁', color: '#EDE9FE', title: '眼神分析', text: result.value?.eyeAnalysis || '眼睛清澈有神，展现出敏锐的洞察力与深厚的内在世界' },
  { icon: '👃', color: '#CCFBF1', title: '鼻相分析', text: result.value?.noseAnalysis || '鼻梁挺直，财运亨通，具备良好的财务管理能力' },
  { icon: '💬', color: '#DBEAFE', title: '口相分析', text: result.value?.mouthAnalysis || '嘴型端正，善于表达，具备较强的沟通和说服能力' },
])

const matchTypes = [
  { type: 'INTJ', label: '建筑师', color: '#6C3EF6' },
  { type: 'ENTJ', label: '指挥官', color: '#4C1D95' },
  { type: 'INTP', label: '逻辑学家', color: '#06B6D4' },
]
</script>

<style scoped>
.face-result { min-height: 100vh; background: var(--bg); }

.face-result__hero { border-radius: 0 0 32px 32px; overflow: hidden; }

.face-result__score-area {
  display: flex; align-items: center; gap: 20px;
  padding: 24px 24px 32px;
}

.score-ring {
  position: relative; flex-shrink: 0;
  .score-ring__num {
    position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
    font-size: 28px; font-weight: 900; color: white;
  }
}

.face-result__summary {
  h2 { color: white; font-size: 20px; font-weight: 800; margin-bottom: 8px; }
  p { color: rgba(255,255,255,0.8); font-size: 13.5px; line-height: 1.6; }
}

.face-result__body { padding: 20px; }

.result-section { margin-bottom: 24px; }
.section-title { font-size: 16px; font-weight: 800; color: var(--text); margin-bottom: 14px; }

.traits-grid { display: flex; flex-direction: column; gap: 12px; }
.trait-card {
  background: white; border-radius: 12px; padding: 14px 16px;
  border: 1px solid var(--border);
  &__bar-wrap { display: flex; justify-content: space-between; margin-bottom: 8px; }
  &__name { font-size: 14px; font-weight: 600; color: var(--text); }
  &__score-txt { font-size: 13px; font-weight: 700; color: var(--primary); }
  &__bar { height: 6px; background: #F3F4F6; border-radius: 3px; overflow: hidden; margin-bottom: 6px; }
  &__fill { height: 100%; border-radius: 3px; transition: width 1s ease; }
  &__desc { font-size: 12px; color: var(--text-secondary); }
}

.ai-analysis { padding: 16px; display: flex; flex-direction: column; gap: 16px; }
.analysis-item {
  display: flex; gap: 14px;
  &__icon {
    width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 18px;
  }
  &__title { font-size: 14px; font-weight: 700; color: var(--text); margin-bottom: 4px; }
  &__text { font-size: 13px; color: var(--text-secondary); line-height: 1.6; }
}

.match-types { display: flex; flex-wrap: wrap; gap: 10px; }
.match-chip {
  display: flex; flex-direction: column; align-items: center;
  padding: 10px 20px; border-radius: 12px; border: 2px solid;
  background: white; cursor: pointer;
  &__type { font-size: 18px; font-weight: 900; }
  &__label { font-size: 11px; margin-top: 2px; }
}

.face-result__cta { padding: 8px 0 32px; }
</style>
