<template>
  <div class="journey-panel">
    <div class="journey-head">
      <div>
        <h3 class="journey-title">用户旅程漏斗</h3>
        <p class="journey-desc">
          注册 → 绑手机 → 完成测评 → 手机号登录看报告 → 分享 → 付费解锁 → 复测
          <span class="journey-beta">前端占位版 · 待后端接口上线</span>
        </p>
      </div>
      <el-button type="primary" link size="small" :icon="Refresh" @click="refresh">刷新</el-button>
    </div>

    <div class="funnel-wrap">
      <div
        v-for="(stage, idx) in stages"
        :key="stage.key"
        class="funnel-row"
        :style="{ animationDelay: `${idx * 40}ms` }"
      >
        <div class="funnel-meta">
          <span class="funnel-idx">{{ idx + 1 }}</span>
          <div class="funnel-meta-body">
            <span class="funnel-label">{{ stage.label }}</span>
            <span class="funnel-note">{{ stage.note }}</span>
          </div>
        </div>
        <div class="funnel-bar-track">
          <div class="funnel-bar-fill" :style="{ width: barWidth(stage.count) }"></div>
        </div>
        <div class="funnel-num">
          <span class="funnel-count">{{ stage.count }}</span>
          <span class="funnel-percent" v-if="idx > 0 && stages[0].count > 0">
            {{ ((stage.count / stages[0].count) * 100).toFixed(1) }}%
          </span>
        </div>
      </div>
    </div>

    <div class="journey-hint">
      <el-icon><InfoFilled /></el-icon>
      <span>
        这里先以前端 mock 展示漏斗形态；后端接口
        <code>GET /api/admin/users/journey-stats</code> 上线后自动切真数据（参考 <em>开发文档/1、需求/修改/用户运营参考Soul规则.md</em>）。
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Refresh, InfoFilled } from '@element-plus/icons-vue'
// import { request } from '@/utils/request'

/**
 * 旅程阶段（与 Soul 永平仓库的 journey-stats 字段对齐，按 mbti 业务裁剪）：
 * - register:     注册
 * - bind_phone:   授权手机号
 * - tested:       完成任意测评
 * - viewed_full:  通过手机号登录看到完整报告
 * - shared:       发起过分享
 * - paid:         付费解锁
 * - repeat:       复测（>=2 次）
 */
interface StageRow {
  key: string
  label: string
  note: string
  count: number
}

const mock = (): StageRow[] => [
  { key: 'register',     label: '注册',             note: '进入小程序并静默登录',      count: 1280 },
  { key: 'bind_phone',   label: '授权手机号',       note: 'getPhoneNumber 成功',       count: 842 },
  { key: 'tested',       label: '完成任意测评',     note: 'MBTI / DISC / PDP / SBTI',  count: 716 },
  { key: 'viewed_full',  label: '看完整报告',       note: '结果页手机号登录解锁',      count: 498 },
  { key: 'shared',       label: '发起分享',         note: '好友 / 朋友圈',             count: 263 },
  { key: 'paid',         label: '付费解锁',         note: '人脸或其他收费项',          count: 134 },
  { key: 'repeat',       label: '复测',             note: '>= 2 次完成记录',           count: 86 }
]

const stages = ref<StageRow[]>(mock())

const topCount = computed(() => Math.max(1, ...stages.value.map(s => s.count)))

function barWidth(count: number) {
  return `${Math.round((count / topCount.value) * 100)}%`
}

async function refresh() {
  // TODO: 后端上线后替换为：
  // const res: any = await request.get('/admin/users/journey-stats')
  // stages.value = mapResponseToStages(res.data)
  stages.value = mock()
}
</script>

<style scoped lang="scss">
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(6px); }
  to   { opacity: 1; transform: translateY(0); }
}

.journey-panel {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  padding: 20px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.03);
}

.journey-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.journey-title {
  margin: 0 0 4px;
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
}

.journey-desc {
  margin: 0;
  color: #64748b;
  font-size: 12.5px;
  line-height: 1.55;
}

.journey-beta {
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

.funnel-wrap {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 8px 0 6px;
}

.funnel-row {
  display: grid;
  grid-template-columns: minmax(200px, 230px) minmax(0, 1fr) minmax(86px, 92px);
  gap: 14px;
  align-items: center;
  animation: fadeInUp 0.3s ease-out both;
}

.funnel-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.funnel-idx {
  flex-shrink: 0;
  width: 22px;
  height: 22px;
  border-radius: 999px;
  background: #eef2ff;
  color: #4f46e5;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
}

.funnel-meta-body {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.funnel-label {
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
  line-height: 1.2;
}

.funnel-note {
  font-size: 11.5px;
  color: #94a3b8;
  margin-top: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.funnel-bar-track {
  height: 12px;
  background: #f1f5f9;
  border-radius: 6px;
  overflow: hidden;
}

.funnel-bar-fill {
  height: 100%;
  border-radius: 6px;
  background: linear-gradient(90deg, #4f46e5 0%, #6366f1 100%);
  transition: width 0.45s ease;
}

.funnel-num {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}

.funnel-count {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  font-variant-numeric: tabular-nums;
  line-height: 1;
}

.funnel-percent {
  font-size: 11.5px;
  color: #10b981;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.journey-hint {
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
  .funnel-row {
    grid-template-columns: minmax(140px, 160px) minmax(0, 1fr) minmax(70px, 76px);
    gap: 10px;
  }

  .funnel-note { display: none; }
}
</style>
