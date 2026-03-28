<template>
  <div class="page-container" :class="{ 'is-embedded': embedded }">
    <div class="page-header" :class="{ 'header-embedded': embedded }">
      <div v-if="!embedded" class="header-left">
        <h2>数据库管理</h2>
        <p class="subtitle">管理数据库连接、备份和恢复</p>
      </div>
      <div class="header-actions">
        <el-button type="primary" color="#ef4444" @click="handleBackup">
          <el-icon class="mr-1"><DocumentCopy /></el-icon>备份数据库
        </el-button>
      </div>
    </div>

    <!-- 数据库信息 -->
    <div class="info-grid">
      <div class="info-card">
        <div class="info-label">数据库类型</div>
        <div class="info-value">{{ dbInfo.databaseType }}</div>
      </div>
      <div class="info-card">
        <div class="info-label">连接状态</div>
        <div class="info-value">
          <el-tag :type="dbInfo.connected ? 'success' : 'danger'" size="small">
            {{ dbInfo.connected ? '已连接' : '未连接' }}
          </el-tag>
        </div>
      </div>
      <div class="info-card">
        <div class="info-label">数据库大小</div>
        <div class="info-value">{{ formatSize(dbInfo.databaseSize * 1024 * 1024) }}</div>
      </div>
      <div class="info-card">
        <div class="info-label">表数量</div>
        <div class="info-value">{{ dbInfo.tableCount }}</div>
      </div>
    </div>

    <!-- 集合列表 -->
    <div class="content-card">
      <div class="card-header">
        <h3>数据库集合</h3>
        <el-input
          v-model="searchTerm"
          placeholder="搜索集合名称..."
          clearable
          class="search-input"
          style="max-width: 300px;"
        >
          <template #prefix>
            <el-icon><Search /></el-icon>
          </template>
        </el-input>
      </div>

      <el-table 
        :data="filteredCollections" 
        style="width: 100%" 
        v-loading="loading" 
        class="custom-table"
        v-if="filteredCollections.length > 0 && !loading"
      >
        <el-table-column label="集合名称" min-width="200">
          <template #default="{ row }">
            <span class="collection-name">{{ row.name }}</span>
          </template>
        </el-table-column>

        <el-table-column label="文档数量" width="120" align="right">
          <template #default="{ row }">
            <span class="doc-count">{{ row.docCount.toLocaleString() }}</span>
          </template>
        </el-table-column>

        <el-table-column label="大小" width="120" align="right">
          <template #default="{ row }">
            <span class="size">{{ formatSize(row.size) }}</span>
          </template>
        </el-table-column>

        <el-table-column label="索引数" width="100" align="right">
          <template #default="{ row }">
            <span class="index-count">{{ row.indexCount }}</span>
          </template>
        </el-table-column>

        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <div class="action-buttons">
              <el-button link @click="handleView(row)"><el-icon><View /></el-icon></el-button>
              <el-button link @click="handleExport(row)"><el-icon><Download /></el-icon></el-button>
              <el-button link type="danger" @click="handleClear(row)"><el-icon><Delete /></el-icon></el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>
      <!-- 空数据占位图 -->
      <div v-else-if="!loading" class="empty-placeholder">
        <el-icon class="empty-icon"><DocumentCopy /></el-icon>
        <p class="empty-text">暂无数据表</p>
      </div>
    </div>

    <!-- 备份记录 -->
    <div class="content-card">
      <div class="card-header">
        <h3>备份记录</h3>
      </div>

      <el-table 
        :data="backups" 
        style="width: 100%" 
        class="custom-table"
        v-if="backups.length > 0"
      >
        <el-table-column label="备份时间" width="180">
          <template #default="{ row }">
            <span class="backup-time">{{ formatTime(row.time) }}</span>
          </template>
        </el-table-column>

        <el-table-column label="备份大小" width="120" align="right">
          <template #default="{ row }">
            <span class="backup-size">{{ formatSize(row.size) }}</span>
          </template>
        </el-table-column>

        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 'success' ? 'success' : 'danger'" size="small">
              {{ row.status === 'success' ? '成功' : '失败' }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <div class="action-buttons">
              <el-button link @click="handleRestore(row)"><el-icon><RefreshLeft /></el-icon>恢复</el-button>
              <el-button link @click="handleDownload(row)"><el-icon><Download /></el-icon>下载</el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { DocumentCopy, Search, View, Download, Delete, RefreshLeft } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { request } from '@/utils/request'

withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false })

const loading = ref(false)
const searchTerm = ref('')
const dbInfo = ref({
  databaseType: 'MySQL',
  databaseName: '',
  connected: false,
  databaseSize: 0,
  tableCount: 0
})

const collections = ref([])
const backups = ref([])

const filteredCollections = computed(() => {
  if (!searchTerm.value) {
    return collections.value
  }
  return collections.value.filter((item: any) => 
    item.name.toLowerCase().includes(searchTerm.value.toLowerCase())
  )
})

const formatSize = (bytes: number) => {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(2) + ' MB'
}

const formatTime = (time: string) => {
  const date = new Date(time)
  return `${date.getFullYear()}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getDate().toString().padStart(2, '0')} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`
}

