<template>
  <div class="ccp-wrap">
    <p class="ccp-hint">
      先选择要查看的企业，再加载或导出该企业在小程序中记录的用户合作意向（与表 <code>mbti_user_cooperation_choices</code> 一致）。
    </p>

    <div class="ccp-toolbar">
      <el-select
        v-model="enterpriseId"
        filterable
        clearable
        placeholder="选择企业"
        class="ccp-select"
        :loading="entLoading"
        @visible-change="onEntDropdown"
      >
        <el-option v-for="e in entOptions" :key="e.id" :label="e.label" :value="e.id" />
      </el-select>
      <el-input
        v-model="keyword"
        placeholder="搜索昵称/手机/模式代码"
        clearable
        class="ccp-search"
        @keyup.enter="onSearch"
      />
      <el-button type="primary" :disabled="!enterpriseId" :loading="loading" @click="onSearch">查询</el-button>
      <el-button :disabled="!enterpriseId" :loading="exporting" @click="doExport">导出 CSV</el-button>
    </div>

    <el-table v-loading="loading" :data="list" border stripe class="ccp-table" empty-text="暂无数据或无权限">
      <el-table-column prop="enterpriseId" label="企业ID" width="88" />
      <el-table-column prop="enterpriseName" label="企业名称" min-width="140" show-overflow-tooltip />
      <el-table-column prop="userId" label="用户ID" width="88" />
      <el-table-column prop="nickname" label="微信昵称" min-width="120" show-overflow-tooltip />
      <el-table-column prop="phone" label="手机号" width="120" show-overflow-tooltip />
      <el-table-column prop="modeCode" label="模式代码" width="120" />
      <el-table-column prop="modeTitle" label="模式标题" min-width="120" show-overflow-tooltip />
      <el-table-column prop="chosenAtText" label="选择时间" width="170" />
      <el-table-column prop="updatedAtText" label="更新时间" width="170" />
    </el-table>

    <div class="pager-wrap" v-if="enterpriseId && total > 0">
      <el-pagination
        v-model:current-page="page"
        v-model:page-size="pageSize"
        :total="total"
        :page-sizes="[20, 50, 100]"
        layout="total, sizes, prev, pager, next"
        @current-change="load"
        @size-change="load"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { request } from '@/utils/request'
import { downloadCsvGet } from '@/utils/downloadCsv'

const entLoading = ref(false)
const entOptions = ref<{ id: number; label: string }[]>([])

const enterpriseId = ref<number | undefined>(undefined)
const keyword = ref('')
const loading = ref(false)
const exporting = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)

const loadEntOptions = async () => {
  if (entOptions.value.length > 0) return
  entLoading.value = true
  try {
    const res: any = await request.get('/superadmin/enterprises', { params: { page: 1, pageSize: 500 } })
    const raw = res?.data?.list ?? res?.data?.data?.list
    const rows = Array.isArray(raw) ? raw : []
    entOptions.value = rows.map((r: any) => ({
      id: Number(r.id),
      label: `${r.name || '未命名'} (#${r.id})`
    }))
  } catch {
    entOptions.value = []
  } finally {
    entLoading.value = false
  }
}

const onEntDropdown = (open: boolean) => {
  if (open) loadEntOptions()
}

const load = async () => {
  const eid = enterpriseId.value
  if (!eid) {
    list.value = []
    total.value = 0
    return
  }
  loading.value = true
  try {
    const res: any = await request.get(`/superadmin/enterprises/${eid}/cooperation-choices`, {
      params: {
        page: page.value,
        pageSize: pageSize.value,
        keyword: keyword.value.trim() || undefined
      }
    })
    if (res.code === 200) {
      list.value = res.data?.list || []
      total.value = res.data?.total ?? 0
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '加载失败')
  } finally {
    loading.value = false
  }
}

const onSearch = () => {
  page.value = 1
  load()
}

const doExport = async () => {
  const eid = enterpriseId.value
  if (!eid) return
  exporting.value = true
  try {
    const q: Record<string, string> = {}
    const k = keyword.value.trim()
    if (k) q.keyword = k
    await downloadCsvGet(
      `superadmin/enterprises/${eid}/cooperation-choices/export`,
      `cooperation-choices-e${eid}.csv`,
      q
    )
    ElMessage.success('已开始下载')
  } catch (e: any) {
    ElMessage.error(e?.message || '导出失败')
  } finally {
    exporting.value = false
  }
}

watch(enterpriseId, () => {
  page.value = 1
  load()
})
</script>

<style scoped lang="scss">
.ccp-wrap {
  max-width: 1200px;
}
.ccp-hint {
  font-size: 13px;
  color: #6b7280;
  line-height: 1.5;
  margin: 0 0 16px 0;
  code {
    font-size: 12px;
    background: #f3f4f6;
    padding: 2px 6px;
    border-radius: 4px;
  }
}
.ccp-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  margin-bottom: 16px;
}
.ccp-select {
  min-width: 280px;
}
.ccp-search {
  width: 240px;
  max-width: 100%;
}
.ccp-table {
  width: 100%;
}
.pager-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
</style>
