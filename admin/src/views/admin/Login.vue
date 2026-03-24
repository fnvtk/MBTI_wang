<template>
  <div class="login-container">
    <div class="login-card">
      <div class="card-header">
        <h2 class="title">管理员登录</h2>
        <p class="description">请输入您的管理员凭据以访问后台</p>
      </div>

      <el-alert
        v-if="errorMessage"
        type="error"
        :closable="false"
        show-icon
        class="error-alert"
      >
        {{ errorMessage }}
      </el-alert>

      <el-form
        ref="loginFormRef"
        :model="loginForm"
        :rules="loginRules"
        @submit.prevent="handleLogin"
        class="login-form"
      >
        <el-form-item prop="username">
          <label class="form-label">用户名</label>
          <el-input
            v-model="loginForm.username"
            placeholder="请输入用户名"
            size="large"
            clearable
            class="form-input"
          />
        </el-form-item>

        <el-form-item prop="password">
          <label class="form-label">密码</label>
          <el-input
            v-model="loginForm.password"
            type="password"
            placeholder="请输入密码"
            size="large"
            show-password
            @keyup.enter="handleLogin"
            class="form-input"
          />
        </el-form-item>

        <el-form-item>
          <el-button
            type="primary"
            size="large"
            :loading="loading"
            class="login-button"
            @click="handleLogin"
          >
            {{ loading ? '登录中...' : '登录' }}
          </el-button>
        </el-form-item>
      </el-form>

      <div class="security-info">
        <el-icon class="security-icon"><Lock /></el-icon>
        <span>安全连接 | 仅限授权人员访问</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { Lock } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'

const router = useRouter()
const authStore = useAuthStore()

const loginFormRef = ref<FormInstance>()
const loading = ref(false)
const errorMessage = ref('')

const loginForm = reactive({
  username: '',
  password: ''
})

const loginRules: FormRules = {
  username: [
    { required: true, message: '请输入用户名', trigger: 'blur' }
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' }
  ]
}

const handleLogin = async () => {
  if (!loginFormRef.value) return

  try {
    await loginFormRef.value.validate()
    
    loading.value = true
    errorMessage.value = ''

    try {
      const success = await authStore.adminLogin(loginForm.username, loginForm.password)
      
      if (success) {
        ElMessage.success('登录成功')
        router.push('/admin/dashboard')
      } else {
        errorMessage.value = '用户名或密码错误'
      }
    } catch (error: any) {
      errorMessage.value = error?.message || error?.response?.data?.message || '登录失败，请稍后重试'
    } finally {
      loading.value = false
    }
  } catch (error) {
    console.log('表单验证失败:', error)
    loading.value = false
  }
}
</script>

<style scoped lang="scss">
.login-container {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background-color: #f3f4f6;
  padding: 20px;
}

.login-card {
  width: 100%;
  max-width: 448px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
  padding: 32px;
}

.card-header {
  text-align: center;
  margin-bottom: 24px;

  .title {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 8px;
  }

  .description {
    font-size: 14px;
    color: #6b7280;
    margin: 0;
  }
}

.error-alert {
  margin-bottom: 20px;
}

.login-form {
  :deep(.el-form-item) {
    margin-bottom: 20px;
  }

  :deep(.el-form-item__content) {
    display: block;
  }

  .form-label {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: #374151;
    margin-bottom: 8px;
  }

  .form-input {
    :deep(.el-input__wrapper) {
      border-radius: 8px;
      box-shadow: 0 0 0 1px #e5e7eb inset;
      padding: 12px 16px;
      background-color: #f9fafb;
      transition: all 0.2s;

      &.is-focus {
        box-shadow: 0 0 0 1px #7c3aed inset, 0 0 0 3px rgba(124, 58, 237, 0.1);
        background-color: #fff;
      }

      &:hover {
        box-shadow: 0 0 0 1px #d1d5db inset;
      }
    }

    :deep(.el-input__inner) {
      font-size: 15px;
      color: #111827;

      &::placeholder {
        color: #9ca3af;
      }
    }
  }
}

.login-button {
  width: 100%;
  height: 48px;
  font-size: 16px;
  font-weight: 600;
  border-radius: 8px;
  background-color: #7c3aed;
  border-color: #7c3aed;
  margin-top: 8px;

  &:hover {
    background-color: #6d28d9;
    border-color: #6d28d9;
  }

  &:active {
    background-color: #5b21b6;
    border-color: #5b21b6;
  }
}

.security-info {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid #f3f4f6;
  font-size: 12px;
  color: #9ca3af;
  gap: 6px;

  .security-icon {
    font-size: 14px;
    color: #9ca3af;
  }
}
</style>
