<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h2>AI 服务商配置</h2>
        <p class="subtitle">管理 AI 服务商的API密钥、模型参数和余额监控</p>
      </div>
      <div class="header-actions">
        <el-button
          variant="outline"
          @click="queryAllBalances"
          :disabled="isQueryingAll"
        >
          <el-icon class="mr-1" :class="{ 'rotating': isQueryingAll }">
            <Refresh />
          </el-icon>
          查询全部余额
        </el-button>
      </div>
    </div>

    <!-- 余额告警通知 -->
    <el-alert
      v-if="alerts.length > 0"
      type="error"
      :closable="true"
      class="alert-notification"
    >
      <template #title>
        <span class="alert-title">余额告警：</span>
        <span
          v-for="(alert, index) in alerts"
          :key="index"
          class="alert-item"
        >
          {{ alert.providerName }} 余额 {{ alert.currency === 'USD' ? '$' : '¥' }}{{ alert.balance?.toFixed(2) }}（阈值 {{ alert.currency === 'USD' ? '$' : '¥' }}{{ alert.threshold }}）
          <span v-if="index < alerts.length - 1">；</span>
        </span>
      </template>
    </el-alert>

    <!-- 保存成功提示 -->
    <el-alert
      v-if="saveSuccess"
      type="success"
      :closable="true"
      @close="saveSuccess = false"
      class="alert-notification"
    >
      配置已保存成功
    </el-alert>

    <!-- 统计卡片 -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon purple">
          <el-icon><Cpu /></el-icon>
        </div>
        <div class="stat-info">
          <div class="stat-label">服务商总数</div>
          <div class="stat-value">{{ stats.totalProviders }}</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon green">
          <el-icon><Lightning /></el-icon>
        </div>
        <div class="stat-info">
          <div class="stat-label">已启用</div>
          <div class="stat-value">{{ stats.enabled }}</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon yellow">
          <el-icon><Tools /></el-icon>
        </div>
        <div class="stat-info">
          <div class="stat-label">已配置密钥</div>
          <div class="stat-value">{{ stats.configured }}</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon red">
          <el-icon><Warning /></el-icon>
        </div>
        <div class="stat-info">
          <div class="stat-label">余额告警</div>
          <div class="stat-value" :class="{ 'alert-value': stats.alerts > 0 }">
            {{ stats.alerts }}
          </div>
        </div>
      </div>
    </div>

    <!-- AI 服务商配置卡片 -->
    <div class="providers-grid">
      <div
        v-for="provider in providers"
        :key="provider.id"
        :class="['provider-card', { 'enabled': provider.enabled }]"
      >
        <div class="card-header">
          <div class="provider-info">
            <div :class="['provider-icon', getBrandColor(provider.id)]">
              <span class="icon-emoji">{{ getBrandIcon(provider.id) }}</span>
            </div>
            <div class="provider-details">
              <div class="provider-name-row">
                <span class="provider-name">{{ provider.name }}</span>
                <el-tag v-if="provider.enabled" type="success" size="small" class="enabled-tag">
                  已启用
                </el-tag>
                <el-tag
                  v-if="renderBalanceBadge(provider.id)"
                  :type="getBalanceBadgeType(provider.id)"
                  size="small"
                  class="balance-tag"
                >
                  <el-icon class="mr-1"><Wallet /></el-icon>
                  {{ renderBalanceBadge(provider.id) }}
                </el-tag>
              </div>
              <div class="provider-model">
                <span v-if="provider.model">模型: {{ provider.model }}</span>
                <span v-if="provider.notes" class="notes-text"> · {{ provider.notes }}</span>
                <span v-if="provider.isFree" class="free-badge">免费额度,无需余额监控</span>
              </div>
            </div>
          </div>
          <div class="provider-status">
            <span class="status-label">启用</span>
            <el-switch
              v-model="provider.enabled"
              @change="handleToggleProvider(provider.id, provider.enabled)"
            />
          </div>
        </div>

        <div class="card-body">
          <!-- API Key 配置区域 -->
          <div class="api-key-section">
            <label class="api-key-label">API Key</label>
            <div class="api-key-input-wrapper">
              <el-input
                v-model="provider.apiKey"
                type="password"
                :placeholder="`输入 ${provider.name} API Key`"
                show-password
                class="api-key-input"
              >
                <template #suffix>
                  <div class="input-actions" v-if="provider.apiKey">
                    <el-button
                      text
                      size="small"
                      @click="copyApiKey(provider.id, provider.apiKey)"
                      class="action-btn"
                      title="复制 API Key"
                    >
                      <el-icon>
                        <component :is="copiedKey === provider.id ? Check : DocumentCopy" />
                      </el-icon>
                    </el-button>
                  </div>
                </template>
              </el-input>
            </div>
          </div>

          <!-- 操作按钮组 -->
          <div class="card-actions">
            <el-button
              type="primary"
              color="#a855f7"
              size="small"
              @click="handleSave(provider.id)"
              :loading="saving === provider.id"
            >
              <el-icon class="mr-1"><Document /></el-icon>保存
            </el-button>
            <el-button
              size="small"
              @click="handleQueryBalance(provider.id)"
              :disabled="checkingBalance[provider.id] || !provider._hasKey"
            >
              <el-icon class="mr-1" :class="{ 'rotating': checkingBalance[provider.id] }">
                <Refresh />
              </el-icon>
              {{ checkingBalance[provider.id] ? '查询中...' : '查询余额' }}
            </el-button>
            <el-button
              size="small"
              @click="toggleExpand(provider.id)"
            >
              <el-icon class="mr-1">
                <component :is="expandedCards[provider.id] ? ArrowUp : ArrowDown" />
              </el-icon>
              {{ expandedCards[provider.id] ? '收起' : '更多配置' }}
            </el-button>
            <el-button
              size="small"
              link
              @click="handleViewDocs(provider)"
            >
              <el-icon class="mr-1"><Link /></el-icon>文档
            </el-button>
          </div>

          <!-- 余额查询结果 -->
          <div
            v-if="balances[provider.id]"
            :class="['balance-result', getBalanceResultClass(balances[provider.id].status)]"
          >
            <div class="balance-message">
              {{ balances[provider.id].message }}
            </div>
            <div class="balance-time" v-if="balances[provider.id].checkedAt">
              {{ formatTime(balances[provider.id].checkedAt) }}
            </div>
          </div>

          <!-- 展开的高级配置 -->
          <div v-if="expandedCards[provider.id]" class="advanced-config">
            <div class="config-grid">
              <div class="config-item">
                <label class="config-label">API 端点</label>
                <el-input
                  v-model="provider.apiEndpoint"
                  placeholder="https://api.example.com/v1"
                  class="config-input"
                />
              </div>
              <div class="config-item">
                <label class="config-label">默认模型</label>
                <el-input
                  v-model="provider.model"
                  placeholder="模型名称"
                  class="config-input"
                />
              </div>
              <div v-if="provider.id === 'openai'" class="config-item">
                <label class="config-label">Organization ID</label>
                <el-input
                  v-model="provider.organizationId"
                  placeholder="org-xxx"
                  class="config-input"
                />
              </div>
              <div class="config-item">
                <label class="config-label">Max Tokens</label>
                <el-input-number
                  v-model="provider.maxTokens"
                  :min="1"
                  :max="100000"
                  :controls="false"
                  class="config-input"
                />
              </div>
            </div>

            <!-- 余额告警配置 -->
            <div class="alert-config">
              <div class="alert-header">
                <div class="alert-info">
                  <p class="alert-title">余额告警</p>
                  <p class="alert-desc">余额低于阈值时发送通知</p>
                </div>
                <el-switch
                  v-model="provider.balanceAlertEnabled"
                />
              </div>
              <div v-if="provider.balanceAlertEnabled" class="alert-threshold">
                <label class="config-label">告警阈值（元）</label>
                <el-input-number
                  v-model="provider.balanceAlertThreshold"
                  :min="1"
                  :step="1"
                  :controls="false"
                  class="config-input threshold-input"
                />
              </div>
            </div>

            <!-- 备注 -->
            <div class="config-item">
              <label class="config-label">备注</label>
              <el-input
                v-model="provider.notes"
                placeholder="选填，用于记录说明"
                class="config-input"
              />
            </div>

            <!-- 额外参数 extraConfig（可为空） -->
            <div class="extra-config-section">
              <div class="config-label">额外参数</div>
              <p v-if="!extraConfigEntries(provider).length" class="extra-config-empty">暂无额外参数</p>
              <div v-else class="extra-config-list">
                <div
                  v-for="(entry, idx) in extraConfigEntries(provider)"
                  :key="idx"
                  class="extra-config-row"
                >
                  <el-input
                    :model-value="entry.key"
                    placeholder="参数名"
                    class="extra-config-key"
                    @update:model-value="(v) => updateExtraConfigKey(provider, entry.key, v)"
                  />
                  <el-input
                    :model-value="entry.value"
                    placeholder="参数值"
                    class="extra-config-value"
                    @update:model-value="(v) => updateExtraConfigValue(provider, entry.key, v)"
                  />
                  <el-button
                    type="danger"
                    link
                    size="small"
                    class="extra-config-delete"
                    @click="removeExtraConfig(provider, entry.key)"
                    title="删除"
                  >
                    <el-icon><Delete /></el-icon>
                  </el-button>
                </div>
              </div>
              <div class="extra-config-add">
                <el-input v-model="newExtraKey[provider.id]" placeholder="参数名" class="extra-config-key" />
                <el-input v-model="newExtraVal[provider.id]" placeholder="参数值" class="extra-config-value" />
                <el-button size="small" @click="addExtraConfig(provider)">添加</el-button>
              </div>
            </div>

            <!-- 保存展开区的配置 -->
            <el-button
              type="primary"
              color="#a855f7"
              size="small"
              @click="handleSave(provider.id)"
              :loading="saving === provider.id"
              class="save-all-btn"
            >
              <el-icon class="mr-1"><Document /></el-icon>保存全部配置
            </el-button>
          </div>
        </div>
      </div>
    </div>

    <!-- 安全提示 -->
    <div class="safety-warning">
      <div class="warning-header">
        <el-icon class="warning-icon"><Warning /></el-icon>
        <h3 class="warning-title">安全提示</h3>
      </div>
      <ul class="warning-list">
        <li>API 密钥存储在服务端,已做脱敏处理,保存后显示为部分隐藏</li>
        <li>修改 API Key 后需点击"保存"按钮才会生效</li>
        <li>余额查询结果仅供参考,以各平台控制台为准</li>
        <li>Groq 为免费额度服务商,无需配置余额告警</li>
        <li>部分服务商(Anthropic/Coze/通义千问/智谱)暂不支持余额查询,仅验证API Key 有效性</li>
        <li>显示/隐藏由数据库控制：在表 <code>ai_providers</code> 中将 <code>visible</code> 设为 0 可隐藏该服务商（不在本列表展示），设为 1 可重新显示</li>
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import {
  Refresh,
  Cpu,
  Lightning,
  Tools,
  Warning,
  DocumentCopy,
  Document,
  Check,
  ArrowDown,
  ArrowUp,
  Link,
  Wallet,
  Delete
} from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { request } from '@/utils/request'

