<template>
  <div class="page-container" v-loading="loading">
    <div class="page-header">
      <div class="header-left">
        <h2>企业设置</h2>
        <p class="subtitle">财务管理 · 终端展示（小程序/海报）· 账号与推送 · 功能开关</p>
      </div>
    </div>

    <div class="settings-content">
      <div class="pill-tabs" role="tablist">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          type="button"
          class="pill-tab"
          :class="{ 'is-active': activeTab === tab.value }"
          @click="selectTab(tab.value)"
        >
          {{ tab.label }}
        </button>
      </div>

      <div
        class="tab-content-card"
        :class="{ 'flat-embed': activeTab === 'finance' || activeTab === 'terminal' }"
      >
        <div v-if="activeTab === 'terminal'" class="terminal-wrap">
          <div class="terminal-section">
            <h3 class="terminal-h3">小程序展示与 TabBar</h3>
            <p class="terminal-desc">文案、Tab 配置等与小程序端 runtime 同步；保存后建议在真机预览验收。</p>
            <MiniprogramConfigPanel ref="miniRef" />
          </div>
          <div class="terminal-section terminal-section--poster">
            <h3 class="terminal-h3">分销海报</h3>
            <p class="terminal-desc">推广海报素材；与分销入口共用企业上下文。</p>
            <div class="poster-embed">
              <PosterEditor />
            </div>
          </div>
        </div>

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
                <el-button type="primary" class="save-btn" @click="saveAdminPermissions" :loading="permSaving">
                  保存功能开关
                </el-button>
              </div>
            </template>

            <div v-if="isEnterpriseAdmin()" class="coop-embed-section" v-loading="coopLoading">
              <div class="cunkebao-embed-header">
                <h4 class="cunkebao-embed-title">合作模式</h4>
                <p class="hint-muted cunkebao-embed-desc">
                  用户在本企业版完成简历、面相与 MBTI 后，可择一合作意向；与超管侧配置一致，仅影响本企业。代码为小写英文、数字、下划线（1–32 位），已保存的代码不可改。
                </p>
              </div>
              <div class="coop-embed-toolbar">
                <el-button type="primary" link @click="addCoopRow">+ 新增合作模式</el-button>
              </div>
              <el-table
                v-if="coopModes.length"
                :data="coopModes"
                border
                size="small"
                class="w-full"
                style="width: 100%"
              >
                <el-table-column label="代码" width="160">
                  <template #default="{ row }">
                    <el-input
                      v-model="row.code"
                      size="small"
                      placeholder="如 partner_project"
                      :disabled="row.codeLocked"
                      maxlength="32"
                      @blur="() => normalizeSettingsCoopCode(row)"
                    />
                  </template>
                </el-table-column>
                <el-table-column label="启用" width="78" align="center">
                  <template #default="{ row }">
                    <el-switch v-model="row.enabled" />
                  </template>
                </el-table-column>
                <el-table-column label="排序" width="104" align="center">
                  <template #default="{ row }">
                    <el-input-number v-model="row.sortOrder" :min="0" :max="9999" size="small" controls-position="right" />
                  </template>
                </el-table-column>
                <el-table-column label="标题" min-width="120">
                  <template #default="{ row }">
                    <el-input v-model="row.title" size="small" />
                  </template>
                </el-table-column>
                <el-table-column label="说明" min-width="160">
                  <template #default="{ row }">
                    <el-input v-model="row.description" type="textarea" :rows="2" size="small" />
                  </template>
                </el-table-column>
                <el-table-column label="操作" width="72" align="center" fixed="right">
                  <template #default="{ $index }">
                    <el-button type="danger" link size="small" @click="removeCoopRow($index)">删除</el-button>
                  </template>
                </el-table-column>
              </el-table>
              <p v-else class="hint-muted">暂无配置，可点击「新增合作模式」添加。</p>
              <div class="save-actions">
                <el-button type="primary" class="save-btn" :loading="coopSaving" @click="saveCooperationModes">
                  保存合作模式
                </el-button>
              </div>
            </div>

            <template v-if="canConfigureCunkebaoKeys()">
              <div class="cunkebao-embed-section" v-loading="cunkebaoLoading">
                <div class="cunkebao-embed-header">
                  <h4 class="cunkebao-embed-title">存客宝线索（测评）</h4>
                  <p class="hint-muted cunkebao-embed-desc">
                    人脸、MBTI、DISC、PDP 共用一把 Key，仅本企业有效；留空表示不启用。上报时机对全部测评生效。
                  </p>
                </div>
                <div class="form-item cunkebao-key-row">
                  <label>存客宝 Key</label>
                  <el-input
                    v-model="cunkebaoUnified.apiKey"
                    clearable
                    placeholder="请输入存客宝场景 Key"
                    class="w-full"
                  />
                </div>
                <div class="cunkebao-timing-row">
                  <label>上报时机</label>
                  <el-switch
                    v-model="cunkebaoUnified.reportTiming"
                    active-value="after_test"
                    inactive-value="after_paid"
                    active-text="测试完即上报"
                    inactive-text="付款后才上报"
                    inline-prompt
                    style="--el-switch-on-color: #4f46e5; --el-switch-off-color: #94a3b8"
                  />
                </div>
                <div class="save-actions">
                  <el-button type="primary" class="save-btn" :loading="cunkebaoSaving" @click="saveCunkebaoKeys">
                    保存存客宝设置
                  </el-button>
                </div>
              </div>
            </template>
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
            <el-button type="primary" class="save-btn" @click="saveAccountSettings" :loading="loading">
              <el-icon><DocumentCopy /></el-icon>
              <span>保存凭据</span>
            </el-button>
          </div>
        </div>

        <div v-if="activeTab === 'pushhook'" class="tab-content">
          <PushHookConfigPanel api-prefix="/admin" :show-test-tools="false" />
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
import PushHookConfigPanel from './PushHookConfigPanel.vue'
import MiniprogramConfigPanel from './MiniprogramConfigPanel.vue'
import PosterEditor from './PosterEditor.vue'

