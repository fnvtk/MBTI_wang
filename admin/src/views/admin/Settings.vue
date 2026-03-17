<template>
  <div class="page-container" v-loading="loading">
    <div class="page-header">
      <div class="header-left">
        <h2>系统设置</h2>
        <p class="subtitle">管理管理员账号</p>
      </div>
    </div>

    <div class="settings-content">
      <div class="custom-tabs-container">
        <div class="custom-tabs">
          <div
            v-for="tab in tabs"
            :key="tab.value"
            :class="['tab-item', { active: activeTab === tab.value }]"
            @click="activeTab = tab.value"
          >
            {{ tab.label }}
          </div>
        </div>
      </div>

      <div class="tab-content-card" :class="{ 'no-pad': activeTab === 'poster' }">
        <!-- 小程序配置 -->
        <div v-if="activeTab === 'miniprogram'" class="tab-content" v-loading="miniprogramLoading">
          <div class="content-header">
            <h3>小程序配置</h3>
            <p class="content-description">配置小程序名称及展示文案，与超管共用全局配置，将显示在小程序导航栏等位置</p>
          </div>
          <div class="form-section">
            <div class="form-item">
              <label>小程序名称</label>
              <el-input
                v-model="miniprogramConfig.miniprogramName"
                placeholder="用于小程序导航栏等展示"
                class="w-full"
              />
            </div>
            <div class="text-config-section">
              <div class="section-label">小程序文案配置</div>
              <p class="section-desc">以下文案将显示在小程序对应位置，留空则使用默认值</p>
              <div class="form-grid">
                <div class="form-item">
                  <label>分析中提示</label>
                  <el-input v-model="miniprogramConfig.textConfig.analyzingTitle" placeholder="默认：正在分析中" class="w-full" />
                </div>
                <div class="form-item">
                  <label>开始按钮（个人版）</label>
                  <el-input v-model="miniprogramConfig.textConfig.startButtonText" placeholder="默认：开始面相测试" class="w-full" />
                </div>
                <div class="form-item">
                  <label>开始按钮（企业版）</label>
                  <el-input v-model="miniprogramConfig.textConfig.startButtonEnterprise" placeholder="默认：开始面部测试" class="w-full" />
                </div>
                <div class="form-item">
                  <label>报告页标题</label>
                  <el-input v-model="miniprogramConfig.textConfig.reportTitle" placeholder="默认：分析报告" class="w-full" />
                </div>
                <div class="form-item">
                  <label>智能分析文案</label>
                  <el-input v-model="miniprogramConfig.textConfig.aiAnalysisText" placeholder="默认：智能分析" class="w-full" />
                </div>
              </div>
            </div>
          </div>
          <div class="save-actions">
            <el-button type="primary" color="#7c3aed" class="save-btn" @click="saveMiniprogramConfig" :loading="miniprogramLoading">
              保存小程序配置
            </el-button>
          </div>
        </div>

        <!-- 海报配置 -->
        <div v-if="activeTab === 'poster'" class="tab-content poster-tab">
          <PosterEditor />
        </div>

        <!-- 账号设置 -->
        <div v-if="activeTab === 'account'" class="tab-content">
          <div class="content-header">
            <h3>管理员账号设置</h3>
            <p class="content-description">修改管理员账号和密码</p>
          </div>

          <div class="form-section">
            <div class="form-item">
              <label>管理员用户名</label>
              <el-input
                v-model="accountConfig.username"
                placeholder="输入管理员用户名"
                class="w-full"
              />
            </div>

            <div class="form-item">
              <label>当前密码</label>
              <el-input
                v-model="accountConfig.currentPassword"
                type="password"
                placeholder="输入当前密码（修改密码时必填）"
                show-password
                class="w-full"
              />
            </div>
            <div class="form-item">
              <label>新密码</label>
              <el-input
                v-model="accountConfig.password"
                type="password"
                placeholder="输入新密码（不修改则留空）"
                show-password
                class="w-full"
              />
            </div>
            <div class="form-item">
              <label>确认新密码</label>
              <el-input
                v-model="accountConfig.confirmPassword"
                type="password"
                placeholder="再次输入新密码"
                show-password
                class="w-full"
              />
            </div>
          </div>

          <div class="save-actions">
            <el-button type="primary" color="#7c3aed" class="save-btn" @click="saveAccountSettings" :loading="loading">
              <el-icon><DocumentCopy /></el-icon>
              <span>保存凭据</span>
            </el-button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import { DocumentCopy } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { request } from '@/utils/request'
