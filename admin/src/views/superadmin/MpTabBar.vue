<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h2>小程序底部菜单</h2>
        <p class="subtitle">
          拖拽排序、开关显隐、修改文案/图标，保存后小程序 60 秒内自动生效。
          至少保留 2 个可见 Tab，最多 5 个。
        </p>
      </div>
      <div class="header-actions">
        <el-button @click="fetchList">
          <el-icon><Refresh /></el-icon>刷新
        </el-button>
        <el-button type="primary" @click="onSave" :loading="saving">
          <el-icon><Check /></el-icon>保存
        </el-button>
      </div>
    </div>

    <el-card shadow="hover">
      <template #header>
        <div class="card-header">
          <span>Tab 列表（拖拽行首"☰"排序）</span>
          <el-button size="small" @click="onAdd" :disabled="list.length >= 5">
            <el-icon><Plus /></el-icon>新增 Tab
          </el-button>
        </div>
      </template>

      <el-table :data="list" row-key="tempId" border stripe>
        <el-table-column width="60" align="center" label="排序">
          <template #default="{ row, $index }">
            <div class="drag-handle">
              <el-icon><Operation /></el-icon>
              <span style="margin-left: 4px">{{ $index + 1 }}</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="SVG图标" width="110">
          <template #default="{ row }">
            <el-select v-model="row.iconKey" size="small" style="width: 100%">
              <el-option label="首页" value="home" />
              <el-option label="拍摄" value="camera" />
              <el-option label="AI" value="ai" />
              <el-option label="我" value="profile" />
            </el-select>
          </template>
        </el-table-column>

        <el-table-column label="图片URL（优先）" min-width="220">
          <template #default="{ row }">
            <el-input
              v-model="row.iconUrl"
              size="small"
              placeholder="/images/xxx.png 或 https://..."
              clearable
            >
              <template #append v-if="row.iconUrl">
                <img :src="row.iconUrl" style="width: 22px; height: 22px; border-radius: 4px;" />
              </template>
            </el-input>
          </template>
        </el-table-column>

        <el-table-column label="文案" width="140">
          <template #default="{ row }">
            <el-input v-model="row.text" size="small" maxlength="6" show-word-limit />
          </template>
        </el-table-column>

        <el-table-column label="页面路径" min-width="240">
          <template #default="{ row }">
            <el-input v-model="row.pagePath" size="small" placeholder="pages/xxx/xxx">
              <template #prepend>/</template>
            </el-input>
          </template>
        </el-table-column>

        <el-table-column label="中间浮钮" width="110" align="center">
          <template #default="{ row }">
            <el-switch v-model="row.highlight" :active-value="1" :inactive-value="0" />
          </template>
        </el-table-column>

        <el-table-column label="显示" width="90" align="center">
          <template #default="{ row }">
            <el-switch v-model="row.visible" :active-value="1" :inactive-value="0" />
          </template>
        </el-table-column>

        <el-table-column label="操作" width="150" align="center">
          <template #default="{ row, $index }">
            <el-button size="small" link @click="onMove($index, -1)" :disabled="$index === 0">↑</el-button>
            <el-button size="small" link @click="onMove($index, 1)" :disabled="$index === list.length - 1">↓</el-button>
            <el-button size="small" type="danger" link @click="onRemove($index)" :disabled="list.length <= 2">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="preview">
        <div class="preview-title">📱 小程序底部预览</div>
        <div class="mp-bar">
          <template v-for="(it, idx) in visibleItems" :key="idx">
            <div v-if="it.highlight" class="mp-item mp-item--fab">
              <div class="mp-fab" :class="'mp-icon--' + (it.iconKey || 'home')"></div>
              <div class="mp-text">{{ it.text }}</div>
            </div>
            <div v-else class="mp-item">
              <div class="mp-icon" :class="'mp-icon--' + (it.iconKey || 'home')"></div>
              <div class="mp-text">{{ it.text }}</div>
            </div>
          </template>
        </div>
        <div class="preview-hint">仅为示意，实际样式以小程序端为准。</div>
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Check, Plus, Operation } from '@element-plus/icons-vue'
import { request } from '@/utils/request'

interface TabItem {
  id?: number
  tempId?: string
  sortOrder: number
  pagePath: string
  text: string
  iconKey: string
  iconUrl?: string | null
  visible: number
  highlight: number
  badgeKey?: string | null
}

const list = ref<TabItem[]>([])
const saving = ref(false)

const visibleItems = computed(() => list.value.filter(x => x.visible === 1))

const tmpId = () => 't' + Date.now() + '-' + Math.floor(Math.random() * 1000)

const fetchList = async () => {
  try {
    const res: any = await request.get('/superadmin/tabbar')
    if (res && res.code === 200) {
      const raw = (res.data && res.data.list) || []
      list.value = raw.map((r: any) => ({
        id: r.id,
        tempId: 'db-' + r.id,
        sortOrder: r.sortOrder,
        pagePath: r.pagePath,
        text: r.text,
        iconKey: r.iconKey || 'home',
        iconUrl: r.iconUrl || '',
        visible: Number(r.visible ?? 1),
        highlight: Number(r.highlight ?? 0),
        badgeKey: r.badgeKey || null,
      }))
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '加载失败')
  }
}

