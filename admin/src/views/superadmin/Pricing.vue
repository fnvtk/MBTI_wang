<template>
  <div class="page-container" :class="{ 'is-embedded': embedded }">
    <div v-if="!embedded" class="page-header">
      <div class="header-left">
        <h2>全局定价</h2>
        <p class="subtitle">
          {{
            enterpriseProcurementOnly
              ? '维护企业向平台采购的测试单价、充值门槛及企业侧深度服务（个人版 C 端价格在后台仍由接口存储，此处不展示）'
              : '配置全局定价策略，影响所有企业与个人版'
          }}
        </p>
      </div>
    </div>

    <div class="pricing-content">
      <p v-if="embedded && enterpriseProcurementOnly" class="embed-enterprise-hint">
        此处维护企业采购单价与深度服务；个人版 C 端定价不在此展示，仍由后端存储并在小程序对个人用户生效。
      </p>
      <div class="custom-tabs-container">
        <div class="custom-tabs">
          <div
            v-for="tab in visibleTabs"
            :key="tab.value"
            :class="['tab-item', { active: activeTab === tab.value }]"
            @click="activeTab = tab.value"
          >
            {{ tab.label }}
          </div>
        </div>
      </div>

      <div class="tab-content-card" v-loading="loading">
        <!-- 个人版价格（超管「订单和财务」入口不展示，数据仍从接口加载以便深度服务保存时不覆盖） -->
        <div v-if="activeTab === 'personal' && !enterpriseProcurementOnly" class="tab-content">
          <div class="form-section">
            <div class="form-grid">
              <div class="form-item">
                <label>人脸测试价格 (元/次)</label>
                <el-input-number 
                  v-model="personal.face" 
                  :min="0" 
                  :precision="2"
                  :controls="false"
                  class="w-full"
                />
              </div>
              <div class="form-item">
                <label>MBTI测试价格 (元/次)</label>
                <el-input-number 
                  v-model="personal.mbti" 
                  :min="0" 
                  :precision="2"
                  :controls="false"
                  class="w-full"
                />
              </div>
              <div class="form-item">
                <label>DISC测试价格 (元/次)</label>
                <el-input-number 
                  v-model="personal.disc" 
                  :min="0" 
                  :precision="2"
                  :controls="false"
                  class="w-full"
                />
              </div>
              <div class="form-item">
                <label>PDP测试价格 (元/次)</label>
                <el-input-number 
                  v-model="personal.pdp" 
                  :min="0" 
                  :precision="2"
                  :controls="false"
                  class="w-full"
                />
              </div>
              <div class="form-item">
                <label>SBTI测试价格 (元/次)</label>
                <el-input-number 
                  v-model="personal.sbti" 
                  :min="0" 
                  :precision="2"
                  :controls="false"
                  class="w-full"
                />
              </div>
            </div>
            <div class="save-actions">
              <el-button type="primary" class="save-btn" @click="savePersonal">
                保存个人版价格设置
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
                <el-input-number 
                  v-model="enterprise.face" 
                  :min="0" 
                  :precision="2"
                  :controls="false"
                  class="w-full"
                />
              </div>
              <div class="form-item">
                <label>MBTI测试价格 (元/次)</label>
                <el-input-number 
                  v-model="enterprise.mbti" 
                  :min="0" 
                  :precision="2"
                  :controls="false"
                  class="w-full"
                />
              </div>
              <div class="form-item">
                <label>PDP测试价格 (元/次)</label>
                <el-input-number 
                  v-model="enterprise.pdp" 
                  :min="0" 
                  :precision="2"
                  :controls="false"
                  class="w-full"
                />
              </div>
              <div class="form-item">
                <label>DISC测试价格 (元/次)</label>
                <el-input-number 
                  v-model="enterprise.disc" 
                  :min="0" 
                  :precision="2"
                  :controls="false"
                  class="w-full"
                />
              </div>
              <div class="form-item">
                <label>SBTI测试价格 (元/次)</label>
                <el-input-number 
                  v-model="enterprise.sbti" 
                  :min="0" 
                  :precision="2"
                  :controls="false"
                  class="w-full"
                />
              </div>
              <div class="form-item">
                <label>最低充值金额 (元)</label>
                <el-input-number 
                  v-model="enterprise.minRecharge" 
                  :min="0" 
                  :precision="2"
                  :controls="false"
                  class="w-full"
                />
              </div>
            </div>
            <div class="save-actions">
              <el-button type="primary" class="save-btn" @click="saveEnterprise">
                保存企业版价格设置
              </el-button>
            </div>
          </div>
        </div>

        <!-- 深度服务价格：个人/企业可配置类目列表（可视化列表编辑） -->
        <div v-if="activeTab === 'deep'" class="tab-content">
          <div class="form-section">
            <div class="deep-columns" :class="{ 'deep-columns-single': enterpriseProcurementOnly }">
              <!-- 个人版类目：列表展示 + 编辑/删除 -->
              <div v-if="!enterpriseProcurementOnly" class="deep-column">
                <div class="deep-column-header">
                  <h3>个人版深度服务类目</h3>
                  <el-button size="small" @click="openPersonalDialog()">新增类目</el-button>
                </div>
                <div v-if="deepPersonal.length === 0" class="deep-empty">
                  暂无个人版类目，请点击「新增类目」
                </div>
                <div v-else class="deep-table">
                  <div class="deep-table-header">
                    <span class="col-name">名称</span>
                    <span class="col-price">价格（元）</span>
                    <span class="col-unit">价格单位</span>
                    <span class="col-actions">操作</span>
                  </div>
                  <div
                    v-for="(item, index) in deepPersonal"
                    :key="item._key"
                    class="deep-table-row"
                  >
                    <span class="col-name">{{ item.title || `类目 ${index + 1}` }}</span>
                    <span class="col-price">{{ item.price ?? '-' }}</span>
                    <span class="col-unit">{{ item.priceUnit || '/次' }}</span>
                    <span class="col-actions">
                      <el-button link type="primary" size="small" @click="openPersonalDialog(index)">
                        编辑
                      </el-button>
                      <el-button link type="danger" size="small" @click="removePersonalCategory(index)">
                        删除
                      </el-button>
                    </span>
                  </div>
                </div>
              </div>

              <!-- 企业版类目：列表展示 + 编辑/删除 -->
              <div class="deep-column">
                <div class="deep-column-header">
                  <h3>企业版深度服务类目</h3>
                  <el-button size="small" @click="openEnterpriseDialog()">新增类目</el-button>
                </div>
                <div v-if="deepEnterprise.length === 0" class="deep-empty">
                  暂无企业版类目，请点击「新增类目」
                </div>
                <div v-else class="deep-table">
                  <div class="deep-table-header">
                    <span class="col-name">名称</span>
                    <span class="col-price">价格（元）</span>
                    <span class="col-unit">价格单位</span>
                    <span class="col-actions">操作</span>
                  </div>
                  <div
                    v-for="(item, index) in deepEnterprise"
                    :key="item._key"
                    class="deep-table-row"
                  >
                    <span class="col-name">{{ item.title || `类目 ${index + 1}` }}</span>
                    <span class="col-price">{{ item.priceDisplay || '-' }}</span>
                    <span class="col-unit">/次</span>
                    <span class="col-actions">
                      <el-button link type="primary" size="small" @click="openEnterpriseDialog(index)">
                        编辑
                      </el-button>
                      <el-button link type="danger" size="small" @click="removeEnterpriseCategory(index)">
                        删除
                      </el-button>
                    </span>
                  </div>
                </div>
              </div>
            </div>
            <div class="info-box">
              <el-icon><InfoFilled /></el-icon>
              <span v-if="enterpriseProcurementOnly">
                此处仅配置企业向平台采购的深度服务类目；保存后企业相关展示会同步。个人版深度类目仍由数据接口保留，不在本页编辑。
              </span>
              <span v-else>可通过上方列表自由新增/修改/下线个人版与企业版深度服务类目，保存后小程序开通会员页会自动同步。</span>
            </div>
            <div class="save-actions">
              <el-button type="primary" class="save-btn" @click="saveDeep">
                保存深度服务配置
              </el-button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 新增个人版类目弹框（企业采购视图下整块不挂载） -->
  <template v-if="!enterpriseProcurementOnly">
  <el-dialog v-model="personalDialogVisible" title="新增个人版深度服务类目" width="640px">
    <div class="deep-card-grid">
      <div class="deep-form-item hidden-field">
        <label>标识 ID</label>
        <el-input v-model="personalDraft.id" placeholder="如 personal_insight" />
      </div>
      <div class="deep-form-item">
        <label>名称</label>
        <el-input v-model="personalDraft.title" placeholder="如 个人深度洞察版" />
      </div>
      <div class="deep-form-item">
        <label>价格（元）</label>
        <el-input-number
          v-model="personalDraft.price"
          :min="0"
          :precision="2"
          :controls="false"
          class="w-full"
        />
      </div>
      <div class="deep-form-item">
        <label>价格单位</label>
        <el-input v-model="personalDraft.priceUnit" placeholder="如 /次、/小时、/2小时" />
      </div>
      <div class="deep-form-item deep-form-item-full" v-if="personalDraft.actionType === 'buy'">
        <label>购买按钮文案</label>
        <el-input v-model="personalDraft.purchaseButtonText" placeholder="空则小程序默认「了解自己并付款」；例：了解并付款" />
      </div>
      <div class="deep-form-item deep-form-item-full">
        <label>副标题</label>
        <el-input v-model="personalDraft.subtitle" placeholder="简要介绍该服务" />
      </div>
      <div class="deep-form-item deep-form-item-full">
        <label>功能要点</label>
        <div class="feature-list">
          <div
            class="feature-row"
            v-for="(_f, idx) in personalDraftFeatures"
            :key="`pf_${idx}`"
          >
            <el-input
              v-model="personalDraftFeatures[idx]"
              placeholder="如：AI面部分析（基于东方面相学与西方心理学）"
            />
            <el-button
              link
              type="danger"
              size="small"
              @click="removePersonalFeature(idx)"
            >
              删除
            </el-button>
          </div>
          <el-button type="primary" link size="small" @click="addPersonalFeature">
            + 新增要点
          </el-button>
        </div>
      </div>
      <div class="deep-form-item">
        <label>动作类型</label>
        <el-select v-model="personalDraft.actionType" placeholder="请选择">
          <el-option label="立即购买" value="buy" />
          <el-option label="申请咨询" value="consult" />
        </el-select>
      </div>
      <div class="deep-form-item hidden-field" v-if="personalDraft.actionType === 'buy'">
        <label>产品标识（小程序支付用）</label>
        <el-input v-model="personalDraft.productKey" placeholder="如 personal_insight" />
      </div>
      <div class="deep-form-item">
        <label>客服微信（备档）</label>
        <el-input v-model="personalDraft.serviceWechat" placeholder="已不再在小程序成功弹窗展示；线索以存客宝+飞书群通知为准" />
      </div>
      <div class="deep-form-item">
        <label>存客宝KEY</label>
        <el-input v-model="personalDraft.consultWechat" placeholder="如 mi5p9-f4gx6-..." />
      </div>
      <div class="deep-form-item deep-form-item-full">
        <label>成功提示词</label>
        <el-input v-model="personalDraft.successMessage" type="textarea" :rows="2" placeholder="用户操作成功后弹窗提示语，选填" />
      </div>
    </div>
    <template #footer>
      <span class="dialog-footer">
        <el-button @click="personalDialogVisible = false">取 消</el-button>
        <el-button type="primary" @click="confirmPersonalDialog">确 定</el-button>
      </span>
    </template>
  </el-dialog>
  </template>

  <!-- 新增企业版类目弹框 -->
  <el-dialog v-model="enterpriseDialogVisible" title="新增企业版深度服务类目" width="640px">
    <div class="deep-card-grid">
      <div class="deep-form-item hidden-field">
        <label>标识 ID</label>
        <el-input v-model="enterpriseDraft.id" placeholder="如 startup" />
      </div>
      <div class="deep-form-item">
        <label>名称</label>
        <el-input v-model="enterpriseDraft.title" placeholder="如 团队启动版" />
      </div>
      <div class="deep-form-item">
        <label>价格展示</label>
        <el-input v-model="enterpriseDraft.priceDisplay" placeholder="如 ¥19,800" />
      </div>
      <div class="deep-form-item">
        <label>人数上限</label>
        <el-input v-model="enterpriseDraft.userLimit" placeholder="如 最多10人" />
      </div>
      <div class="deep-form-item deep-form-item-full">
        <label>副标题</label>
        <el-input v-model="enterpriseDraft.subtitle" placeholder="适合哪类团队" />
      </div>
      <div class="deep-form-item deep-form-item-full">
        <label>功能要点</label>
        <div class="feature-list">
          <div
            class="feature-row"
            v-for="(_f, idx) in enterpriseDraftFeatures"
            :key="`ef_${idx}`"
          >
            <el-input
              v-model="enterpriseDraftFeatures[idx]"
              placeholder="如：10人完成个人深度洞察报告"
            />
            <el-button
              link
              type="danger"
              size="small"
              @click="removeEnterpriseFeature(idx)"
            >
              删除
            </el-button>
          </div>
          <el-button type="primary" link size="small" @click="addEnterpriseFeature">
            + 新增要点
          </el-button>
        </div>
      </div>
      <div class="deep-form-item">
        <label>按钮文案</label>
        <el-input v-model="enterpriseDraft.buttonText" placeholder="如 申请咨询" />
      </div>
      <div class="deep-form-item">
        <label>客服微信（备档）</label>
        <el-input v-model="enterpriseDraft.serviceWechat" placeholder="已不再在小程序成功弹窗展示；线索以存客宝+飞书群通知为准" />
      </div>
      <div class="deep-form-item">
        <label>存客宝KEY</label>
        <el-input v-model="enterpriseDraft.consultWechat" placeholder="如 mi5p9-f4gx6-..." />
      </div>
      <div class="deep-form-item deep-form-item-full">
        <label>成功提示词</label>
        <el-input v-model="enterpriseDraft.successMessage" type="textarea" :rows="2" placeholder="用户操作成功后弹窗提示语，选填" />
      </div>
    </div>
    <template #footer>
      <span class="dialog-footer">
        <el-button @click="enterpriseDialogVisible = false">取 消</el-button>
        <el-button type="primary" @click="confirmEnterpriseDialog">确 定</el-button>
      </span>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { InfoFilled } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { request } from '@/utils/request'