// 服务商品牌配置
const PROVIDER_BRANDS: Record<string, { icon: string; color: string; docUrl: string }> = {
  openai: {
    icon: '🤖',
    color: 'green',
    docUrl: 'https://platform.openai.com/api-keys'
  },
  anthropic: {
    icon: '🧠',
    color: 'purple',
    docUrl: 'https://console.anthropic.com/settings/keys'
  },
  deepseek: {
    icon: '🔍',
    color: 'blue',
    docUrl: 'https://platform.deepseek.com/api_keys'
  },
  moonshot: {
    icon: '🌙',
    color: 'purple',
    docUrl: 'https://platform.moonshot.cn/console/api-keys'
  },
  groq: {
    icon: '⚡',
    color: 'orange',
    docUrl: 'https://console.groq.com/keys'
  },
  coze: {
    icon: '🎯',
    color: 'red',
    docUrl: 'https://www.coze.cn/open/api'
  },
  qwen: {
    icon: '☁️',
    color: 'blue',
    docUrl: 'https://dashscope.console.aliyun.com/apiKey'
  },
  zhipu: {
    icon: '💎',
    color: 'blue',
    docUrl: 'https://open.bigmodel.cn/usercenter/apikeys'
  }
}

interface BalanceResult {
  providerId: string
  providerName: string
  balance: number | null
  currency: string
  used: number | null
  total: number | null
  status: 'success' | 'error' | 'unsupported'
  message: string
  checkedAt: string
}

