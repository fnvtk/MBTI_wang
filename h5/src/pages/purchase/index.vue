<template>
  <div class="purchase-page">
    <AppNavBar title="了解自己" />

    <!-- Tab 切换 -->
    <div class="tab-bar">
      <div :class="['tab-item', activeTab === 'personal' && 'active']" @click="activeTab = 'personal'">个人版</div>
      <div :class="['tab-item', activeTab === 'enterprise' && 'active']" @click="activeTab = 'enterprise'">企业版</div>
    </div>

    <!-- Hero 区 -->
    <div class="hero-section">
      <div class="hero-icon">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none">
          <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke="white" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h2 class="hero-title">深度了解自己，掌握人生主动权</h2>
      <p class="hero-desc">专业顾问 1v1 深度解读，帮你发现潜能、规划未来</p>
    </div>

    <!-- 加载状态 -->
    <div class="loading-wrap" v-if="loading">
      <div class="loading-spinner"></div>
      <p class="loading-text">加载中...</p>
    </div>

    <!-- 错误状态 -->
    <div class="error-wrap" v-else-if="loadError">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" style="color:#D1D5DB">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/>
        <line x1="12" y1="8" x2="12" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <line x1="12" y1="16" x2="12.01" y2="16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
      <p style="color:#6B7280; margin: 8px 0 16px;">{{ loadErrorMsg }}</p>
      <button class="retry-btn" @click="loadPricing">重新加载</button>
    </div>

    <!-- 价格方案 -->
    <div class="plans-wrap" v-else>
      <div
        v-for="(cat, idx) in currentCategories"
        :key="cat.id || idx"
        :class="['plan-card', selectedIndex === idx && 'selected']"
        @click="selectedIndex = idx"
      >
        <div class="plan-card-header">
          <div class="plan-badge" v-if="cat.badge">{{ cat.badge }}</div>
          <div class="plan-title">{{ cat.title }}</div>
          <div class="plan-price">
            <span class="price-symbol">¥</span>
            <span class="price-num">{{ formatPrice(cat.price) }}</span>
            <span class="price-unit">/次</span>
          </div>
        </div>
        <ul class="plan-features">
          <li v-for="(feat, fi) in (cat.features || [])" :key="fi">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
              <path d="M20 6L9 17l-5-5" stroke="#10B981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            {{ feat }}
          </li>
        </ul>
        <div class="plan-select-indicator">
          <div :class="['radio', selectedIndex === idx && 'radio-active']">
            <div class="radio-dot" v-if="selectedIndex === idx"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- 底部按钮 -->
    <div class="bottom-bar" v-if="!loading && !loadError && currentCategories.length">
      <button class="pay-btn" @click="handlePurchase" :disabled="purchasing">
        <span v-if="purchasing">处理中...</span>
        <span v-else>立即购买 · ¥{{ selectedCategory ? formatPrice(selectedCategory.price) : '--' }}</span>
      </button>
      <p class="pay-hint">支付安全加密 · 购买后顾问1-24小时内联系您</p>
    </div>

    <div style="height: 120px;"></div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppNavBar from '@/components/AppNavBar.vue'
import { request } from '@/utils/request'

const activeTab = ref<'personal' | 'enterprise'>('personal')
const loading = ref(true)
const loadError = ref(false)
const loadErrorMsg = ref('')
const purchasing = ref(false)
const selectedIndex = ref(0)

const personalCategories = ref<any[]>([])
const enterpriseCategories = ref<any[]>([])

const currentCategories = computed(() =>
  activeTab.value === 'personal' ? personalCategories.value : enterpriseCategories.value
)
const selectedCategory = computed(() => currentCategories.value[selectedIndex.value] || null)

function formatPrice(p: any) {
  if (!p && p !== 0) return '--'
  const n = Number(p)
  if (isNaN(n)) return String(p)
  return n % 1 === 0 ? String(n) : n.toFixed(2)
}

async function loadPricing() {
  loading.value = true
  loadError.value = false
  try {
    const [pRes, eRes] = await Promise.all([
      request({ url: '/api/config/deep-pricing?scope=personal', method: 'GET' }),
      request({ url: '/api/config/deep-pricing?scope=enterprise', method: 'GET' })
    ])
    if (pRes.code === 200 && pRes.data?.categories) {
      personalCategories.value = pRes.data.categories
    } else {
      personalCategories.value = defaultPersonalCategories()
    }
    if (eRes.code === 200 && eRes.data?.categories) {
      enterpriseCategories.value = eRes.data.categories
    } else {
      enterpriseCategories.value = defaultEnterpriseCategories()
    }
  } catch {
    personalCategories.value = defaultPersonalCategories()
    enterpriseCategories.value = defaultEnterpriseCategories()
  } finally {
    loading.value = false
  }
}

function defaultPersonalCategories() {
  return [
    {
      id: 'personal-basic', title: '个人深度解读', price: 99,
      badge: '热门',
      features: ['1v1专属顾问解读', 'MBTI+面相综合分析', '职业方向建议', '人际关系建议'],
      actionType: 'buy', productKey: 'personal-basic'
    },
    {
      id: 'personal-pro', title: '个人深度套餐', price: 299,
      badge: '超值',
      features: ['1v1专属顾问解读', '全测评综合报告', '职业规划路线图', '90天持续跟踪', '团队匹配建议'],
      actionType: 'buy', productKey: 'personal-pro'
    }
  ]
}