const props = withDefaults(
  defineProps<{ embedded?: boolean; enterpriseProcurementOnly?: boolean }>(),
  { embedded: false, enterpriseProcurementOnly: false }
)

const activeTab = ref('personal')
const loading = ref(false)

const allTabs = [
  { label: '个人版价格', value: 'personal' },
  { label: '企业版价格', value: 'enterprise' },
  { label: '深度服务价格', value: 'deep' }
]

const visibleTabs = computed(() =>
  props.enterpriseProcurementOnly ? allTabs.filter((t) => t.value !== 'personal') : allTabs
)

watch(
  visibleTabs,
  (vt) => {
    if (!vt.some((t) => t.value === activeTab.value)) {
      activeTab.value = 'enterprise'
    }
  },
  { immediate: true }
)

const personal = reactive({
  face: 9.9,
  mbti: 9.9,
  disc: 9.9,
  pdp: 9.9,
  sbti: 9.9
})

const enterprise = reactive({
  face: 8.0,
  mbti: 8.0,
  pdp: 8.0,
  disc: 8.0,
  sbti: 8.0,
  minRecharge: 1000.0
})

// 深度服务类目（可视化编辑列表）
interface PersonalCategory {
  _key: string
  id: string
  title: string
  price: number | null
  priceUnit: string
  subtitle: string
  featuresText: string
  actionType: 'buy' | 'consult'
  /** 小程序「立即购买」主按钮文案，空则接口默认「了解自己并付款」 */
  purchaseButtonText: string
  productKey: string
  serviceWechat: string
  consultWechat: string
  successMessage: string
}

