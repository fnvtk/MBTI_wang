<template>
  <div class="gk-result">
    <div class="gk-result__hero">
      <AppNavBar title="高考志愿分析报告" :show-back="true" :dark="true" />
      <div class="gk-result__hero-body">
        <div class="gk-result__score-display">
          <span class="gk-result__score">{{ totalScore }}</span>
          <span class="gk-result__score-label">综合评估分</span>
        </div>
        <div class="gk-result__level">{{ scoreLevel }}</div>
        <p class="gk-result__summary">{{ summary }}</p>
      </div>
    </div>

    <!-- 加载状态 -->
    <div v-if="loading" class="gk-loading">
      <div class="loading-spin" style="border-top-color:#FF6B6B"/>
      <p>AI 正在综合面相、成绩、性格生成专属报告…</p>
    </div>

    <div v-else class="gk-result__body">
      <!-- 综合评估 -->
      <section class="rs">
        <h3 class="rs__title">综合能力评估</h3>
        <div class="ability-grid">
          <div v-for="ab in abilities" :key="ab.name" class="ab-card">
            <div class="ab-card__score" :style="{ color: ab.color }">{{ ab.score }}</div>
            <div class="ab-card__name">{{ ab.name }}</div>
            <div class="ab-card__bar"><div :style="{ width: ab.score + '%', background: ab.color }"/></div>
          </div>
        </div>
      </section>

      <!-- 推荐专业方向 -->
      <section class="rs">
        <h3 class="rs__title">推荐专业方向</h3>
        <div class="major-list">
          <div v-for="(m, i) in majors" :key="m.name" class="major-card" :class="{ 'major-card--top': i === 0 }">
            <div class="major-card__rank">{{ i + 1 }}</div>
            <div class="major-card__info">
              <div class="major-card__name">{{ m.name }}</div>
              <div class="major-card__reason">{{ m.reason }}</div>
              <div class="major-card__meta">
                <span :style="{ color: m.matchColor }">匹配度 {{ m.match }}%</span>
                <span>就业前景：{{ m.prospect }}</span>
              </div>
            </div>
            <div class="major-card__badge" :style="{ background: m.matchColor + '20', color: m.matchColor }">
              {{ m.match }}%
            </div>
          </div>
        </div>
      </section>

      <!-- 推荐院校 -->
      <section class="rs">
        <h3 class="rs__title">院校推荐</h3>
        <div class="school-tabs">
          <button v-for="lv in schoolLevels" :key="lv" :class="['stab', { active: schoolTab === lv }]" @click="schoolTab = lv">{{ lv }}</button>
        </div>
        <div class="school-list">
          <div v-for="s in filteredSchools" :key="s.name" class="school-card">
            <div class="school-card__left">
              <div class="school-card__name">{{ s.name }}</div>
              <div class="school-card__city">{{ s.city }} · {{ s.level }}</div>
            </div>
            <div class="school-card__right">
              <div class="school-card__score">{{ s.minScore }}</div>
              <div class="school-card__label">去年最低分</div>
            </div>
          </div>
        </div>
      </section>

      <!-- AI 综合建议 -->
      <section class="rs">
        <h3 class="rs__title">AI 专属建议</h3>
        <div class="advice-card card">
          <div class="advice-icon">🤖</div>
          <p>{{ aiAdvice }}</p>
        </div>
      </section>

      <!-- CTA -->
      <div class="gk-cta">
        <button class="btn-primary" style="background:linear-gradient(135deg,#FF6B6B,#FF8E53);border-radius:28px;box-shadow:0 4px 20px rgba(255,107,107,.35)" @click="$router.push('/ai-chat?src=gaokao')">
          与 AI 深度讨论志愿方案
        </button>
        <button class="btn-secondary" style="margin-top:10px;border-radius:28px;border-color:#FF6B6B;color:#FF6B6B" @click="$router.push('/gaokao')">
          重新分析
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppNavBar from '@/components/AppNavBar.vue'
import http from '@/utils/request'

const loading = ref(false)
const result = ref<any>(null)

onMounted(async () => {
  // 如果已有结果则直接展示
  const cached = localStorage.getItem('gaokaoResult')
  if (cached) { try { result.value = JSON.parse(cached) } catch(e) {} }
  if (!result.value) {
    loading.value = true
    try {
      const score = JSON.parse(localStorage.getItem('gaokaoScore') || '{}')
      const face  = JSON.parse(localStorage.getItem('aiResult') || '{}')
      const mbti  = JSON.parse(localStorage.getItem('mbtiResult') || '{}')
      const res = await http.post('/api/gaokao/analyze', { score, face, mbti })
      result.value = res.data.data
      localStorage.setItem('gaokaoResult', JSON.stringify(result.value))
    } catch(e) {
      result.value = null
    } finally { loading.value = false }
  }
})

const totalScore = computed(() => result.value?.score || 92)
const scoreLevel = computed(() => result.value?.level || '重点院校冲刺型')
const summary = computed(() => result.value?.summary || '综合面相特质、成绩分布与MBTI性格，你最适合理工+商科复合方向')
const aiAdvice = computed(() => result.value?.advice || '你的INTJ性格结合理科强势科目，建议冲刺计算机/数学+经济复合专业。优先考虑985院校的实验班，兼顾211院校的王牌专业。注意填报梯度：冲刺2所、稳妥2所、保底1所。')

const abilities = computed(() => result.value?.abilities || [
  { name: '逻辑推理', score: 91, color: '#6C3EF6' },
  { name: '语言表达', score: 78, color: '#06B6D4' },
  { name: '创新思维', score: 85, color: '#F59E0B' },
  { name: '社交协作', score: 72, color: '#10B981' },
])

