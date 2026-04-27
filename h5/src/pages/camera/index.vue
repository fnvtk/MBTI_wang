<template>
  <div class="camera-page">
    <AppNavBar title="面相拍摄" :show-back="true" :dark="true" />

    <!-- 相机预览区 -->
    <div class="camera__viewport">
      <!-- 已选图片预览 -->
      <div v-if="previewUrl" class="camera__preview">
        <img :src="previewUrl" alt="预览" />
        <div class="camera__preview-overlay">
          <div class="face-guide">
            <div class="face-guide__circle" />
            <div class="face-guide__hint">请确保面部清晰</div>
          </div>
        </div>
      </div>
      <!-- 引导界面 -->
      <div v-else class="camera__guide">
        <div class="camera__guide-inner">
          <div class="face-oval" />
          <div class="guide-corners">
            <span /><span /><span /><span />
          </div>
        </div>
        <p class="camera__guide-text">请将面部对准框内</p>
        <p class="camera__guide-sub">光线充足，正面拍摄效果最佳</p>
      </div>

      <!-- 拍摄状态提示 -->
      <div v-if="analyzing" class="camera__analyzing">
        <div class="loading-spin" />
        <p>AI 正在分析面相特征…</p>
        <p class="analyzing-sub">通常需要 5~15 秒</p>
      </div>
    </div>

    <!-- 提示说明 -->
    <div class="camera__tips">
      <div v-for="tip in tips" :key="tip" class="tip-item">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" fill="#6C3EF6" opacity="0.15"/>
          <path d="M8 12l3 3 5-5" stroke="#6C3EF6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span>{{ tip }}</span>
      </div>
    </div>

    <!-- 底部操作区 -->
    <div class="camera__bottom">
      <!-- 上传按钮 -->
      <label class="camera__upload" for="photo-input">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
          <rect x="3" y="3" width="8" height="8" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="13" y="3" width="8" height="8" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="3" y="13" width="8" height="8" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="13" y="13" width="8" height="8" rx="1" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span>相册选择</span>
        <input
          id="photo-input"
          type="file"
          accept="image/*"
          capture="user"
          style="display:none"
          @change="handleFileSelect"
        />
      </label>

      <!-- 拍照 / 分析按钮 -->
      <button
        v-if="!previewUrl"
        class="camera__shutter"
        @click="triggerCapture"
      >
        <label for="camera-input" class="shutter-label">
          <div class="shutter-inner" />
        </label>
        <input
          id="camera-input"
          type="file"
          accept="image/*"
          capture="environment"
          style="display:none"
          @change="handleFileSelect"
        />
      </button>

      <button v-else class="camera__analyze-btn btn-primary" :disabled="analyzing" @click="startAnalyze">
        <span v-if="!analyzing">开始 AI 面相分析</span>
        <span v-else>分析中...</span>
      </button>

      <!-- 跳过 -->
      <button class="camera__skip" @click="$router.push('/test-select')">
        跳过拍照
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
const imageFile = ref<File | null>(null)
const analyzing = ref(false)

const tips = [
  '正面拍摄，面部完整清晰',
  '光线均匀，避免强逆光',
  '去除口罩、墨镜等遮挡',
]

const handleFileSelect = (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  imageFile.value = file
  const reader = new FileReader()
  reader.onload = (ev) => { previewUrl.value = ev.target?.result as string }
  reader.readAsDataURL(file)
}

const triggerCapture = () => {
  document.getElementById('camera-input')?.click()
}