interface EnterpriseCategory {
  _key: string
  id: string
  title: string
  priceDisplay: string
  subtitle: string
  userLimit: string
  featuresText: string
  buttonText: string
  serviceWechat: string
  consultWechat: string
  successMessage: string
}

function createPersonalCategory (): PersonalCategory {
  const ts = Date.now().toString()
  return {
    _key: `personal_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
    id: ts,
    title: '',
    price: null,
    priceUnit: '/次',
    subtitle: '',
    featuresText: '',
    actionType: 'buy',
    purchaseButtonText: '',
    productKey: ts,
    serviceWechat: '',
    consultWechat: '',
    successMessage: '购买成功！我们的顾问会尽快与您联系，为您提供专属深度解读服务。'
  }
}

function createEnterpriseCategory (): EnterpriseCategory {
  const ts = Date.now().toString()
  return {
    _key: `enterprise_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
    id: ts,
    title: '',
    priceDisplay: '',
    subtitle: '',
    userLimit: '',
    featuresText: '',
    buttonText: '申请咨询',
    serviceWechat: '',
    consultWechat: '',
    successMessage: '感谢您的申请，我们的顾问会尽快与您联系！'
  }
}

const deepPersonal = ref<PersonalCategory[]>([])
const deepEnterprise = ref<EnterpriseCategory[]>([])