// 加载数据库信息
const loadDbInfo = async () => {
  try {
    const response: any = await request.get('/superadmin/database/info')
    if (response.code === 200 && response.data) {
      dbInfo.value = response.data
    }
  } catch (error: any) {
    console.error('加载数据库信息失败:', error)
  }
}

// 加载表列表
const loadTables = async () => {
  loading.value = true
  try {
    const response: any = await request.get('/superadmin/database/tables')
    if (response.code === 200 && response.data) {
      collections.value = response.data
    }
  } catch (error: any) {
    ElMessage.error(error.message || '加载表列表失败')
  } finally {
    loading.value = false
  }
}

// 加载备份记录
const loadBackups = async () => {
  try {
    const response: any = await request.get('/superadmin/database/backups')
    if (response.code === 200 && response.data) {
      backups.value = response.data
    }
  } catch (error: any) {
    console.error('加载备份记录失败:', error)
  }
}

const handleBackup = async () => {
  try {
    await ElMessageBox.confirm('确定要备份数据库吗？', '备份数据库', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'info'
    })
    
    loading.value = true
    const response: any = await request.post('/superadmin/database/backup')
    
    if (response.code === 200) {
      ElMessage.success('数据库备份成功')
      await loadBackups()
    }
  } catch (error: any) {
    if (error !== 'cancel') {
      ElMessage.error(error.message || '备份失败')
    }
  } finally {
    loading.value = false
  }
}

const handleView = async (row: any) => {
  ElMessage.info('查看表: ' + row.name + ' (功能开发中)')
}

const handleExport = async (row: any) => {
  try {
    loading.value = true
    const response: any = await request.post('/superadmin/database/export-table', {
      table: row.name
    })
    
    if (response.code === 200 && response.data) {
      // 下载文件
      window.open(response.data.downloadUrl, '_blank')
      ElMessage.success('导出成功')
    }
  } catch (error: any) {
    ElMessage.error(error.message || '导出失败')
  } finally {
    loading.value = false
  }
}

const handleClear = async (row: any) => {
  try {
    await ElMessageBox.confirm(`确定要清空表 "${row.name}" 吗？此操作不可恢复！`, '警告', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    
    loading.value = true
    const response: any = await request.post('/superadmin/database/clear-table', {
      table: row.name
    })
    
    if (response.code === 200) {
      ElMessage.success('表数据已清空')
      await loadTables()
      await loadDbInfo()
    }
  } catch (error: any) {
    if (error !== 'cancel') {
      ElMessage.error(error.message || '清空失败')
    }
  } finally {
    loading.value = false
  }
}

const handleRestore = async (row: any) => {
  try {
    await ElMessageBox.confirm('确定要恢复此备份吗？当前数据将被覆盖！', '警告', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    
    loading.value = true
    const response: any = await request.post('/superadmin/database/restore', {
      file: row.filename
    })
    
    if (response.code === 200) {
      ElMessage.success('数据库恢复成功')
      await loadTables()
      await loadDbInfo()
    }
  } catch (error: any) {
    if (error !== 'cancel') {
      ElMessage.error(error.message || '恢复失败')
    }
  } finally {
    loading.value = false
  }
}

const handleDownload = (row: any) => {
  window.open(`/api/v1/superadmin/database/download?file=${encodeURIComponent(row.filename)}`, '_blank')
}

onMounted(() => {
  loadDbInfo()
  loadTables()
  loadBackups()
})
</script>

<style scoped lang="scss">
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

.info-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

.info-card {
  background: #fff;
  border-radius: 10px;
  padding: 20px;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);

  .info-label {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 8px;
  }

  .info-value {
    font-size: 18px;
    font-weight: 600;
    color: #111827;
  }
}

.content-card {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  overflow: hidden;
  margin-bottom: 24px;

  .card-header {
    padding: 20px;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: space-between;

    h3 {
      font-size: 18px;
      font-weight: 700;
      color: #111827;
      margin: 0;
    }
  }
}

.custom-table {
  :deep(.el-table__header) {
    th {
      background-color: #f9fafb;
      color: #6b7280;
      font-weight: 500;
      font-size: 13px;
      padding: 12px 0;
    }
  }

  .collection-name, .doc-count, .size, .index-count, .backup-time, .backup-size {
    font-size: 13px;
    color: #374151;
  }

  .action-buttons {
    display: flex;
    gap: 4px;
    
    .el-button {
      padding: 4px;
      font-size: 16px;
      color: #6b7280;
      
      &:hover {
        color: #ef4444;
      }
      
      &.el-button--danger:hover {
        color: #ef4444;
      }
    }
  }
}

.mr-1 {
  margin-right: 4px;
}

.empty-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  color: #9ca3af;

  .empty-icon {
    font-size: 64px;
    color: #d1d5db;
    margin-bottom: 16px;
  }

  .empty-text {
    font-size: 14px;
    color: #9ca3af;
    margin: 0;
  }
}

@media (max-width: 1200px) {
  .info-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

.page-container.is-embedded {
  min-height: auto;
}

.page-header.header-embedded {
  justify-content: flex-end;
}
</style>
