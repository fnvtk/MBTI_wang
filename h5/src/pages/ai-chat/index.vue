<template>
  <div class="ai-page">
    <!-- 对话列表 -->
    <div class="chat-scroll" ref="chatScrollRef">
      <!-- 欢迎卡（无消息时显示） -->
      <div class="welcome-card" v-if="messages.length === 0">
        <div class="ai-logo">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" fill="#6366F1"/>
            <path d="M8 12h8M12 8v8" stroke="white" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="welcome-body">
          <div class="welcome-title">我是神仙 AI</div>
          <div class="welcome-sub" v-if="mbtiType">
            已了解你的 <span class="mbti-badge">{{ mbtiType }}</span>，继续聊～
          </div>
          <div class="welcome-sub" v-else>先做一下 MBTI 测评，我能帮你聊得更懂你哦～</div>
          <div class="welcome-actions">
            <button class="go-mbti-btn" @click="$router.push('/test/mbti')">去测 MBTI</button>
          </div>
        </div>
      </div>

      <!-- 消息列表 -->
      <div v-for="msg in messages" :key="msg.id" :class="['msg-row', msg.role === 'user' ? 'msg-row-user' : 'msg-row-ai']">
        <!-- AI 消息 -->
        <div class="msg-ai-wrap" v-if="msg.role === 'assistant'">
          <div class="ai-avatar">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" fill="#6366F1"/>
              <path d="M8 12h8M12 8v8" stroke="white" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="bubble bubble-ai">
            <p class="bubble-text">{{ msg.content }}</p>
          </div>
        </div>
        <!-- 用户消息 -->
        <div class="bubble bubble-user" v-else>
          <p class="bubble-text">{{ msg.content }}</p>
        </div>
      </div>

      <!-- 输入中 -->
      <div class="msg-row msg-row-ai" v-if="sending">
        <div class="ai-avatar">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" fill="#6366F1"/>
            <path d="M8 12h8M12 8v8" stroke="white" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="bubble bubble-ai">
          <div class="typing-dots">
            <span></span><span></span><span></span>
          </div>
        </div>
      </div>

      <div ref="bottomRef" style="height: 1px;"></div>
    </div>

    <!-- 快捷提问 -->
    <div class="quick-chips-wrap" v-if="quickQuestions.length">
      <div class="quick-chips">
        <button
          v-for="q in quickQuestions"
          :key="q"
          class="chip"
          @click="sendQuick(q)"
        >{{ q }}</button>
      </div>
    </div>

    <!-- 输入栏 -->
    <div class="composer">
      <button class="resume-btn" @click="uploadResume">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
          <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
          <line x1="9" y1="13" x2="15" y2="13" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
        </svg>
        <span>简历</span>
      </button>
      <input
        ref="inputRef"
        v-model="draft"
        class="chat-input"
        placeholder="问你想了解的自己"
        @keydown.enter.prevent="sendMessage"
        :disabled="sending"
        maxlength="800"
      />
      <button
        :class="['send-btn', draft && 'send-btn-active']"
        @click="sendMessage"
        :disabled="sending || !draft.trim()"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <line x1="22" y1="2" x2="11" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <polygon points="22 2 15 22 11 13 2 9 22 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, nextTick, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { request } from '@/utils/request'

const router = useRouter()
const chatScrollRef = ref<HTMLDivElement | null>(null)
const bottomRef = ref<HTMLDivElement | null>(null)
const inputRef = ref<HTMLInputElement | null>(null)

const messages = ref<Array<{ id: string; role: string; content: string }>>([])
const draft = ref('')
const sending = ref(false)
const mbtiType = ref('')

const quickQuestions = ref([
  '我适合什么职业？',
  '我在团队里是什么角色？',
  '我和ENFP的人合拍吗？',
  '如何发挥我的优势？',
  '我的盲点是什么？'
])

function scrollToBottom() {
  nextTick(() => {
    if (bottomRef.value) {
      bottomRef.value.scrollIntoView({ behavior: 'smooth' })
    }
  })
}

async function sendMessage() {
  const text = draft.value.trim()
  if (!text || sending.value) return
  draft.value = ''
  const userMsg = { id: Date.now().toString(), role: 'user', content: text }
  messages.value.push(userMsg)
  scrollToBottom()
  sending.value = true
  try {
    const res = await request({
      url: '/api/ai/chat',
      method: 'POST',
      data: { message: text, history: messages.value.slice(-6).map(m => ({ role: m.role, content: m.content })) }
    })
    // request() 已经返回 res.data，所以直接访问字段
    const reply = res?.reply || res?.content || res?.data?.reply || res?.message || '抱歉，我暂时无法回答这个问题。'
    messages.value.push({ id: (Date.now() + 1).toString(), role: 'assistant', content: reply })
  } catch {
    messages.value.push({ id: (Date.now() + 1).toString(), role: 'assistant', content: '网络异常，请稍后重试。' })
  } finally {
    sending.value = false
    scrollToBottom()
  }
}

function sendQuick(q: string) {
  draft.value = q
  sendMessage()
}