const personalDialogVisible = ref(false)
const enterpriseDialogVisible = ref(false)
const personalEditIndex = ref<number | null>(null)
const enterpriseEditIndex = ref<number | null>(null)
const personalDraft = ref<PersonalCategory>(createPersonalCategory())
const enterpriseDraft = ref<EnterpriseCategory>(createEnterpriseCategory())
const personalDraftFeatures = ref<string[]>([])
const enterpriseDraftFeatures = ref<string[]>([])

// 加载定价配置
const loadPricing = async () => {
  loading.value = true
  try {
    const response: any = await request.get('/superadmin/pricing')
    
    if (response.code === 200 && response.data) {
      // 更新个人版配置
      if (response.data.personal) {
        Object.assign(personal, response.data.personal)
      }
      
      // 更新企业版配置
      if (response.data.enterprise) {
        Object.assign(enterprise, response.data.enterprise)
      }
      
      // 更新深度服务配置（个人/企业类目）
      if (response.data.deep_personal && Array.isArray(response.data.deep_personal.categories)) {
        deepPersonal.value = response.data.deep_personal.categories.map((c: any, idx: number) => ({
          _key: c.id || `personal_${idx}_${Date.now()}`,
          id: c.id || '',
          title: c.title || '',
          price: typeof c.price === 'number' ? c.price : (c.price ? Number(c.price) : null),
          priceUnit: c.priceUnit || '/次',
          subtitle: c.subtitle || '',
          featuresText: Array.isArray(c.features) ? c.features.join('\n') : '',
          actionType: (c.actionType === 'consult' ? 'consult' : 'buy'),
          purchaseButtonText: c.purchaseButtonText ?? '',
          productKey: c.productKey || 'personal_insight',
          serviceWechat: c.serviceWechat ?? '',
          consultWechat: c.consultWechat ?? '',
          successMessage: c.successMessage ?? ''
        }))
      }
      if (response.data.deep_enterprise && Array.isArray(response.data.deep_enterprise.categories)) {
        deepEnterprise.value = response.data.deep_enterprise.categories.map((c: any, idx: number) => ({
          _key: c.id || `enterprise_${idx}_${Date.now()}`,
          id: c.id || '',
          title: c.title || '',
          priceDisplay: c.priceDisplay || (typeof c.price === 'number' ? `¥${c.price}` : ''),
          subtitle: c.subtitle || '',
          userLimit: c.userLimit || '',
          featuresText: Array.isArray(c.features) ? c.features.join('\n') : '',
          buttonText: c.buttonText || '申请咨询',
          serviceWechat: c.serviceWechat ?? '',
          consultWechat: c.consultWechat ?? '',
          successMessage: c.successMessage ?? ''
        }))
      }
    }
  } catch (error: any) {
    console.error('加载定价配置失败:', error)
    ElMessage.error(error.message || '加载定价配置失败')
  } finally {
    loading.value = false
  }
}

