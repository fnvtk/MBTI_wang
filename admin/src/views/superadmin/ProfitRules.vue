<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h2>分账规则</h2>
        <p class="subtitle">按产品类型配置分账接收人与比例，支付成功后自动执行。比例合计须等于 100%。</p>
      </div>
      <el-button type="primary" @click="openEdit()">
        <el-icon><Plus /></el-icon>新增规则
      </el-button>
    </div>

    <el-table :data="list" border stripe v-loading="loading">
      <el-table-column prop="productType" label="产品类型" width="200" />
      <el-table-column prop="name" label="名称" width="240" />
      <el-table-column label="分账明细" min-width="320">
        <template #default="{ row }">
          <div v-for="(r, i) in row.receivers" :key="i" class="receiver-line">
            <el-tag size="small">{{ labelOf(r.type) }}</el-tag>
            <span style="margin-left: 6px;">{{ r.name }}</span>
            <span class="ratio">{{ (Number(r.ratio) * 100).toFixed(1) }}%</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="row.status === 'active' ? 'success' : 'info'">
            {{ row.status === 'active' ? '生效中' : '已禁用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="200">
        <template #default="{ row }">
          <el-button size="small" link @click="openEdit(row)">编辑</el-button>
          <el-button size="small" link @click="onToggle(row)">
            {{ row.status === 'active' ? '禁用' : '启用' }}
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="showDialog" :title="form.id ? '编辑规则' : '新增规则'" width="680px">
      <el-form :model="form" label-width="110px">
        <el-form-item label="产品类型">
          <el-input v-model="form.productType" :disabled="!!form.id" placeholder="如 ai_deep_report" />
        </el-form-item>
        <el-form-item label="名称">
          <el-input v-model="form.name" placeholder="如 AI 深度画像报告" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" active-value="active" inactive-value="disabled" active-text="生效" inactive-text="禁用" />
        </el-form-item>
        <el-form-item label="分账接收人">
          <div>
            <div v-for="(r, i) in form.receivers" :key="i" class="receiver-row">
              <el-select v-model="r.type" style="width: 160px">
                <el-option label="平台" value="platform" />
                <el-option label="一级分销" value="distributor_l1" />
                <el-option label="二级分销" value="distributor_l2" />
                <el-option label="顾问" value="consultant" />
                <el-option label="自定义" value="custom" />
              </el-select>
              <el-input v-model="r.name" placeholder="显示名" style="width: 180px; margin-left: 8px" />
              <el-input-number v-model="r.ratio" :min="0" :max="1" :step="0.05" :precision="3" style="margin-left: 8px" />
              <span style="margin-left: 8px">比例(0~1)</span>
              <el-button link type="danger" @click="form.receivers.splice(i, 1)">删除</el-button>
            </div>
            <div style="margin-top: 10px">
              <el-button size="small" @click="form.receivers.push({ type: 'platform', name: '平台', ratio: 0 })">
                <el-icon><Plus /></el-icon>添加接收人
              </el-button>
              <span class="sum-tip" :class="{ err: !sumOk }">
                当前合计：{{ sumPercent.toFixed(1) }}%
              </span>
            </div>
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" :disabled="!sumOk" @click="onSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { request } from '@/utils/request'

interface Receiver { type: string; name: string; ratio: number }
interface Rule {
  id?: number
  productType: string
  name: string
  receivers: Receiver[]
  status: 'active' | 'disabled'
}

const list = ref<Rule[]>([])
const loading = ref(false)
const showDialog = ref(false)
const saving = ref(false)

const emptyForm = (): Rule => ({
  productType: '', name: '', receivers: [{ type: 'platform', name: '平台', ratio: 1 }], status: 'active'
})
const form = ref<Rule>(emptyForm())

const sumPercent = computed(() => {
  return form.value.receivers.reduce((s, r) => s + (Number(r.ratio) || 0), 0) * 100
})
const sumOk = computed(() => Math.abs(sumPercent.value - 100) < 0.5)

const labelOf = (t: string) => ({
  platform: '平台',
  distributor_l1: '一级分销',
  distributor_l2: '二级分销',
  consultant: '顾问',
  custom: '自定义',
}[t] || t)

const fetchList = async () => {
  loading.value = true
  try {
    const res: any = await request.get('/superadmin/profit-rules')
    if (res && res.code === 200) {
      list.value = (res.data?.list || []).map((r: any) => ({
        id: r.id,
        productType: r.productType,
        name: r.name,
        receivers: Array.isArray(r.receivers) ? r.receivers : [],
        status: r.status
      }))
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '加载失败')
  } finally {
    loading.value = false
  }
}

const openEdit = (row?: Rule) => {
  if (row) {
    form.value = JSON.parse(JSON.stringify(row))
  } else {
    form.value = emptyForm()
  }
  showDialog.value = true
}

const onSave = async () => {
  if (!form.value.productType || !form.value.name) return ElMessage.warning('请填写产品类型与名称')
  if (!sumOk.value) return ElMessage.warning('分账比例合计必须 = 100%')
  saving.value = true
  try {
    const res: any = await request.post('/superadmin/profit-rules/save', form.value)
    if (res && res.code === 200) {
      ElMessage.success('已保存')
      showDialog.value = false
      fetchList()
    } else {
      ElMessage.error(res?.message || '保存失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '保存失败')
  } finally {
    saving.value = false
  }
}

const onToggle = async (row: Rule) => {
  try {
    const res: any = await request.post(`/superadmin/profit-rules/${row.id}/toggle`)
    if (res && res.code === 200) {
      ElMessage.success('已切换')
      fetchList()
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '操作失败')
  }
}

onMounted(fetchList)
</script>

<style scoped>
.page-container { padding: 24px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.page-header h2 { margin: 0 0 6px; color: #1F1B4D; }
.subtitle { color: #6B6894; font-size: 13px; max-width: 800px; }
.receiver-line { display: flex; align-items: center; margin-bottom: 4px; }
.ratio { margin-left: auto; color: #7c3aed; font-weight: 600; }
.receiver-row { display: flex; align-items: center; margin-bottom: 10px; }
.sum-tip { margin-left: 16px; color: #059669; font-weight: 600; }
.sum-tip.err { color: #EF4444; }
</style>
