<template>
  <div class="quiz">
    <!-- 顶部进度导航 -->
    <div class="quiz__header" :style="{ background: themeColor }">
      <button class="quiz__back" @click="confirmExit">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
          <path d="M19 12H5M12 5l-7 7 7 7" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <div class="quiz__progress-wrap">
        <div class="quiz__progress-track">
          <div class="quiz__progress-fill" :style="{ width: progressPct + '%' }"/>
        </div>
        <span class="quiz__progress-text">{{ current + 1 }} / {{ questions.length }}</span>
      </div>
    </div>

    <!-- 题目区 -->
    <div class="quiz__body" v-if="!submitting && !done">
      <transition name="slide-up" mode="out-in">
        <div :key="current" class="quiz__question-card fade-in">
          <div class="quiz__q-num">第 {{ current + 1 }} 题</div>
          <h3 class="quiz__q-text">{{ q.question }}</h3>

          <!-- 量表题 -->
          <div v-if="q.type === 'scale'" class="quiz__scale">
            <div class="scale-labels">
              <span>{{ q.leftLabel || '完全不符合' }}</span>
              <span>{{ q.rightLabel || '完全符合' }}</span>
            </div>
            <div class="scale-options">
              <button
                v-for="n in [1,2,3,4,5]" :key="n"
                class="scale-btn"
                :class="{ 'scale-btn--active': answers[current] === n, 'scale-btn--large': n === 1 || n === 5 }"
                :style="answers[current] === n ? { background: themeColor, borderColor: themeColor } : {}"
                @click="selectScale(n)"
              >{{ n }}</button>
            </div>
          </div>

          <!-- 单选题 -->
          <div v-else class="quiz__options">
            <button
              v-for="(opt, i) in q.options"
              :key="i"
              class="quiz__option"
              :class="{ 'quiz__option--active': answers[current] === i }"
              :style="answers[current] === i ? { borderColor: themeColor, background: themeColor + '12' } : {}"
              @click="selectOption(i)"
            >
              <span class="quiz__option-letter">{{ letters[i] }}</span>
              <span class="quiz__option-text">{{ opt }}</span>
            </button>
          </div>
        </div>
      </transition>
    </div>

    <!-- 提交中 -->
    <div v-if="submitting" class="quiz__submitting">
      <div class="loading-spin" :style="{ borderTopColor: themeColor }"/>
      <p>AI 正在深度分析你的性格特征…</p>
      <p class="submitting-sub">综合 {{ questions.length }} 道题目进行精准建模</p>
    </div>

    <!-- 底部操作 -->
    <div v-if="!submitting && !done" class="quiz__footer">
      <button v-if="current > 0" class="quiz__prev" @click="prev">上一题</button>
      <button
        class="quiz__next"
        :disabled="answers[current] === undefined"
        :style="{ background: themeColor }"
        @click="next"
      >
        {{ current === questions.length - 1 ? '提交测评' : '下一题' }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import http from '@/utils/request'

const props = defineProps<{
  testType: string
  themeColor?: string
  apiPath: string
  resultStorageKey: string
  resultPath: string
}>()

const router = useRouter()
const questions = ref<any[]>([])
const current = ref(0)
const answers = ref<Record<number, any>>({})
const submitting = ref(false)
const done = ref(false)
const loading = ref(true)

const letters = ['A', 'B', 'C', 'D', 'E']
const themeColor = computed(() => props.themeColor || '#6C3EF6')
const progressPct = computed(() => ((current.value + 1) / Math.max(questions.value.length, 1)) * 100)
const q = computed(() => questions.value[current.value] || {})

onMounted(async () => {
  try {
    const res = await http.get(`/api/test/${props.testType}/questions`)
    questions.value = res.data.data || []
  } catch (e) {
    // 回退到本地题目
    questions.value = getFallbackQuestions(props.testType)
  } finally {
    loading.value = false
  }
})

const selectOption = (i: number) => {
  answers.value[current.value] = i
  setTimeout(() => {
    if (current.value < questions.value.length - 1) next()
  }, 200)
}

const selectScale = (n: number) => {
  answers.value[current.value] = n
  setTimeout(() => {
    if (current.value < questions.value.length - 1) next()
  }, 200)
}

const next = async () => {
  if (answers.value[current.value] === undefined) return
  if (current.value < questions.value.length - 1) {
    current.value++
  } else {
    await submit()
  }
}

const prev = () => {
  if (current.value > 0) current.value--
}

const confirmExit = () => {
  if (confirm('确定退出测评？已作答题目将丢失')) router.back()
}

const submit = async () => {
  submitting.value = true
  try {
    const payload = {
      type: props.testType,
      answers: Object.values(answers.value)
    }
    const res = await http.post(props.apiPath, payload)
    const result = res.data.data
    localStorage.setItem(props.resultStorageKey, JSON.stringify(result))
    router.replace(props.resultPath)
  } catch (e: any) {
    alert(e.message || '提交失败，请重试')
  } finally {
    submitting.value = false
  }
}

// 每种测评的本地兜底题目（前 3 题用于演示）
function getFallbackQuestions(type: string) {
  const base = {
    mbti: [
      { question: '在社交场合中，你通常会主动发起对话和认识新朋友', type: 'scale', leftLabel: '不符合', rightLabel: '符合' },
      { question: '当面对问题时，你更倾向于从实际经验出发，而非理论推导', type: 'scale', leftLabel: '不符合', rightLabel: '符合' },
      { question: '在做决定时，你更看重逻辑分析，而不是个人感受', type: 'scale', leftLabel: '不符合', rightLabel: '符合' },
    ],
    disc: [
      { question: '我喜欢掌控局面，快速做出决定', options: ['完全不同意', '不太同意', '比较同意', '完全同意'] },
      { question: '我善于影响和激励他人', options: ['完全不同意', '不太同意', '比较同意', '完全同意'] },
      { question: '我做事有条理，遵循既定流程', options: ['完全不同意', '不太同意', '比较同意', '完全同意'] },
    ],
    pdp: [
      { question: '我在工作中更喜欢独立完成任务', options: ['非常不同意', '不同意', '中立', '同意', '非常同意'] },
      { question: '我善于激励团队，让大家充满热情', options: ['非常不同意', '不同意', '中立', '同意', '非常同意'] },
      { question: '我注重细节，确保工作万无一失', options: ['非常不同意', '不同意', '中立', '同意', '非常同意'] },
    ],
    sbti: [
      { question: '我愿意为了潜在的高回报承担较大的风险', options: ['非常不同意', '不同意', '中立', '同意', '非常同意'] },
      { question: '我具备强烈的创业冲动和行动力', options: ['非常不同意', '不同意', '中立', '同意', '非常同意'] },
      { question: '我善于发现商业机会并快速执行', options: ['非常不同意', '不同意', '中立', '同意', '非常同意'] },
    ],
  }
  return (base as any)[type] || base.mbti
}
</script>

<style scoped>
.quiz { min-height: 100vh; background: var(--bg); display: flex; flex-direction: column; }

.quiz__header {
  display: flex; align-items: center; gap: 16px;
  padding: 48px 20px 16px;
  position: sticky; top: 0; z-index: 10;
}

.quiz__back {
  width: 36px; height: 36px; border-radius: 50%;
  background: rgba(255,255,255,0.2); border: none; cursor: pointer;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}

.quiz__progress-wrap { flex: 1; display: flex; flex-direction: column; gap: 6px; }
.quiz__progress-track { height: 5px; background: rgba(255,255,255,0.25); border-radius: 3px; overflow: hidden; }
.quiz__progress-fill { height: 100%; background: white; border-radius: 3px; transition: width 0.3s ease; }
.quiz__progress-text { color: rgba(255,255,255,0.85); font-size: 12px; text-align: right; }

.quiz__body { flex: 1; padding: 24px 20px 120px; }

.quiz__question-card { background: white; border-radius: 20px; padding: 24px; box-shadow: var(--shadow-sm); }
.quiz__q-num { font-size: 12px; color: var(--text-secondary); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.05em; }
.quiz__q-text { font-size: 17px; font-weight: 700; color: var(--text); line-height: 1.6; margin-bottom: 24px; }

/* 量表 */
.quiz__scale {}
.scale-labels { display: flex; justify-content: space-between; font-size: 12px; color: var(--text-secondary); margin-bottom: 12px; }
.scale-options { display: flex; gap: 10px; justify-content: center; }
.scale-btn {
  width: 52px; height: 52px; border-radius: 50%;
  border: 2px solid #E5E7EB; background: white;
  font-size: 16px; font-weight: 700; color: var(--text); cursor: pointer;
  transition: all 0.2s;
  &--active { color: white; }
  &--large { width: 56px; height: 56px; }
}

/* 单选 */
.quiz__options { display: flex; flex-direction: column; gap: 10px; }
.quiz__option {
  display: flex; align-items: center; gap: 14px;
  padding: 14px 16px; border-radius: 14px;
  border: 2px solid #E8E4F8; background: white; cursor: pointer;
  text-align: left; transition: all 0.15s;
  &:active { transform: scale(0.98); }
  &--active { font-weight: 600; }
}
.quiz__option-letter {
  width: 28px; height: 28px; border-radius: 8px;
  background: #F3F4F6; display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700; flex-shrink: 0; color: var(--text);
}
.quiz__option-text { font-size: 14.5px; color: var(--text); line-height: 1.5; }

/* 提交中 */
.quiz__submitting {
  flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px;
  p { font-size: 16px; font-weight: 600; color: var(--text); }
  .submitting-sub { font-size: 13px; color: var(--text-secondary); font-weight: 400; }
}

/* 底部导航 */
.quiz__footer {
  position: fixed; bottom: 0; left: 0; right: 0;
  padding: 16px 20px calc(16px + env(safe-area-inset-bottom,0px));
  background: rgba(255,255,255,0.96); backdrop-filter: blur(20px);
  display: flex; gap: 12px;
  border-top: 1px solid var(--border);
}
.quiz__prev {
  height: 50px; border-radius: 14px; border: 2px solid var(--border);
  background: white; color: var(--text-secondary); font-size: 15px; cursor: pointer;
  padding: 0 20px; white-space: nowrap;
}
.quiz__next {
  flex: 1; height: 50px; border-radius: 14px; border: none;
  color: white; font-size: 15px; font-weight: 700; cursor: pointer;
  transition: opacity 0.2s;
  &:disabled { opacity: 0.4; cursor: not-allowed; }
}

/* 过渡 */
.slide-up-enter-active, .slide-up-leave-active { transition: all 0.25s; }
.slide-up-enter-from { opacity: 0; transform: translateY(16px); }
.slide-up-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