const savePersonal = async () => {
  loading.value = true
  try {
    const response: any = await request.put('/superadmin/pricing', {
      type: 'personal',
      config: personal
    })
    
    if (response.code === 200) {
      ElMessage.success('个人版价格设置已保存')
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
    const response: any = await request.put('/superadmin/pricing', {
      type: 'enterprise',
      config: {
        face: enterprise.face,
        mbti: enterprise.mbti,
        pdp: enterprise.pdp,
        disc: enterprise.disc,
        sbti: enterprise.sbti,
        minRecharge: enterprise.minRecharge
      }
    })
    
    if (response.code === 200) {
      ElMessage.success('企业版价格设置已保存')
    }
  } catch (error: any) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    loading.value = false
  }
}

const removePersonalCategory = (index: number) => {
  deepPersonal.value.splice(index, 1)
}

const removeEnterpriseCategory = (index: number) => {
  deepEnterprise.value.splice(index, 1)
}

const openPersonalDialog = (index?: number) => {
  if (typeof index === 'number') {
    personalEditIndex.value = index
    personalDraft.value = { ...deepPersonal.value[index] }
    personalDraftFeatures.value = deepPersonal.value[index].featuresText
      ? deepPersonal.value[index].featuresText.split(/\r?\n/).map((s) => s.trim()).filter(Boolean)
      : []
  } else {
    personalEditIndex.value = null
    personalDraft.value = createPersonalCategory()
    personalDraftFeatures.value = []
  }
  personalDialogVisible.value = true
}