interface AlertItem {
  providerName: string
  balance: number
  threshold: number
  currency: string
}

const loading = ref(false)
const saving = ref<string | null>(null)
const saveSuccess = ref(false)
const isQueryingAll = ref(false)
const expandedCards = ref<Record<string, boolean>>({})
const checkingBalance = ref<Record<string, boolean>>({})
const balances = ref<Record<string, BalanceResult>>({})
const alerts = ref<AlertItem[]>([])
const copiedKey = ref<string | null>(null)
const newExtraKey = ref<Record<string, string>>({})
const newExtraVal = ref<Record<string, string>>({})

const providers = reactive([
  {
    id: 'openai',
    name: 'OpenAI (GPT)',
    model: 'gpt-4o',
    enabled: false,
    apiKey: '',
    apiEndpoint: '',
    organizationId: '',
    maxTokens: 4096,
    balanceAlertThreshold: 10,
    balanceAlertEnabled: false,
    notes: '',
    isFree: false,
    supportsBalance: true,
    _hasKey: false,
    extraConfig: {}
  },
  {
    id: 'deepseek',
    name: 'DeepSeek',
    model: 'deepseek-chat',
    enabled: false,
    apiKey: '',
    apiEndpoint: '',
    maxTokens: 4096,
    balanceAlertThreshold: 10,
    balanceAlertEnabled: false,
    notes: '',
    isFree: false,
    supportsBalance: true,
    _hasKey: false,
    extraConfig: {}
  },
  {
    id: 'groq',
    name: 'Groq',
    model: 'llama3-8b-8192',
    enabled: true,
    apiKey: '',
    apiEndpoint: '',
    maxTokens: 8192,
    balanceAlertThreshold: 0,
    balanceAlertEnabled: false,
    notes: '',
    isFree: true,
    supportsBalance: false,
    _hasKey: false,
    extraConfig: {}
  },
  {
    id: 'qwen',
    name: '通义千问 (Qwen)',
    model: 'qwen-turbo',
    enabled: false,
    apiKey: '',
    apiEndpoint: '',
    maxTokens: 4096,
    balanceAlertThreshold: 10,
    balanceAlertEnabled: false,
    notes: '',
    isFree: false,
    supportsBalance: false,
    _hasKey: false,
    extraConfig: {}
  },
  {
    id: 'anthropic',
    name: 'Anthropic (Claude)',
    model: 'claude-sonnet-4-20250514',
    enabled: false,
    apiKey: '',
    apiEndpoint: '',
    maxTokens: 4096,
    balanceAlertThreshold: 10,
    balanceAlertEnabled: false,
    notes: '',
    isFree: false,
    supportsBalance: false,
    _hasKey: false,
    extraConfig: {}
  },
  {
    id: 'moonshot',
    name: 'Moonshot (Kimi)',
    model: 'moonshot-v1-8k',
    enabled: false,
    apiKey: '',
    apiEndpoint: '',
    maxTokens: 4096,
    balanceAlertThreshold: 10,
    balanceAlertEnabled: false,
    notes: '',
    isFree: false,
    supportsBalance: true,
    _hasKey: false,
    extraConfig: {}
  },
  {
    id: 'coze',
    name: 'Coze (扣子)',
    model: '',
    enabled: false,
    apiKey: '',
    apiEndpoint: '',
    maxTokens: 4096,
    balanceAlertThreshold: 10,
    balanceAlertEnabled: false,
    notes: '',
    isFree: false,
    supportsBalance: false,
    _hasKey: false,
    extraConfig: {}
  },
  {
    id: 'zhipu',
    name: '智谱 (GLM)',
    model: 'glm-4-flash',
    enabled: false,
    apiKey: '',
    apiEndpoint: '',
    maxTokens: 4096,
    balanceAlertThreshold: 10,
    balanceAlertEnabled: false,
    notes: '',
    isFree: false,
    supportsBalance: false,
    _hasKey: false,
    extraConfig: {}
  }
])

