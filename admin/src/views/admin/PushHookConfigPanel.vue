<template>
  <div class="tab-content" v-loading="loading">
    <div class="content-header">
      <h3>通用 HTTP 出站推送</h3>
      <p class="scope-badge" v-if="scopeLabel">
        <span class="scope-pill">{{ scopeLabel }}</span>
        <span v-if="enterpriseName" class="scope-name">{{ enterpriseName }}</span>
      </p>
      <p class="content-description">
        向自有系统 POST JSON（含事件名、投递 ID、业务负载）。可与飞书获客并行。
        <template v-if="scope === 'enterprise'">
          当前为<strong>本企业专属</strong>配置；若本企业未启用或 URL 无效，事件将<strong>回落</strong>到超管配置的「全平台默认」。
        </template>
        <template v-else>
          当前为<strong>全平台默认</strong>（无企业专属或超管维护）；业务归属某企业且该企业配置了专属 Hook 时，将优先推送到企业 URL。
        </template>
        留空「订阅事件」表示三类事件全部推送。
      </p>

      <div class="form-section test-tools">
        <h4 class="test-tools-title">测试与调试</h4>
        <p class="hint test-tools-hint">
          <strong>发送连接测试</strong>：只验证 URL 是否可达（hook.ping），不写去重表。<strong>手动测试测评完成</strong>：按库表
          <code>test_results.id</code> 重放真实 <code>test.result_completed</code>（人脸、问卷、简历等均可）。
        </p>
        <div class="test-tools-actions">
          <el-button plain :loading="testLoading" @click="sendTest">发送连接测试</el-button>
        </div>
        <div class="manual-test-row">
          <span class="manual-test-label">记录 ID</span>
          <el-input
            v-model.number="replayTestResultId"
            type="number"
            :min="1"
            step="1"
            placeholder="test_results 主键"
            class="manual-test-input"
          />
          <span class="manual-test-label force-label">强制</span>
          <el-switch v-model="replayForce" />
          <el-button
            type="primary"
            color="#7c3aed"
            class="manual-test-btn"
            :loading="replayLoading"
            @click="replayTestResult"
          >
            手动测试测评完成
          </el-button>
        </div>
      </div>
    </div>
    <div class="form-section">
      <div class="form-item row-line">
        <label>启用</label>
        <el-switch v-model="form.enabled" />
      </div>
      <div class="form-item">
        <label>接收 URL</label>
        <el-input
          v-model="form.url"
          type="textarea"
          :rows="2"
          placeholder="https://example.com/hooks/mbti"
          class="w-full"
        />
      </div>
      <div class="form-item">
        <label>签名密钥（可选）</label>
        <el-input
          v-model="form.secret"
          type="password"
          show-password
          placeholder="填写则请求头带 X-MBTI-Signature: sha256=…"
          class="w-full"
        />
      </div>
      <div class="form-item">
        <label>订阅事件</label>
        <el-checkbox-group v-model="form.eventChecks">
          <el-checkbox v-for="opt in eventOptions" :key="opt.value" :label="opt.value">
            {{ opt.label }}
          </el-checkbox>
        </el-checkbox-group>
        <p class="hint">与文档一致：<code>lead.order_paid</code>、<code>lead.phone_bound</code>、<code>test.result_completed</code>。全部勾选保存后将以「空列表」存库，表示订阅全部。上方「手动测试测评完成」与真实落库推送使用同一套订阅校验。</p>
      </div>
    </div>
    <div class="save-actions save-actions-row">
      <el-button type="primary" color="#7c3aed" class="save-btn" :loading="loading" @click="save">
        保存配置
      </el-button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { request } from '@/utils/request'

const ALL_EVENTS = ['lead.order_paid', 'lead.phone_bound', 'test.result_completed'] as const

const props = withDefaults(
  defineProps<{
    /** 如 /admin 或 /superadmin，对应 request 基路径下的 settings */
    apiPrefix: string
  }>(),
  { apiPrefix: '/admin' }
)

const eventOptions = [
  { value: 'lead.order_paid', label: '支付成功（订单）' },
  { value: 'lead.phone_bound', label: '首次绑定手机号' },
  { value: 'test.result_completed', label: '测评/分析结果落库' }
] as const

const loading = ref(false)
const testLoading = ref(false)
const replayLoading = ref(false)
const replayTestResultId = ref<number | null>(null)
const replayForce = ref(false)
/** platform | enterprise，来自接口 */
const scope = ref<'platform' | 'enterprise'>('platform')
const enterpriseName = ref<string | null>(null)

const scopeLabel = computed(() => {
  if (scope.value === 'enterprise') return '本企业专属'
  return '全平台默认'
})

const form = reactive({
  enabled: false,
  url: '',
  secret: '',
  /** 勾选中的事件；与 ALL 一致时保存为空数组表示「全部」 */
  eventChecks: [] as string[]
})

const normalizeLoadedEvents = (raw: unknown): string[] => {
  if (!Array.isArray(raw) || raw.length === 0) {
    return [...ALL_EVENTS]
  }
  const set = new Set<string>()
  for (const e of raw) {
    const s = String(e).trim()
    if (s) set.add(s)
  }
  return ALL_EVENTS.filter((e) => set.has(e))
}

const eventsForSave = (): string[] => {
  const checks = form.eventChecks
  if (checks.length === ALL_EVENTS.length && ALL_EVENTS.every((e) => checks.includes(e))) {
    return []
  }
  return [...checks]
}