const confirmPersonalDialog = () => {
  personalDraft.value.featuresText = personalDraftFeatures.value
    .map((s) => s.trim())
    .filter(Boolean)
    .join('\n')
  const payload = { ...personalDraft.value }
  if (personalEditIndex.value !== null) {
    deepPersonal.value.splice(personalEditIndex.value, 1, payload)
  } else {
    deepPersonal.value.push(payload)
  }
  personalDialogVisible.value = false
}

const openEnterpriseDialog = (index?: number) => {
  if (typeof index === 'number') {
    enterpriseEditIndex.value = index
    enterpriseDraft.value = { ...deepEnterprise.value[index] }
    enterpriseDraftFeatures.value = deepEnterprise.value[index].featuresText
      ? deepEnterprise.value[index].featuresText.split(/\r?\n/).map((s) => s.trim()).filter(Boolean)
      : []
  } else {
    enterpriseEditIndex.value = null
    enterpriseDraft.value = createEnterpriseCategory()
    enterpriseDraftFeatures.value = []
  }
  enterpriseDialogVisible.value = true
}

const confirmEnterpriseDialog = () => {
  enterpriseDraft.value.featuresText = enterpriseDraftFeatures.value
    .map((s) => s.trim())
    .filter(Boolean)
    .join('\n')
  const payload = { ...enterpriseDraft.value }
  if (enterpriseEditIndex.value !== null) {
    deepEnterprise.value.splice(enterpriseEditIndex.value, 1, payload)
  } else {
    deepEnterprise.value.push(payload)
  }
  enterpriseDialogVisible.value = false
}

const addPersonalFeature = () => {
  personalDraftFeatures.value.push('')
}

const removePersonalFeature = (index: number) => {
  personalDraftFeatures.value.splice(index, 1)
}

const addEnterpriseFeature = () => {
  enterpriseDraftFeatures.value.push('')
}

const removeEnterpriseFeature = (index: number) => {
  enterpriseDraftFeatures.value.splice(index, 1)
}

const saveDeep = async () => {
  loading.value = true
  try {
    // 将可视化列表转换为后端所需结构（数据库中存标准 JSON：features 为数组）
    const personalCategories = deepPersonal.value.map((item, index) => ({
      id: item.id || `personal_${index + 1}`,
      title: item.title || '',
      price: typeof item.price === 'number' ? item.price : 0,
      priceUnit: item.priceUnit || '/次',
      subtitle: item.subtitle || '',
      features: item.featuresText
        ? item.featuresText.split(/\r?\n/).map((s) => s.trim()).filter(Boolean)
        : [],
      actionType: item.actionType || 'buy',
      purchaseButtonText: (item.purchaseButtonText ?? '').trim(),
      productKey: item.productKey || 'personal_insight',
      serviceWechat: item.serviceWechat ?? '',
      consultWechat: item.consultWechat ?? '',
      successMessage: item.successMessage ?? ''
    }))
    const enterpriseCategories = deepEnterprise.value.map((item, index) => ({
      id: item.id || `enterprise_${index + 1}`,
      title: item.title || '',
      priceDisplay: item.priceDisplay || '',
      subtitle: item.subtitle || '',
      userLimit: item.userLimit || '',
      features: item.featuresText
        ? item.featuresText.split(/\r?\n/).map((s) => s.trim()).filter(Boolean)
        : [],
      buttonText: item.buttonText || '申请咨询',
      actionType: 'consult',
      serviceWechat: item.serviceWechat ?? '',
      consultWechat: item.consultWechat ?? '',
      successMessage: item.successMessage ?? ''
    }))

    // 先保存个人版深度服务
    await request.put('/superadmin/pricing', {
      type: 'deep_personal',
      config: { categories: personalCategories }
    })
    // 再保存企业版深度服务
    await request.put('/superadmin/pricing', {
      type: 'deep_enterprise',
      config: { categories: enterpriseCategories }
    })

    ElMessage.success('深度服务配置已保存')
  } catch (error: any) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    loading.value = false
  }
}