const stats = computed(() => {
  const enabled = providers.filter(p => p.enabled).length
  const configured = providers.filter(p => p._hasKey).length
  const alertCount = alerts.value.length
  return {
    totalProviders: providers.length,
    enabled,
    configured,
    alerts: alertCount
  }
})

const getBrandIcon = (id: string) => {
  return PROVIDER_BRANDS[id]?.icon || '🔧'
}

const getBrandColor = (id: string) => {
  return PROVIDER_BRANDS[id]?.color || 'gray'
}

/** 文档链接：优先用接口返回的 docUrl（数据库），否则用前端默认 */
const getBrandDocUrl = (provider: { id: string; docUrl?: string | null }) => {
  const url = provider?.docUrl?.trim()
  if (url) return url
  return PROVIDER_BRANDS[provider?.id ?? '']?.docUrl || '#'
}

const copyApiKey = async (id: string, key: string) => {
  try {
    await navigator.clipboard.writeText(key)
    copiedKey.value = id
    ElMessage.success('API Key 已复制')
    setTimeout(() => {
      copiedKey.value = null
    }, 2000)
  } catch (error) {
    ElMessage.error('复制失败')
  }
}

const toggleExpand = (id: string) => {
  expandedCards.value[id] = !expandedCards.value[id]
}

const renderBalanceBadge = (id: string) => {
  const result = balances.value[id]
  if (!result) return null

  if (result.status === 'success' && result.balance !== null) {
    const symbol = result.currency === 'USD' ? '$' : '¥'
    return `${symbol}${result.balance.toFixed(2)}`
  }
  return null
}

const getBalanceBadgeType = (id: string) => {
  const result = balances.value[id]
  if (!result || result.status !== 'success' || result.balance === null) return 'info'
  
  const provider = providers.find(p => p.id === id)
  if (provider?.balanceAlertThreshold && result.balance <= provider.balanceAlertThreshold) {
    return 'danger'
  }
  return 'success'
}

const getBalanceResultClass = (status: string) => {
  return {
    'success': status === 'success',
    'error': status === 'error',
    'unsupported': status === 'unsupported'
  }
}

