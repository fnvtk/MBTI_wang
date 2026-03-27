<template>
  <div class="tab-content" v-loading="miniprogramLoading">
    <div class="content-header">
      <h3>小程序配置</h3>
      <p class="content-description">配置小程序名称及展示文案，将显示在小程序导航栏等位置</p>
    </div>
    <div class="form-section">
      <div class="form-item">
        <label>小程序名称</label>
        <el-input
          v-model="miniprogramConfig.miniprogramName"
          placeholder="用于小程序导航栏等展示"
          class="w-full"
        />
      </div>
      <div class="text-config-section">
        <div class="section-label">小程序文案配置</div>
        <p class="section-desc">以下文案将显示在小程序对应位置，留空则使用默认值</p>
        <div class="form-grid">
          <div class="form-item">
            <label>分析中提示</label>
            <el-input v-model="miniprogramConfig.textConfig.analyzingTitle" placeholder="默认：正在分析中" class="w-full" />
          </div>
          <div class="form-item">
            <label>开始按钮（个人版）</label>
            <el-input v-model="miniprogramConfig.textConfig.startButtonText" placeholder="默认：开始面相测试" class="w-full" />
          </div>
          <div class="form-item">
            <label>开始按钮（企业版）</label>
            <el-input v-model="miniprogramConfig.textConfig.startButtonEnterprise" placeholder="默认：开始面部测试" class="w-full" />
          </div>
          <div class="form-item">
            <label>报告页标题</label>
            <el-input v-model="miniprogramConfig.textConfig.reportTitle" placeholder="默认：分析报告" class="w-full" />
          </div>
          <div class="form-item">
            <label>智能分析文案</label>
            <el-input v-model="miniprogramConfig.textConfig.aiAnalysisText" placeholder="默认：智能分析" class="w-full" />
          </div>
        </div>
      </div>
    </div>
    <div class="save-actions">
      <el-button type="primary" color="#7c3aed" class="save-btn" @click="saveMiniprogramConfig" :loading="miniprogramLoading">
        保存小程序配置
      </el-button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { request } from '@/utils/request'

const miniprogramLoading = ref(false)
const miniprogramConfig = reactive({
  miniprogramName: '神仙团队AI性格测试',
  textConfig: {
    analyzingTitle: '正在分析中',
    startButtonText: '开始面相测试',
    startButtonEnterprise: '开始面部测试',
    reportTitle: '分析报告',
    aiAnalysisText: '智能分析'
  }
})

const loadMiniprogramConfig = async () => {
  miniprogramLoading.value = true
  try {
    const res: any = await request.get('/admin/settings/miniprogram')
    if (res.code === 200 && res.data) {
      miniprogramConfig.miniprogramName = res.data.miniprogramName ?? '神仙团队AI性格测试'
      if (res.data.textConfig && typeof res.data.textConfig === 'object') {
        Object.assign(miniprogramConfig.textConfig, res.data.textConfig)
      }
    }
  } catch (e) {
    console.error('加载小程序配置失败:', e)
  } finally {
    miniprogramLoading.value = false
  }
}

const saveMiniprogramConfig = async () => {
  if (!miniprogramConfig.miniprogramName?.trim()) {
    ElMessage.error('小程序名称不能为空')
    return
  }
  miniprogramLoading.value = true
  try {
    const res: any = await request.put('/admin/settings/miniprogram', {
      miniprogramName: miniprogramConfig.miniprogramName.trim(),
      textConfig: miniprogramConfig.textConfig
    })
    if (res.code === 200) {
      ElMessage.success('小程序配置已保存')
    } else {
      ElMessage.error(res.msg || '保存失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '保存失败')
  } finally {
    miniprogramLoading.value = false
  }
}

onMounted(() => {
  loadMiniprogramConfig()
})

defineExpose({ loadMiniprogramConfig })
</script>

<style scoped lang="scss">
.content-header {
  margin-bottom: 32px;

  h3 {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 8px 0;
  }

  .content-description {
    font-size: 13px;
    color: #6b7280;
    margin: 0;
    line-height: 1.5;
  }
}

.form-section {
  display: flex;
  flex-direction: column;
  gap: 24px;
  margin-bottom: 32px;

  .form-item {
    display: flex;
    flex-direction: column;
    gap: 8px;

    label {
      font-size: 13px;
      font-weight: 600;
      color: #374151;
    }

    :deep(.el-input .el-input__wrapper) {
      border-radius: 8px;
      box-shadow: 0 0 0 1px #e5e7eb inset;
    }
  }
}

.text-config-section {
  margin-top: 24px;
  padding-top: 24px;
  border-top: 1px solid #e5e7eb;

  .section-label {
    font-size: 15px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 4px;
  }

  .section-desc {
    font-size: 13px;
    color: #6b7280;
    margin: 0 0 16px 0;
  }
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24px;
}

.save-actions {
  padding-top: 24px;
  border-top: 1px solid #f3f4f6;

  .save-btn {
    height: 42px;
    padding: 0 32px;
    border-radius: 8px;
    font-weight: 600;
  }
}

.w-full {
  width: 100%;
}

@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