const onAdd = () => {
  if (list.value.length >= 5) return
  list.value.push({
    tempId: tmpId(),
    sortOrder: (list.value.length + 1) * 10,
    pagePath: 'pages/',
    text: '新菜单',
    iconKey: 'home',
    iconUrl: '',
    visible: 1,
    highlight: 0,
  })
}

const onRemove = async (idx: number) => {
  const it = list.value[idx]
  const r = await ElMessageBox.confirm(`删除「${it.text}」？`, '确认', { type: 'warning' }).catch(() => 'cancel')
  if (r === 'cancel') return
  list.value.splice(idx, 1)
}

const onMove = (idx: number, dir: -1 | 1) => {
  const target = idx + dir
  if (target < 0 || target >= list.value.length) return
  const arr = list.value.slice()
  const [moved] = arr.splice(idx, 1)
  arr.splice(target, 0, moved)
  list.value = arr
}

const onSave = async () => {
  if (list.value.length < 2) return ElMessage.warning('至少 2 个 Tab')
  if (list.value.length > 5) return ElMessage.warning('最多 5 个 Tab')
  const v = list.value.filter(x => x.visible === 1).length
  if (v < 2) return ElMessage.warning('至少 2 个可见 Tab')

  for (const [idx, it] of list.value.entries()) {
    if (!it.text || !it.text.trim()) return ElMessage.warning(`第 ${idx + 1} 行文案不能为空`)
    if (!it.pagePath || !it.pagePath.trim()) return ElMessage.warning(`第 ${idx + 1} 行页面路径不能为空`)
    it.sortOrder = (idx + 1) * 10
    it.pagePath = it.pagePath.replace(/^\//, '')
  }

  saving.value = true
  try {
    const res: any = await request.post('/superadmin/tabbar/save', {
      items: list.value.map(it => ({
        id: it.id,
        sortOrder: it.sortOrder,
        pagePath: it.pagePath,
        text: it.text,
        iconKey: it.iconKey,
        iconUrl: it.iconUrl,
        visible: it.visible,
        highlight: it.highlight,
        badgeKey: it.badgeKey,
      }))
    })
    if (res && res.code === 200) {
      ElMessage.success(res.message || '保存成功')
      fetchList()
    } else {
      ElMessage.error((res && res.message) || '保存失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '保存失败')
  } finally {
    saving.value = false
  }
}

onMounted(fetchList)
</script>

<style scoped>
.page-container { padding: 24px; }
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
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.drag-handle {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #9CA3AF;
}

.preview {
  margin-top: 24px;
  border: 1px dashed #E5E7EB;
  padding: 16px;
  border-radius: 12px;
  background: #FAFAFA;
}
.preview-title {
  font-size: 13px;
  color: #6B6894;
  margin-bottom: 12px;
}
.mp-bar {
  display: flex;
  align-items: center;
  justify-content: space-around;
  background: #fff;
  border-radius: 14px;
  padding: 10px 16px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}
.mp-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  flex: 1;
}
.mp-item--fab {
  flex: 1;
  position: relative;
  transform: translateY(-10px);
}
.mp-icon {
  width: 24px;
  height: 24px;
  background-size: 22px 22px;
  background-repeat: no-repeat;
  background-position: center;
}
.mp-fab {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%);
  box-shadow: 0 4px 12px rgba(124,58,237,0.4);
  background-size: 24px 24px;
  background-repeat: no-repeat;
  background-position: center;
  display: flex;
  align-items: center;
  justify-content: center;
}
.mp-text {
  font-size: 11px;
  color: #6B6894;
}
.mp-icon--home   { background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236B6894' stroke-width='2' stroke-linejoin='round'><path d='M3 12l9-9 9 9M5 10v10h14V10'/></svg>"); }
.mp-icon--camera { background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236B6894' stroke-width='2' stroke-linejoin='round'><path d='M4 7h3l2-3h6l2 3h3v13H4z'/><circle cx='12' cy='13' r='4'/></svg>"); }
.mp-icon--ai     { background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236B6894' stroke-width='2' stroke-linecap='round'><path d='M12 3v3M12 18v3M3 12h3M18 12h3'/><circle cx='12' cy='12' r='3'/></svg>"); }
.mp-icon--profile{ background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236B6894' stroke-width='2' stroke-linejoin='round'><circle cx='12' cy='8' r='4'/><path d='M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8'/></svg>"); }
.mp-fab.mp-icon--camera,
.mp-fab.mp-icon--ai,
.mp-fab.mp-icon--home,
.mp-fab.mp-icon--profile {
  background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2' stroke-linejoin='round'><path d='M4 7h3l2-3h6l2 3h3v13H4z'/><circle cx='12' cy='13' r='4'/></svg>"),
    linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%);
}
.preview-hint {
  margin-top: 8px;
  font-size: 12px;
  color: #9CA3AF;
}
</style>