const formatTime = (time: string) => {
  if (!time) return ''
  const date = new Date(time)
  return date.toLocaleString('zh-CN')
}

/** 将 extraConfig 转为 { key, value } 数组，便于展示；空/非对象返回 [] */
const extraConfigEntries = (provider: any): { key: string; value: string }[] => {
  const raw = provider?.extraConfig
  if (raw == null || typeof raw !== 'object' || Array.isArray(raw)) return []
  return Object.entries(raw).map(([k, v]) => ({
    key: k,
    value: typeof v === 'string' ? v : (v != null ? JSON.stringify(v) : '')
  }))
}

/** 添加一项额外参数到当前 provider，并清空输入框 */
const addExtraConfig = (provider: any) => {
  const id = provider.id
  const key = (newExtraKey.value[id] || '').trim()
  if (!key) {
    ElMessage.warning('请输入参数名')
    return
  }
  const val = newExtraVal.value[id] ?? ''
  const current = provider.extraConfig && typeof provider.extraConfig === 'object' && !Array.isArray(provider.extraConfig)
    ? { ...provider.extraConfig }
    : {}
  current[key] = val
  provider.extraConfig = current
  newExtraKey.value[id] = ''
  newExtraVal.value[id] = ''
}

/** 更新额外参数的值 */
const updateExtraConfigValue = (provider: any, key: string, value: string) => {
  if (!provider.extraConfig || typeof provider.extraConfig !== 'object') provider.extraConfig = {}
  provider.extraConfig[key] = value
}

/** 更新额外参数的 key（重命名） */
const updateExtraConfigKey = (provider: any, oldKey: string, newKey: string) => {
  const trimmed = (newKey || '').trim()
  if (!trimmed || oldKey === trimmed) return
  const current = provider.extraConfig && typeof provider.extraConfig === 'object' ? { ...provider.extraConfig } : {}
  const val = current[oldKey]
  delete current[oldKey]
  current[trimmed] = val
  provider.extraConfig = current
}

/** 删除一项额外参数 */
const removeExtraConfig = (provider: any, key: string) => {
  if (!provider.extraConfig || typeof provider.extraConfig !== 'object') return
  const next = { ...provider.extraConfig }
  delete next[key]
  provider.extraConfig = next
}

/** 将接口返回的一条配置规范化为列表项（含 extraConfig 为对象） */
const normalizeProviderItem = (item: any) => {
  const extra = item.extraConfig
  const extraObj = extra != null && typeof extra === 'object' && !Array.isArray(extra) ? extra : {}
  return {
    id: item.id ?? '',
    name: item.name ?? '',
    enabled: !!item.enabled,
    apiKey: item.apiKey ?? '',
    apiEndpoint: item.apiEndpoint ?? '',
    model: item.model ?? '',
    organizationId: item.organizationId ?? '',
    maxTokens: item.maxTokens ?? 4096,
    balanceAlertThreshold: item.balanceAlertThreshold ?? 10,
    balanceAlertEnabled: !!item.balanceAlertEnabled,
    notes: item.notes ?? '',
    docUrl: item.docUrl ?? '',
    isFree: !!item.isFree,
    supportsBalance: !!item.supportsBalance,
    _hasKey: !!(item._hasKey ?? item.apiKey),
    extraConfig: extraObj,
    lastBalance: item.lastBalance ?? null,
    lastBalanceCurrency: item.lastBalanceCurrency ?? null,
    lastBalanceCheckedAt: item.lastBalanceCheckedAt ?? null
  }
}

const loadConfig = async () => {
  loading.value = true
  try {
    const response: any = await request.get('/superadmin/ai-config')
    
    if (response.code === 200 && response.data && Array.isArray(response.data)) {
      // 以接口为数据源：用接口返回的列表完整替换 providers，这样数据库新增的服务商会显示
      const list = response.data.map((item: any) => normalizeProviderItem(item))
      providers.splice(0, providers.length, ...list)
    }
  } catch (error: any) {
    console.error('加载AI配置失败:', error)
    ElMessage.error(error.message || '加载AI配置失败')
  } finally {
    loading.value = false
  }
}

const handleSave = async (id: string) => {
  const provider = providers.find(p => p.id === id)
  if (!provider) return

  saving.value = id
  try {
    const response: any = await request.put('/superadmin/ai-config', {
      providerId: id,
      name: provider.name,
      enabled: provider.enabled,
      apiKey: provider.apiKey,
      apiEndpoint: provider.apiEndpoint,
      model: provider.model,
      organizationId: provider.organizationId,
      maxTokens: provider.maxTokens,
      balanceAlertEnabled: provider.balanceAlertEnabled,
      balanceAlertThreshold: provider.balanceAlertThreshold,
      notes: provider.notes,
      extraConfig: provider.extraConfig && typeof provider.extraConfig === 'object' ? provider.extraConfig : {}
    })
    
    if (response.code === 200) {
      // 更新 _hasKey 状态
      provider._hasKey = !!provider.apiKey
      
      saveSuccess.value = true
      setTimeout(() => {
        saveSuccess.value = false
      }, 3000)
      
      ElMessage.success(`${provider.name} 配置已保存`)
      await loadConfig()
    }
  } catch (error: any) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    saving.value = null
  }
}

