<template>
  <div class="page-container">
    <!-- 页面标题 -->
    <div class="page-header">
      <div>
        <h2>系统设置</h2>
        <p class="subtitle">
          审核与站点、题库及超管账户等平台级配置（全局定价在「订单和财务」）。此处变更会间接影响各企业管理后台行为。
        </p>
      </div>
    </div>

    <!-- 保存成功提示 -->
    <el-alert
      v-if="saveSuccess"
      type="success"
      :closable="true"
      @close="saveSuccess = null"
      class="success-alert"
    >
      设置已保存成功
    </el-alert>

    <div class="settings-content">
      <div class="custom-tabs-container tabs-scroll">
        <div class="custom-tabs tabs-many">
          <div
            v-for="tab in tabs"
            :key="tab.value"
            :class="['tab-item', { active: activeTab === tab.value }]"
            @click="selectTab(tab.value)"
          >
            <el-icon class="tab-icon"><component :is="tab.icon" /></el-icon>
            {{ tab.label }}
          </div>
        </div>
      </div>

      <div
        class="tab-content-card"
        :class="{ 'flat-embed': isFlatEmbed }"
      >
        <!-- 审核模式 -->
        <div v-if="activeTab === 'review'" class="tab-content">
          <el-card shadow="never" class="settings-card">
            <template #header>
              <div class="card-header">
                <div class="header-title">
                  <el-icon class="header-icon"><Document /></el-icon>
                  <span>小程序审核模式</span>
                </div>
                <p class="header-description">提交微信审核前开启，审核通过后关闭。开启后小程序将隐藏所有AI相关功能。</p>
              </div>
            </template>
            <div class="card-content">
              <div class="review-mode-alert" :class="{ active: systemConfig.maintenanceMode }">
                <div class="review-mode-status">
                  <span class="review-dot" :class="{ on: systemConfig.maintenanceMode }"></span>
                  <span class="review-status-text">{{ systemConfig.maintenanceMode ? '审核模式已开启' : '审核模式已关闭' }}</span>
                </div>
                <p class="review-mode-desc">{{ systemConfig.maintenanceMode ? '当前小程序处于审核状态，AI面相分析功能已隐藏' : '当前小程序正常运行，所有功能可用' }}</p>
              </div>

              <div class="switch-section">
                <div class="switch-item">
                  <div class="switch-info">
                    <p class="switch-title">开启审核模式</p>
                    <p class="switch-desc">开启后小程序将执行以下变更：</p>
                  </div>
                  <el-switch
                    v-model="systemConfig.maintenanceMode"
                    active-color="#ef4444"
                    inactive-color="#d1d5db"
                  />
                </div>
              </div>

              <div class="review-changes-list">
                <div class="review-change-item">
                  <span class="change-icon hide">隐藏</span>
                  <span class="change-text">首页「面相分析」「骨相分析」标签</span>
                </div>
                <div class="review-change-item">
                  <span class="change-icon hide">隐藏</span>
                  <span class="change-text">AI拍照分析入口（相机/上传）</span>
                </div>
                <div class="review-change-item">
                  <span class="change-icon hide">隐藏</span>
                  <span class="change-text">底部导航「拍照」Tab</span>
                </div>
                <div class="review-change-item">
                  <span class="change-icon hide">隐藏</span>
                  <span class="change-text">结果页「人工智能生成」标签</span>
                </div>
                <div class="review-change-item">
                  <span class="change-icon hide">隐藏</span>
                  <span class="change-text">所有含「AI」字样的文案</span>
                </div>
                <div class="review-change-item">
                  <span class="change-icon show">保留</span>
                  <span class="change-text">MBTI / DISC / PDP 问卷测试功能</span>
                </div>
                <div class="review-change-item">
                  <span class="change-icon show">保留</span>
                  <span class="change-text">问卷测试结果展示</span>
                </div>
                <div class="review-change-item">
                  <span class="change-icon show">保留</span>
                  <span class="change-text">用户中心、历史记录等基础功能</span>
                </div>
              </div>

              <div class="review-tip">
                <strong>使用流程：</strong>开启审核模式 → 提交小程序代码审核 → 审核通过后关闭审核模式
              </div>

              <el-button
                type="primary"
                :color="systemConfig.maintenanceMode ? '#ef4444' : '#6366f1'"
                class="save-button"
                @click="handleSave('review')"
              >
                <el-icon class="mr-1"><Document /></el-icon>
                {{ systemConfig.maintenanceMode ? '保存并开启审核模式' : '保存并关闭审核模式' }}
              </el-button>
            </div>
          </el-card>
        </div>

        <!-- 系统配置 -->
        <div v-if="activeTab === 'system'" class="tab-content">
          <el-card shadow="never" class="settings-card">
          <template #header>
            <div class="card-header">
              <div class="header-title">
                <el-icon class="header-icon"><Setting /></el-icon>
                <span>系统基础配置</span>
              </div>
              <p class="header-description">管理网站名称等基础设置</p>
            </div>
          </template>
          <div class="card-content">
            <div class="form-grid">
              <div class="form-item">
                <label class="form-label">网站名称</label>
                <el-input
                  v-model="systemConfig.siteName"
                  class="form-input"
                  placeholder="用于管理后台、Web 端展示"
                />
              </div>
              <div class="form-item">
                <label class="form-label">小程序名称</label>
                <el-input
                  v-model="systemConfig.miniprogramName"
                  class="form-input"
                  placeholder="用于小程序导航栏等展示，未填则用网站名称"
                />
              </div>
              <div class="form-item">
                <label class="form-label">网站描述</label>
                <el-input
                  v-model="systemConfig.siteDescription"
                  class="form-input"
                />
              </div>
              <div class="form-item">
                <label class="form-label">每日最大测试数</label>
                <el-input-number
                  v-model="systemConfig.maxTestsPerDay"
                  :min="1"
                  :controls="false"
                  class="form-input"
                />
              </div>
              <div class="form-item">
                <label class="form-label">企业试用测试次数</label>
                <el-input-number
                  v-model="systemConfig.trialTestCount"
                  :min="1"
                  :controls="false"
                  class="form-input"
                />
              </div>
              <div class="form-item form-item-full">
                <label class="form-label">小程序默认企业</label>
                <el-select
                  :key="'default-enterprise-select-' + enterpriseSelectRenderKey"
                  v-model="systemConfig.defaultEnterpriseId"
                  class="form-input w-full"
                  placeholder="不设置则小程序无带参入口时不回落企业"
                  clearable
                  filterable
                >
                  <el-option
                    v-for="ent in enterpriseOptions"
                    :key="ent.id"
                    :label="`${ent.name}（ID: ${ent.id}）`"
                    :value="ent.id"
                  />
                </el-select>
                <span class="form-hint">未带企业入口参数（如 scene / 链接中的 eid）时，小程序回落使用该企业的上下文；优先级：扫码或链接中的企业 &gt; 用户已绑定企业 &gt; 此处默认企业</span>
              </div>
            </div>

            <!-- 小程序文案配置 -->
            <div class="form-section text-config-section">
              <div class="section-label">小程序文案配置</div>
              <p class="section-desc">以下文案将显示在小程序对应位置，留空则使用默认值</p>
              <div class="form-grid">
                <div class="form-item">
                  <label class="form-label">分析中提示</label>
                  <el-input
                    v-model="textConfig.analyzingTitle"
                    class="form-input"
                    placeholder="默认：正在分析中"
                  />
                  <span class="form-hint">原「AI正在分析中」</span>
                </div>
                <div class="form-item">
                  <label class="form-label">开始按钮（个人版）</label>
                  <el-input
                    v-model="textConfig.startButtonText"
                    class="form-input"
                    placeholder="默认：开始面相测试"
                  />
                  <span class="form-hint">原「开始AI面相测试」</span>
                </div>
                <div class="form-item">
                  <label class="form-label">开始按钮（企业版）</label>
                  <el-input
                    v-model="textConfig.startButtonEnterprise"
                    class="form-input"
                    placeholder="默认：开始面部测试"
                  />
                  <span class="form-hint">原「开始AI面部测试」</span>
                </div>
                <div class="form-item">
                  <label class="form-label">报告页标题</label>
                  <el-input
                    v-model="textConfig.reportTitle"
                    class="form-input"
                    placeholder="默认：分析报告"
                  />
                  <span class="form-hint">原「AI分析报告」</span>
                </div>
                <div class="form-item">
                  <label class="form-label">智能分析文案</label>
                  <el-input
                    v-model="textConfig.aiAnalysisText"
                    class="form-input"
                    placeholder="默认：智能分析"
                  />
                  <span class="form-hint">用于步骤、立即分析按钮等，原「AI分析」</span>
                </div>
              </div>
            </div>



            <el-button
              type="primary"
              color="#6366f1"
              class="save-button"
              @click="handleSave('system')"
            >
              <el-icon class="mr-1"><Document /></el-icon>保存系统配置
            </el-button>
          </div>
        </el-card>
        </div>

        <!-- 提示词配置 -->
        <div v-if="activeTab === 'prompts'" class="tab-content">
          <el-card shadow="never" class="settings-card">
            <template #header>
              <div class="card-header">
                <div class="header-title">
                  <el-icon class="header-icon"><ChatDotRound /></el-icon>
                  <span>提示词配置</span>
                </div>
                <p class="header-description">配置 AI 面相分析场景使用的系统提示词，保存后将在对应接口中生效</p>
              </div>
            </template>
            <div class="card-content">
              <div class="prompt-two-col">
                <div class="form-item prompt-col">
                  <label class="form-label">个人版面相分析提示词</label>
                  <el-input
                    v-model="promptsConfig.faceAnalyze"
                    type="textarea"
                  :rows="20"
                  placeholder='仅填写 JSON 返回模板，例如：{"mbti":"四字母如INTJ",...}，接口会自动在前面拼接固定中文说明'
                    class="form-input prompt-textarea"
                  />
                </div>
                <div class="form-item prompt-col">
                  <label class="form-label">企业版面相分析提示词</label>
                  <el-input
                    v-model="promptsConfig.reportSummary"
                    type="textarea"
                  :rows="20"
                  placeholder="企业版面相分析场景使用的提示词，可按需扩展"
                    class="form-input prompt-textarea"
                  />
                </div>
              </div>
              <el-button
                type="primary"
                color="#6366f1"
                class="save-button"
                @click="handleSave('prompts')"
              >
                <el-icon class="mr-1"><Document /></el-icon>保存提示词配置
              </el-button>
            </div>
          </el-card>
        </div>

        <!-- 海报配置 -->
        <div v-if="activeTab === 'poster'" class="tab-content">
          <el-card shadow="never" class="settings-card poster-card">
            <template #header>
              <div class="card-header">
                <div class="header-title">
                  <el-icon class="header-icon"><Postcard /></el-icon>
                  <span>分销海报配置</span>
                </div>
                <p class="header-description">可视化设计分销推广海报，配置背景、文字、昵称、头像、小程序码等元素的位置与样式</p>
              </div>
            </template>
            <div class="card-content" style="padding: 0;">
              <PosterEditor />
            </div>
          </el-card>
        </div>

        <div v-if="activeTab === 'pushhook'" class="tab-content">
          <PushHookConfigPanel api-prefix="/superadmin" />
        </div>

        <!-- 账户安全 -->
        <div v-if="activeTab === 'security'" class="tab-content">
          <el-card shadow="never" class="settings-card">
          <template #header>
            <div class="card-header">
              <div class="header-title">
                <el-icon class="header-icon"><Lock /></el-icon>
                <span>超管账户安全</span>
              </div>
              <p class="header-description">修改超级管理员用户名和密码</p>
            </div>
          </template>
          <div class="card-content">
            <div class="form-section">
              <div class="form-item">
                <label class="form-label">用户名</label>
                <el-input
                  v-model="credentials.username"
                  class="form-input credentials-input"
                />
              </div>
              <div class="form-item">
                <label class="form-label">当前密码</label>
                <el-input
                  v-model="credentials.currentPassword"
                  type="password"
                  placeholder="输入当前密码"
                  show-password
                  class="form-input credentials-input"
                />
              </div>
              <div class="form-item">
                <label class="form-label">新密码</label>
                <el-input
                  v-model="credentials.newPassword"
                  type="password"
                  placeholder="输入新密码"
                  show-password
                  class="form-input credentials-input"
                />
              </div>
              <div class="form-item">
                <label class="form-label">确认新密码</label>
                <el-input
                  v-model="credentials.confirmPassword"
                  type="password"
                  placeholder="再次输入新密码"
                  show-password
                  class="form-input credentials-input"
                />
              </div>
            </div>

            <el-button
              type="primary"
              color="#6366f1"
              class="save-button"
              @click="handleSave('credentials')"
            >
              <el-icon class="mr-1"><Document /></el-icon>更新账户信息
            </el-button>
          </div>
        </el-card>
        </div>

        <div v-if="activeTab === 'questions'" class="embed-wrap">
          <Questions embedded />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch, computed, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Setting,
  Lock,
  Document,
  ChatDotRound,
  Postcard,
  Reading,
  Connection
} from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { request } from '@/utils/request'
import PosterEditor from './PosterEditor.vue'
import Questions from './Questions.vue'
import PushHookConfigPanel from '../admin/PushHookConfigPanel.vue'