const load = async () => {
  loading.value = true
  try {
    const res: any = await request.get(`${props.apiPrefix}/settings/push-hook`)
    if (res.code === 200 && res.data) {
      const d = res.data
      scope.value = d.scope === 'enterprise' ? 'enterprise' : 'platform'
      enterpriseName.value = d.enterpriseName != null && d.enterpriseName !== '' ? String(d.enterpriseName) : null
      form.enabled = !!d.enabled
      form.url = d.url || ''
      form.secret = d.secret || ''
      form.eventChecks = normalizeLoadedEvents(d.events)
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const save = async () => {
  loading.value = true
  try {
    const res: any = await request.put(`${props.apiPrefix}/settings/push-hook`, {
      enabled: form.enabled,
      url: form.url,
      secret: form.secret,
      events: eventsForSave()
    })
    if (res.code === 200) {
      ElMessage.success('已保存')
    } else {
      ElMessage.error(res.msg || '保存失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '保存失败')
  } finally {
    loading.value = false
  }
}

const replayTestResult = async () => {
  const id = Number(replayTestResultId.value || 0)
  if (!Number.isFinite(id) || id <= 0) {
    ElMessage.warning('请填写有效的测试记录 ID')
    return
  }
  replayLoading.value = true
  try {
    const res: any = await request.post(`${props.apiPrefix}/settings/push-hook/test-result`, {
      testResultId: id,
      force: replayForce.value ? 1 : 0
    })
    if (res.code === 200 && res.data) {
      const d = res.data
      const detail =
        [d.businessHint && `业务提示：${d.businessHint}`, d.curlError && `网络：${d.curlError}`, d.responsePreview && `响应体：\n${d.responsePreview}`]
          .filter(Boolean)
          .join('\n\n') || ''
      if (d.ok) {
        ElMessage.success(d.message || res.msg || '重放已发出')
        if (detail) {
          await ElMessageBox.alert(detail, '对端返回详情', { confirmButtonText: '知道了' })
        }
      } else {
        ElMessage.error(d.message || res.msg || '重放未成功')
        await ElMessageBox.alert(detail || d.message || '请检查记录 ID 是否存在、是否已订阅 test.result_completed、URL 是否有效。', '排查信息', {
          confirmButtonText: '知道了'
        })
      }
    } else {
      ElMessage.error(res.msg || '请求失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '请求失败')
  } finally {
    replayLoading.value = false
  }
}

const sendTest = async () => {
  testLoading.value = true
  try {
    const res: any = await request.post(`${props.apiPrefix}/settings/push-hook/test`)
    if (res.code === 200 && res.data) {
      const d = res.data
      const detail =
        [d.businessHint && `业务提示：${d.businessHint}`, d.curlError && `网络：${d.curlError}`, d.responsePreview && `响应体：\n${d.responsePreview}`]
          .filter(Boolean)
          .join('\n\n') || ''
      if (d.ok) {
        ElMessage.success(d.message || res.msg || '测试已发出')
        if (detail) {
          await ElMessageBox.alert(detail, '对端返回详情', { confirmButtonText: '知道了' })
        }
      } else {
        ElMessage.error(d.message || res.msg || '测试未成功')
        await ElMessageBox.alert(detail || d.message || '请检查 URL 是否为「可接收任意 JSON」的地址；企微/飞书机器人需用对应协议，不能直接用通用 JSON。', '排查信息', {
          confirmButtonText: '知道了'
        })
      }
    } else {
      ElMessage.error(res.msg || '请求失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '请求失败')
  } finally {
    testLoading.value = false
  }
}

onMounted(() => {
  load()
})
</script>

<style scoped>
.content-header h3 {
  margin: 0 0 8px;
  font-size: 18px;
}
.scope-badge {
  margin: 0 0 10px;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.scope-pill {
  display: inline-block;
  font-size: 12px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 999px;
  background: #ede9fe;
  color: #5b21b6;
}
.scope-name {
  font-size: 13px;
  color: #64748b;
}
.content-description {
  margin: 0 0 20px;
  color: #64748b;
  font-size: 14px;
  line-height: 1.5;
}
.form-section {
  max-width: 640px;
}
.form-item {
  margin-bottom: 16px;
}
.form-item label {
  display: block;
  margin-bottom: 6px;
  font-size: 14px;
  color: #334155;
}
.row-line {
  display: flex;
  align-items: center;
  gap: 12px;
}
.row-line label {
  margin-bottom: 0;
}
.hint {
  margin: 8px 0 0;
  font-size: 13px;
  color: #64748b;
  line-height: 1.5;
}
.hint code {
  font-size: 12px;
  background: #f1f5f9;
  padding: 1px 4px;
  border-radius: 4px;
}
.save-actions {
  margin-top: 8px;
}
.save-actions-row {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
}
.test-tools {
  margin-top: 8px;
  padding: 16px 18px;
  max-width: 640px;
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border: 1px solid #e2e8f0;
  border-radius: 12px;
}
.test-tools-title {
  margin: 0 0 10px;
  font-size: 15px;
  font-weight: 600;
  color: #0f172a;
}
.test-tools-hint {
  margin: 0 0 14px;
}
.test-tools-actions {
  margin-bottom: 14px;
}
.manual-test-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px 12px;
}
.manual-test-label {
  font-size: 14px;
  color: #334155;
  white-space: nowrap;
}
.force-label {
  margin-left: 4px;
}
.manual-test-input {
  width: 160px;
  max-width: 100%;
}
.manual-test-btn {
  margin-left: auto;
}
@media (max-width: 640px) {
  .manual-test-btn {
    margin-left: 0;
    width: 100%;
  }
}
</style>