import PosterEditor from './PosterEditor.vue'

const activeTab = ref('account')
const loading = ref(false)

const tabs = [
  { label: '账号设置', value: 'account' },
  { label: '小程序配置', value: 'miniprogram' },
  { label: '海报配置', value: 'poster' }
]

// ── 小程序配置 ──
const miniprogramLoading = ref(false)
const miniprogramConfig = reactive({
  miniprogramName: '神仙团队AI性格测试',
  textConfig: {
    analyzingTitle: '正在分析中',
    startButtonText: '开始面相测试',
    startButtonEnterprise: '开始面部测试',
    reportTitle: '分析报告',
    aiAnalysisText: '智能分析'
  }
})

const loadMiniprogramConfig = async () => {
  miniprogramLoading.value = true
  try {
    const res: any = await request.get('/admin/settings/miniprogram')
    if (res.code === 200 && res.data) {
      miniprogramConfig.miniprogramName = res.data.miniprogramName ?? '神仙团队AI性格测试'
      if (res.data.textConfig && typeof res.data.textConfig === 'object') {
        Object.assign(miniprogramConfig.textConfig, res.data.textConfig)
      }
    }
  } catch (e) {
    console.error('加载小程序配置失败:', e)
  } finally {
    miniprogramLoading.value = false
  }
}

