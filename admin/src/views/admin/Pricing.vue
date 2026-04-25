<template>
  <div class="page-container" :class="{ 'is-embedded': embedded }" v-loading="loading">
    <div v-if="!embedded" class="page-header">
      <div class="header-left">
        <h2>价格设置</h2>
        <p class="subtitle">分别配置个人版和企业版的测试价格</p>
      </div>
    </div>

    <!-- Tab 切换 -->
    <div class="custom-tabs-container">
      <div class="custom-tabs">
        <div
          v-for="tab in tabs"
          :key="tab.value"
          :class="['tab-item', activeTab === tab.value ? 'active' : '']"
          @click="activeTab = tab.value"
        >{{ tab.label }}</div>
      </div>
    </div>

    <div class="pricing-content">
      <div class="tab-content-card">

        <!-- 个人版价格 -->
        <div v-if="activeTab === 'personal'" class="tab-content">
          <div class="form-section">
            <div class="form-grid">
              <div class="form-item">
                <label>人脸测试价格 (元/次)</label>
                <el-input-number v-model="personal.face" :min="0" :precision="2" :controls="false" class="w-full" />
              </div>
              <div class="form-item">
                <label>MBTI测试价格 (元/次)</label>
                <el-input-number v-model="personal.mbti" :min="0" :precision="2" :controls="false" class="w-full" />
              </div>
              <div class="form-item">
                <label>DISC测试价格 (元/次)</label>
                <el-input-number v-model="personal.disc" :min="0" :precision="2" :controls="false" class="w-full" />
              </div>
              <div class="form-item">
                <label>PDP测试价格 (元/次)</label>
                <el-input-number v-model="personal.pdp" :min="0" :precision="2" :controls="false" class="w-full" />
              </div>
              <div class="form-item">
                <label>SBTI测试价格 (元/次)</label>
                <el-input-number v-model="personal.sbti" :min="0" :precision="2" :controls="false" class="w-full" />
              </div>
              <div class="form-item">
                <label>高考志愿报告 (元/次)</label>
                <el-input-number v-model="personal.gaokao" :min="0" :precision="2" :controls="false" class="w-full" />
              </div>
            </div>
            <div v-if="isUsingSuperAdminPersonalConfig" class="notice-box">
              <el-icon class="notice-icon"><InfoFilled /></el-icon>
              <span>当前使用超管默认定价，保存后将创建您的个人版专属配置</span>
            </div>
            <div class="save-actions">
              <el-button type="primary" class="save-btn" @click="savePersonal" :loading="loading">
                保存个人版价格
              </el-button>
            </div>
          </div>
        </div>

        <!-- 企业版价格 -->
        <div v-if="activeTab === 'enterprise'" class="tab-content">
          <div class="form-section">
            <div class="form-grid">
              <div class="form-item">
                <label>人脸测试价格 (元/次)</label>
                <el-input-number v-model="enterprise.face" :min="0" :precision="2" :controls="false" class="w-full" />
              </div>
              <div class="form-item">
                <label>MBTI测试价格 (元/次)</label>
                <el-input-number v-model="enterprise.mbti" :min="0" :precision="2" :controls="false" class="w-full" />
              </div>
              <div class="form-item">
                <label>DISC测试价格 (元/次)</label>
                <el-input-number v-model="enterprise.disc" :min="0" :precision="2" :controls="false" class="w-full" />
              </div>
              <div class="form-item">
                <label>PDP测试价格 (元/次)</label>
                <el-input-number v-model="enterprise.pdp" :min="0" :precision="2" :controls="false" class="w-full" />
              </div>
              <div class="form-item">
                <label>SBTI测试价格 (元/次)</label>
                <el-input-number v-model="enterprise.sbti" :min="0" :precision="2" :controls="false" class="w-full" />
              </div>
              <div class="form-item">
                <label>高考志愿报告 (元/次)</label>
                <el-input-number v-model="enterprise.gaokao" :min="0" :precision="2" :controls="false" class="w-full" />
              </div>
            </div>
            <div v-if="isUsingSuperAdminEnterpriseConfig" class="notice-box">
              <el-icon class="notice-icon"><InfoFilled /></el-icon>
              <span>当前使用超管默认企业定价，保存后将创建您的企业版专属配置</span>
            </div>
            <div class="save-actions">
              <el-button type="primary" class="save-btn" @click="saveEnterprise" :loading="loading">
                保存企业版价格
              </el-button>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { InfoFilled } from '@element-plus/icons-vue'