const TAB_IDS = [
  'review',
  'system',
  'prompts',
  'poster',
  'pushhook',
  'security',
  'questions'
] as const
type TabId = (typeof TAB_IDS)[number]

function isTabId(s: string): s is TabId {
  return (TAB_IDS as readonly string[]).includes(s)
}

const route = useRoute()
const router = useRouter()

const activeTab = ref<TabId>('review')
const saveSuccess = ref<string | null>(null)

const tabs: { label: string; value: TabId; icon: any }[] = [
  { label: '审核模式', value: 'review', icon: Document },
  { label: '系统配置', value: 'system', icon: Setting },
  { label: '提示词配置', value: 'prompts', icon: ChatDotRound },
  { label: '海报配置', value: 'poster', icon: Postcard },
  { label: '出站推送', value: 'pushhook', icon: Connection },
  { label: '账户安全', value: 'security', icon: Lock },
  { label: '题库管理', value: 'questions', icon: Reading }
]

const isFlatEmbed = computed(() => activeTab.value === 'questions')

const applyRouteTab = () => {
  const t = route.query.tab
  if (t === 'pricing') {
    router.replace({ path: '/superadmin/commerce', query: { tab: 'pricing' } })
    return
  }
  if (typeof t === 'string' && isTabId(t)) {
    activeTab.value = t
  } else {
    activeTab.value = 'review'
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
  if (tab !== 'review') {
    q.tab = tab
  }
  router.replace({ path: '/superadmin/settings', query: Object.keys(q).length ? q : {} })
}

watch(
  () => route.query.tab,
  () => applyRouteTab()
)

// 系统配置
const systemConfig = reactive({
  siteName: '神仙团队AI性格测试',
  siteDescription: '专业的AI性格测试平台',
  miniprogramName: '神仙团队AI性格测试',
  maintenanceMode: false,
  maxTestsPerDay: 100,
  trialTestCount: 10,
  defaultEnterpriseId: null as number | null,
})

/** 下拉：企业管理中的企业列表 */
const enterpriseOptions = ref<Array<{ id: number; name: string }>>([])
/** 与选项加载顺序配合，避免 el-select 在 options 为空时绑定值导致刷新后不显示 */
const enterpriseSelectRenderKey = ref(0)

// 超管凭据
const credentials = reactive({
  username: 'admin',
  currentPassword: '',
  newPassword: '',
  confirmPassword: '',
})

// 小程序文案配置（分析中提示、按钮、报告标题等）
const textConfig = reactive({
  analyzingTitle: '正在分析中',
  startButtonText: '开始面相测试',
  startButtonEnterprise: '开始面部测试',
  reportTitle: '分析报告',
  aiAnalysisText: '智能分析',
})

// 提示词配置（faceAnalyze 只存 JSON 返回模板，由后端与固定前缀拼接）
const promptsConfig = reactive<Record<string, string>>({
  faceAnalyze: '{"mbti":"四字母如INTJ","pdp":"老虎/孔雀/无尾熊/猫头鹰/变色龙其一","disc":"D/I/S/C其一","overview":"一段50字以内的综合描述","faceAnalysis":"面相特点简短描述"}',
  reportSummary: '',
})

// 加载配置
const loadSettings = async () => {
  try {
    const response: any = await request.get('/superadmin/settings')
    
    if (response.code === 200 && response.data) {
      // 系统配置（含 maintenanceMode：审核模式唯一存储字段）
      if (response.data.system) {
        Object.assign(systemConfig, response.data.system)
        systemConfig.maintenanceMode = !!systemConfig.maintenanceMode
        const de = response.data.system.defaultEnterpriseId
        systemConfig.defaultEnterpriseId =
          de != null && de !== '' && Number(de) > 0 ? Number(de) : null
        await nextTick()
        enterpriseSelectRenderKey.value++
      }
      // 加载小程序文案配置
      if (response.data.textConfig && typeof response.data.textConfig === 'object') {
        Object.assign(textConfig, response.data.textConfig)
      }

      // 加载提示词配置
      if (response.data.prompts && typeof response.data.prompts === 'object') {
        Object.keys(promptsConfig).forEach(k => {
          if (response.data.prompts[k] !== undefined) {
            promptsConfig[k] = response.data.prompts[k] || ''
          }
        })
        Object.keys(response.data.prompts).forEach(k => {
          if (!(k in promptsConfig)) {
            promptsConfig[k] = response.data.prompts[k] || ''
          }
        })
      }

      // 加载用户名
      if (response.data.username) {
        credentials.username = response.data.username
      }
    }
  } catch (error: any) {
    console.error('加载设置失败:', error)
    ElMessage.error(error.message || '加载设置失败')
  }
}

async function loadEnterpriseOptions() {
  try {
    const res: any = await request.get('/enterprises', { params: { page: 1, pageSize: 500 } })
    const list = (res?.code === 200 && res?.data?.list) ? res.data.list : []
    enterpriseOptions.value = Array.isArray(list)
      ? list.map((r: any) => ({ id: Number(r.id), name: String(r.name || '') }))
      : []
  } catch {
    enterpriseOptions.value = []
  }
}

onMounted(async () => {
  applyRouteTab()
  // 必须先加载下拉选项，再灌入 defaultEnterpriseId，否则 el-select 刷新后无法反显已选企业
  await loadEnterpriseOptions()
  await loadSettings()
})

// 保存配置
const handleSave = async (section: string) => {
  try {
    let response: any
    
    switch (section) {
      case 'review':
        response = await request.put('/superadmin/settings/system', {
          maintenanceMode: !!systemConfig.maintenanceMode
        })
        if (response.code === 200) {
          await loadSettings()
          ElMessage.success(systemConfig.maintenanceMode ? '审核模式已开启，小程序将隐藏AI功能' : '审核模式已关闭，AI功能已恢复')
          saveSuccess.value = section
          setTimeout(() => { saveSuccess.value = null }, 3000)
        }
        break

      case 'system':
        response = await request.put('/superadmin/settings/system', {
          ...systemConfig,
          textConfig
        })
        if (response.code === 200) {
          // 后端返回的是合并后的 system JSON（与 GET 中 system 字段结构一致）
          const saved = response.data
          if (saved && typeof saved === 'object') {
            Object.assign(systemConfig, {
              siteName: saved.siteName ?? systemConfig.siteName,
              siteDescription: saved.siteDescription ?? systemConfig.siteDescription,
              miniprogramName: saved.miniprogramName ?? systemConfig.miniprogramName,
              maintenanceMode: !!saved.maintenanceMode,
              maxTestsPerDay: Number(saved.maxTestsPerDay ?? systemConfig.maxTestsPerDay),
              trialTestCount: Number(saved.trialTestCount ?? systemConfig.trialTestCount)
            })
            const de = saved.defaultEnterpriseId
            systemConfig.defaultEnterpriseId =
              de != null && de !== '' && Number(de) > 0 ? Number(de) : null
            await nextTick()
            enterpriseSelectRenderKey.value++
          }
          ElMessage.success('系统配置已保存')
          saveSuccess.value = section
          setTimeout(() => {
            saveSuccess.value = null
          }, 3000)
        }
        break

      case 'prompts':
        response = await request.put('/superadmin/settings/prompts', { prompts: promptsConfig })
        if (response.code === 200) {
          ElMessage.success('提示词配置已保存')
          saveSuccess.value = section
          setTimeout(() => {
            saveSuccess.value = null
          }, 3000)
        }
        break

      case 'credentials':
        if (credentials.newPassword && credentials.newPassword !== credentials.confirmPassword) {
          ElMessage.error('两次输入的密码不一致')
          return
        }
        
        response = await request.put('/superadmin/settings/credentials', {
          username: credentials.username,
          currentPassword: credentials.currentPassword,
          newPassword: credentials.newPassword,
          confirmPassword: credentials.confirmPassword
        })
        
        if (response.code === 200) {
          ElMessage.success('账户信息已更新')
          credentials.currentPassword = ''
          credentials.newPassword = ''
          credentials.confirmPassword = ''
          saveSuccess.value = section
          setTimeout(() => {
            saveSuccess.value = null
          }, 3000)
        }
        break
    }
  } catch (error: any) {
    ElMessage.error(error.message || '保存失败')
  }
}
</script>

<style scoped lang="scss">
.page-container {
  padding: 24px;
  min-height: calc(100vh - 64px);
}

.page-header {
  margin-bottom: 24px;

  h2 {
    font-size: 24px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 4px 0;
  }

  .subtitle {
    font-size: 14px;
    color: #6b7280;
    margin: 0;
  }
}

.success-alert {
  margin-bottom: 24px;
  background-color: #dcfce7;
  border-color: #bbf7d0;
  color: #166534;

  :deep(.el-alert__content) {
    color: #166534;
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

  &.tabs-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  .custom-tabs {
    display: flex;
    gap: 4px;
    width: 100%;
    min-width: min-content;

    &.tabs-many .tab-item {
      flex: 0 0 auto;
      padding: 6px 12px;
      font-size: 12px;
    }

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
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;

      .tab-icon {
        font-size: 14px;
      }

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

.settings-card {
  border-radius: 8px;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);

  :deep(.el-card__header) {
    padding: 20px;
    border-bottom: 1px solid #f3f4f6;
  }

  :deep(.el-card__body) {
    padding: 20px;
  }

  .card-header {
    .header-title {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 16px;
      font-weight: 600;
      color: #111827;
      margin-bottom: 4px;

      .header-icon {
        font-size: 16px;
        color: #6366f1;
      }
    }

    .header-description {
      font-size: 13px;
      color: #6b7280;
      margin: 0;
    }
  }

  .card-content {
    display: flex;
    flex-direction: column;
    gap: 20px;
  }
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: 16px;

  @media (min-width: 768px) {
    grid-template-columns: repeat(2, 1fr);
  }

  .form-item-full {
    grid-column: 1 / -1;
  }
}

.w-full {
  width: 100%;
}

.form-section {
  display: flex;
  flex-direction: column;
  gap: 16px;

  &.text-config-section {
    margin-top: 20px;
    padding-top: 20px;
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
      margin: 0 0 12px 0;
    }
  }
}

.form-hint {
  font-size: 12px;
  color: #9ca3af;
  margin-top: 4px;
}

.form-item {
  display: flex;
  flex-direction: column;
  gap: 8px;

  .form-label {
    font-size: 14px;
    font-weight: 500;
    color: #374151;
  }

  .form-input {
    :deep(.el-input__wrapper) {
      border-radius: 6px;
      box-shadow: 0 0 0 1px #e5e7eb inset;
      padding: 8px 12px;
      height: 36px;

      &.is-focus {
        box-shadow: 0 0 0 1px #6366f1 inset, 0 0 0 3px rgba(99, 102, 241, 0.1);
      }
    }

    :deep(.el-input__inner) {
      font-size: 14px;
    }
  }
}

.credentials-input {
  max-width: 384px;
}

.prompt-two-col {
  display: flex;
  gap: 20px;
  align-items: flex-start;
}

.prompt-col {
  flex: 1;
  min-width: 0;
}

.prompt-textarea {
  max-width: 100%;

  :deep(.el-textarea__inner) {
    border-radius: 6px;
    font-size: 13px;
    line-height: 1.5;
  }
}

.switch-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding-top: 8px;
}

.switch-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px;
  background-color: #f9fafb;
  border-radius: 8px;

  .switch-info {
    flex: 1;

    .switch-title {
      font-size: 14px;
      font-weight: 500;
      color: #111827;
      margin: 0 0 2px 0;
    }

    .switch-desc {
      font-size: 12px;
      color: #6b7280;
      margin: 0;
    }
  }
}

