<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h2>合作意向</h2>
        <p class="subtitle">用户在企业版完成三项测评并选择合作模式后的记录；可导出为 CSV 用 Excel 打开</p>
      </div>
    </div>

    <el-alert
      v-if="!canUse"
      type="warning"
      :closable="false"
      show-icon
      title="当前账号未绑定企业，无数据范围"
    />

    <template v-else>
      <div class="toolbar">
        <el-input
          v-model="keyword"
          placeholder="搜索微信昵称、手机号、模式代码"
          clearable
          class="search-input"
          @keyup.enter="onSearch"
        />
        <el-button type="primary" @click="onSearch" :loading="loading">查询</el-button>
        <el-button :loading="exporting" @click="doExport">导出 CSV</el-button>
      </div>

      <el-table v-loading="loading" :data="list" border stripe class="data-table" empty-text="暂无选择记录">
        <el-table-column prop="userId" label="用户ID" width="88" />
        <el-table-column prop="nickname" label="微信昵称" min-width="120" show-overflow-tooltip />
        <el-table-column prop="phone" label="手机号" width="120" show-overflow-tooltip />
        <el-table-column prop="modeCode" label="模式代码" width="120" />
        <el-table-column prop="modeTitle" label="模式标题" min-width="120" show-overflow-tooltip />
        <el-table-column prop="chosenAtText" label="选择时间" width="170" />
        <el-table-column prop="updatedAtText" label="更新时间" width="170" />
      </el-table>

      <div class="pager-wrap" v-if="total > 0">
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
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { request } from '@/utils/request'
import { downloadCsvGet } from '@/utils/downloadCsv'
import { getAdminRole } from '@/utils/authStorage'

const canUse = computed(() => {
  const r = getAdminRole()
  return r === 'admin' || r === 'enterprise_admin'
})

const loading = ref(false)
const exporting = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const keyword = ref('')

const load = async () => {
  if (!canUse.value) return
  loading.value = true
  try {
    const res: any = await request.get('/admin/enterprise/cooperation-choices', {
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
  if (!canUse.value) return
  exporting.value = true
  try {
    const q: Record<string, string> = {}
    const k = keyword.value.trim()
    if (k) q.keyword = k
    await downloadCsvGet(
      'admin/enterprise/cooperation-choices/export',
      `cooperation-choices.csv`,
      q
    )
    ElMessage.success('已开始下载')
  } catch (e: any) {
    ElMessage.error(e?.message || '导出失败')
  } finally {
    exporting.value = false
  }
}

onMounted(() => {
  if (canUse.value) load()
})
</script>

<style scoped lang="scss">
.page-container {
  padding: 24px;
  min-height: calc(100vh - 64px);
}
.page-header {
  margin-bottom: 20px;
  .header-left h2 {
    margin: 0 0 4px 0;
    font-size: 22px;
    font-weight: 700;
    color: #111827;
  }
  .subtitle {
    margin: 0;
    font-size: 13px;
    color: #6b7280;
  }
}
.toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}
.search-input {
  width: 280px;
  max-width: 100%;
}
.data-table {
  width: 100%;
}
.pager-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
</style>