import { request } from '@/utils/request'

withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false })

const tabs = [
  { label: '个人版价格', value: 'personal' },
  { label: '企业版价格', value: 'enterprise' },
]
const activeTab = ref('personal')

const personal = reactive({ face: 0, mbti: 0, disc: 0, pdp: 0, sbti: 0, gaokao: 0 })
const enterprise = reactive({ face: 0, mbti: 0, disc: 0, pdp: 0, sbti: 0, gaokao: 0 })

const loading = ref(false)
const isUsingSuperAdminPersonalConfig = ref(false)
const isUsingSuperAdminEnterpriseConfig = ref(false)

const loadPricing = async () => {
  loading.value = true
  try {
    const response: any = await request.get('/admin/pricing')
    if (response.code === 200 && response.data) {
      if (response.data.personal) {
        Object.assign(personal, response.data.personal)
      }
      if (response.data.enterprise) {
        Object.assign(enterprise, response.data.enterprise)
      }
      isUsingSuperAdminPersonalConfig.value = response.data.isUsingSuperAdminPersonalConfig
        ?? response.data.isUsingSuperAdminConfig
        ?? false
      isUsingSuperAdminEnterpriseConfig.value = response.data.isUsingSuperAdminEnterpriseConfig ?? false
    }
  } catch (error: any) {
    console.error('加载定价配置失败:', error)
  } finally {
    loading.value = false
  }
}

const savePersonal = async () => {
  loading.value = true
  try {
    const response: any = await request.put('/admin/pricing', { personalConfig: personal })
    if (response.code === 200) {
      ElMessage.success('个人版价格已保存')
      isUsingSuperAdminPersonalConfig.value = false
      await loadPricing()
    }
  } catch (error: any) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    loading.value = false
  }
}

const saveEnterprise = async () => {
  loading.value = true
  try {
    const response: any = await request.put('/admin/pricing', {
      enterpriseConfig: {
        face: enterprise.face,
        mbti: enterprise.mbti,
        disc: enterprise.disc,
        pdp: enterprise.pdp,
        sbti: enterprise.sbti,
        gaokao: enterprise.gaokao
      }
    })
    if (response.code === 200) {
      ElMessage.success('企业版价格已保存')
      isUsingSuperAdminEnterpriseConfig.value = false
      await loadPricing()
    }
  } catch (error: any) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadPricing()
})
</script>

<style scoped lang="scss">
.page-header {
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
}

/* .custom-tabs-container 视觉已统一在 admin-theme.css */

.pricing-content {
  display: flex;
  flex-direction: column;
}

.tab-content-card {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #f3f4f6;
  padding: 32px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.tab-content {
  .form-section {
    display: flex;
    flex-direction: column;
    gap: 24px;
    margin-bottom: 24px;
  }

  .form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
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
    }
  }

  .notice-box {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 12px 16px;
    background-color: #eff6ff;
    border-radius: 8px;
    margin-bottom: 24px;
    border: 1px solid #bfdbfe;

    .notice-icon {
      color: #3b82f6;
      font-size: 16px;
      margin-top: 2px;
      flex-shrink: 0;
    }

    span {
      font-size: 13px;
      color: #1e40af;
      line-height: 1.5;
    }
  }

  .form-item {
    :deep(.el-input-number) {
      width: 100%;

      .el-input__wrapper {
        border-radius: 8px;
        box-shadow: 0 0 0 1px #e5e7eb inset;
        padding: 8px 12px;
        transition: all 0.2s;

        &.is-focus {
          box-shadow: 0 0 0 1px #7c3aed inset, 0 0 0 3px rgba(124, 58, 237, 0.1);
        }

        &:hover {
          box-shadow: 0 0 0 1px #d1d5db inset;
        }
      }

      .el-input__inner {
        font-size: 14px;
        color: #111827;
        text-align: left;
      }
    }
  }

  .save-actions {
    padding-top: 24px;
    border-top: 1px solid #f3f4f6;

    .save-btn {
      height: 42px;
      padding: 0 32px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 14px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
      transition: all 0.2s;

      &:hover {
        opacity: 0.9;
        transform: translateY(-1px);
      }

      &:active {
        transform: translateY(0);
      }
    }
  }
}

.w-full { width: 100%; }

@media (max-width: 768px) {
  .tab-content .form-grid {
    grid-template-columns: 1fr;
  }
  .tab-content-card {
    padding: 24px;
  }
}

.page-container.is-embedded {
  min-height: auto;
}
</style>
