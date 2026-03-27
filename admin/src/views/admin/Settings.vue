<template>
  <div class="page-container" v-loading="loading">
    <div class="page-header">
      <div class="header-left">
        <h2>系统设置</h2>
        <p class="subtitle">管理员账号与企业余额；小程序文案与海报请在「用户运营」中配置</p>
      </div>
    </div>

    <div class="settings-content">
      <div class="custom-tabs-container">
        <div class="custom-tabs">
          <div
            v-for="tab in tabs"
            :key="tab.value"
            :class="['tab-item', { active: activeTab === tab.value }]"
            @click="selectTab(tab.value)"
          >
            {{ tab.label }}
          </div>
        </div>
      </div>

      <div class="tab-content-card" :class="{ 'flat-embed': activeTab === 'finance' }">
        <div v-if="activeTab === 'account'" class="tab-content">
          <div class="content-header">
            <h3>管理员账号设置</h3>
            <p class="content-description">修改管理员账号和密码</p>
          </div>

          <div class="form-section">
            <div class="form-item">
              <label>管理员用户名</label>
              <el-input v-model="accountConfig.username" placeholder="输入管理员用户名" class="w-full" />
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

        <div v-if="activeTab === 'finance'" class="embed-wrap">
          <Finance embedded />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { DocumentCopy } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { request } from '@/utils/request'
import Finance from './Finance.vue'

const TAB_IDS = ['account', 'finance'] as const
type TabId = (typeof TAB_IDS)[number]

function isTabId(s: string): s is TabId {
  return (TAB_IDS as readonly string[]).includes(s)
}

const route = useRoute()
const router = useRouter()
const activeTab = ref<TabId>('account')
const loading = ref(false)

const tabs: { label: string; value: TabId }[] = [
  { label: '账号设置', value: 'account' },
  { label: '企业余额', value: 'finance' }
]

const applyRouteTab = () => {
  const t = route.query.tab
  if (typeof t === 'string' && isTabId(t)) {
    activeTab.value = t
  } else {
    activeTab.value = 'account'
  }
}

const selectTab = (tab: TabId) => {
  activeTab.value = tab
  const q: Record<string, string> = {}
  Object.entries(route.query).forEach(([k, v]) => {
    if (v !== undefined && v !== null && k !== 'tab') {
      q[k] = Array.isArray(v) ? String(v[0]) : String(v)
    }
  })
  if (tab !== 'account') {
    q.tab = tab
  }
  router.replace({ path: '/admin/settings', query: Object.keys(q).length ? q : {} })
}

const accountConfig = reactive({
  username: '',
  password: '',
  currentPassword: '',
  confirmPassword: ''
})

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

watch(
  () => route.query.tab,
  () => applyRouteTab()
)

onMounted(() => {
  applyRouteTab()
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

  &.flat-embed {
    background: transparent;
    border: none;
    box-shadow: none;
    padding: 0;
  }
}

.embed-wrap {
  width: 100%;
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
      }

      :deep(.el-input .el-input__wrapper) {
        border-radius: 8px;
        box-shadow: 0 0 0 1px #e5e7eb inset;
        padding: 8px 12px;
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
      display: flex;
      align-items: center;
      gap: 8px;
    }
  }
}

.w-full {
  width: 100%;
}

@media (max-width: 768px) {
  .tab-content-card {
    padding: 24px;
  }
}
</style>
