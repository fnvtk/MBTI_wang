<template>
  <div class="gk-camera" style="background:#0d0d1a;min-height:100vh">
    <AppNavBar title="第一步：面相拍摄" :show-back="true" :dark="true" />
    <div class="gk-step-hint">
      <div class="gk-step-dot active"/>
      <div class="gk-step-dot"/>
      <div class="gk-step-dot"/>
      <span>1 / 3  面部分析</span>
    </div>
    <!-- 复用拍照页核心逻辑 -->
    <div class="gk-camera__viewport">
      <div v-if="previewUrl" class="gk-preview">
        <img :src="previewUrl" alt=""/>
      </div>
      <div v-else class="gk-guide">
        <div class="face-oval"/>
        <p style="color:rgba(255,255,255,0.8);font-size:14px;margin-top:16px">请面对镜头，保持光线充足</p>
      </div>
      <div v-if="analyzing" class="gk-analyzing">
        <div class="loading-spin" style="border-top-color:#FF6B6B"/>
        <p>正在分析面相特征…</p>
      </div>
    </div>
    <div class="gk-camera__actions">
      <label class="gk-upload" for="gk-photo">
        <span>从相册选择</span>
        <input id="gk-photo" type="file" accept="image/*" style="display:none" @change="onFile"/>
      </label>
      <button v-if="!previewUrl" class="gk-shutter" @click="doCapture">
        <label for="gk-cap" class="gk-shutter__inner">
          <div class="gk-shutter__dot"/>
        </label>
        <input id="gk-cap" type="file" accept="image/*" capture="user" style="display:none" @change="onFile"/>
      </button>
      <button v-else class="btn-primary gk-next" style="background:linear-gradient(135deg,#FF6B6B,#FF8E53);max-width:280px;border-radius:28px" :disabled="analyzing" @click="analyze">
        {{ analyzing ? '分析中…' : '完成拍摄，进行 AI 分析' }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AppNavBar from '@/components/AppNavBar.vue'
import http from '@/utils/request'

const router = useRouter()
const previewUrl = ref('')
const file = ref<File|null>(null)
const analyzing = ref(false)

const onFile = (e: Event) => {
  const f = (e.target as HTMLInputElement).files?.[0]
  if (!f) return
  file.value = f
  const reader = new FileReader()
  reader.onload = ev => { previewUrl.value = ev.target?.result as string }
  reader.readAsDataURL(f)
}
const doCapture = () => document.getElementById('gk-cap')?.click()

const analyze = async () => {
  if (!file.value || analyzing.value) return
  analyzing.value = true
  try {
    const fd = new FormData()
    fd.append('image', file.value)
    const res = await http.post('/api/face/analyze', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    localStorage.setItem('aiResult', JSON.stringify(res.data.data))
    router.push('/gaokao/score')
  } catch(e: any) {
    alert(e.message || '分析失败，请重试')
  } finally { analyzing.value = false }
}
</script>

<style scoped>
.gk-step-hint { display:flex;align-items:center;gap:6px;padding:12px 20px;color:rgba(255,255,255,.6);font-size:12.5px }
.gk-step-dot { width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.25) }
.gk-step-dot.active { background:#FF6B6B;width:20px;border-radius:4px }
.gk-camera__viewport { flex:1;min-height:55vw;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px }
.gk-preview { width:100%;max-height:380px;border-radius:20px;overflow:hidden img{width:100%;height:100%;object-fit:cover} }
.gk-guide { display:flex;flex-direction:column;align-items:center }
.face-oval { width:200px;height:260px;border-radius:50%;border:2.5px solid rgba(255,107,107,.8);box-shadow:0 0 40px rgba(255,107,107,.2) }
.gk-analyzing { position:absolute;inset:0;background:rgba(0,0,0,.65);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px p{color:white;font-size:15px} }
.gk-camera__actions { padding:20px 24px 40px;display:flex;flex-direction:column;align-items:center;gap:16px;background:#0d0d1a }
.gk-upload { color:rgba(255,255,255,.65);font-size:14px;cursor:pointer;padding:8px 20px;border-radius:20px;border:1px solid rgba(255,255,255,.25) }
.gk-shutter { width:68px;height:68px;border-radius:50%;background:rgba(255,255,255,.2);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center }
.gk-shutter__inner { display:flex;align-items:center;justify-content:center;width:100%;height:100%;border-radius:50%;cursor:pointer }
.gk-shutter__dot { width:54px;height:54px;border-radius:50%;background:white }
.gk-next { width:100% }
</style>