.warning-box {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 12px;
  background-color: #fffbeb;
  border: 1px solid #fef3c7;
  border-radius: 8px;
  font-size: 12px;
  color: #92400e;

  .warning-icon {
    font-size: 16px;
    color: #f59e0b;
    flex-shrink: 0;
    margin-top: 2px;
  }
}

.save-button {
  background-color: #6366f1;
  border-color: #6366f1;
  color: #fff;
  height: 36px;
  padding: 0 16px;
  font-size: 14px;
  font-weight: 500;
  border-radius: 6px;

  &:hover {
    background-color: #4f46e5;
    border-color: #4f46e5;
  }

  :deep(.el-icon) {
    color: #fff;
  }
}

.mr-1 {
  margin-right: 4px;
}

.poster-card {
  :deep(.el-card__body) {
    padding: 0;
  }
}

.review-mode-alert {
  padding: 16px 20px;
  border-radius: 8px;
  background-color: #f0fdf4;
  border: 1px solid #bbf7d0;
  transition: all 0.3s;

  &.active {
    background-color: #fef2f2;
    border-color: #fecaca;
  }

  .review-mode-status {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
  }

  .review-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #22c55e;

    &.on {
      background-color: #ef4444;
      animation: pulse-dot 1.5s infinite;
    }
  }

  .review-status-text {
    font-size: 15px;
    font-weight: 600;
    color: #111827;
  }

  .review-mode-desc {
    font-size: 13px;
    color: #6b7280;
    margin: 0;
  }
}

@keyframes pulse-dot {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

.review-changes-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 0;
}

.review-change-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  background-color: #f9fafb;
  border-radius: 6px;
  font-size: 13px;
  color: #374151;

  .change-icon {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 4px;
    flex-shrink: 0;

    &.hide {
      background-color: #fef2f2;
      color: #dc2626;
    }

    &.show {
      background-color: #f0fdf4;
      color: #16a34a;
    }
  }

  .change-text {
    flex: 1;
  }
}

.review-tip {
  padding: 12px 16px;
  background-color: #fffbeb;
  border: 1px solid #fef3c7;
  border-radius: 8px;
  font-size: 13px;
  color: #92400e;
  line-height: 1.5;
}
</style>
