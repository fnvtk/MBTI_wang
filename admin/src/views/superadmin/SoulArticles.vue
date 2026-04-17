<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h2>Soul 引流文章</h2>
        <p class="subtitle">
          采集由服务端<strong>仅通过 HTTPS</strong>请求「一场 soul 创业实验」开放接口，<strong>不使用 SSH</strong>。
          「当前推荐」至多 3 篇；需在下方开启<strong>神仙 AI 页</strong>或<strong>我的页推荐条</strong>后，小程序对应位置才会展示；排序第 1 篇用于「我的」底部灰字链接。
        </p>
      </div>
      <div class="header-actions">
        <el-input
          v-model="searchKeyword"
          placeholder="搜索一场创业实验内容关键词"
          clearable
          style="width: 260px"
          @keyup.enter="onSearchImport"
        />
        <el-button @click="onSearchImport" :loading="searching">
          搜索并添加
        </el-button>
        <el-button type="primary" @click="onSync" :loading="syncing">
          <el-icon><Refresh /></el-icon>
          采集最新 10 篇
        </el-button>
      </div>
    </div>

    <!-- 神仙 AI 健康小条 -->
    <el-card v-if="health.loaded" class="health-card" shadow="never" :class="{ 'health-card--warn': health.hasAlert }">
      <div class="health-bar">
        <div class="health-title">
          <span class="dot" :class="health.hasAlert ? 'dot-warn' : 'dot-ok'"></span>
          神仙 AI 服务状态
          <el-tag v-if="health.hasAlert" type="danger" size="small">有告警</el-tag>
          <el-tag v-else type="success" size="small">正常</el-tag>
        </div>
        <div class="health-providers">
          <span v-for="p in health.providers" :key="p.providerId" class="provider-chip" :class="{ 'chip-warn': p.status === 'low-balance' || p.status === 'no-key' || p.status === 'disabled' }">
            {{ p.name }} · {{ p.currency === 'USD' ? '$' : '¥' }}{{ p.balance == null ? '—' : Number(p.balance).toFixed(2) }}
            <template v-if="p.status === 'low-balance'">（欠费）</template>
            <template v-else-if="p.status === 'disabled'">（已停用）</template>
            <template v-else-if="p.status === 'no-key'">（缺 key）</template>
          </span>
        </div>
        <div class="health-actions">
          <el-button size="small" :loading="checking" @click="onBalanceCheck">立即扫描余额</el-button>
          <el-button size="small" type="primary" plain @click="fetchHealth">刷新</el-button>
        </div>
      </div>
      <div v-if="health.lastAlert" class="health-alert">
        最近告警：{{ health.lastAlert.providerId }} · 余额 {{ health.lastAlert.balance }} / 阈值 {{ health.lastAlert.threshold }}（{{ health.lastAlert.dateStr }}）
      </div>
    </el-card>

    <!-- 小程序 · 推荐文章展示（神仙 AI / 我的） -->
    <el-card class="ai-chat-display-card" shadow="never">
      <template #header>
        <span>小程序 · 推荐文章展示</span>
      </template>
      <el-form label-width="160px" class="ai-chat-display-form">
        <el-form-item label="神仙 AI 页展示">
          <el-switch v-model="aiChatDisplay.enabled" />
          <span class="form-hint-inline">开启后神仙 AI 页显示「精选推荐」列表</span>
        </el-form-item>
        <el-form-item v-if="aiChatDisplay.enabled" label="最多展示条数">
          <el-radio-group v-model="aiChatDisplay.maxShow">
            <el-radio :label="1">1 篇</el-radio>
            <el-radio :label="2">2 篇</el-radio>
            <el-radio :label="3">3 篇</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="aiChatDisplay.enabled" label="默认展开列表">
          <el-switch v-model="aiChatDisplay.sectionExpandedDefault" />
          <span class="form-hint-inline">关闭则默认收起，用户点击「精选推荐」后展开</span>
        </el-form-item>
        <el-divider content-position="left">我的 · 底部推荐条</el-divider>
        <el-form-item label="我的页展示首条">
          <el-switch v-model="aiChatDisplay.profileRecoEnabled" />
          <span class="form-hint-inline">在「我的」快捷入口下方展示<strong>当前推荐排序第 1 篇</strong>（灰色字可点）</span>
        </el-form-item>
        <el-form-item v-if="aiChatDisplay.profileRecoEnabled" label="区块标题 / 标签">
          <el-input
            v-model="aiChatDisplay.profileSectionLabel"
            maxlength="32"
            show-word-limit
            placeholder="如：一场创业实验、精选阅读"
            style="max-width: 360px"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :loading="savingAcDisplay" @click="saveAiChatDisplay">
            保存展示设置
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 推荐位概览 -->
    <el-card class="reco-card" shadow="hover">
      <template #header>
        <div class="card-header">
          <span>当前推荐（至多 3 篇 · 需在上方开启「小程序展示」后按条数展示）</span>
          <div class="reco-head-actions">
            <span class="reco-count">{{ recommended.length }} / 3</span>
            <el-button size="small" @click="onNormalizeOrder">一键归一排序</el-button>
          </div>
        </div>
      </template>
      <el-empty v-if="recommended.length === 0" description="还没有推荐文章，去下方列表勾选一篇吧" />
      <div v-else class="reco-list">
        <div v-for="(a, idx) in recommended" :key="a.id" class="reco-item">
          <div class="reco-rank">{{ idx + 1 }}</div>
          <img v-if="a.cover" :src="a.cover" class="reco-cover" />
          <div v-else class="reco-cover reco-cover--placeholder">MBTI</div>
          <div class="reco-body">
            <div class="reco-title">{{ a.title }}</div>
            <div class="reco-meta">
              <el-tag size="small" type="primary">{{ a.tag || 'MBTI' }}</el-tag>
              <span class="meta-text">{{ formatDate(a.publishedAt) }}</span>
            </div>
          </div>
          <el-button size="small" @click="onToggleRecommend(a)">取消推荐</el-button>
        </div>
      </div>
    </el-card>

    <!-- 候选文章列表 -->
    <el-card class="list-card" shadow="hover">
      <template #header>
        <div class="card-header card-header-col">
          <span>候选文章池</span>
          <div class="filter-row">
            <el-input
              v-model="keyword"
              placeholder="按标题关键词筛选"
              clearable
              style="width: 220px"
              @keyup.enter="onSearch"
            />
            <el-input
              v-model="tagFilter"
              placeholder="标签（如 MBTI）"
              clearable
              style="width: 140px"
              @keyup.enter="onSearch"
            />
            <el-date-picker
              v-model="dateRange"
              type="daterange"
              unlink-panels
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              value-format="YYYY-MM-DD"
              style="width: 320px"
            />
            <el-radio-group v-model="filter" size="small" @change="fetchList">
              <el-radio-button label="">全部</el-radio-button>
              <el-radio-button label="0">未推荐</el-radio-button>
              <el-radio-button label="1">已推荐</el-radio-button>
            </el-radio-group>
            <el-button type="primary" plain @click="onSearch">筛选</el-button>
            <el-button @click="onReset">重置</el-button>
          </div>
        </div>
      </template>

      <el-table :data="list" v-loading="loading" stripe style="width: 100%">
        <el-table-column label="封面" width="100">
          <template #default="{ row }">
            <img v-if="row.cover" :src="row.cover" style="width: 80px; height: 50px; object-fit: cover; border-radius: 6px;" />
            <div v-else style="width: 80px; height: 50px; background: #a78bfa; color: white; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 12px;">MBTI</div>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="320" show-overflow-tooltip />
        <el-table-column prop="tag" label="标签" width="100">
          <template #default="{ row }">
            <el-tag size="small">{{ row.tag || 'MBTI' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="author" label="作者" width="120" />
        <el-table-column label="发布时间" width="160">
          <template #default="{ row }">{{ formatDate(row.publishedAt) }}</template>
        </el-table-column>
        <el-table-column label="阅读" width="80">
          <template #default="{ row }">{{ row.viewCount || 0 }}</template>
        </el-table-column>
        <el-table-column label="状态" width="110">
          <template #default="{ row }">
            <el-tag v-if="row.isRecommended" type="success" size="small">推荐中</el-tag>
            <el-tag v-else type="info" size="small">未推荐</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="置顶权重" width="120" align="center">
          <template #default="{ row }">
            <div v-if="row.isRecommended" class="order-cell">
              <el-input-number
                v-model="row.recommendedOrder"
                :min="0"
                :max="999"
                :controls="false"
                size="small"
                style="width: 72px"
              />
              <el-button size="small" type="primary" link @click="onUpdateOrder(row)">保存</el-button>
            </div>
            <span v-else class="order-empty">—</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button size="small" type="primary" link @click="preview(row)">预览</el-button>
            <el-button size="small" :type="row.isRecommended ? 'warning' : 'success'" @click="onToggleRecommend(row)">
              {{ row.isRecommended ? '取消' : '推荐' }}
            </el-button>
            <el-button size="small" type="danger" link @click="onDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :total="total"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next"
          @current-change="fetchList"
          @size-change="fetchList"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import { request } from '@/utils/request'

const loading = ref(false)
const syncing = ref(false)
const searching = ref(false)
const checking = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const filter = ref<string>('')
const searchKeyword = ref('')
const keyword = ref('')
const tagFilter = ref('')
const dateRange = ref<string[]>([])
const health = ref<any>({ loaded: false, providers: [], lastAlert: null, hasAlert: false })

const aiChatDisplay = ref({
  enabled: false,
  maxShow: 1,
  sectionExpandedDefault: false,
  profileRecoEnabled: false,
  profileSectionLabel: '推荐阅读'
})
const savingAcDisplay = ref(false)

const recommended = computed(() => list.value.filter(x => x.isRecommended))

const formatDate = (ts: number) => {
  if (!ts) return '—'
  const d = new Date(ts * 1000)
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}
const pad = (n: number) => n < 10 ? '0' + n : '' + n

const fetchList = async () => {
  loading.value = true
  try {
    const res: any = await request.get('/superadmin/soul-articles', {
      params: {
        page: page.value,
        pageSize: pageSize.value,
        ...(filter.value !== '' ? { isRecommended: filter.value } : {}),
        ...(keyword.value ? { keyword: keyword.value } : {}),
        ...(tagFilter.value ? { tag: tagFilter.value } : {}),
        ...(dateRange.value && dateRange.value.length === 2 ? { dateRange: dateRange.value } : {})
      }
    })
    if (res && res.code === 200) {
      list.value = (res.data && res.data.list) || []
      total.value = (res.data && res.data.total) || 0
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '加载失败')
  } finally {
    loading.value = false
  }
}

const onSearch = () => {
  page.value = 1
  fetchList()
}

const onReset = () => {
  keyword.value = ''
  tagFilter.value = ''
  dateRange.value = []
  filter.value = ''
  page.value = 1
  fetchList()
}

const onSync = async () => {
  syncing.value = true
  try {
    const res: any = await request.post('/superadmin/soul-articles/sync', { limit: 10, tag: 'MBTI' })
    if (res && res.code === 200) {
      const d = res.data || {}
      ElMessage.success(`采集完成：新增 ${d.created || 0} 篇，更新 ${d.updated || 0} 篇`)
      page.value = 1
      fetchList()
    } else {
      ElMessage.error((res && res.message) || '采集失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '采集失败，请稍后再试')
  } finally {
    syncing.value = false
  }
}

const onSearchImport = async () => {
  const keyword = (searchKeyword.value || '').trim()
  if (!keyword) {
    ElMessage.warning('请先输入关键词')
    return
  }
  searching.value = true
  try {
    const res: any = await request.post('/superadmin/soul-articles/sync', {
      limit: 10,
      tag: 'MBTI',
      keyword
    })
    if (res && res.code === 200) {
      const d = res.data || {}
      ElMessage.success(`已搜索并添加：新增 ${d.created || 0} 篇，更新 ${d.updated || 0} 篇`)
      page.value = 1
      fetchList()
    } else {
      ElMessage.error((res && res.message) || '搜索添加失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '搜索添加失败')
  } finally {
    searching.value = false
  }
}

const onToggleRecommend = async (row: any) => {
  const current = list.value.filter(x => x.isRecommended).length
  if (!row.isRecommended && current >= 3) {
    const r = await ElMessageBox.confirm(
      '当前已有 3 篇推荐，继续推荐将自动顶掉最老的一篇，确认继续？',
      '推荐已满',
      { type: 'warning' }
    ).catch(() => 'cancel')
    if (r === 'cancel') return
  }
  try {
    const res: any = await request.post(`/superadmin/soul-articles/${row.id}/recommend`)
    if (res && res.code === 200) {
      ElMessage.success(res.message || '操作成功')
      fetchList()
    } else {
      ElMessage.error((res && res.message) || '操作失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '操作失败')
  }
}

const onDelete = async (row: any) => {
  const r = await ElMessageBox.confirm(`确认删除「${row.title}」？`, '确认', { type: 'warning' }).catch(() => 'cancel')
  if (r === 'cancel') return
  try {
    const res: any = await request.post(`/superadmin/soul-articles/${row.id}/delete`)
    if (res && res.code === 200) {
      ElMessage.success('已删除')
      fetchList()
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '删除失败')
  }
}

const onUpdateOrder = async (row: any) => {
  try {
    const res: any = await request.post(`/superadmin/soul-articles/${row.id}/order`, {
      recommendedOrder: Number(row.recommendedOrder || 0),
    })
    if (res && res.code === 200) {
      ElMessage.success('置顶权重已更新')
      fetchList()
    } else {
      ElMessage.error((res && res.message) || '更新失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '更新失败')
  }
}

const onNormalizeOrder = async () => {
  try {
    const res: any = await request.post('/superadmin/soul-articles/reorder-normalize')
    if (res && res.code === 200) {
      ElMessage.success('推荐权重已归一')
      fetchList()
    } else {
      ElMessage.error((res && res.message) || '归一失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '归一失败')
  }
}

const preview = (row: any) => {
  if (!row.url) {
    ElMessage.warning('该文章无 URL')
    return
  }
  window.open(row.url, '_blank')
}

const fetchAiChatDisplay = async () => {
  try {
    const res: any = await request.get('/superadmin/soul-articles/ai-chat-display')
    if (res && res.code === 200 && res.data) {
      const d = res.data
      aiChatDisplay.value = {
        enabled: !!d.enabled,
        maxShow: Math.max(1, Math.min(3, Number(d.maxShow) || 1)),
        sectionExpandedDefault: !!d.sectionExpandedDefault,
        profileRecoEnabled: !!d.profileRecoEnabled,
        profileSectionLabel: String(d.profileSectionLabel || '推荐阅读').slice(0, 32)
      }
    }
  } catch (e) {}
}

const saveAiChatDisplay = async () => {
  savingAcDisplay.value = true
  try {
    const res: any = await request.post('/superadmin/soul-articles/ai-chat-display', {
      enabled: aiChatDisplay.value.enabled,
      maxShow: aiChatDisplay.value.maxShow,
      sectionExpandedDefault: aiChatDisplay.value.sectionExpandedDefault,
      profileRecoEnabled: aiChatDisplay.value.profileRecoEnabled,
      profileSectionLabel: aiChatDisplay.value.profileSectionLabel
    })
    if (res && res.code === 200) {
      ElMessage.success('展示设置已保存')
      await fetchAiChatDisplay()
    } else {
      ElMessage.error((res && res.message) || '保存失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '保存失败')
  } finally {
    savingAcDisplay.value = false
  }
}

const fetchHealth = async () => {
  try {
    const res: any = await request.get('/superadmin/ai/health')
    if (res && res.code === 200) {
      const d = res.data || {}
      const providers = Array.isArray(d.providers) ? d.providers : []
      const hasAlert = providers.some((p: any) => p.status === 'low-balance' || p.status === 'no-key') || !!d.lastAlert
      health.value = { loaded: true, providers, lastAlert: d.lastAlert || null, hasAlert }
    }
  } catch (e) {}
}

const onBalanceCheck = async () => {
  checking.value = true
  try {
    const res: any = await request.post('/superadmin/ai/balance-check')
    if (res && res.code === 200) {
      const d = res.data || {}
      ElMessage.success(`扫描完成：推送 ${d.alerted || 0} 条告警，跳过 ${d.skipped || 0} 条（当日去重）`)
      fetchHealth()
    } else {
      ElMessage.error((res && res.message) || '扫描失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '扫描失败')
  } finally {
    checking.value = false
  }
}

onMounted(() => {
  fetchList()
  fetchHealth()
  fetchAiChatDisplay()
})
</script>

<style scoped>
.page-container {
  padding: 24px;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 20px;
  gap: 16px;
  flex-wrap: wrap;
}

.header-left h2 {
  margin: 0 0 8px 0;
  font-size: 22px;
  color: #1F1B4D;
}

.subtitle {
  color: #6B6894;
  font-size: 13px;
  line-height: 1.6;
  max-width: 720px;
}

.reco-card,
.list-card {
  margin-bottom: 20px;
}

.ai-chat-display-card {
  margin-bottom: 16px;
  border: 1px solid #E9D5FF;
  background: #FAF5FF;
}

.ai-chat-display-form {
  max-width: 720px;
}

.form-hint-inline {
  margin-left: 12px;
  font-size: 12px;
  color: #6B6894;
  vertical-align: middle;
}

.health-card {
  margin-bottom: 16px;
  border: 1px solid #E5E7EB;
  background: #F9FAFB;
}

.health-card--warn {
  background: #FEF2F2;
  border-color: #FECACA;
}

.health-bar {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.health-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  color: #1F1B4D;
  min-width: 180px;
}

.dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}
.dot-ok { background: #10B981; }
.dot-warn { background: #EF4444; }

.health-providers {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  flex: 1;
  min-width: 240px;
}

.provider-chip {
  padding: 4px 10px;
  border-radius: 999px;
  background: #EEF2FF;
  color: #4338CA;
  font-size: 12px;
  font-weight: 500;
}

.chip-warn {
  background: #FEE2E2;
  color: #B91C1C;
}

.health-actions {
  display: flex;
  gap: 8px;
}

.health-alert {
  margin-top: 10px;
  font-size: 12px;
  color: #B91C1C;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.card-header-col {
  flex-direction: column;
  align-items: stretch;
  gap: 10px;
}
.filter-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.order-cell {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.order-empty {
  color: #9CA3AF;
}

.reco-count {
  color: #7c3aed;
  font-weight: 600;
}
.reco-head-actions {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.reco-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.reco-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px;
  border-radius: 8px;
  background: #F5F3FF;
  border: 1px solid #EDE9FE;
}

.reco-rank {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
  flex-shrink: 0;
}

.reco-cover {
  width: 120px;
  height: 72px;
  object-fit: cover;
  border-radius: 6px;
  flex-shrink: 0;
}

.reco-cover--placeholder {
  background: linear-gradient(135deg, #a78bfa 0%, #7c3aed 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}

.reco-body {
  flex: 1;
  min-width: 0;
}

.reco-title {
  font-size: 15px;
  color: #1F1B4D;
  font-weight: 600;
  margin-bottom: 6px;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.reco-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #6B6894;
}

.pagination {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}
</style>
