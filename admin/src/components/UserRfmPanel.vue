<template>
  <div class="rfm-panel">
    <div class="rfm-head">
      <div>
        <h3 class="rfm-title">RFM 价值分层</h3>
        <p class="rfm-desc">R 最近付费 · F 付费频次 · M 累计金额 · 综合得分按 S/A/B/C/D 五档分层</p>
      </div>
      <div class="rfm-head-right">
        <span class="rfm-updated" v-if="lastUpdated">更新于 {{ lastUpdated }}</span>
        <el-button type="primary" link size="small" :icon="Refresh" @click="refresh" :loading="loading">刷新</el-button>
      </div>
    </div>

    <div v-if="loadError" class="rfm-error">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" style="color:#EF4444">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/>
        <line x1="12" y1="8" x2="12" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <line x1="12" y1="16" x2="12.01" y2="16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
      <span>{{ loadError }}</span>
      <el-button size="small" @click="refresh">重试</el-button>
    </div>

    <div class="rfm-kpi-row" v-loading="loading">
      <div v-for="bucket in buckets" :key="bucket.level" class="rfm-kpi" :class="`level-${bucket.level.toLowerCase()}`">
        <div class="rfm-kpi-level">{{ bucket.level }}</div>
        <div class="rfm-kpi-count">{{ bucket.count }}</div>
        <div class="rfm-kpi-label">{{ bucket.label }}</div>
      </div>
    </div>

    <div class="rfm-table-wrap">
      <el-table :data="rows" size="small" class="rfm-table">
        <el-table-column label="#" width="44">
          <template #default="{ $index }">{{ $index + 1 }}</template>
        </el-table-column>
        <el-table-column label="档位" width="72">
          <template #default="{ row }">
            <span class="rfm-level-tag" :class="`level-${row.level.toLowerCase()}`">{{ row.level }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="nickname" label="用户" min-width="120" show-overflow-tooltip />
        <el-table-column prop="phone" label="手机号" width="136" />
        <el-table-column label="R" width="64" align="center">
          <template #default="{ row }">{{ row.r }}</template>
        </el-table-column>
        <el-table-column label="F" width="64" align="center">
          <template #default="{ row }">{{ row.f }}</template>
        </el-table-column>
        <el-table-column label="M (¥)" width="88" align="right">
          <template #default="{ row }">{{ row.m.toFixed(2) }}</template>
        </el-table-column>
        <el-table-column label="综合分" width="96" align="center">
          <template #default="{ row }">
            <strong class="rfm-score">{{ row.score }}</strong>
          </template>
        </el-table-column>
      </el-table>
    </div>


  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import { request } from '@/utils/request'
import { ElMessage } from 'element-plus'

interface RfmRow {
  id: number
  nickname: string
  phone: string
  r: number
  f: number
  m: number
  score: number
  level: 'S' | 'A' | 'B' | 'C' | 'D'
}

const rows = ref<RfmRow[]>([])
const loading = ref(false)
const loadError = ref('')
const lastUpdated = ref('')

const buckets = computed(() => {
  const levels: RfmRow['level'][] = ['S', 'A', 'B', 'C', 'D']
  const labels: Record<RfmRow['level'], string> = {
    S: '忠实高价值', A: '重点维护', B: '潜力用户', C: '普通用户', D: '流失风险'
  }
  return levels.map((lv) => ({
    level: lv, label: labels[lv],
    count: rows.value.filter((r) => r.level === lv).length
  }))
})

// 前端 RFM 算法：根据后端返回原始数据计算 R/F/M 得分和档位
function computeRfmLevel(r: number, f: number, m: number): RfmRow['level'] {
  // R: 0-100 越大越好（最近付费），F: 频次归一化，M: 金额对数归一化
  const score = r * 0.35 + Math.min(f * 15, 100) * 0.25 + Math.min(Math.log(m + 1) * 12, 100) * 0.40
  if (score >= 75) return 'S'
  if (score >= 55) return 'A'
  if (score >= 38) return 'B'
  if (score >= 22) return 'C'
  return 'D'
}

async function refresh() {
  loading.value = true
  loadError.value = ''
  try {
    const res: any = await request.get('/admin/users/rfm', { params: { limit: 50 } })
    if (res.code === 200 && Array.isArray(res.data?.list)) {
      rows.value = res.data.list.map((u: any) => {
        const r = Number(u.rScore ?? u.r ?? 50)
        const f = Number(u.fScore ?? u.f ?? 1)
        const m = Number(u.mScore ?? u.totalPaid ?? u.m ?? 0)
        const score = Math.round(r * 0.35 + Math.min(f * 15, 100) * 0.25 + Math.min(Math.log(m + 1) * 12, 100) * 0.40)
        return {
          id: u.id,
          nickname: u.nickname || u.nickName || '用户' + u.id,
          phone: u.phone || u.mobile || '--',
          r, f, m, score,
          level: u.level || computeRfmLevel(r, f, m)
        }
      })
      const now = new Date()
      lastUpdated.value = `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`
    } else {
      loadError.value = res.message || '暂无 RFM 数据'
    }
  } catch (e: any) {
    loadError.value = e?.message || '接口暂未就绪'
    ElMessage.warning('RFM 接口暂未返回数据')
  } finally {
    loading.value = false
  }
}

onMounted(refresh)
</script>

<style scoped lang="scss">
.rfm-panel {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  padding: 20px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.03);
}