// 组件挂载时加载数据
onMounted(() => {
  if (props.enterpriseProcurementOnly) {
    activeTab.value = 'enterprise'
  }
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

.embed-enterprise-hint {
  margin: 0 0 12px;
  font-size: 13px;
  line-height: 1.5;
  color: #6b7280;
}

.pricing-content {
  display: flex;
  flex-direction: column;
  gap: 0;
}

/* .custom-tabs-container 视觉已统一在 admin-theme.css */

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

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;

      @media (max-width: 768px) {
        grid-template-columns: 1fr;
      }
    }

    .form-item {
      display: flex;
      flex-direction: column;
      gap: 8px;

      label {
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0;
      }

      .form-hint {
        font-size: 12px;
        color: #9ca3af;
        margin: 0;
        line-height: 1.4;
      }

      :deep(.el-input-number) {
        width: 100%;

        .el-input__wrapper {
          border-radius: 8px;
          box-shadow: 0 0 0 1px #e5e7eb inset;
          padding: 8px 12px;
          transition: all 0.2s;

          &.is-focus {
            box-shadow: 0 0 0 1px #ef4444 inset, 0 0 0 3px rgba(239, 68, 68, 0.1);
          }

          &:hover {
            box-shadow: 0 0 0 1px #d1d5db inset;
          }
        }

        .el-input__inner {
          font-size: 14px;
          color: #111827;
        }
      }
    }

    .info-box {
      padding: 16px;
      background-color: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: 8px;
      display: flex;
      align-items: flex-start;
      gap: 12px;
      color: #1e40af;
      font-size: 13px;
      line-height: 1.5;
      margin-bottom: 24px;

      .el-icon {
        font-size: 18px;
        flex-shrink: 0;
        margin-top: 2px;
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

.deep-columns {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;

  @media (max-width: 1024px) {
    grid-template-columns: 1fr;
  }

  &.deep-columns-single {
    grid-template-columns: 1fr;
  }
}

.deep-column {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.deep-column-header {
  display: flex;
  align-items: center;
  justify-content: space-between;

  h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: #111827;
  }
}

.deep-empty {
  font-size: 13px;
  color: #9ca3af;
  padding: 12px 0;
}

.deep-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 16px 16px 12px 16px;
  background-color: #f9fafb;
}

.deep-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;

  span {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
  }
}

.deep-card-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 16px;

  @media (max-width: 1024px) {
    grid-template-columns: 1fr;
  }
}

.deep-form-item {
  display: flex;
  flex-direction: column;
  gap: 4px;

  label {
    font-size: 12px;
    color: #4b5563;
  }
}

.deep-form-item-full {
  grid-column: 1 / -1;
}

.hidden-field {
  display: none;
}

.deep-table {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
  background-color: #fff;
}

.deep-table-header,
.deep-table-row {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  align-items: center;
  padding: 8px 12px;
  font-size: 13px;
}

.deep-table-header {
  background-color: #f9fafb;
  font-weight: 600;
  color: #4b5563;
  border-bottom: 1px solid #e5e7eb;
}

.deep-table-row {
  border-top: 1px solid #f3f4f6;
}

.deep-table-row:nth-child(odd) {
  background-color: #fbfbfb;
}

.col-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.col-price,
.col-unit {
  color: #374151;
}

.col-actions {
  display: flex;
  gap: 4px;
  justify-content: flex-end;
}

.feature-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.feature-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.feature-row :deep(.el-input) {
  flex: 1;
}

.w-full {
  width: 100%;
}

.page-container.is-embedded {
  min-height: auto;
}
</style>