function defaultEnterpriseCategories() {
  return [
    {
      id: 'enterprise-consult', title: '企业团队咨询', price: 0,
      badge: '定制',
      features: ['专属企业方案设计', '团队性格画像分析', '岗位匹配建议', '批量测评折扣'],
      actionType: 'consult'
    }
  ]
}

async function handlePurchase() {
  if (!selectedCategory.value) return
  const cat = selectedCategory.value
  if (cat.actionType === 'consult') {
    alert('申请成功！我们的顾问会尽快与您联系')
    return
  }
  purchasing.value = true
  try {
    const res = await request({
      url: '/api/payment/create-order',
      method: 'POST',
      data: { productKey: cat.productKey || cat.id, description: cat.title }
    })
    if (res.code === 200) {
      alert('购买成功！顾问将在1-24小时内联系您')
    } else {
      alert(res.message || '支付失败，请稍后重试')
    }
  } catch {
    alert('网络异常，请稍后重试')
  } finally {
    purchasing.value = false
  }
}

onMounted(loadPricing)
</script>

<style scoped>
.purchase-page { min-height: 100vh; background: #F4F6FB; }

.tab-bar {
  display: flex; background: white; padding: 0 16px;
  border-bottom: 1px solid #F3F4F6;
}
.tab-item {
  flex: 1; text-align: center; padding: 14px;
  font-size: 14px; font-weight: 500; color: #6B7280;
  cursor: pointer; border-bottom: 2px solid transparent;
  transition: all 0.18s;
}
.tab-item.active { color: #4338CA; border-bottom-color: #4338CA; font-weight: 700; }

.hero-section {
  background: linear-gradient(135deg, #1E40AF, #4338CA);
  padding: 28px 20px; text-align: center; color: white;
}
.hero-icon {
  width: 72px; height: 72px; border-radius: 20px;
  background: rgba(255,255,255,0.15); backdrop-filter: blur(8px);
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 16px;
}
.hero-title { font-size: 18px; font-weight: 800; color: white; margin: 0 0 8px; }
.hero-desc { font-size: 13px; color: rgba(255,255,255,0.75); margin: 0; line-height: 1.5; }

.loading-wrap, .error-wrap {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; padding: 60px 20px;
}
.loading-spinner {
  width: 36px; height: 36px; border-radius: 50%;
  border: 3px solid #E5E7EB; border-top-color: #4338CA;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.loading-text { font-size: 13px; color: #9CA3AF; margin-top: 12px; }
.retry-btn {
  padding: 10px 24px; background: #4338CA; color: white;
  border: none; border-radius: 8px; font-size: 14px; cursor: pointer;
}

.plans-wrap { padding: 16px; display: flex; flex-direction: column; gap: 12px; }

.plan-card {
  background: white; border-radius: 16px;
  border: 2px solid #E5E7EB;
  padding: 20px; transition: all 0.2s; cursor: pointer;
  position: relative; overflow: hidden;
}
.plan-card.selected { border-color: #4338CA; box-shadow: 0 4px 16px rgba(67,56,202,0.15); }

.plan-badge {
  display: inline-block; font-size: 11px; font-weight: 700;
  background: linear-gradient(90deg, #F59E0B, #EF4444);
  color: white; border-radius: 20px; padding: 2px 10px;
  margin-bottom: 8px;
}
.plan-title { font-size: 16px; font-weight: 800; color: #111827; margin-bottom: 8px; }
.plan-price { display: flex; align-items: baseline; gap: 2px; margin-bottom: 16px; }
.price-symbol { font-size: 14px; color: #4338CA; font-weight: 700; }
.price-num { font-size: 32px; font-weight: 900; color: #4338CA; line-height: 1; }
.price-unit { font-size: 13px; color: #9CA3AF; }

.plan-features { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px; }
.plan-features li { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #374151; }

.plan-select-indicator {
  position: absolute; top: 16px; right: 16px;
}
.radio {
  width: 20px; height: 20px; border-radius: 50%;
  border: 2px solid #D1D5DB; display: flex; align-items: center; justify-content: center;
  transition: all 0.18s;
}
.radio.radio-active { border-color: #4338CA; background: #4338CA; }
.radio-dot { width: 8px; height: 8px; border-radius: 50%; background: white; }

.bottom-bar {
  position: fixed; bottom: 0; left: 0; right: 0;
  background: white; padding: 12px 16px 28px;
  box-shadow: 0 -4px 20px rgba(0,0,0,0.08);
}
.pay-btn {
  width: 100%; padding: 16px;
  background: linear-gradient(90deg, #1E40AF, #4338CA);
  color: white; border: none; border-radius: 14px;
  font-size: 16px; font-weight: 700; cursor: pointer;
  transition: opacity 0.18s;
}
.pay-btn:disabled { opacity: 0.6; }
.pay-hint { text-align: center; font-size: 11px; color: #9CA3AF; margin: 8px 0 0; }
</style>