const majors = computed(() => result.value?.majors || [
  { name: '计算机科学与技术', reason: '与INTJ性格高度匹配，逻辑思维强，职业发展空间广阔', match: 95, matchColor: '#6C3EF6', prospect: '极佳' },
  { name: '数学与应用数学', reason: '发挥你的抽象思维优势，可跨领域发展', match: 88, matchColor: '#4C1D95', prospect: '好' },
  { name: '金融学', reason: '结合分析能力，在量化金融领域有天然优势', match: 82, matchColor: '#06B6D4', prospect: '好' },
  { name: '人工智能', reason: '前沿领域，与你的思维方式完美契合', match: 90, matchColor: '#10B981', prospect: '极佳' },
])

const schoolLevels = ['冲刺', '稳妥', '保底']
const schoolTab = ref('冲刺')

const allSchools = [
  { name: '北京大学', city: '北京', level: '985', minScore: 680, category: '冲刺' },
  { name: '清华大学', city: '北京', level: '985', minScore: 690, category: '冲刺' },
  { name: '复旦大学', city: '上海', level: '985', minScore: 672, category: '冲刺' },
  { name: '华中科技大学', city: '武汉', level: '985', minScore: 651, category: '稳妥' },
  { name: '西安交通大学', city: '西安', level: '985', minScore: 645, category: '稳妥' },
  { name: '南京大学', city: '南京', level: '985', minScore: 668, category: '稳妥' },
  { name: '苏州大学', city: '苏州', level: '211', minScore: 598, category: '保底' },
  { name: '深圳大学', city: '深圳', level: '省重点', minScore: 572, category: '保底' },
]
const filteredSchools = computed(() => allSchools.filter(s => s.category === schoolTab.value))
</script>

<style scoped>
.gk-result { min-height:100vh;background:var(--bg) }
.gk-result__hero { background:linear-gradient(135deg,#FF6B6B 0%,#FF8E53 60%,#F59E0B 100%);border-radius:0 0 32px 32px;overflow:hidden }
.gk-result__hero-body { padding:16px 24px 36px;text-align:center }
.gk-result__score-display { display:flex;flex-direction:column;align-items:center;gap:4px;margin-bottom:12px }
.gk-result__score { font-size:52px;font-weight:900;color:white;line-height:1 }
.gk-result__score-label { font-size:13px;color:rgba(255,255,255,.8) }
.gk-result__level { display:inline-block;padding:6px 20px;background:rgba(255,255,255,.25);border-radius:20px;color:white;font-size:14px;font-weight:700;margin-bottom:10px }
.gk-result__summary { color:rgba(255,255,255,.85);font-size:13.5px;line-height:1.7 }
.gk-loading { display:flex;flex-direction:column;align-items:center;gap:16px;padding:60px 20px;p { color:var(--text-secondary);font-size:14px;text-align:center } }
.gk-result__body { padding:20px 16px 40px }
.rs { margin-bottom:24px }
.rs__title { font-size:16px;font-weight:800;color:var(--text);margin-bottom:14px }
.ability-grid { display:grid;grid-template-columns:1fr 1fr;gap:10px }
.ab-card { background:white;border-radius:14px;padding:14px 16px;border:1px solid var(--border);text-align:center;&__score { font-size:26px;font-weight:900;margin-bottom:2px }&__name { font-size:12px;color:var(--text-secondary);margin-bottom:8px }&__bar { height:4px;background:#F3F4F6;border-radius:2px;overflow:hidden;div { height:100%;border-radius:2px;transition:width .8s } } }
.major-list { display:flex;flex-direction:column;gap:10px }
.major-card { display:flex;align-items:center;gap:12px;background:white;border-radius:16px;padding:14px 16px;border:1.5px solid var(--border);transition:transform .15s;&--top { border-color:#FF6B6B;background:linear-gradient(135deg,#fff5f5,white) }&__rank { width:28px;height:28px;border-radius:50%;background:#F3F4F6;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex-shrink:0 }.major-card--top &__rank { background:#FF6B6B;color:white }&__info { flex:1 }&__name { font-size:15px;font-weight:700;color:var(--text);margin-bottom:4px }&__reason { font-size:12.5px;color:var(--text-secondary);margin-bottom:6px;line-height:1.5 }&__meta { display:flex;gap:12px;font-size:12px;color:var(--text-secondary) }&__badge { font-size:16px;font-weight:900;padding:6px 12px;border-radius:10px;flex-shrink:0 } }
.school-tabs { display:flex;gap:6px;margin-bottom:12px }
.stab { padding:6px 16px;border-radius:20px;border:1.5px solid var(--border);background:white;font-size:13px;font-weight:500;color:var(--text-secondary);cursor:pointer;&.active { background:linear-gradient(135deg,#FF6B6B,#FF8E53);color:white;border-color:transparent;font-weight:700 } }
.school-list { display:flex;flex-direction:column;gap:8px }
.school-card { display:flex;justify-content:space-between;align-items:center;background:white;border-radius:14px;padding:14px 16px;border:1px solid var(--border);&__name { font-size:15px;font-weight:700;color:var(--text);margin-bottom:3px }&__city { font-size:12.5px;color:var(--text-secondary) }&__right { text-align:right }&__score { font-size:20px;font-weight:900;color:#FF6B6B }&__label { font-size:11px;color:var(--text-secondary) } }
.advice-card { display:flex;gap:14px;padding:16px;align-items:flex-start;&.card p { font-size:13.5px;color:var(--text);line-height:1.8 }.advice-icon { font-size:24px;flex-shrink:0 } }
.gk-cta { padding:8px 0 40px;display:flex;flex-direction:column }
</style>
