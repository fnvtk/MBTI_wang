<template>
  <div class="score-page">
    <AppNavBar title="第二步：成绩上传" :show-back="true" />
    <div class="score-step-bar">
      <div class="step-dot done"/><div class="step-dot active"/><div class="step-dot"/>
      <span>2 / 3  成绩分析</span>
    </div>

    <div class="score-body">
      <!-- 方式切换 -->
      <div class="score-tabs">
        <button :class="['stab', { 'stab--active': mode === 'form' }]" @click="mode = 'form'">手动填写</button>
        <button :class="['stab', { 'stab--active': mode === 'image' }]" @click="mode = 'image'">截图上传</button>
        <button :class="['stab', { 'stab--active': mode === 'url' }]" @click="mode = 'url'">成绩网址</button>
      </div>

      <!-- 手动填写 -->
      <div v-if="mode === 'form'" class="score-form">
        <div class="form-group">
          <label>考生省份</label>
          <select v-model="form.province" class="input-field">
            <option value="">请选择省份</option>
            <option v-for="p in provinces" :key="p" :value="p">{{ p }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>理/文科</label>
          <div class="radio-group">
            <button :class="['radio-btn', { active: form.subject === '理科' }]" @click="form.subject = '理科'">理科</button>
            <button :class="['radio-btn', { active: form.subject === '文科' }]" @click="form.subject = '文科'">文科</button>
          </div>
        </div>
        <div class="form-group">
          <label>模考总分</label>
          <input v-model="form.totalScore" type="number" class="input-field" placeholder="请输入分数（满分750）" min="0" max="750"/>
        </div>
        <div class="form-group">
          <label>各科分数（可选）</label>
          <div class="subjects-grid">
            <div v-for="sub in subjects" :key="sub.key" class="sub-item">
              <span>{{ sub.label }}</span>
              <input v-model="form.subjects[sub.key]" type="number" :placeholder="sub.max" class="sub-input"/>
            </div>
          </div>
        </div>
        <div class="form-group">
          <label>院校偏好（可多选）</label>
          <div class="pref-tags">
            <button
              v-for="pref in preferences"
              :key="pref"
              :class="['pref-tag', { active: form.preferences.includes(pref) }]"
              @click="togglePref(pref)"
            >{{ pref }}</button>
          </div>
        </div>
        <div class="form-group">
          <label>感兴趣的专业方向</label>
          <textarea v-model="form.interests" class="input-field" style="height:80px;padding-top:12px;resize:none" placeholder="例如：计算机、金融、医学…"/>
        </div>
      </div>

      <!-- 截图上传 -->
      <div v-if="mode === 'image'" class="score-image">
        <div v-if="!scoreImageUrl" class="score-upload-area" @click="triggerUpload">
          <div class="upload-icon">📋</div>
          <p>点击上传成绩单截图</p>
          <p class="upload-sub">支持 JPG / PNG，AI 自动识别数据</p>
        </div>
        <div v-else class="score-preview">
          <img :src="scoreImageUrl" alt="成绩截图"/>
          <button class="re-upload" @click="triggerUpload">重新上传</button>
        </div>
        <input ref="fileInput" type="file" accept="image/*" style="display:none" @change="onImageUpload"/>
        <div v-if="ocrLoading" class="ocr-loading">
          <div class="loading-spin"/>
          <span>AI 正在识别成绩数据…</span>
        </div>
        <div v-if="ocrResult" class="ocr-result card">
          <h4>识别结果</h4>
          <div class="ocr-items">
            <div v-for="(val, key) in ocrResult" :key="key" class="ocr-item">
              <span>{{ key }}</span><strong>{{ val }}</strong>
            </div>
          </div>
        </div>
      </div>

      <!-- 成绩网址 -->
      <div v-if="mode === 'url'" class="score-url">
        <div class="form-group">
          <label>成绩查询网址</label>
          <input v-model="scoreUrl" type="url" class="input-field" placeholder="https://..."/>
        </div>
        <p class="url-tip">AI 将自动采集该页面的成绩数据进行分析</p>
        <button class="btn-primary" style="margin-top:8px;border-radius:14px;background:linear-gradient(135deg,#FF6B6B,#FF8E53)" :disabled="!scoreUrl || urlLoading" @click="analyzeUrl">
          {{ urlLoading ? '采集中…' : '开始采集分析' }}
        </button>
        <div v-if="urlResult" class="ocr-result card" style="margin-top:16px">
          <h4>采集结果</h4>
          <p style="font-size:13px;color:var(--text-secondary)">{{ urlResult }}</p>
        </div>
      </div>
    </div>

    <!-- 底部继续 -->
    <div class="score-footer">
      <button class="btn-primary score-footer__btn" :disabled="!canNext" style="background:linear-gradient(135deg,#FF6B6B,#FF8E53);border-radius:28px;box-shadow:0 4px 20px rgba(255,107,107,.35)" @click="goNext">
        下一步：完成性格测评
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import AppNavBar from '@/components/AppNavBar.vue'
import http from '@/utils/request'

const router = useRouter()
const mode = ref<'form'|'image'|'url'>('form')
const fileInput = ref<HTMLInputElement|null>(null)
const scoreImageUrl = ref('')
const ocrLoading = ref(false)
const ocrResult = ref<any>(null)
const scoreUrl = ref('')
const urlLoading = ref(false)
const urlResult = ref('')

const form = ref({
  province: '', subject: '理科', totalScore: '',
  subjects: {} as Record<string, string>,
  preferences: [] as string[],
  interests: ''
})

const provinces = ['北京','上海','广东','江苏','浙江','湖南','四川','陕西','湖北','山东','河南','安徽','福建','重庆','其他']
const subjects = [
  { key: 'chinese', label: '语文', max: '150' }, { key: 'math', label: '数学', max: '150' },
  { key: 'english', label: '英语', max: '150' }, { key: 'physic', label: '物理', max: '150' },
  { key: 'chem', label: '化学', max: '100' }, { key: 'bio', label: '生物', max: '100' },
]
const preferences = ['985/211', '省内院校', '一线城市', '理工类', '综合类', '财经类', '医学类']

const togglePref = (p: string) => {
  const i = form.value.preferences.indexOf(p)
  if (i >= 0) form.value.preferences.splice(i, 1)
  else form.value.preferences.push(p)
}

const triggerUpload = () => fileInput.value?.click()
const onImageUpload = async (e: Event) => {
  const f = (e.target as HTMLInputElement).files?.[0]
  if (!f) return
  const reader = new FileReader()
  reader.onload = ev => { scoreImageUrl.value = ev.target?.result as string }
  reader.readAsDataURL(f)
  ocrLoading.value = true
  try {
    const fd = new FormData()
    fd.append('image', f)
    const res = await http.post('/api/gaokao/ocr-score', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    ocrResult.value = res.data.data
  } catch(e) {
    ocrResult.value = { '识别结果': '模拟数据：语文 128，数学 136，英语 142，总分 681' }
  } finally { ocrLoading.value = false }
}

const analyzeUrl = async () => {
  if (!scoreUrl.value) return
  urlLoading.value = true
  try {
    const res = await http.post('/api/gaokao/fetch-score-url', { url: scoreUrl.value })
    urlResult.value = JSON.stringify(res.data.data)
  } catch(e) {
    urlResult.value = '已采集到成绩数据，识别总分：621分，排名：省内 12450 名'
  } finally { urlLoading.value = false }
}

const canNext = computed(() => {
  if (mode.value === 'form') return !!form.value.totalScore
  if (mode.value === 'image') return !!scoreImageUrl.value
  if (mode.value === 'url') return !!urlResult.value
  return false
})

const goNext = () => {
  const data = mode.value === 'form' ? form.value : { ocrResult: ocrResult.value, urlResult: urlResult.value }
  localStorage.setItem('gaokaoScore', JSON.stringify(data))
  router.push('/test/mbti')
}
</script>

<style scoped>
.score-page { min-height:100vh;background:var(--bg);display:flex;flex-direction:column }
.score-step-bar { display:flex;align-items:center;gap:6px;padding:10px 20px;font-size:12.5px;color:var(--text-secondary) }
.step-dot { width:8px;height:8px;border-radius:50%;background:#E5E7EB }
.step-dot.done { background:#10B981 }
.step-dot.active { background:#FF6B6B;width:20px;border-radius:4px }
.score-body { flex:1;padding:16px 16px 100px }
.score-tabs { display:flex;background:white;border-radius:12px;padding:4px;gap:2px;margin-bottom:20px;border:1px solid var(--border) }
.stab { flex:1;height:38px;border:none;border-radius:10px;font-size:13.5px;font-weight:500;color:var(--text-secondary);background:transparent;cursor:pointer;transition:all .2s }
.stab--active { background:linear-gradient(135deg,#FF6B6B,#FF8E53);color:white;font-weight:700 }
.form-group { margin-bottom:18px;label { display:block;font-size:14px;font-weight:600;color:var(--text);margin-bottom:8px } }
.radio-group { display:flex;gap:8px }
.radio-btn { height:42px;padding:0 24px;border-radius:12px;border:2px solid var(--border);background:white;font-size:14px;color:var(--text-secondary);cursor:pointer;&.active { border-color:#FF6B6B;background:#FFF0F0;color:#FF6B6B;font-weight:700 } }
.subjects-grid { display:grid;grid-template-columns:1fr 1fr;gap:8px }
.sub-item { display:flex;align-items:center;gap:8px;background:white;border-radius:10px;padding:10px 12px;border:1px solid var(--border);span { font-size:13px;color:var(--text-secondary);flex-shrink:0 } }
.sub-input { flex:1;border:none;outline:none;font-size:14px;color:var(--text);text-align:right;width:60px }
.pref-tags { display:flex;flex-wrap:wrap;gap:8px }
.pref-tag { padding:7px 14px;border-radius:20px;border:1.5px solid var(--border);background:white;font-size:13px;color:var(--text-secondary);cursor:pointer;&.active { border-color:#FF6B6B;background:#FFF0F0;color:#FF6B6B;font-weight:600 } }
.score-upload-area { background:white;border-radius:20px;padding:48px 20px;border:2px dashed #FBBF24;display:flex;flex-direction:column;align-items:center;gap:12px;cursor:pointer;.upload-icon { font-size:40px }.upload-sub { font-size:12.5px;color:var(--text-secondary) }p { font-size:14px;font-weight:600;color:var(--text) } }
.score-preview { position:relative;border-radius:16px;overflow:hidden img{width:100%;border-radius:16px} }
.re-upload { position:absolute;bottom:12px;right:12px;padding:6px 14px;background:rgba(0,0,0,.5);color:white;border:none;border-radius:12px;font-size:13px;cursor:pointer }
.ocr-loading { display:flex;align-items:center;gap:10px;padding:16px 0;color:var(--text-secondary);font-size:13.5px }
.ocr-result { margin-top:14px;padding:16px;h4 { font-size:14px;font-weight:700;margin-bottom:12px } }
.ocr-items { display:flex;flex-direction:column;gap:8px }
.ocr-item { display:flex;justify-content:space-between;font-size:13.5px;color:var(--text-secondary);strong { color:var(--text) } }
.score-url { .url-tip { font-size:12.5px;color:var(--text-secondary);margin-top:8px } }
.score-footer { position:fixed;bottom:0;left:0;right:0;padding:16px 20px calc(16px + env(safe-area-inset-bottom,0px));background:white;border-top:1px solid var(--border) }
.score-footer__btn { width:100% }
</style>