const saveMiniprogramConfig = async () => {
  if (!miniprogramConfig.miniprogramName?.trim()) {
    ElMessage.error('小程序名称不能为空')
    return
  }
  miniprogramLoading.value = true
  try {
    const res: any = await request.put('/admin/settings/miniprogram', {
      miniprogramName: miniprogramConfig.miniprogramName.trim(),
      textConfig: miniprogramConfig.textConfig
    })
    if (res.code === 200) {
      ElMessage.success('小程序配置已保存')
    } else {
      ElMessage.error(res.msg || '保存失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '保存失败')
  } finally {
    miniprogramLoading.value = false
  }
}

// 账号配置
const accountConfig = reactive({
  username: '',
  password: '',
  currentPassword: '',
  confirmPassword: ''
})

// 加载当前用户信息
const loadSettings = async () => {
  loading.value = true
  try {
    const response: any = await request.get('/admin/settings')
    if (response.code === 200 && response.data) {
      accountConfig.username = response.data.username || ''
    }
  } catch (error: any) {
    console.error('加载设置失败:', error)
  } finally {
    loading.value = false
  }
}

// 保存账号设置
const saveAccountSettings = async () => {
  if (!accountConfig.username) {
    ElMessage.error('用户名不能为空')
    return
  }

  if (accountConfig.password && accountConfig.password !== accountConfig.confirmPassword) {
    ElMessage.error('两次输入的密码不一致')
    return
  }

  loading.value = true
  try {
    const response: any = await request.put('/admin/settings/credentials', {
      username: accountConfig.username,
      currentPassword: accountConfig.currentPassword,
      newPassword: accountConfig.password,
      confirmPassword: accountConfig.confirmPassword
    })
    if (response.code === 200) {
      ElMessage.success('账号设置已保存')
      accountConfig.password = ''
      accountConfig.currentPassword = ''
      accountConfig.confirmPassword = ''
      await loadSettings()
    }
  } catch (error: any) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    loading.value = false
  }
}

watch(activeTab, (tab) => {
  if (tab === 'miniprogram') loadMiniprogramConfig()
})

onMounted(() => {
  loadSettings()
})
</script>

<style scoped lang="scss">
.page-header {
  margin-bottom: 24px;

  .header-left {
    h2 {
      font-size: 22px;
      font-weight: 700;
      color: #111827;
      margin: 0 0 4px 0;
    }
    .subtitle {
      font-size: 13px;
      color: #6b7280;
      margin: 0;
    }
  }
}

.settings-content {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.custom-tabs-container {
  background-color: #f3f4f6;
  padding: 4px;
  border-radius: 8px;
  display: flex;
  margin-bottom: 20px;
  width: 100%;

  .custom-tabs {
    display: flex;
    gap: 4px;
    width: 100%;

    .tab-item {
      flex: 1;
      padding: 6px 20px;
      font-size: 13px;
      color: #6b7280;
      cursor: pointer;
      border-radius: 6px;
      transition: all 0.2s;
      white-space: nowrap;
      text-align: center;

      &:hover {
        color: #111827;
      }

      &.active {
        background-color: #fff;
        color: #111827;
        font-weight: 600;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
      }
    }
  }
}

.tab-content-card {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #f3f4f6;
  padding: 32px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);

  &.no-pad {
    padding: 16px;
  }
}

.poster-tab {
  min-height: 780px;
}

.tab-content {
  .content-header {
    margin-bottom: 32px;

    h3 {
      font-size: 20px;
      font-weight: 700;
      color: #111827;
      margin: 0 0 8px 0;
    }

    .content-description {
      font-size: 13px;
      color: #6b7280;
      margin: 0;
      line-height: 1.5;
    }
  }

  .form-section {
    display: flex;
    flex-direction: column;
    gap: 24px;
    margin-bottom: 32px;

    .form-item {
      display: flex;
      flex-direction: column;
      gap: 8px;

      label {
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0;
      }

      .form-hint {
        font-size: 12px;
        color: #9ca3af;
        margin: 0;
        line-height: 1.4;
      }

      :deep(.el-input) {
        .el-input__wrapper {
          border-radius: 8px;
          box-shadow: 0 0 0 1px #e5e7eb inset;
          padding: 8px 12px;
          transition: all 0.2s;

          &.is-focus {
            box-shadow: 0 0 0 1px #7c3aed inset, 0 0 0 3px rgba(124, 58, 237, 0.1);
          }

          &:hover {
            box-shadow: 0 0 0 1px #d1d5db inset;
          }
        }

        .el-input__inner {
          font-size: 14px;
          color: #111827;

          &::placeholder {
            color: #9ca3af;
          }
        }
      }
    }
  }

  .save-actions {
    padding-top: 24px;
    border-top: 1px solid #f3f4f6;

    .save-btn {
      height: 42px;
      padding: 0 32px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
      transition: all 0.2s;

      &:hover {
        opacity: 0.9;
        transform: translateY(-1px);
      }

      &:active {
        transform: translateY(0);
      }
    }
  }
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24px;
  margin-bottom: 24px;
}

.text-config-section {
  margin-top: 24px;
  padding-top: 24px;
  border-top: 1px solid #e5e7eb;

  .section-label {
    font-size: 15px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 4px;
  }

  .section-desc {
    font-size: 13px;
    color: #6b7280;
    margin: 0 0 16px 0;
    line-height: 1.5;
  }
}

.notice-box {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 12px 16px;
  background-color: #eff6ff;
  border-radius: 8px;
  margin-bottom: 24px;
  border: 1px solid #bfdbfe;

  .notice-icon {
    color: #3b82f6;
    font-size: 16px;
    margin-top: 2px;
    flex-shrink: 0;
  }

  span {
    font-size: 13px;
    color: #1e40af;
    line-height: 1.5;
  }
}

:deep(.el-input-number) {
  width: 100%;

  .el-input__wrapper {
    border-radius: 8px;
    box-shadow: 0 0 0 1px #e5e7eb inset;
    padding: 8px 12px;
    transition: all 0.2s;

    &.is-focus {
      box-shadow: 0 0 0 1px #7c3aed inset, 0 0 0 3px rgba(124, 58, 237, 0.1);
    }

    &:hover {
      box-shadow: 0 0 0 1px #d1d5db inset;
    }
  }

  .el-input__inner {
    font-size: 14px;
    color: #111827;
    text-align: left;
  }
}

.w-full {
  width: 100%;
}

@media (max-width: 768px) {
  .tab-content-card {
    padding: 24px;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