.rfm-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.rfm-title {
  margin: 0 0 4px;
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
}

.rfm-desc {
  margin: 0;
  color: #64748b;
  font-size: 12.5px;
  line-height: 1.55;
}

.rfm-head-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}
.rfm-updated {
  font-size: 11px;
  color: #9CA3AF;
  font-variant-numeric: tabular-nums;
}
.rfm-error {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #FEF2F2;
  border: 1px solid #FECACA;
  border-radius: 8px;
  padding: 10px 14px;
  margin-bottom: 14px;
  font-size: 13px;
  color: #EF4444;
}

.rfm-kpi-row {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 16px;
}

.rfm-kpi {
  padding: 12px 14px;
  border-radius: 10px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  gap: 4px;
  transition: all 0.18s;

  &:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
  }
}

.rfm-kpi-level {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: #64748b;
}

.rfm-kpi-count {
  font-size: 22px;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
}

.rfm-kpi-label {
  font-size: 11.5px;
  color: #94a3b8;
}

.rfm-kpi.level-s { background: #eef2ff; border-color: #c7d2fe; }
.rfm-kpi.level-s .rfm-kpi-level { color: #4f46e5; }
.rfm-kpi.level-a { background: #ecfdf5; border-color: #a7f3d0; }
.rfm-kpi.level-a .rfm-kpi-level { color: #10b981; }
.rfm-kpi.level-b { background: #e0f2fe; border-color: #bae6fd; }
.rfm-kpi.level-b .rfm-kpi-level { color: #0ea5e9; }
.rfm-kpi.level-c { background: #fffbeb; border-color: #fde68a; }
.rfm-kpi.level-c .rfm-kpi-level { color: #f59e0b; }
.rfm-kpi.level-d { background: #fef2f2; border-color: #fecaca; }
.rfm-kpi.level-d .rfm-kpi-level { color: #ef4444; }

.rfm-table-wrap {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
}

.rfm-level-tag {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: 0.04em;
}

.rfm-level-tag.level-s { background: #eef2ff; color: #4f46e5; }
.rfm-level-tag.level-a { background: #ecfdf5; color: #10b981; }
.rfm-level-tag.level-b { background: #e0f2fe; color: #0284c7; }
.rfm-level-tag.level-c { background: #fffbeb; color: #b45309; }
.rfm-level-tag.level-d { background: #fef2f2; color: #dc2626; }

.rfm-score {
  color: #4f46e5;
  font-variant-numeric: tabular-nums;
}



@media (max-width: 900px) {
  .rfm-kpi-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