const handleToggleProvider = async (id: string, enabled: boolean) => {
  const provider = providers.find(p => p.id === id)
  if (!provider) return
  provider.enabled = enabled
  await handleSave(id)
}

/** 切换显示/隐藏（直接写库，由数据库控制） */
const handleQueryBalance = async (id: string) => {
  const provider = providers.find(p => p.id === id)
  if (!provider) return

  if (!provider.supportsBalance) {
    ElMessage.warning(`${provider.name} 暂不支持余额查询，仅验证 API Key 有效性`)
    return
  }

  if (!provider.apiKey || !provider._hasKey) {
    ElMessage.warning('请先配置 API Key')
    return
  }

  checkingBalance.value[id] = true
  try {
    const response: any = await request.post('/superadmin/ai-config/query-balance', {
      providerId: id
    })
    
    if (response.code === 200 && response.data) {
      const result: BalanceResult = response.data
      balances.value[id] = result
      
      // 检查告警
      if (provider.balanceAlertEnabled && result.balance !== null && provider.balanceAlertThreshold) {
        if (result.balance <= provider.balanceAlertThreshold) {
          const existingAlert = alerts.value.find(a => a.providerName === provider.name)
          if (!existingAlert) {
            alerts.value.push({
              providerName: provider.name,
              balance: result.balance,
              threshold: provider.balanceAlertThreshold,
              currency: result.currency || 'CNY'
            })
          } else {
            // 更新现有告警
            existingAlert.balance = result.balance
          }
        } else {
          // 移除告警
          const alertIndex = alerts.value.findIndex(a => a.providerName === provider.name)
          if (alertIndex !== -1) {
            alerts.value.splice(alertIndex, 1)
          }
        }
      }
      
      if (result.status === 'success') {
        ElMessage.success(result.message)
      } else if (result.status === 'error') {
        ElMessage.error(result.message)
      }
    }
  } catch (error: any) {
    ElMessage.error(error.message || '查询余额失败')
  } finally {
    checkingBalance.value[id] = false
  }
}

const queryAllBalances = async () => {
  const enabledIds = providers.filter(p => p.enabled && p._hasKey && p.supportsBalance).map(p => p.id)
  if (enabledIds.length === 0) {
    ElMessage.warning('没有已启用且已配置密钥的服务商')
    return
  }

  isQueryingAll.value = true
  const checkingState: Record<string, boolean> = {}
  enabledIds.forEach(id => {
    checkingState[id] = true
  })
  checkingBalance.value = checkingState

  try {
    const response: any = await request.post('/superadmin/ai-config/query-all-balances', {
      providerIds: enabledIds
    })
    
    if (response.code === 200 && response.data) {
      // 更新余额结果
      response.data.forEach((result: BalanceResult) => {
        balances.value[result.providerId] = result
        
        // 检查告警
        const provider = providers.find(p => p.id === result.providerId)
        if (provider && provider.balanceAlertEnabled && result.balance !== null && provider.balanceAlertThreshold) {
          if (result.balance <= provider.balanceAlertThreshold) {
            const existingAlert = alerts.value.find(a => a.providerName === provider.name)
            if (!existingAlert) {
              alerts.value.push({
                providerName: provider.name,
                balance: result.balance,
                threshold: provider.balanceAlertThreshold,
                currency: result.currency || 'CNY'
              })
            } else {
              existingAlert.balance = result.balance
            }
          } else {
            const alertIndex = alerts.value.findIndex(a => a.providerName === provider.name)
            if (alertIndex !== -1) {
              alerts.value.splice(alertIndex, 1)
            }
          }
        }
      })
      
      ElMessage.success('全部余额查询完成')
    }
  } catch (error: any) {
    ElMessage.error(error.message || '批量查询余额失败')
  } finally {
    isQueryingAll.value = false
    checkingBalance.value = {}
  }
}

const handleViewDocs = (provider: { id: string; docUrl?: string | null }) => {
  const url = getBrandDocUrl(provider)
  window.open(url, '_blank')
}

onMounted(() => {
  loadConfig()
})
</script>

<style scoped lang="scss">
.page-container {
  padding: 24px;
  background-color: #f9fafb;
  min-height: 100vh;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
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

  .header-actions {
    display: flex;
    gap: 12px;
  }
}

