<template>
  <div class="mp-preview-root">

    <!-- 顶部标题栏 -->
    <div class="preview-header">
      <div class="preview-header__inner">
        <div class="preview-header__left">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
            <rect x="3" y="3" width="8" height="8" rx="2" fill="#7c3aed"/>
            <rect x="13" y="3" width="8" height="8" rx="2" fill="#a78bfa"/>
            <rect x="3" y="13" width="8" height="8" rx="2" fill="#a78bfa"/>
            <rect x="13" y="13" width="8" height="8" rx="2" fill="#7c3aed" opacity="0.5"/>
          </svg>
          <span class="preview-header__title">小程序界面预览</span>
          <span class="preview-header__badge">微信小程序</span>
        </div>
        <div class="preview-header__tabs">
          <button
            v-for="tab in previewTabs"
            :key="tab.key"
            :class="['preview-tab-btn', { active: currentPreview === tab.key }]"
            @click="currentPreview = tab.key"
          >{{ tab.label }}</button>
        </div>
      </div>
    </div>

    <!-- 手机容器 -->
    <div class="preview-stage">
      <div class="phone-shell">
        <!-- 手机顶部刘海 -->
        <div class="phone-notch">
          <div class="phone-notch__dot"></div>
          <div class="phone-notch__island"></div>
          <div class="phone-notch__dot"></div>
        </div>
        <!-- 状态栏 -->
        <div class="status-bar">
          <span class="status-time">9:41</span>
          <div class="status-icons">
            <svg width="14" height="10" viewBox="0 0 14 10" fill="none">
              <rect x="0" y="3" width="2" height="7" rx="0.5" fill="currentColor"/>
              <rect x="3" y="2" width="2" height="8" rx="0.5" fill="currentColor"/>
              <rect x="6" y="1" width="2" height="9" rx="0.5" fill="currentColor"/>
              <rect x="9" y="0" width="2" height="10" rx="0.5" fill="currentColor"/>
            </svg>
            <svg width="15" height="11" viewBox="0 0 15 11" fill="none">
              <path d="M7.5 2C10 2 12.2 3.1 13.8 4.8L15 3.5C13 1.3 10.4 0 7.5 0S2 1.3 0 3.5L1.2 4.8C2.8 3.1 5 2 7.5 2z" fill="currentColor"/>
              <path d="M7.5 5C9.2 5 10.7 5.8 11.8 7L13 5.7C11.6 4.1 9.7 3 7.5 3S3.4 4.1 2 5.7L3.2 7C4.3 5.8 5.8 5 7.5 5z" fill="currentColor"/>
              <circle cx="7.5" cy="9.5" r="1.5" fill="currentColor"/>
            </svg>
            <svg width="25" height="12" viewBox="0 0 25 12" fill="currentColor">
              <rect x="0" y="1" width="22" height="10" rx="2" stroke="currentColor" stroke-width="1" fill="none" opacity="0.4"/>
              <rect x="22.5" y="3.5" width="1.5" height="5" rx="0.75" fill="currentColor" opacity="0.4"/>
              <rect x="1" y="2" width="18" height="8" rx="1.5" fill="currentColor"/>
            </svg>
          </div>
        </div>

        <!-- 导航栏 -->
        <div class="mp-navbar" :class="{ 'navbar-transparent': currentPreview === 'home' }">
          <template v-if="currentPreview === 'home'">
            <div class="mp-navbar__content">
              <div class="navbar-enterprise-btn">
                <span>🏢</span>
                <span class="navbar-enterprise-text">企业版</span>
              </div>
              <span class="mp-navbar__title--home">神仙团队性格测试</span>
              <div style="width:100px"></div>
            </div>
          </template>
          <template v-else>
            <div class="mp-navbar__content mp-navbar__content--simple">
              <svg class="mp-navbar__back" width="10" height="16" viewBox="0 0 10 16" fill="none">
                <path d="M8 2L2 8L8 14" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <span class="mp-navbar__title">{{ navbarTitles[currentPreview] }}</span>
            </div>
          </template>
        </div>

        <!-- 页面内容 -->
        <div class="mp-screen">

          <!-- ======== 首页 ======== -->
          <div v-if="currentPreview === 'home'" class="mp-page page-home">
            <div class="home-bg-top"></div>
            <div class="home-bg-bottom"></div>

            <!-- 主视觉图 -->
            <div class="home-hero">
              <div class="home-hero__ring">
                <div class="home-hero__inner">
                  <div class="home-hero__illus">
                    <div class="mbti-grid">
                      <div class="mbti-cell mbti-cell--i">I</div>
                      <div class="mbti-cell mbti-cell--n">N</div>
                      <div class="mbti-cell mbti-cell--t">T</div>
                      <div class="mbti-cell mbti-cell--j">J</div>
                      <div class="mbti-cell mbti-cell--e">E</div>
                      <div class="mbti-cell mbti-cell--s">S</div>
                      <div class="mbti-cell mbti-cell--f">F</div>
                      <div class="mbti-cell mbti-cell--p">P</div>
                    </div>
                    <div class="hero-center-badge">
                      <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" fill="#7c3aed"/>
                        <path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                    </div>
                  </div>
                </div>
              </div>
              <!-- 浮动标签 -->
              <div class="home-float-tag tag-top-right">面相分析</div>
              <div class="home-float-tag tag-bottom-left">骨相分析</div>
              <div class="home-float-tag tag-bottom-right">性格测评</div>
            </div>

            <!-- 测试流程 -->
            <div class="home-process">
              <div class="home-process__title">测试流程</div>
              <div class="home-process__steps">
                <div class="home-step">
                  <div class="home-step__circle home-step__circle--active">1</div>
                  <div class="home-step__label">STEP1</div>
                  <div class="home-step__text">拍摄照片</div>
                </div>
                <div class="home-step__line"></div>
                <div class="home-step">
                  <div class="home-step__circle">2</div>
                  <div class="home-step__label">STEP2</div>
                  <div class="home-step__text">AI 分析</div>
                </div>
                <div class="home-step__line"></div>
                <div class="home-step">
                  <div class="home-step__circle">3</div>
                  <div class="home-step__label">STEP3</div>
                  <div class="home-step__text">生成报告</div>
                </div>
              </div>
            </div>

            <!-- 主按钮 -->
            <div class="home-cta">
              <button class="home-cta__btn" @click="currentPreview = 'test-select'">30秒测出你的性格</button>
            </div>
          </div>

          <!-- ======== 测试选择页 ======== -->
          <div v-else-if="currentPreview === 'test-select'" class="mp-page page-test-select">
            <div class="ts-intro-card">
              <div class="ts-intro__title">选择一项详细性格测试</div>
              <div class="ts-intro__desc">问卷测评、高考志愿规划与 AI 对话 / 拍照面相分项进入，完成后可获得对应报告</div>
            </div>

            <div
              v-for="item in testItems"
              :key="item.key"
              class="ts-entry-card"
              @click="currentPreview = 'result'"
            >
              <div :class="['ts-icon-wrap', `ts-icon-${item.key}`]">
                <span class="ts-emoji">{{ item.icon }}</span>
              </div>
              <div class="ts-content">
                <div class="ts-name">{{ item.name }}</div>
                <div class="ts-brief">{{ item.brief }}</div>
              </div>
              <div class="ts-arrow">→</div>
            </div>
          </div>

          <!-- ======== 我的页面 ======== -->
          <div v-else-if="currentPreview === 'profile'" class="mp-page page-profile">
            <div class="profile-topbar">
              <span class="profile-topbar__title">我的</span>
            </div>

            <!-- 用户卡片 -->
            <div class="profile-user-card">
              <div class="profile-avatar-wrap">
                <div class="profile-avatar-ring">
                  <div class="profile-avatar-letter" style="background: linear-gradient(135deg, #7c3aed, #a78bfa)">
                    <span>张</span>
                  </div>
                </div>
                <div class="profile-online-dot"></div>
              </div>
              <div class="profile-user-meta">
                <span class="profile-nickname">张小明</span>
                <div class="profile-tags-row">
                  <span class="profile-tag profile-tag--purple">INTJ</span>
                  <span class="profile-tag profile-tag--blue">D 型</span>
                  <span class="profile-tag profile-tag--orange">老虎</span>
                </div>
              </div>
              <span class="profile-chevron">›</span>
            </div>

            <!-- 数据条 -->
            <div class="profile-stats">
              <div class="profile-stat">
                <span class="profile-stat__v">12</span>
                <span class="profile-stat__l">测评记录</span>
              </div>
              <div class="profile-stat">
                <span class="profile-stat__v">5</span>
                <span class="profile-stat__l">邀请好友</span>
              </div>
              <div class="profile-stat profile-stat--highlight">
                <span class="profile-stat__v">¥28.50</span>
                <span class="profile-stat__l">可提现 ›</span>
              </div>
            </div>

            <!-- 我的测评横滑卡片 -->
            <div class="profile-section">
              <div class="profile-sec-head">
                <span class="profile-sec-head__title">我的测评</span>
                <span class="profile-sec-head__link">全部 · 12 ›</span>
              </div>
              <div class="profile-cards-scroll">
                <div
                  v-for="card in resultCards"
                  :key="card.key"
                  :class="['profile-result-card', `profile-card-${card.key}`]"
                  @click="currentPreview = 'result'"
                >
                  <div :class="['profile-card-deco']"></div>
                  <div :class="['profile-card-icon-wrap', `profile-card-icon-${card.key}`]">
                    <span>{{ card.icon }}</span>
                  </div>
                  <span class="profile-card-label">{{ card.label }}</span>
                  <span :class="['profile-card-value', `profile-card-value-${card.key}`]">{{ card.value }}</span>
                  <span class="profile-card-time">{{ card.time }}</span>
                </div>
              </div>
            </div>

            <!-- 快捷入口 -->
            <div class="profile-section">
              <div class="profile-quick-grid">
                <div v-for="g in quickGridItems" :key="g.key" class="profile-quick-item">
                  <div :class="['profile-quick-ic', `profile-quick-ic--${g.color}`]">{{ g.icon }}</div>
                  <span class="profile-quick-label">{{ g.label }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- ======== 测评结果页 ======== -->
          <div v-else-if="currentPreview === 'result'" class="mp-page page-result">
            <div class="result-hero">
              <div class="result-hero__type-badge">INTJ</div>
              <div class="result-hero__name">建筑师</div>
              <div class="result-hero__desc">独立、自信、具有战略眼光的思想者</div>
            </div>

            <!-- 四维度 -->
            <div class="result-dims">
              <div class="result-dim" v-for="dim in mbtiDims" :key="dim.left">
                <div class="result-dim__labels">
                  <span class="result-dim__l">{{ dim.left }}</span>
                  <span class="result-dim__r">{{ dim.right }}</span>
                </div>
                <div class="result-dim__bar">
                  <div class="result-dim__fill" :style="{ width: dim.pct + '%', background: dim.color }"></div>
                  <div class="result-dim__dot" :style="{ left: dim.pct + '%' }"></div>
                </div>
                <div class="result-dim__pct">{{ dim.pct }}%</div>
              </div>
            </div>

            <!-- 团队匹配推荐 -->
            <div class="result-team-section">
              <div class="result-section-title">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                  <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" stroke="#7c3aed" stroke-width="2" stroke-linecap="round"/>
                  <circle cx="9" cy="7" r="4" stroke="#7c3aed" stroke-width="2"/>
                  <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="#7c3aed" stroke-width="2" stroke-linecap="round"/>
                </svg>
                团队最佳搭档
              </div>
              <div class="result-team-cards">
                <div v-for="partner in teamPartners" :key="partner.type" class="result-team-card">
                  <div class="result-team-card__type" :style="{ color: partner.color }">{{ partner.type }}</div>
                  <div class="result-team-card__role">{{ partner.role }}</div>
                  <div class="result-team-card__reason">{{ partner.reason }}</div>
                </div>
              </div>
            </div>

            <!-- 购买报告 -->
            <div class="result-purchase-btn">
              <button class="result-purchase-btn__inner">解锁完整报告 ·  ¥29</button>
            </div>
          </div>

        </div><!-- end mp-screen -->

        <!-- 底部 TabBar -->
        <div class="mp-tabbar">
          <div
            v-for="(tab, idx) in tabItems"
            :key="tab.key"
            @click="currentPreview = tab.page"
          >
            <!-- 中间凸起拍摄按钮 -->
            <div v-if="tab.highlight" class="mp-tabbar__fab-slot">
              <div :class="['mp-tabbar__fab', { active: currentPreview === tab.page }]">
                <div class="mp-tabbar__fab-circle">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                    <path d="M4 7h3l2-3h6l2 3h3v13H4z" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="13" r="4" stroke="white" stroke-width="1.8"/>
                  </svg>
                </div>
                <span class="mp-tabbar__fab-text">拍摄</span>
              </div>
            </div>
            <!-- 普通 Tab -->
            <div
              v-else
              :class="['mp-tabbar__item', { active: currentPreview === tab.page }]"
            >
              <div class="mp-tabbar__icon">
                <!-- 首页图标 -->
                <svg v-if="tab.key === 'home'" width="22" height="22" viewBox="0 0 24 24" fill="none">
                  <path d="M3 12l9-9 9 9" :stroke="currentPreview === tab.page ? '#7c3aed' : '#999'" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M5 10v10h14V10" :stroke="currentPreview === tab.page ? '#7c3aed' : '#999'" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" :fill="currentPreview === tab.page ? '#EDE9FE' : 'none'"/>
                  <path d="M10 20v-6h4v6" :stroke="currentPreview === tab.page ? '#fff' : '#bbb'" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <!-- AI 图标 -->
                <svg v-else-if="tab.key === 'ai'" width="22" height="22" viewBox="0 0 24 24" fill="none">
                  <circle cx="12" cy="12" r="9" :stroke="currentPreview === tab.page ? '#7c3aed' : '#999'" stroke-width="1.8"/>
                  <path d="M8 14s1.5 2 4 2 4-2 4-2" :stroke="currentPreview === tab.page ? '#7c3aed' : '#999'" stroke-width="1.8" stroke-linecap="round"/>
                  <circle cx="9.5" cy="10.5" r="1.2" :fill="currentPreview === tab.page ? '#7c3aed' : '#999'"/>
                  <circle cx="14.5" cy="10.5" r="1.2" :fill="currentPreview === tab.page ? '#7c3aed' : '#999'"/>
                </svg>
                <!-- 我的图标 -->
                <svg v-else-if="tab.key === 'profile'" width="22" height="22" viewBox="0 0 24 24" :fill="currentPreview === tab.page ? '#EDE9FE' : 'none'">
                  <circle cx="12" cy="8" r="4" :stroke="currentPreview === tab.page ? '#7c3aed' : '#999'" stroke-width="1.8"/>
                  <path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8" :stroke="currentPreview === tab.page ? '#7c3aed' : '#999'" stroke-width="1.8" stroke-linecap="round" fill="none"/>
                </svg>
              </div>
              <span :class="['mp-tabbar__text', { active: currentPreview === tab.page }]">{{ tab.label }}</span>
            </div>
          </div>
        </div>

        <!-- 手机底部横条 -->
        <div class="phone-home-indicator"></div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const currentPreview = ref<string>('home')

const previewTabs = [
  { key: 'home', label: '首页' },
  { key: 'test-select', label: '测试选择' },
  { key: 'profile', label: '我的' },
  { key: 'result', label: '测评结果' },
]

const navbarTitles: Record<string, string> = {
  'test-select': '选择测试',
  'profile': '我的',
  'result': 'MBTI 性格报告',
}

const tabItems = [
  { key: 'home', label: '首页', page: 'home', highlight: false },
  { key: 'camera', label: '拍摄', page: 'home', highlight: true },
  { key: 'ai', label: '神仙AI', page: 'home', highlight: false },
  { key: 'profile', label: '我的', page: 'profile', highlight: false },
]

const testItems = [
  { key: 'mbti', icon: '🧠', name: 'MBTI 性格测试', brief: '16型人格 · 发现你的认知与决策风格' },
  { key: 'pdp', icon: '🦁', name: 'PDP 行为偏好测试', brief: '老虎 / 孔雀 / 无尾熊 / 猫头鹰 / 变色龙 · 行为风格' },
  { key: 'disc', icon: '📊', name: 'DISC 性格测试', brief: 'D/I/S/C 四维 · 沟通与行为倾向' },
  { key: 'gaokao', icon: '🎓', name: '高考志愿任务中心', brief: '完成测评与信息表单 · 购买后生成志愿分析' },
  { key: 'ai-chat', icon: '💬', name: 'AI 对话解读', brief: '与神仙 AI 对话 · 深度解读与建议' },
  { key: 'ai-face', icon: '📷', name: '拍照面相分析', brief: '上传正面与侧面照 · 面相 / 骨相与性格报告' },
]

const resultCards = [
  { key: 'purple', icon: '🧠', label: 'MBTI性格', value: 'INTJ', time: '3天前' },
  { key: 'orange', icon: '🦁', label: 'PDP行为', value: '老虎型', time: '3天前' },
  { key: 'blue', icon: '📊', label: 'DISC测评', value: 'D型', time: '7天前' },
  { key: 'rose', icon: '👁️', label: '面相分析', value: '权贵相', time: '3天前' },
]

const quickGridItems = [
  { key: 'test', icon: '🧠', label: '性格测试', color: 'purple' },
  { key: 'order', icon: '🧾', label: '我的订单', color: 'emerald' },
  { key: 'deep', icon: '💎', label: '了解自己', color: 'indigo' },
  { key: 'job', icon: '💼', label: '匹配工作', color: 'blue' },
]

const mbtiDims = [
  { left: '内向 I', right: 'E 外向', pct: 72, color: 'linear-gradient(90deg,#7c3aed,#a78bfa)' },
  { left: '直觉 N', right: 'S 实感', pct: 68, color: 'linear-gradient(90deg,#4f46e5,#818cf8)' },
  { left: '思考 T', right: 'F 情感', pct: 81, color: 'linear-gradient(90deg,#0891b2,#38bdf8)' },
  { left: '判断 J', right: 'P 知觉', pct: 75, color: 'linear-gradient(90deg,#059669,#34d399)' },
]

const teamPartners = [
  { type: 'ENFP', role: '活动家', reason: '互补创意与执行', color: '#d97706' },
  { type: 'ESTP', role: '企业家', reason: '平衡直觉与行动', color: '#0891b2' },
  { type: 'INFJ', role: '提倡者', reason: '深层价值观共鸣', color: '#7c3aed' },
]
</script>

<style scoped lang="scss">
/* ===== 预览框架 ===== */
.mp-preview-root {
  min-height: 100vh;
  background: #F1F5F9;
  display: flex;
  flex-direction: column;
}

/* 顶部标题栏 */
.preview-header {
  background: #fff;
  border-bottom: 1px solid #E5E7EB;
  padding: 0 24px;
  height: 60px;
  flex-shrink: 0;
  position: sticky;
  top: 0;
  z-index: 100;
}
.preview-header__inner {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.preview-header__left {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}
.preview-header__title {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
}
.preview-header__badge {
  background: #EEF2FF;
  color: #4338CA;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 20px;
  border: 1px solid #C7D2FE;
}
.preview-header__tabs {
  display: flex;
  gap: 4px;
}
.preview-tab-btn {
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 500;
  color: #6B7280;
  background: transparent;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.18s;

  &:hover { background: #F3F4F6; color: #374151; }
  &.active {
    background: #7c3aed;
    color: #fff;
    border-color: #7c3aed;
    font-weight: 600;
  }
}

/* 手机展示舞台 */
.preview-stage {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px;
}

/* 手机外壳 */
.phone-shell {
  width: 390px;
  background: #fff;
  border-radius: 54px;
  border: 8px solid #1a1a2e;
  box-shadow:
    0 0 0 2px #2d2d3e,
    0 40px 80px rgba(0,0,0,0.3),
    inset 0 0 0 1px rgba(255,255,255,0.08);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
  min-height: 840px;
}

/* 刘海 */
.phone-notch {
  height: 34px;
  background: #1a1a2e;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  flex-shrink: 0;
}
.phone-notch__dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #2d2d3e;
}
.phone-notch__island {
  width: 100px;
  height: 22px;
  background: #1a1a2e;
  border-radius: 11px;
  border: 1.5px solid #2d2d3e;
}

/* 状态栏 */
.status-bar {
  height: 44px;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  flex-shrink: 0;
  color: #111827;
}
.status-time {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
}
.status-icons {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #111827;
}

/* 导航栏 */
.mp-navbar {
  height: 44px;
  background: #fff;
  border-bottom: 1px solid #f3f4f6;
  flex-shrink: 0;
  display: flex;
  align-items: center;

  &.navbar-transparent {
    border-bottom-color: transparent;
  }
}
.mp-navbar__content {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
}
.mp-navbar__content--simple {
  justify-content: flex-start;
  gap: 8px;
}
.mp-navbar__back {
  cursor: pointer;
}
.mp-navbar__title {
  font-size: 16px;
  font-weight: 700;
  color: #111827;
}
.mp-navbar__title--home {
  font-size: 15px;
  font-weight: 700;
  color: #e63946;
  text-align: center;
  flex: 1;
}
.navbar-enterprise-btn {
  display: flex;
  align-items: center;
  gap: 4px;
  background: rgba(230,57,70,0.1);
  border: 1px solid rgba(230,57,70,0.2);
  border-radius: 20px;
  padding: 4px 10px;
  font-size: 11px;
  cursor: pointer;
}
.navbar-enterprise-text {
  color: #e63946;
  font-weight: 600;
  font-size: 11px;
}

/* 内容区 */
.mp-screen {
  flex: 1;
  overflow-y: auto;
  scrollbar-width: none;
  &::-webkit-scrollbar { display: none; }
}
.mp-page {
  min-height: 100%;
}

/* ===== 首页 ===== */
.page-home {
  background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 50%, #ffffff 100%);
  position: relative;
  padding-bottom: 16px;
  overflow: hidden;
}

.home-bg-top {
  position: absolute;
  width: 280px; height: 280px;
  border-radius: 50%;
  top: -120px; right: -120px;
  background: radial-gradient(circle, rgba(124,58,237,0.1) 0%, transparent 70%);
  pointer-events: none;
}
.home-bg-bottom {
  position: absolute;
  width: 240px; height: 240px;
  border-radius: 50%;
  bottom: -80px; left: -80px;
  background: radial-gradient(circle, rgba(99,102,241,0.08) 0%, transparent 70%);
  pointer-events: none;
}

/* 主视觉英雄区 */
.home-hero {
  position: relative;
  padding: 16px 20px 8px;
  display: flex;
  justify-content: center;
}
.home-hero__ring {
  width: 240px;
  height: 240px;
  border-radius: 50%;
  background: linear-gradient(135deg, #ede9fe, #dbeafe);
  padding: 4px;
  box-shadow: 0 8px 32px rgba(124,58,237,0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
}
.home-hero__inner {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
}
.home-hero__illus {
  position: relative;
  width: 160px;
  height: 160px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.mbti-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 4px;
  width: 148px;
}
.mbti-cell {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 800;
  letter-spacing: -0.5px;
}
.mbti-cell--i { background: #7c3aed; color: #fff; }
.mbti-cell--n { background: #4f46e5; color: #fff; }
.mbti-cell--t { background: #0891b2; color: #fff; }
.mbti-cell--j { background: #059669; color: #fff; }
.mbti-cell--e { background: #EDE9FE; color: #7c3aed; }
.mbti-cell--s { background: #EEF2FF; color: #4f46e5; }
.mbti-cell--f { background: #ECFEFF; color: #0891b2; }
.mbti-cell--p { background: #ECFDF5; color: #059669; }

.hero-center-badge {
  position: absolute;
  top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  width: 40px; height: 40px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 4px 16px rgba(124,58,237,0.2);
  display: flex;
  align-items: center;
  justify-content: center;
}

.home-float-tag {
  position: absolute;
  background: rgba(255,255,255,0.95);
  border-radius: 20px;
  padding: 5px 12px;
  font-size: 10px;
  font-weight: 700;
  color: #e63946;
  box-shadow: 0 3px 12px rgba(230,57,70,0.2);
  white-space: nowrap;
  z-index: 10;
}
.tag-top-right { top: 28px; right: 20px; }
.tag-bottom-left { bottom: 16px; left: 8px; }
.tag-bottom-right { bottom: 16px; right: 8px; }

/* 流程区 */
.home-process {
  padding: 8px 20px 16px;
}
.home-process__title {
  font-size: 13px;
  font-weight: 700;
  color: #e63946;
  text-align: center;
  margin-bottom: 12px;
}
.home-process__steps {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 8px;
}
.home-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
  gap: 3px;
}
.home-step__circle {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: #b8b9bc;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  font-weight: 700;
  color: #fff;
  margin-bottom: 4px;

  &.home-step__circle--active {
    background: #e63946;
  }
}
.home-step__label {
  font-size: 9px;
  color: #999;
}
.home-step__text {
  font-size: 11px;
  font-weight: 600;
  color: #333;
}
.home-step__line {
  width: 32px; height: 2px;
  background: #e0e0e0;
  margin: 0 4px 24px;
  flex-shrink: 0;
}

/* CTA 按钮 */
.home-cta {
  padding: 8px 20px 0;
}
.home-cta__btn {
  width: 100%;
  background: linear-gradient(135deg, #e63946 0%, #ff6b9d 100%);
  border: none;
  border-radius: 28px;
  padding: 14px;
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 6px 20px rgba(230,57,70,0.3);
  letter-spacing: 0.02em;

  &:hover { opacity: 0.92; }
  &:active { transform: scale(0.98); }
}

/* ===== 测试选择页 ===== */
.page-test-select {
  background: linear-gradient(180deg, #fff5f5 0%, #f5f5f5 50%, #fff 100%);
  padding: 12px;
  box-sizing: border-box;
}
.ts-intro-card {
  background: #fff;
  border-radius: 14px;
  padding: 18px;
  margin-bottom: 10px;
  text-align: center;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.ts-intro__title {
  font-size: 14px;
  font-weight: 700;
  color: #e63946;
  margin-bottom: 6px;
}
.ts-intro__desc {
  font-size: 11px;
  color: #888;
  line-height: 1.6;
}
.ts-entry-card {
  background: #fff;
  border-radius: 14px;
  padding: 14px 16px;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 14px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  cursor: pointer;
  border: 2px solid transparent;
  transition: all 0.18s;

  &:hover {
    border-color: rgba(230,57,70,0.2);
    background: linear-gradient(135deg, rgba(230,57,70,0.02) 0%, rgba(255,107,157,0.02) 100%);
  }
  &:active { opacity: 0.9; }
}
.ts-icon-wrap {
  width: 44px; height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.ts-icon-mbti { background: linear-gradient(135deg, rgba(124,58,237,0.15), rgba(139,92,246,0.2)); }
.ts-icon-pdp  { background: linear-gradient(135deg, rgba(230,57,70,0.12), rgba(255,107,157,0.15)); }
.ts-icon-disc { background: linear-gradient(135deg, rgba(59,130,246,0.12), rgba(96,165,250,0.18)); }
.ts-icon-gaokao  { background: linear-gradient(135deg, rgba(79,70,229,0.18), rgba(129,140,248,0.22)); border: 1px solid rgba(99,102,241,0.25); }
.ts-icon-ai-chat { background: linear-gradient(135deg, rgba(124,58,237,0.16), rgba(99,102,241,0.2)); }
.ts-icon-ai-face { background: linear-gradient(135deg, rgba(236,72,153,0.14), rgba(244,114,182,0.18)); }
.ts-emoji { font-size: 22px; }
.ts-content { flex: 1; min-width: 0; }
.ts-name {
  font-size: 13px;
  font-weight: 600;
  color: #333;
  margin-bottom: 3px;
}
.ts-brief {
  font-size: 10px;
  color: #888;
  line-height: 1.4;
}
.ts-arrow {
  font-size: 14px;
  color: #e63946;
  font-weight: 600;
  flex-shrink: 0;
}

/* ===== 我的页面 ===== */
.page-profile {
  background: #F9FAFB;
  min-height: 100%;
  padding-bottom: 16px;
}
.profile-topbar {
  padding: 14px 18px 12px;
}
.profile-topbar__title {
  font-size: 22px;
  font-weight: 800;
  color: #111827;
  letter-spacing: -0.5px;
}
.profile-user-card {
  margin: 0 14px 12px;
  background: #fff;
  border-radius: 20px;
  padding: 18px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.05);
  cursor: pointer;
}
.profile-avatar-wrap {
  position: relative;
  flex-shrink: 0;
}
.profile-avatar-ring {
  width: 60px; height: 60px;
  border-radius: 50%;
  background: linear-gradient(135deg, #FFDEE9, #B5FFFC);
  padding: 2px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: inset 0 2px 8px rgba(0,0,0,0.05);
}
.profile-avatar-letter {
  width: 52px; height: 52px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #fff;
  span { font-size: 22px; font-weight: 700; color: #fff; }
}
.profile-online-dot {
  position: absolute;
  bottom: 2px; right: 2px;
  width: 12px; height: 12px;
  background: #22C55E;
  border-radius: 50%;
  border: 2px solid #fff;
}
.profile-user-meta {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.profile-nickname {
  font-size: 16px;
  font-weight: 700;
  color: #111827;
}
.profile-tags-row {
  display: flex;
  gap: 5px;
}
.profile-tag {
  font-size: 10px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 6px;

  &.profile-tag--purple { background: #EDE9FE; color: #7C3AED; }
  &.profile-tag--blue   { background: #DBEAFE; color: #2563EB; }
  &.profile-tag--orange { background: #FEF3C7; color: #D97706; }
}
.profile-chevron {
  font-size: 22px;
  color: #D1D5DB;
  flex-shrink: 0;
}
/* 数据条 */
.profile-stats {
  display: flex;
  margin: 0 14px 12px;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 2px 12px rgba(15,23,42,0.04);
  border: 1px solid #EEF2F7;
  overflow: hidden;
}
.profile-stat {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  padding: 14px 8px;
  position: relative;

  & + .profile-stat::before {
    content: '';
    position: absolute; left: 0; top: 10px; bottom: 10px;
    width: 1px;
    background: #E2E8F0;
  }
  &.profile-stat--highlight .profile-stat__v { color: #7c3aed; }
}
.profile-stat__v {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.15;
}
.profile-stat__l {
  font-size: 10px;
  color: #64748b;
}
/* 小节 */
.profile-section {
  padding: 0 14px;
  margin-bottom: 12px;
}
.profile-sec-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}
.profile-sec-head__title {
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
}
.profile-sec-head__link {
  font-size: 11px;
  color: #64748b;
}
/* 测评卡横滑 */
.profile-cards-scroll {
  display: flex;
  gap: 10px;
  overflow-x: auto;
  padding-bottom: 4px;
  scrollbar-width: none;
  &::-webkit-scrollbar { display: none; }
}
.profile-result-card {
  width: 110px;
  height: 100px;
  border-radius: 16px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  position: relative;
  overflow: hidden;
  box-shadow: 0 4px 16px rgba(0,0,0,0.06);
  background: #fff;
  flex-shrink: 0;
  cursor: pointer;
  &:active { opacity: 0.9; }
}
.profile-card-deco {
  position: absolute;
  top: -12px; right: -12px;
  width: 50px; height: 50px;
  border-radius: 50%;
  opacity: 0.25;
}
.profile-card-purple .profile-card-deco { background: #DDD6FE; }
.profile-card-orange .profile-card-deco { background: #FDE68A; }
.profile-card-blue   .profile-card-deco { background: #BFDBFE; }
.profile-card-rose   .profile-card-deco { background: #FECDD3; }

.profile-card-icon-wrap {
  width: 28px; height: 28px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
}
.profile-card-icon-purple { background: #EDE9FE; }
.profile-card-icon-orange { background: #FEF3C7; }
.profile-card-icon-blue   { background: #DBEAFE; }
.profile-card-icon-rose   { background: #FFE4E6; }
.profile-card-label {
  font-size: 9px;
  color: #9CA3AF;
  font-weight: 500;
}
.profile-card-value {
  font-size: 18px;
  font-weight: 800;
  letter-spacing: -0.5px;
}
.profile-card-value-purple { color: #7C3AED; }
.profile-card-value-orange { color: #D97706; }
.profile-card-value-blue   { color: #2563EB; }
.profile-card-value-rose   { color: #E11D48; }
.profile-card-time {
  font-size: 9px;
  color: #D1D5DB;
}
/* 快捷入口 */
.profile-quick-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
  background: #fff;
  border-radius: 18px;
  padding: 14px 10px;
  box-shadow: 0 4px 16px rgba(15,23,42,0.05);
  border: 1px solid #F1F5F9;
}
.profile-quick-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 8px 4px;
  border-radius: 12px;
  cursor: pointer;
  &:active { background: #F8FAFC; }
}
.profile-quick-ic {
  width: 40px; height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  &.profile-quick-ic--purple  { background: #F5F3FF; }
  &.profile-quick-ic--emerald { background: #ECFDF5; }
  &.profile-quick-ic--indigo  { background: #EEF2FF; }
  &.profile-quick-ic--blue    { background: #EFF6FF; }
}
.profile-quick-label {
  font-size: 10px;
  color: #374151;
  text-align: center;
  line-height: 1.2;
}

/* ===== 测评结果页 ===== */
.page-result {
  background: #F9FAFB;
  min-height: 100%;
  padding-bottom: 24px;
}
.result-hero {
  background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%);
  padding: 24px 20px 28px;
  text-align: center;
  position: relative;
  overflow: hidden;

  &::before {
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 160px; height: 160px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
  }
  &::after {
    content: '';
    position: absolute;
    bottom: -60px; left: -30px;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: rgba(255,255,255,0.06);
  }
}
.result-hero__type-badge {
  display: inline-block;
  background: rgba(255,255,255,0.2);
  border: 1px solid rgba(255,255,255,0.35);
  border-radius: 20px;
  padding: 4px 18px;
  font-size: 22px;
  font-weight: 900;
  color: #fff;
  letter-spacing: 0.08em;
  margin-bottom: 8px;
}
.result-hero__name {
  font-size: 18px;
  font-weight: 800;
  color: #fff;
  margin-bottom: 6px;
}
.result-hero__desc {
  font-size: 11px;
  color: rgba(255,255,255,0.8);
  line-height: 1.6;
}
/* 四维度 */
.result-dims {
  background: #fff;
  border-radius: 16px;
  padding: 16px;
  margin: 12px 14px 0;
  box-shadow: 0 4px 16px rgba(0,0,0,0.05);
  border: 1px solid #E5E7EB;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.result-dim {}
.result-dim__labels {
  display: flex;
  justify-content: space-between;
  margin-bottom: 4px;
}
.result-dim__l, .result-dim__r {
  font-size: 10px;
  font-weight: 600;
  color: #6B7280;
}
.result-dim__bar {
  height: 8px;
  background: #F3F4F6;
  border-radius: 4px;
  position: relative;
  overflow: visible;
}
.result-dim__fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.6s cubic-bezier(0.34,1.56,0.64,1);
}
.result-dim__dot {
  position: absolute;
  top: 50%; transform: translate(-50%, -50%);
  width: 14px; height: 14px;
  border-radius: 50%;
  background: #fff;
  border: 2px solid #7c3aed;
  box-shadow: 0 2px 8px rgba(124,58,237,0.3);
}
.result-dim__pct {
  text-align: right;
  font-size: 10px;
  font-weight: 700;
  color: #7c3aed;
  margin-top: 2px;
}
/* 团队匹配 */
.result-team-section {
  background: #fff;
  border-radius: 16px;
  padding: 14px 16px;
  margin: 12px 14px 0;
  box-shadow: 0 4px 16px rgba(0,0,0,0.05);
  border: 1px solid #E5E7EB;
}
.result-section-title {
  font-size: 13px;
  font-weight: 700;
  color: #111827;
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 12px;
}
.result-team-cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}
.result-team-card {
  background: #F9FAFB;
  border-radius: 12px;
  padding: 10px 8px;
  text-align: center;
  border: 1px solid #E5E7EB;
}
.result-team-card__type {
  font-size: 16px;
  font-weight: 900;
  letter-spacing: 0.04em;
  margin-bottom: 2px;
}
.result-team-card__role {
  font-size: 9px;
  color: #9CA3AF;
  margin-bottom: 4px;
}
.result-team-card__reason {
  font-size: 9px;
  color: #6B7280;
  line-height: 1.4;
}
/* 购买按钮 */
.result-purchase-btn {
  padding: 14px;
}
.result-purchase-btn__inner {
  width: 100%;
  background: linear-gradient(135deg, #e63946 0%, #ff6b9d 100%);
  border: none;
  border-radius: 24px;
  padding: 14px;
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 6px 20px rgba(230,57,70,0.3);
  letter-spacing: 0.02em;
  &:hover { opacity: 0.92; }
}

/* ===== 底部 TabBar ===== */
.mp-tabbar {
  height: 76px;
  background: #fff;
  border-top: 1px solid #E5E7EB;
  display: flex;
  align-items: flex-end;
  justify-content: space-around;
  padding: 6px 8px 10px;
  flex-shrink: 0;
  box-shadow: 0 -4px 24px rgba(15,23,42,0.06);
}
.mp-tabbar__item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  padding-bottom: 2px;
  cursor: pointer;
}
.mp-tabbar__icon {
  display: flex;
  align-items: center;
  justify-content: center;
}
.mp-tabbar__text {
  font-size: 10px;
  color: #999;
  &.active { color: #7c3aed; font-weight: 600; }
}
/* 中间凸起按钮 */
.mp-tabbar__fab-slot {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  position: relative;
}
.mp-tabbar__fab {
  display: flex;
  flex-direction: column;
  align-items: center;
  transform: translateY(-24px);
  margin-bottom: -20px;
  cursor: pointer;
}
.mp-tabbar__fab-circle {
  width: 52px; height: 52px;
  border-radius: 50%;
  background: linear-gradient(145deg, #7c3aed 0%, #a78bfa 55%, #8b5cf6 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  border: 3px solid #fff;
  box-shadow: 0 8px 24px rgba(124,58,237,0.45);
  margin-bottom: 4px;
}
.mp-tabbar__fab-text {
  font-size: 10px;
  color: #999;
  line-height: 1.2;
}

/* 手机底部横条 */
.phone-home-indicator {
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fff;
  flex-shrink: 0;

  &::after {
    content: '';
    width: 120px; height: 4px;
    background: #1a1a2e;
    border-radius: 2px;
    opacity: 0.3;
  }
}
</style>