const TAB_IDS = ['finance', 'terminal', 'account', 'pushhook', 'features'] as const
type TabId = (typeof TAB_IDS)[number]

function isTabId(s: string): s is TabId {
  return (TAB_IDS as readonly string[]).includes(s)
}

const route = useRoute()
const router = useRouter()
const activeTab = ref<TabId>('finance')
const loading = ref(false)
const miniRef = ref<InstanceType<typeof MiniprogramConfigPanel> | null>(null)

const isEnterpriseAdmin = () => getAdminRole() === 'enterprise_admin'

/** 企业后台可操作存客宝 Key 的账号（企业管理员或已绑定企业的 admin） */
const canConfigureCunkebaoKeys = () => {
  const r = getAdminRole()
  return r === 'enterprise_admin' || r === 'admin'
}

const tabs = computed(() => {
  const rows: { label: string; value: TabId }[] = [
    { label: '财务管理', value: 'finance' },
    { label: '终端展示', value: 'terminal' },
    { label: '账号设置', value: 'account' },
    { label: '出站推送', value: 'pushhook' }
  ]
  if (isEnterpriseAdmin() || canConfigureCunkebaoKeys()) {
    rows.push({ label: '功能开关', value: 'features' })
  }
  return rows
})

const applyRouteTab = () => {
  const t = route.query.tab
  if (typeof t === 'string' && t === 'cunkebao') {
    activeTab.value = 'features'
    return
  }
  if (typeof t === 'string' && isTabId(t)) {
    if (t === 'features' && !isEnterpriseAdmin() && !canConfigureCunkebaoKeys()) {
      activeTab.value = 'finance'
    } else {
      activeTab.value = t
    }
  } else {
    activeTab.value = 'finance'
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
  if (tab !== 'finance') {
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
  { key: 'sbti', label: 'SBTI' },
  { key: 'pdp', label: 'PDP' },
  { key: 'disc', label: 'DISC' },
  { key: 'gaokao', label: '高考志愿' },
  { key: 'distribution', label: '分销推广' }
] as const

const defaultAdminPermissions = () =>
  ({ face: true, mbti: true, sbti: true, pdp: true, disc: true, gaokao: true, distribution: true }) as Record<string, boolean>

const permLoading = ref(false)
const permSaving = ref(false)
const adminPermsCeiling = ref<Record<string, boolean>>(defaultAdminPermissions())
const adminPerms = reactive<Record<string, boolean>>(defaultAdminPermissions())

/** 测评类存客宝：共用一对 Key + 统一上报时机 */
const cunkebaoUnified = reactive({
  apiKey: '',
  reportTiming: 'after_paid' as 'after_paid' | 'after_test'
})

const cunkebaoLoading = ref(false)
const cunkebaoSaving = ref(false)

/** 企业管理员：合作模式 */
type CoopModeRow = {
  code: string
  title: string
  description: string
  sortOrder: number
  enabled: boolean
  codeLocked: boolean
}

const coopModes = ref<CoopModeRow[]>([])
const coopLoading = ref(false)
const coopSaving = ref(false)

const COOP_CODE_RE = /^[a-z0-9_]{1,32}$/

const normalizeSettingsCoopCode = (row: CoopModeRow) => {
  row.code = String(row.code || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9_]/g, '')
}

const addCoopRow = () => {
  const maxSort = coopModes.value.reduce((m, r) => Math.max(m, Number(r.sortOrder) || 0), 0)
  coopModes.value.push({
    code: '',
    title: '',
    description: '',
    sortOrder: maxSort + 10,
    enabled: true,
    codeLocked: false
  })
}

const removeCoopRow = (index: number) => {
  coopModes.value = coopModes.value.filter((_, i) => i !== index)
}

const loadCooperationModes = async () => {
  if (!isEnterpriseAdmin()) return
  coopLoading.value = true
  try {
    const res: any = await request.get('/admin/enterprise/cooperation-modes')
    const list = res?.data?.list ?? []
    coopModes.value = Array.isArray(list)
      ? list.map((row: any) => ({
          code: String(row.code || ''),
          title: String(row.title || ''),
          description: String(row.description || ''),
          sortOrder: Number(row.sortOrder) || 0,
          enabled: !!row.enabled,
          codeLocked: true
        }))
      : []
  } catch (e: any) {
    coopModes.value = []
    ElMessage.error(e?.message || '加载合作模式失败')
  } finally {
    coopLoading.value = false
  }
}

const saveCooperationModes = async () => {
  if (!isEnterpriseAdmin()) return
  const seen = new Set<string>()
  const modes: Record<string, unknown>[] = []
  for (const r of coopModes.value) {
    const code = String(r.code || '')
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9_]/g, '')
    if (!code) {
      ElMessage.error('每行需填写模式代码，或先删除空行再保存')
      return
    }
    if (!COOP_CODE_RE.test(code)) {
      ElMessage.error(`模式代码不合法：${code}（仅 1–32 位小写字母、数字、下划线）`)
      return
    }
    if (seen.has(code)) {
      ElMessage.error(`模式代码重复：${code}`)
      return
    }
    seen.add(code)
    modes.push({
      modeCode: code,
      enabled: r.enabled,
      sortOrder: r.sortOrder,
      title: r.title,
      description: r.description
    })
  }
  if (modes.length === 0) {
    ElMessage.error('请至少保留一条合作模式')
    return
  }
  coopSaving.value = true
  try {
    const res: any = await request.put('/admin/enterprise/cooperation-modes', { modes })
    if (res.code === 200) {
      ElMessage.success('合作模式已保存')
      await loadCooperationModes()
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '保存失败')
  } finally {
    coopSaving.value = false
  }
}

