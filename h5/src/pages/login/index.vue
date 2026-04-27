<template>
  <div class="login-page">
    <div class="login-hero">
      <div class="hero-logo">
        <svg width="48" height="48" viewBox="0 0 48 48" fill="none">
          <rect width="48" height="48" rx="16" fill="url(#loginGrad)"/>
          <rect x="8" y="8" width="14" height="14" rx="3" fill="rgba(255,255,255,0.9)"/>
          <rect x="26" y="8" width="14" height="14" rx="3" fill="rgba(255,255,255,0.6)"/>
          <rect x="8" y="26" width="14" height="14" rx="3" fill="rgba(255,255,255,0.6)"/>
          <rect x="26" y="26" width="14" height="14" rx="3" fill="rgba(255,255,255,0.9)"/>
          <defs>
            <linearGradient id="loginGrad" x1="0" y1="0" x2="48" y2="48">
              <stop offset="0%" stop-color="#1E40AF"/>
              <stop offset="100%" stop-color="#4338CA"/>
            </linearGradient>
          </defs>
        </svg>
      </div>
      <h1 class="hero-title">神仙团队</h1>
      <p class="hero-sub">MBTI · DISC · PDP 全方位性格测评</p>
    </div>

    <div class="login-card">
      <h2 class="card-title">手机号登录</h2>

      <div class="form-group">
        <label>手机号</label>
        <div class="phone-input-wrap">
          <span class="phone-prefix">+86</span>
          <input v-model="phone" type="tel" placeholder="请输入手机号" maxlength="11" @input="onPhoneInput" />
        </div>
      </div>

      <div class="form-group">
        <label>验证码</label>
        <div class="code-input-wrap">
          <input v-model="code" type="number" placeholder="6位验证码" maxlength="6" />
          <button class="send-code-btn" @click="sendCode" :disabled="countdown > 0 || sendingCode">
            {{ countdown > 0 ? `${countdown}s` : '获取验证码' }}
          </button>
        </div>
      </div>

      <div class="error-tip" v-if="errorMsg">{{ errorMsg }}</div>

      <button class="login-btn" @click="handleLogin" :disabled="logging || !phone || !code">
        <span v-if="logging">登录中...</span>
        <span v-else>登录 / 注册</span>
      </button>

      <p class="login-agree">登录即代表同意 <a href="#">用户协议</a> 与 <a href="#">隐私政策</a></p>
    </div>

    <!-- 测试账号入口（dev 模式） -->
    <div class="dev-login" v-if="isDev">
      <button class="dev-btn" @click="devLogin">开发模式快速登录</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { request } from '@/utils/request'

const router = useRouter()
const route = useRoute()

const phone = ref('')
const code = ref('')
const countdown = ref(0)
const sendingCode = ref(false)
const logging = ref(false)
const errorMsg = ref('')
const isDev = ref(import.meta.env.DEV)

let timer: ReturnType<typeof setInterval> | null = null

function onPhoneInput(e: Event) {
  phone.value = (e.target as HTMLInputElement).value.replace(/\D/g, '').slice(0, 11)
}

async function sendCode() {
  if (!/^1[3-9]\d{9}$/.test(phone.value)) {
    errorMsg.value = '请输入正确的手机号'
    return
  }
  errorMsg.value = ''
  sendingCode.value = true
  try {
    const res = await request({ url: '/api/auth/send-sms', method: 'POST', data: { phone: phone.value } })
    if (res.code === 200) {
      countdown.value = 60
      timer = setInterval(() => {
        countdown.value--
        if (countdown.value <= 0 && timer) { clearInterval(timer); timer = null }
      }, 1000)
    } else {
      errorMsg.value = res.message || '发送失败，请重试'
    }
  } catch {
    errorMsg.value = '网络异常，请重试'
  } finally {
    sendingCode.value = false
  }
}

async function handleLogin() {
  if (!phone.value || !code.value) return
  errorMsg.value = ''
  logging.value = true
  try {
    const res = await request({
      url: '/api/auth/login-by-sms',
      method: 'POST',
      data: { phone: phone.value, code: code.value }
    })
    if (res.code === 200 && res.data?.token) {
      localStorage.setItem('token', res.data.token)
      if (res.data.userInfo) localStorage.setItem('userInfo', JSON.stringify(res.data.userInfo))
      const redirect = (route.query.redirect as string) || '/home'
      router.replace(redirect)
    } else {
      errorMsg.value = res.message || '登录失败，请检查验证码'
    }
  } catch {
    errorMsg.value = '网络异常，请重试'
  } finally {
    logging.value = false
  }
}

function devLogin() {
  localStorage.setItem('token', 'dev-token-12345')
  localStorage.setItem('userInfo', JSON.stringify({ id: 1, nickname: '开发用户', phone: '18888888888' }))
  const redirect = (route.query.redirect as string) || '/home'
  router.replace(redirect)
}

onUnmounted(() => { if (timer) clearInterval(timer) })
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  background: linear-gradient(160deg, #EEF2FF 0%, #F4F6FB 50%, #FDF4FF 100%);
  display: flex; flex-direction: column; align-items: center;
  padding: 60px 20px 40px;
}

.login-hero { text-align: center; margin-bottom: 40px; }
.hero-logo { margin-bottom: 16px; }
.hero-title { font-size: 28px; font-weight: 900; color: #111827; margin: 0 0 8px; letter-spacing: -0.5px; }
.hero-sub { font-size: 14px; color: #6B7280; margin: 0; }

.login-card {
  width: 100%; max-width: 400px;
  background: white; border-radius: 24px;
  padding: 32px 24px;
  box-shadow: 0 8px 40px rgba(0,0,0,0.08);
}
.card-title { font-size: 20px; font-weight: 800; color: #111827; margin: 0 0 24px; }

.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; }

.phone-input-wrap, .code-input-wrap {
  display: flex; align-items: center;
  border: 1.5px solid #E5E7EB; border-radius: 12px; overflow: hidden;
  transition: border-color 0.18s;
}
.phone-input-wrap:focus-within, .code-input-wrap:focus-within { border-color: #4338CA; }

.phone-prefix {
  padding: 14px 12px; background: #F9FAFB;
  font-size: 14px; color: #374151; font-weight: 600;
  border-right: 1px solid #E5E7EB;
}
.phone-input-wrap input, .code-input-wrap input {
  flex: 1; padding: 14px 16px; border: none; outline: none;
  font-size: 16px; color: #111827; background: transparent;
}
.send-code-btn {
  flex-shrink: 0; padding: 10px 14px; margin: 4px;
  background: #4338CA; color: white; border: none;
  border-radius: 8px; font-size: 12px; font-weight: 600;
  cursor: pointer; white-space: nowrap; transition: opacity 0.18s;
}
.send-code-btn:disabled { background: #D1D5DB; color: #6B7280; }

.error-tip { color: #EF4444; font-size: 13px; margin-bottom: 12px; }

.login-btn {
  width: 100%; padding: 16px; border: none; border-radius: 14px;
  background: linear-gradient(90deg, #1E40AF, #4338CA);
  color: white; font-size: 16px; font-weight: 700;
  cursor: pointer; transition: opacity 0.18s; margin-bottom: 16px;
}
.login-btn:disabled { opacity: 0.5; }
.login-agree { text-align: center; font-size: 12px; color: #9CA3AF; }
.login-agree a { color: #4338CA; }

.dev-login { margin-top: 24px; }
.dev-btn {
  padding: 10px 24px; background: transparent;
  border: 1px dashed #D1D5DB; border-radius: 8px;
  color: #9CA3AF; font-size: 13px; cursor: pointer;
}
</style>
