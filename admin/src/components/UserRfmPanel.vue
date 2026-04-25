<template>
  <div class="rfm-panel">
    <div class="rfm-head">
      <div>
        <h3 class="rfm-title">RFM 价值分层</h3>
        <p class="rfm-desc">
          R 最近付费 · F 付费频次 · M 累计金额 · 合并推荐/轨迹/资料，按档位 S/A/B/C/D 排序
          <span class="rfm-beta">前端占位版 · 待后端接口上线</span>
        </p>
      </div>
      <el-button type="primary" link size="small" :icon="Refresh" @click="refresh">刷新</el-button>
    </div>

    <div class="rfm-kpi-row">
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

    <div class="rfm-hint">
      <el-icon><InfoFilled /></el-icon>
      <span>
        数据为示例，后端
        <code>GET /api/admin/users/rfm</code>
        上线后自动替换；口径参考 Soul 永平仓库
        <em>算法-RFM用户价值分层.md</em>，mbti 版本在列表与本排行都采用同一套档位规则，避免「列表与排行分不一致」。
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Refresh, InfoFilled } from '@element-plus/icons-vue'
// import { request } from '@/utils/request'

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

const mockRows: RfmRow[] = [
  { id: 1, nickname: '徐先生',   phone: '138****2103', r: 92, f: 88, m: 648.00, score: 89, level: 'S' },
  { id: 2, nickname: '卡若夏',   phone: '139****8812', r: 86, f: 72, m: 366.00, score: 78, level: 'A' },
  { id: 3, nickname: 'Anita',    phone: '186****0091', r: 68, f: 40, m: 188.50, score: 65, level: 'B' },
  { id: 4, nickname: '小陈同学', phone: '131****6602', r: 55, f: 28, m: 92.00,  score: 51, level: 'B' },
  { id: 5, nickname: '林可可',   phone: '159****7730', r: 42, f: 12, m: 36.80,  score: 36, level: 'C' },
  { id: 6, nickname: '周周',     phone: '185****4461', r: 20, f: 4,  m: 9.90,   score: 22, level: 'D' }
]

const rows = ref<RfmRow[]>(mockRows)

const buckets = computed(() => {
  const levels: RfmRow['level'][] = ['S', 'A', 'B', 'C', 'D']
  const labels: Record<RfmRow['level'], string> = {
    S: '忠实高价值',
    A: '重点维护',
    B: '潜力用户',
    C: '普通用户',
    D: '流失风险'
  }
  return levels.map((lv) => ({
    level: lv,
    label: labels[lv],
    count: rows.value.filter((r) => r.level === lv).length
  }))
})

async function refresh() {
  // TODO: 后端上线后替换为：
  // const res: any = await request.get('/admin/users/rfm', { params: { limit: 20 } })
  // rows.value = res.data?.list || []
  rows.value = [...mockRows]
}
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

.rfm-beta {
  display: inline-block;
  margin-left: 8px;
  padding: 1px 8px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 600;
  color: #b45309;
  background: #fffbeb;
  border: 1px solid #fde68a;
  vertical-align: middle;
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

.rfm-hint {
  margin-top: 18px;
  padding: 10px 12px;
  background: #f8fafc;
  border: 1px dashed #e2e8f0;
  border-radius: 8px;
  color: #64748b;
  font-size: 12px;
  line-height: 1.55;
  display: flex;
  align-items: flex-start;
  gap: 8px;

  code {
    background: #eef2ff;
    color: #4f46e5;
    padding: 1px 6px;
    border-radius: 4px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
    font-size: 11.5px;
  }

  em {
    font-style: normal;
    color: #334155;
    font-weight: 500;
  }
}

@media (max-width: 900px) {
  .rfm-kpi-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