const loadCunkebaoKeys = async () => {
  if (!canConfigureCunkebaoKeys()) return
  cunkebaoLoading.value = true
  try {
    const res: any = await request.get('/admin/settings/cunkebao-keys')
    if (res.code === 200 && res.data?.cunkebaoKeys && typeof res.data.cunkebaoKeys === 'object') {
      const ck = res.data.cunkebaoKeys
      cunkebaoUnified.apiKey = String(ck.apiKey ?? '')
      cunkebaoUnified.reportTiming = ck.reportTiming === 'after_test' ? 'after_test' : 'after_paid'
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
      cunkebaoKeys: { ...cunkebaoUnified }
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
    if (tab === 'features') {
      if (isEnterpriseAdmin()) {
        loadAdminPermissions()
        loadCooperationModes()
      }
      if (canConfigureCunkebaoKeys()) {
        loadCunkebaoKeys()
      }
    }
    if (tab === 'terminal') {
      miniRef.value?.loadMiniprogramConfig?.()
    }
  }
)

onMounted(() => {
  applyRouteTab()
  loadSettings()
  if (activeTab.value === 'features') {
    if (isEnterpriseAdmin()) {
      loadAdminPermissions()
      loadCooperationModes()
    }
    if (canConfigureCunkebaoKeys()) {
      loadCunkebaoKeys()
    }
  }
  if (activeTab.value === 'terminal') {
    miniRef.value?.loadMiniprogramConfig?.()
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

.coop-embed-section {
  margin-top: 28px;
  padding-top: 24px;
  border-top: 1px solid #e5e7eb;
}

.coop-embed-toolbar {
  margin-bottom: 10px;
}

.cunkebao-embed-section {
  margin-top: 28px;
  padding-top: 24px;
  border-top: 1px solid #e5e7eb;
}

.cunkebao-embed-title {
  margin: 0 0 8px 0;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

.cunkebao-embed-desc {
  margin: 0 0 16px 0;
}

.cunkebao-key-row {
  max-width: 560px;
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

.pill-tabs {
  display: inline-flex;
  background: #f1f5f9;
  padding: 4px;
  border-radius: 10px;
  gap: 2px;
  margin-bottom: 20px;
  max-width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.pill-tab {
  border: 0;
  background: transparent;
  color: #64748b;
  font-size: 13px;
  font-weight: 500;
  padding: 7px 18px;
  border-radius: 6px;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.18s;

  &:hover { color: #0f172a; }

  &.is-active {
    background: #ffffff;
    color: #0f172a;
    font-weight: 600;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
  }
}

.tab-content-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  padding: 28px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.03);

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

.terminal-wrap {
  display: flex;
  flex-direction: column;
  gap: 28px;
}
.terminal-section {
  background: #fafafa;
  border-radius: 12px;
  padding: 20px;
  border: 1px solid #f3f4f6;
}
.terminal-section--poster {
  padding-bottom: 12px;
}
.terminal-h3 {
  margin: 0 0 6px;
  font-size: 17px;
  font-weight: 700;
  color: #111827;
}
.terminal-desc {
  margin: 0 0 16px;
  font-size: 13px;
  color: #6b7280;
  line-height: 1.55;
}
.poster-embed {
  background: #fff;
  border-radius: 10px;
  padding: 12px;
  border: 1px solid #eef2f7;
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