const startAnalyze = async () => {
  if (!imageFile.value || analyzing.value) return
  analyzing.value = true
  try {
    const formData = new FormData()
    formData.append('image', imageFile.value)
    const res = await http.post('/api/face/analyze', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    const result = res.data.data
    localStorage.setItem('aiResult', JSON.stringify(result))
    router.push('/face-result')
  } catch (e: any) {
    alert(e.message || '分析失败，请重试')
  } finally {
    analyzing.value = false
  }
}
</script>

<style scoped>
.camera-page {
  min-height: 100vh; display: flex; flex-direction: column;
  background: #0d0d1a;
}

.camera__viewport {
  flex: 1; position: relative; display: flex; flex-direction: column;
  align-items: center; justify-content: center; min-height: 56vw;
  overflow: hidden;
}

.camera__preview {
  position: relative; width: 100%; height: 70vw; max-height: 420px;
  img { width: 100%; height: 100%; object-fit: cover; }
}

.camera__preview-overlay {
  position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
}

.camera__guide {
  display: flex; flex-direction: column; align-items: center; gap: 16px;
  padding: 40px 24px;
}

.camera__guide-inner { position: relative; width: 220px; height: 280px; }

.face-oval {
  width: 200px; height: 260px;
  border: 2.5px solid rgba(108,62,246,0.8);
  border-radius: 50%;
  position: absolute; top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  box-shadow: 0 0 0 1px rgba(108,62,246,0.3), 0 0 40px rgba(108,62,246,0.15);
}

.guide-corners {
  position: absolute; inset: 0;
  span {
    position: absolute; width: 20px; height: 20px;
    border-color: #6C3EF6; border-style: solid;
    &:nth-child(1) { top: 0; left: 0; border-width: 3px 0 0 3px; border-radius: 2px 0 0 0; }
    &:nth-child(2) { top: 0; right: 0; border-width: 3px 3px 0 0; border-radius: 0 2px 0 0; }
    &:nth-child(3) { bottom: 0; left: 0; border-width: 0 0 3px 3px; border-radius: 0 0 0 2px; }
    &:nth-child(4) { bottom: 0; right: 0; border-width: 0 3px 3px 0; border-radius: 0 0 2px 0; }
  }
}

.face-guide {
  display: flex; flex-direction: column; align-items: center; gap: 12px;
  &__circle {
    width: 160px; height: 200px; border-radius: 50%;
    border: 2px dashed rgba(255,255,255,0.6);
  }
  &__hint {
    color: white; font-size: 13px;
    background: rgba(0,0,0,0.5); padding: 4px 12px; border-radius: 20px;
  }
}

.camera__guide-text { color: white; font-size: 15px; font-weight: 600; }
.camera__guide-sub { color: rgba(255,255,255,0.6); font-size: 13px; }

.camera__analyzing {
  position: absolute; inset: 0; background: rgba(0,0,0,0.7);
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px;
  p { color: white; font-size: 15px; font-weight: 600; }
  .analyzing-sub { color: rgba(255,255,255,0.6); font-size: 13px; font-weight: 400; }
}

.camera__tips {
  background: rgba(255,255,255,0.06); padding: 16px 24px;
  display: flex; flex-direction: column; gap: 8px;
}
.tip-item {
  display: flex; align-items: center; gap: 8px;
  color: rgba(255,255,255,0.7); font-size: 13px;
}

.camera__bottom {
  padding: 20px 24px calc(20px + env(safe-area-inset-bottom,0px));
  display: flex; flex-direction: column; align-items: center; gap: 16px;
  background: #0d0d1a;
}

.camera__upload {
  display: flex; align-items: center; gap: 8px;
  color: rgba(255,255,255,0.7); font-size: 14px; cursor: pointer;
  padding: 8px 20px; border-radius: 20px;
  border: 1px solid rgba(255,255,255,0.25);
}

.camera__shutter {
  width: 72px; height: 72px; border-radius: 50%; border: none; padding: 0; cursor: pointer;
  background: rgba(255,255,255,0.2);
  display: flex; align-items: center; justify-content: center;
}
.shutter-label {
  display: flex; align-items: center; justify-content: center;
  width: 100%; height: 100%; border-radius: 50%; cursor: pointer;
}
.shutter-inner {
  width: 58px; height: 58px; border-radius: 50%; background: white;
}

.camera__analyze-btn { max-width: 300px; border-radius: 28px; }
.camera__skip {
  background: none; border: none; color: rgba(255,255,255,0.5);
  font-size: 14px; cursor: pointer; padding: 4px;
}
</style>
