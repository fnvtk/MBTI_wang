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
        <div v-if="activeTab === 'features'" class="tab-content" v-loading="permLoading">
          <div class="content-header">
            <h3>终端功能开关</h3>
            <p class="content-description">
              仅可在超管为贵司授权的范围内调整。若超管未开放某项（如人脸分析），此处不显示对应开关。
            </p>
          </div>
          <div class="form-section">
            <template v-if="visibleAdminPermItems.length === 0">
              <p class="hint-muted">当前无超管授权项可配置，请联系平台管理员。</p>
            </template>
            <template v-else>
              <div class="perm-admin-row" v-for="p in visibleAdminPermItems" :key="p.key">
                <span class="perm-admin-label">{{ p.label }}</span>
                <el-switch v-model="adminPerms[p.key]" />
              </div>
              <div class="save-actions">
                <el-button type="primary" color="#7c3aed" class="save-btn" @click="saveAdminPermissions" :loading="permSaving">
                  保存功能开关
                </el-button>
              </div>
            </template>
          </div>
        </div>

        <div v-if="activeTab === 'cunkebao'" class="tab-content" v-loading="cunkebaoLoading">
          <div class="content-header">
            <h3>存客宝 Key</h3>
            <p class="content-description">
              按测评类型配置企业版与个人版 Key（人脸、MBTI、DISC、PDP 各自独立），数据仅保存在当前企业维度；留空表示未配置。下方「上报时机」对上述四类均生效：免费测评完成后即上报；若该类型标价需付费，可选付款后才上报或测试完即上报。
            </p>
          </div>
          <div class="form-section">
            <div
              v-for="row in cunkebaoRows"
              :key="row.key"
              class="cunkebao-type-block"
            >
              <div class="cunkebao-section-title">{{ row.label }}</div>
              <div class="cunkebao-two-col">
                <div class="form-item">
                  <label>企业版 Key</label>
                  <el-input
                    v-model="cunkebaoKeys[row.key].enterprise"
                    clearable
                    :placeholder="`请输入 ${row.label} 企业版 Key`"
                    class="w-full"
                  />
                </div>
                <div class="form-item">
                  <label>个人版 Key</label>
                  <el-input
                    v-model="cunkebaoKeys[row.key].personal"
                    clearable
                    :placeholder="`请输入 ${row.label} 个人版 Key`"
                    class="w-full"
                  />
                </div>
              </div>
              <div class="cunkebao-timing-row">
                <label>上报时机</label>
                <el-switch
                  v-model="cunkebaoKeys[row.key].reportTiming"
                  active-value="after_test"
                  inactive-value="after_paid"
                  active-text="测试完即上报"
                  inactive-text="付款后才上报"
                  inline-prompt
                  style="--el-switch-on-color: #7c3aed; --el-switch-off-color: #909399"
                />
              </div>
            </div>
            <div class="save-actions">
              <el-button type="primary" color="#7c3aed" class="save-btn" :loading="cunkebaoSaving" @click="saveCunkebaoKeys">
                保存存客宝 Key
              </el-button>
            </div>
          </div>
        </div>

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
import { ref, reactive, onMounted, watch, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { DocumentCopy } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { request } from '@/utils/request'
import { getAdminRole } from '@/utils/authStorage'
import Finance from './Finance.vue'

const TAB_IDS = ['account', 'features', 'cunkebao', 'finance'] as const
type TabId = (typeof TAB_IDS)[number]

function isTabId(s: string): s is TabId {
  return (TAB_IDS as readonly string[]).includes(s)
}

const route = useRoute()
const router = useRouter()
const activeTab = ref<TabId>('account')
const loading = ref(false)

const isEnterpriseAdmin = () => getAdminRole() === 'enterprise_admin'

/** 企业后台可操作存客宝 Key 的账号（企业管理员或已绑定企业的 admin） */
const canConfigureCunkebaoKeys = () => {
  const r = getAdminRole()
  return r === 'enterprise_admin' || r === 'admin'
}

const tabs = computed(() => {
  const rows: { label: string; value: TabId }[] = [{ label: '账号设置', value: 'account' }]
  if (isEnterpriseAdmin()) {
    rows.push({ label: '功能开关', value: 'features' })
  }
  if (canConfigureCunkebaoKeys()) {
    rows.push({ label: '存客宝key', value: 'cunkebao' })
  }
  rows.push({ label: '企业余额', value: 'finance' })
  return rows
})

const applyRouteTab = () => {
  const t = route.query.tab
  if (typeof t === 'string' && isTabId(t)) {
    if (t === 'features' && !isEnterpriseAdmin()) {
      activeTab.value = 'account'
    } else if (t === 'cunkebao' && !canConfigureCunkebaoKeys()) {
      activeTab.value = 'account'
    } else {
      activeTab.value = t
    }
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

const permItems = [
  { key: 'face', label: '人脸分析' },
  { key: 'mbti', label: 'MBTI' },
  { key: 'pdp', label: 'PDP' },
  { key: 'disc', label: 'DISC' },
  { key: 'distribution', label: '分销推广' }
] as const

const defaultAdminPermissions = () =>
  ({ face: true, mbti: true, pdp: true, disc: true, distribution: true }) as Record<string, boolean>

const permLoading = ref(false)
const permSaving = ref(false)
const adminPermsCeiling = ref<Record<string, boolean>>(defaultAdminPermissions())
const adminPerms = reactive<Record<string, boolean>>(defaultAdminPermissions())

type CunkebaoKeyScope = 'face' | 'pdp' | 'disc' | 'mbti'

const cunkebaoRows: { key: CunkebaoKeyScope; label: string }[] = [
  { key: 'face', label: '人脸测试' },
  { key: 'pdp', label: 'PDP' },
  { key: 'disc', label: 'DISC' },
  { key: 'mbti', label: 'MBTI' }
]

const cunkebaoKeys = reactive<Record<CunkebaoKeyScope, { enterprise: string; personal: string; reportTiming: string }>>({
  face: { enterprise: '', personal: '', reportTiming: 'after_paid' },
  pdp: { enterprise: '', personal: '', reportTiming: 'after_paid' },
  disc: { enterprise: '', personal: '', reportTiming: 'after_paid' },
  mbti: { enterprise: '', personal: '', reportTiming: 'after_paid' }
})

const cunkebaoLoading = ref(false)
const cunkebaoSaving = ref(false)

const loadCunkebaoKeys = async () => {
  if (!canConfigureCunkebaoKeys()) return
  cunkebaoLoading.value = true
  try {
    const res: any = await request.get('/admin/settings/cunkebao-keys')
    if (res.code === 200 && res.data?.cunkebaoKeys && typeof res.data.cunkebaoKeys === 'object') {
      const ck = res.data.cunkebaoKeys
      cunkebaoRows.forEach(({ key }) => {
        const row = ck[key]
        if (row && typeof row === 'object') {
          cunkebaoKeys[key].enterprise = String(row.enterprise ?? '')
          cunkebaoKeys[key].personal = String(row.personal ?? '')
          cunkebaoKeys[key].reportTiming = row.reportTiming === 'after_test' ? 'after_test' : 'after_paid'
        }
      })
    }
  } catch (e: any) {
    console.error(e)
    ElMessage.error(e?.message || '加载存客宝 Key 失败')
  } finally {
    cunkebaoLoading.value = false
  }
}

const saveCunkebaoKeys = async () => {
  if (!canConfigureCunkebaoKeys()) return
  cunkebaoSaving.value = true
  try {
    const res: any = await request.put('/admin/settings/cunkebao-keys', {
      cunkebaoKeys: { ...cunkebaoKeys }
    })
    if (res.code === 200) {
      ElMessage.success('存客宝 Key 已保存')
    } else {
      ElMessage.error(res.message || res.msg || '保存失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '保存失败')
  } finally {
    cunkebaoSaving.value = false
  }
}

const visibleAdminPermItems = computed(() =>
  permItems.filter((p) => adminPermsCeiling.value[p.key] !== false)
)

const loadAdminPermissions = async () => {
  if (!isEnterpriseAdmin()) return
  permLoading.value = true
  try {
    const res: any = await request.get('/admin/enterprise/permissions')
    if (res.code === 200 && res.data) {
      Object.assign(adminPerms, defaultAdminPermissions(), res.data.permissions || {})
      adminPermsCeiling.value = { ...defaultAdminPermissions(), ...(res.data.permissionsCeiling || {}) }
    }
  } catch (e: any) {
    console.error(e)
    ElMessage.error(e?.message || '加载功能开关失败')
  } finally {
    permLoading.value = false
  }
}

const saveAdminPermissions = async () => {
  if (!isEnterpriseAdmin()) return
  permSaving.value = true
  try {
    const body: Record<string, boolean> = { ...defaultAdminPermissions() }
    for (const p of permItems) {
      body[p.key] = adminPermsCeiling.value[p.key] === false ? false : !!adminPerms[p.key]
    }
    const res: any = await request.put('/admin/enterprise/permissions', { permissions: body })
    if (res.code === 200) {
      ElMessage.success('功能开关已保存')
      await loadAdminPermissions()
    } else {
      ElMessage.error(res.msg || '保存失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '保存失败')
  } finally {
    permSaving.value = false
  }
}

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

watch(
  () => activeTab.value,
  (tab) => {
    if (tab === 'features' && isEnterpriseAdmin()) {
      loadAdminPermissions()
    }
    if (tab === 'cunkebao' && canConfigureCunkebaoKeys()) {
      loadCunkebaoKeys()
    }
  }
)

onMounted(() => {
  applyRouteTab()
  loadSettings()
  if (activeTab.value === 'features' && isEnterpriseAdmin()) {
    loadAdminPermissions()
  }
  if (activeTab.value === 'cunkebao' && canConfigureCunkebaoKeys()) {
    loadCunkebaoKeys()
  }
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

.hint-muted {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
}

.cunkebao-type-block {
  margin-bottom: 28px;
  padding-bottom: 20px;
  border-bottom: 1px solid #f3f4f6;

  &:last-of-type {
    border-bottom: none;
    margin-bottom: 8px;
  }
}

.cunkebao-section-title {
  font-size: 15px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 14px;
}

.cunkebao-two-col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.cunkebao-timing-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 12px;

  > label {
    font-size: 13px;
    color: #374151;
    white-space: nowrap;
  }
}

@media (max-width: 900px) {
  .cunkebao-two-col {
    grid-template-columns: 1fr;
  }
}

.perm-admin-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  max-width: 360px;
  padding: 12px 0;
  border-bottom: 1px solid #f3f4f6;
}

.perm-admin-label {
  font-size: 14px;
  color: #374151;
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