function uploadResume() {
  const input = document.createElement('input')
  input.type = 'file'
  input.accept = '.pdf,.doc,.docx,.jpg,.jpeg,.png'
  input.onchange = async (e: any) => {
    const file = e.target.files?.[0]
    if (!file) return
    const formData = new FormData()
    formData.append('file', file)
    messages.value.push({ id: Date.now().toString(), role: 'user', content: `[上传了简历：${file.name}]` })
    scrollToBottom()
    sending.value = true
    try {
      const res = await fetch(`${import.meta.env.VITE_API_BASE_URL || 'https://mbtiapi.quwanzhi.com'}/api/ai/analyze-resume`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${localStorage.getItem('token') || ''}` },
        body: formData
      })
      const data = await res.json()
      const reply = data.data?.analysis || data.message || '简历已收到，正在为您分析...'
      messages.value.push({ id: (Date.now() + 1).toString(), role: 'assistant', content: reply })
    } catch {
      messages.value.push({ id: (Date.now() + 1).toString(), role: 'assistant', content: '简历上传失败，请重试。' })
    } finally {
      sending.value = false
      scrollToBottom()
    }
  }
  input.click()
}

onMounted(() => {
  const stored = localStorage.getItem('mbtiResult')
  if (stored) {
    try { mbtiType.value = JSON.parse(stored)?.resultText || '' } catch {}
  }
})
</script>

<style scoped>
.ai-page {
  display: flex; flex-direction: column;
  height: 100vh; background: #F4F6FB;
}

.chat-scroll {
  flex: 1; overflow-y: auto;
  padding: 16px 16px 8px;
  display: flex; flex-direction: column; gap: 12px;
}

.welcome-card {
  display: flex; gap: 12px; align-items: flex-start;
  background: white; border-radius: 16px;
  padding: 16px; margin-bottom: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.ai-logo {
  width: 44px; height: 44px; border-radius: 12px;
  background: #EEF2FF; display: flex;
  align-items: center; justify-content: center; flex-shrink: 0;
}
.welcome-body { flex: 1; }
.welcome-title { font-size: 15px; font-weight: 700; color: #111827; margin-bottom: 4px; }
.welcome-sub { font-size: 13px; color: #6B7280; line-height: 1.5; margin-bottom: 12px; }
.mbti-badge {
  display: inline-block; background: #EEF2FF; color: #4338CA;
  font-weight: 700; border-radius: 4px; padding: 0 6px;
}
.welcome-actions { display: flex; gap: 8px; }
.go-mbti-btn {
  padding: 8px 16px; background: #4338CA; color: white;
  border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;
}

.msg-row { display: flex; }
.msg-row-user { justify-content: flex-end; }
.msg-row-ai { justify-content: flex-start; }

.msg-ai-wrap { display: flex; gap: 8px; align-items: flex-start; max-width: 85%; }
.ai-avatar {
  width: 32px; height: 32px; border-radius: 10px;
  background: #EEF2FF; display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}

.bubble {
  border-radius: 16px; padding: 12px 14px;
  max-width: 85%;
}
.bubble-ai {
  background: white; color: #111827;
  border-radius: 4px 16px 16px 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.bubble-user {
  background: linear-gradient(135deg, #4338CA, #1E40AF);
  color: white; border-radius: 16px 4px 16px 16px;
}
.bubble-text { font-size: 14px; line-height: 1.6; margin: 0; word-break: break-word; }

.typing-dots { display: flex; gap: 4px; align-items: center; height: 20px; }
.typing-dots span {
  width: 6px; height: 6px; border-radius: 50%; background: #D1D5DB;
  animation: bounce 1.4s infinite;
}
.typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes bounce {
  0%, 80%, 100% { transform: translateY(0); }
  40% { transform: translateY(-6px); }
}

.quick-chips-wrap {
  background: white; border-top: 1px solid #F3F4F6;
  padding: 10px 16px;
}
.quick-chips { display: flex; gap: 8px; overflow-x: auto; }
.quick-chips::-webkit-scrollbar { display: none; }
.chip {
  flex-shrink: 0; padding: 6px 14px;
  background: #F3F4F6; color: #374151;
  border: none; border-radius: 20px;
  font-size: 12px; cursor: pointer; white-space: nowrap;
  transition: all 0.15s;
}
.chip:active { background: #EEF2FF; color: #4338CA; }

.composer {
  display: flex; align-items: center; gap: 8px;
  background: white; padding: 10px 16px 24px;
  border-top: 1px solid #F3F4F6;
}
.resume-btn {
  display: flex; flex-direction: column; align-items: center; gap: 2px;
  background: #F3F4F6; border: none; border-radius: 10px;
  padding: 8px 10px; cursor: pointer; color: #6B7280;
  flex-shrink: 0; font-size: 10px;
}
.resume-btn svg { color: #6B7280; }
.chat-input {
  flex: 1; border: 1.5px solid #E5E7EB; border-radius: 20px;
  padding: 10px 16px; font-size: 14px; outline: none;
  background: #F9FAFB; color: #111827; min-width: 0;
  transition: border-color 0.18s;
}
.chat-input:focus { border-color: #6366F1; background: white; }
.send-btn {
  width: 40px; height: 40px; border-radius: 50%;
  border: none; background: #E5E7EB; color: #9CA3AF;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: all 0.18s; flex-shrink: 0;
}
.send-btn.send-btn-active { background: linear-gradient(135deg, #4338CA, #1E40AF); color: white; }
</style>
