<template>
  <div class="top-panel" v-loading="loading">
    <div class="top-head">
      <div>
        <h3 class="top-title">测评 Top 20</h3>
        <p class="top-desc">按完成次数排序 · 本企业数据</p>
      </div>
      <el-button type="primary" link size="small" @click="load">刷新</el-button>
    </div>
    <el-table v-if="rows.length" :data="rows" size="small" stripe class="top-table">
      <el-table-column label="#" width="44">
        <template #default="{ $index }">{{ $index + 1 }}</template>
      </el-table-column>
      <el-table-column label="用户" min-width="100" show-overflow-tooltip>
        <template #default="{ row }">{{ row.username || '未命名' }}</template>
      </el-table-column>
      <el-table-column prop="testCount" label="次数" width="56" align="center" />
      <el-table-column label="测评摘要" min-width="120" show-overflow-tooltip>
        <template #default="{ row }">{{ summarizeTypes(row) }}</template>
      </el-table-column>
    </el-table>
    <div v-else-if="!loading" class="top-empty">暂无测试记录</div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { request } from '@/utils/request'

interface TopUserRow {
  id: number
  username: string
  testCount: number
  mbtiType: string
  pdpType: string
  discType: string
  faceMbtiType: string
  faceDiscType: string
  facePdpType: string
}

const loading = ref(false)
const rows = ref<TopUserRow[]>([])

function summarizeTypes(row: TopUserRow) {
  const parts: string[] = []
  if (row.mbtiType) parts.push(row.mbtiType)
  if (row.pdpType) parts.push(row.pdpType)
  if (row.discType) parts.push(row.discType)
  const faceBits = [row.faceMbtiType, row.facePdpType, row.faceDiscType].filter(Boolean)
  if (faceBits.length) parts.push('面:' + faceBits.join('/'))
  return parts.length ? parts.join(' · ') : '—'
}

async function load() {
  loading.value = true
  try {
    const res: any = await request.get('/admin/dashboard')
    const list = res?.data?.topTestUsers
    rows.value = Array.isArray(list) ? list : []
  } catch {
    rows.value = []
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped lang="scss">
.top-panel {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #f3f4f6;
  padding: 20px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
.top-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}
.top-title {
  margin: 0 0 4px;
  font-size: 17px;
  font-weight: 700;
  color: #111827;
}
.top-desc {
  margin: 0;
  font-size: 12px;
  color: #6b7280;
  line-height: 1.45;
}
.top-empty {
  padding: 28px;
  text-align: center;
  color: #9ca3af;
  font-size: 13px;
}
.top-table {
  width: 100%;
}
</style>