.alert-notification {
  margin-bottom: 20px;

  .alert-title {
    font-weight: 600;
  }

  .alert-item {
    margin-right: 4px;
  }
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 28px;
}

.stat-card {
  background: #fff;
  border-radius: 10px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);

  .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;

    &.purple {
      background-color: #f3e8ff;
      color: #a855f7;
    }

    &.green {
      background-color: #dcfce7;
      color: #22c55e;
    }

    &.yellow {
      background-color: #fef3c7;
      color: #f59e0b;
    }

    &.red {
      background-color: #fee2e2;
      color: #ef4444;
    }
  }

  .stat-info {
    flex: 1;

    .stat-label {
      font-size: 13px;
      color: #6b7280;
      margin-bottom: 4px;
    }

    .stat-value {
      font-size: 24px;
      font-weight: 700;
      color: #111827;
      line-height: 1;

      &.alert-value {
        color: #ef4444;
      }
    }
  }
}

.providers-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
  margin-bottom: 28px;
}

.provider-card {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  overflow: hidden;
  transition: all 0.2s;

  &.enabled {
    border-color: #a855f7;
    box-shadow: 0 1px 3px rgba(168, 85, 247, 0.1);
  }

  .card-header {
    padding: 20px;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    .provider-info {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      flex: 1;

      .provider-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;

        .icon-emoji {
          font-size: 24px;
          line-height: 1;
        }

        &.green {
          background-color: #dcfce7;
        }

        &.blue {
          background-color: #dbeafe;
        }

        &.orange {
          background-color: #fed7aa;
        }

        &.purple {
          background-color: #e9d5ff;
        }

        &.yellow {
          background-color: #fef3c7;
        }

        &.red {
          background-color: #fee2e2;
        }
      }

      .provider-details {
        flex: 1;

        .provider-name-row {
          display: flex;
          align-items: center;
          gap: 8px;
          margin-bottom: 4px;
          flex-wrap: wrap;

          .provider-name {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
          }

          .enabled-tag {
            font-size: 11px;
          }

          .balance-tag {
            font-size: 11px;
          }
        }

        .provider-model {
          font-size: 13px;
          color: #6b7280;
          display: flex;
          align-items: center;
          gap: 8px;
          flex-wrap: wrap;

          .notes-text {
            color: #9ca3af;
          }

          .free-badge {
            font-size: 12px;
            color: #22c55e;
            font-weight: 500;
          }
        }
      }
    }

    .provider-status {
      display: flex;
      align-items: center;
      gap: 8px;

      .status-label {
        font-size: 12px;
        color: #6b7280;
      }
    }
  }

  .card-body {
    padding: 20px;

    .api-key-section {
      margin-bottom: 16px;

      .api-key-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
      }

      .api-key-input-wrapper {
        .api-key-input {
          :deep(.el-input__wrapper) {
            border-radius: 8px;
            box-shadow: 0 0 0 1px #e5e7eb inset;
            padding: 8px 12px;
            transition: all 0.2s;
            font-family: 'Courier New', monospace;

            &.is-focus {
              box-shadow: 0 0 0 1px #a855f7 inset, 0 0 0 3px rgba(168, 85, 247, 0.1);
            }

            &:hover {
              box-shadow: 0 0 0 1px #d1d5db inset;
            }
          }

          .input-actions {
            display: flex;
            gap: 4px;

            .action-btn {
              padding: 4px;
              color: #9ca3af;

              &:hover {
                color: #6b7280;
              }
            }
          }
        }
      }
    }

    .card-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-bottom: 12px;

      .el-button {
        font-size: 12px;
        height: 32px;
        padding: 0 12px;
        
        &.el-button--primary {
          background-color: #a855f7;
          border-color: #a855f7;
          color: #fff;
          
          &:hover {
            background-color: #9333ea;
            border-color: #9333ea;
            color: #fff;
          }
          
          &:active {
            background-color: #7e22ce;
            border-color: #7e22ce;
            color: #fff;
          }
          
          :deep(.el-icon) {
            color: #fff;
          }
        }
      }
    }

    .balance-result {
      padding: 12px;
      border-radius: 8px;
      font-size: 12px;
      margin-bottom: 12px;
      display: flex;
      justify-content: space-between;
      align-items: center;

      &.success {
        background-color: #dcfce7;
        color: #166534;
        border: 1px solid #bbf7d0;
      }

      &.error {
        background-color: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
      }

      &.unsupported {
        background-color: #f3f4f6;
        color: #4b5563;
        border: 1px solid #e5e7eb;
      }

      .balance-message {
        flex: 1;
      }

      .balance-time {
        font-size: 10px;
        color: #9ca3af;
        margin-left: 12px;
      }
    }

    .advanced-config {
      margin-top: 16px;
      padding-top: 16px;
      border-top: 1px solid #f3f4f6;

      .config-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
        margin-bottom: 16px;
      }

      .config-item {
        display: flex;
        flex-direction: column;
        gap: 6px;

        .config-label {
          font-size: 12px;
          font-weight: 600;
          color: #374151;
        }

        .config-input {
          :deep(.el-input__wrapper) {
            border-radius: 6px;
            box-shadow: 0 0 0 1px #e5e7eb inset;
            padding: 6px 10px;
            font-size: 13px;

            &.is-focus {
              box-shadow: 0 0 0 1px #a855f7 inset, 0 0 0 3px rgba(168, 85, 247, 0.1);
            }
          }
        }
      }

      .alert-config {
        background-color: #f9fafb;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;

        .alert-header {
          display: flex;
          align-items: center;
          justify-content: space-between;
          margin-bottom: 12px;

          .alert-info {
            .alert-title {
              font-size: 13px;
              font-weight: 600;
              color: #111827;
              margin: 0 0 2px 0;
            }

            .alert-desc {
              font-size: 11px;
              color: #6b7280;
              margin: 0;
            }
          }
        }

        .alert-threshold {
          display: flex;
          flex-direction: column;
          gap: 6px;

          .threshold-input {
            max-width: 200px;
          }
        }
      }

      .extra-config-section {
        margin-top: 16px;
        margin-bottom: 16px;

        .config-label {
          font-size: 12px;
          font-weight: 600;
          color: #374151;
          margin-bottom: 8px;
          display: block;
        }

        .extra-config-empty {
          font-size: 12px;
          color: #9ca3af;
          margin: 8px 0 0 0;
        }

        .extra-config-list {
          display: flex;
          flex-direction: column;
          gap: 8px;
          margin-bottom: 10px;
        }

        .extra-config-row {
          display: flex;
          gap: 8px;
          align-items: center;

          .extra-config-delete {
            flex-shrink: 0;
            color: #f56c6c;
          }

          .extra-config-key,
          .extra-config-value {
            :deep(.el-input__wrapper) {
              border-radius: 6px;
              box-shadow: 0 0 0 1px #e5e7eb inset;
              padding: 6px 10px;
              font-size: 13px;
              min-height: 32px;

              &.is-focus {
                box-shadow: 0 0 0 1px #a855f7 inset, 0 0 0 3px rgba(168, 85, 247, 0.1);
              }
            }
          }

          .extra-config-key {
            flex: 0 0 140px;
          }
          .extra-config-value {
            flex: 1;
          }
        }

        .extra-config-add {
          display: flex;
          gap: 8px;
          align-items: center;
          flex-wrap: wrap;

          .extra-config-key,
          .extra-config-value {
            :deep(.el-input__wrapper) {
              border-radius: 6px;
              box-shadow: 0 0 0 1px #e5e7eb inset;
              padding: 6px 10px;
              font-size: 13px;
              min-height: 32px;

              &.is-focus {
                box-shadow: 0 0 0 1px #a855f7 inset, 0 0 0 3px rgba(168, 85, 247, 0.1);
              }
            }
          }

          .extra-config-key {
            width: 140px;
          }
          .extra-config-value {
            flex: 1;
            min-width: 120px;
          }
        }
      }

      .save-all-btn {
        margin-top: 8px;
        font-size: 12px;
        height: 32px;
        padding: 0 12px;
        background-color: #a855f7;
        border-color: #a855f7;
        color: #fff;
        
        &:hover {
          background-color: #9333ea;
          border-color: #9333ea;
          color: #fff;
        }
        
        &:active {
          background-color: #7e22ce;
          border-color: #7e22ce;
          color: #fff;
        }
        
        :deep(.el-icon) {
          color: #fff;
        }
      }
    }
  }
}

.safety-warning {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  padding: 20px;

  .warning-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;

    .warning-icon {
      font-size: 20px;
      color: #f59e0b;
    }

    .warning-title {
      font-size: 16px;
      font-weight: 700;
      color: #111827;
      margin: 0;
    }
  }

  .warning-list {
    margin: 0;
    padding-left: 20px;
    list-style: disc;

    li {
      font-size: 13px;
      color: #6b7280;
      line-height: 1.8;
      margin-bottom: 8px;

      &:last-child {
        margin-bottom: 0;
      }
    }
  }
}

.mr-1 {
  margin-right: 4px;
}

.rotating {
  animation: rotate 1s linear infinite;
}

@keyframes rotate {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 1200px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .providers-grid {
    grid-template-columns: 1fr;
  }

  .advanced-config .config-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>
