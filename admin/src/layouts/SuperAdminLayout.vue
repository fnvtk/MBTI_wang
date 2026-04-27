<template>
  <div class="superadmin-layout">
    <!-- 顶部导航栏 -->
    <header class="layout-header">
      <div class="header-content">
        <div class="header-left">
          <button class="menu-toggle" @click="toggleSidebar" :aria-label="sidebarOpen ? '关闭菜单' : '打开菜单'">
            <svg v-if="!sidebarOpen" width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
            </svg>
            <svg v-else width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
            </svg>
          </button>
          <div class="logo">
            <div class="logo-icon">
              <!-- 超管 logo：盾牌+星标 -->
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                <path d="M12 2L3 6v6c0 5.25 3.75 10.15 9 11.25C17.25 22.15 21 17.25 21 12V6L12 2z" fill="rgba(255,255,255,0.2)" stroke="rgba(255,255,255,0.9)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M9 12l2 2 4-4" stroke="rgba(255,255,255,0.95)" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <div class="logo-text-block">
              <span class="logo-text">MBTI 超级管理</span>
              <span class="logo-badge">Super Admin</span>
            </div>
          </div>
        </div>

        <div class="header-right">
          <button class="admin-entry-btn" @click="goAdminConsole">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
              <rect x="3" y="3" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.75"/>
              <path d="M8 21h8M12 17v4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
            </svg>
            <span>管理后台</span>
          </button>
          <button class="logout-btn" @click="handleLogout">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
              <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
              <polyline points="16 17 21 12 16 7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
              <line x1="21" y1="12" x2="9" y2="12" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
            </svg>
            <span>退出</span>
          </button>
        </div>
      </div>
    </header>

    <!-- 侧边栏 -->
    <aside :class="['layout-sidebar', { 'sidebar-open': sidebarOpen }]">
      <nav class="sidebar-nav">
        <!-- 主导航 -->
        <div class="nav-section">
          <div class="nav-section-label">运营总控</div>
          <template v-for="item in navMainItems" :key="item.path">
            <button
              :class="['nav-item', { active: isActive(item.path) || hasActiveChild(item), 'has-children': !!(item.children?.length) }]"
              @click="onNavClick(item)"
            >
              <span class="nav-icon-wrap">
                <!-- 总览 -->
                <svg v-if="item.key === 'ops'" width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <rect x="3" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.75"/>
                  <rect x="14" y="3" width="7" height="4" rx="1.5" stroke="currentColor" stroke-width="1.75"/>
                  <rect x="3" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.75"/>
                  <rect x="14" y="11" width="7" height="10" rx="1.5" stroke="currentColor" stroke-width="1.75"/>
                </svg>
                <!-- 企业管理 -->
                <svg v-else-if="item.key === 'enterprises'" width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <path d="M3 21V7l9-4 9 4v14" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M9 21V15h6v6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M9 9h.01M12 9h.01M15 9h.01M9 12h.01M12 12h.01M15 12h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <!-- 订单和财务 -->
                <svg v-else-if="item.key === 'commerce'" width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" stroke="currentColor" stroke-width="1.75"/>
                  <path d="M12 6v6l4 2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M14.5 16.5c-.83.5-1.62.5-2.5.5-2.76 0-5-2.24-5-5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                </svg>
                <!-- 分销管理 -->
                <svg v-else-if="item.key === 'distribution'" width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <circle cx="18" cy="5" r="3" stroke="currentColor" stroke-width="1.75"/>
                  <circle cx="6" cy="12" r="3" stroke="currentColor" stroke-width="1.75"/>
                  <circle cx="18" cy="19" r="3" stroke="currentColor" stroke-width="1.75"/>
                  <line x1="8.59" y1="13.51" x2="15.42" y2="17.49" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                  <line x1="15.41" y1="6.51" x2="8.59" y2="10.49" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                </svg>
                <!-- 智能算力 -->
                <svg v-else-if="item.key === 'ai'" width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <path d="M12 2a4 4 0 014 4v1h1a3 3 0 013 3v2a3 3 0 01-3 3h-1v1a4 4 0 01-4 4 4 4 0 01-4-4v-1H7a3 3 0 01-3-3v-2a3 3 0 013-3h1V6a4 4 0 014-4z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                  <circle cx="9" cy="10" r="1" fill="currentColor"/>
                  <circle cx="15" cy="10" r="1" fill="currentColor"/>
                  <path d="M9.5 15c.83.5 1.66.75 2.5.75s1.67-.25 2.5-.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <!-- 默认 -->
                <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75"/>
                </svg>
              </span>
              <span class="nav-label">{{ item.label }}</span>
              <svg
                v-if="item.children?.length"
                class="nav-arrow"
                :class="{ 'rotated': isExpanded(item.path) || hasActiveChild(item) }"
                width="14" height="14" viewBox="0 0 24 24" fill="none"
              >
                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
            <!-- 子菜单 -->
            <div
              v-if="item.children?.length && (isExpanded(item.path) || hasActiveChild(item))"
              class="nav-sub"
            >
              <button
                v-for="sub in item.children"
                :key="sub.path"
                :class="['nav-sub-item', { active: isActive(sub.path) }]"
                @click="navigateTo(sub.path)"
              >
                <span class="nav-sub-dot"></span>
                <span>{{ sub.label }}</span>
              </button>
            </div>
          </template>
        </div>

        <!-- 系统 -->
        <div class="nav-section nav-section--footer">
          <div class="nav-section-label">系统</div>
          <button
            v-for="item in navFooterItems"
            :key="item.path"
            :class="['nav-item', { active: isActive(item.path) }]"
            @click="navigateTo(item.path)"
          >
            <span class="nav-icon-wrap">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.75"/>
                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z" stroke="currentColor" stroke-width="1.75"/>
              </svg>
            </span>
            <span class="nav-label">{{ item.label }}</span>
          </button>
        </div>
      </nav>
    </aside>

    <!-- 遮罩层 -->
    <transition name="fade">
      <div v-if="sidebarOpen" class="sidebar-overlay" @click="toggleSidebar" />
    </transition>

    <!-- 主内容区 -->
    <main class="layout-main">
      <router-view />
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const sidebarOpen = ref(false)

interface NavItem {
  path: string
  key: string
  label: string
  children?: NavItem[]
}

const navMainItems: NavItem[] = [
  { path: '/superadmin/ops',           key: 'ops',           label: '总览' },
  { path: '/superadmin/enterprises',   key: 'enterprises',   label: '企业管理' },
  { path: '/superadmin/commerce',      key: 'commerce',      label: '订单和财务' },
  { path: '/superadmin/distribution',  key: 'distribution',  label: '分销管理' },
  { path: '/superadmin/ai-config',     key: 'ai',            label: '智能算力' }
]

const navFooterItems: NavItem[] = [
  { path: '/superadmin/settings', key: 'settings', label: '系统设置' }
]

const expandedPaths = ref<Set<string>>(new Set())

const isActive = (path: string) => route.path === path || route.path.startsWith(`${path}/`)

const hasActiveChild = (item: NavItem): boolean =>
  !!(item.children?.some(c => isActive(c.path)))

const isExpanded = (p: string) => expandedPaths.value.has(p)

const onNavClick = (item: NavItem) => {
  if (item.children?.length) {
    if (expandedPaths.value.has(item.path)) {
      expandedPaths.value.delete(item.path)
    } else {
      expandedPaths.value.add(item.path)
    }
    if (expandedPaths.value.has(item.path) && !hasActiveChild(item)) {
      navigateTo(item.children[0].path)
    }
  } else {
    navigateTo(item.path)
  }
}

const goAdminConsole = () => {
  const url = router.resolve({ path: '/admin/dashboard' }).href
  window.open(url, '_blank', 'noopener,noreferrer')
}

const toggleSidebar = () => {
  sidebarOpen.value = !sidebarOpen.value
}

const navigateTo = (path: string) => {
  router.push(path)
  if (window.innerWidth < 1024) sidebarOpen.value = false
}

const handleLogout = async () => {
  try {
    await authStore.superAdminLogout()
    router.push('/superadmin/login')
  } catch {
    router.push('/superadmin/login')
  }
}
</script>

<style scoped lang="scss">
$primary: #1E40AF;
$primary-soft: #DBEAFE;
$sidebar-w: 240px;
$header-h: 60px;

.superadmin-layout {
  min-height: 100vh;
  background: #F1F5F9;
}

/* ── Header ── */
.layout-header {
  position: fixed;
  top: 0; left: 0; right: 0;
  height: $header-h;
  background: linear-gradient(135deg, #1E3A8A 0%, #1D4ED8 100%);
  z-index: 1000;
  box-shadow: 0 2px 16px rgba(30,58,138,0.35);

  .header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 100%;
    padding: 0 24px;
  }

  .header-left {
    display: flex;
    align-items: center;
    gap: 14px;
  }

  .header-right {
    display: flex;
    align-items: center;
    gap: 10px;
  }
}

.menu-toggle {
  display: none;
  width: 36px; height: 36px;
  border: none;
  background: rgba(255,255,255,0.1);
  border-radius: 8px;
  cursor: pointer;
  color: #fff;
  align-items: center;
  justify-content: center;
  transition: background 0.15s;

  &:hover { background: rgba(255,255,255,0.18); }

  @media (max-width: 1023px) { display: flex; }
}

.logo {
  display: flex;
  align-items: center;
  gap: 10px;

  .logo-icon {
    width: 36px; height: 36px;
    border-radius: 10px;
    background: rgba(255,255,255,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 1px solid rgba(255,255,255,0.25);
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
  }

  .logo-text-block {
    display: flex;
    align-items: center;
    gap: 8px;

    @media (max-width: 480px) { display: none; }
  }

  .logo-text {
    font-size: 15px;
    font-weight: 700;
    color: #fff;
    letter-spacing: -0.01em;
  }

  .logo-badge {
    font-size: 9.5px;
    font-weight: 700;
    color: #93C5FD;
    background: rgba(147,197,253,0.15);
    border: 1px solid rgba(147,197,253,0.3);
    border-radius: 5px;
    padding: 2px 7px;
    letter-spacing: 0.04em;
  }
}

.admin-entry-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border: 1px solid rgba(255,255,255,0.25);
  background: rgba(255,255,255,0.12);
  color: #fff;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;

  &:hover {
    background: rgba(255,255,255,0.22);
    border-color: rgba(255,255,255,0.4);
  }

  span { @media (max-width: 640px) { display: none; } }
}

.logout-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border: 1px solid rgba(255,255,255,0.15);
  background: transparent;
  color: rgba(255,255,255,0.7);
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;

  &:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
    border-color: rgba(255,255,255,0.3);
  }

  span { @media (max-width: 640px) { display: none; } }
}

/* ── Sidebar ── */
.layout-sidebar {
  position: fixed;
  left: 0;
  top: $header-h;
  bottom: 0;
  width: $sidebar-w;
  background: #fff;
  border-right: 1px solid #E2E8F0;
  z-index: 999;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  transition: transform 0.28s cubic-bezier(0.4,0,0.2,1);

  @media (max-width: 1023px) {
    transform: translateX(-100%);
    box-shadow: 4px 0 24px rgba(0,0,0,0.1);

    &.sidebar-open { transform: translateX(0); }
  }
}

.sidebar-nav {
  flex: 1;
  padding: 16px 10px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.nav-section {
  display: flex;
  flex-direction: column;
  gap: 2px;

  &--footer {
    border-top: 1px solid #F1F5F9;
    padding-top: 16px;
  }
}

.nav-section-label {
  font-size: 10.5px;
  font-weight: 700;
  color: #94A3B8;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  padding: 0 10px;
  margin-bottom: 6px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 10px 12px;
  border-radius: 10px;
  font-size: 13.5px;
  font-weight: 500;
  color: #4B5563;
  background: transparent;
  border: none;
  cursor: pointer;
  text-align: left;
  width: 100%;
  transition: all 0.15s;
  position: relative;

  &:hover {
    background: #F8FAFC;
    color: #1E293B;
    .nav-icon-wrap { background: #F1F5F9; color: #334155; }
  }

  &.active {
    background: $primary-soft;
    color: $primary;
    font-weight: 600;

    .nav-icon-wrap {
      background: rgba(30,64,175,0.1);
      color: $primary;
    }

    &::before {
      content: '';
      position: absolute;
      left: -10px;
      top: 50%;
      transform: translateY(-50%);
      width: 3px;
      height: 20px;
      background: $primary;
      border-radius: 0 3px 3px 0;
    }
  }
}

.nav-icon-wrap {
  width: 32px; height: 32px;
  border-radius: 8px;
  background: #F1F5F9;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #6B7280;
  transition: all 0.15s;
}

.nav-label { flex: 1; }

.nav-arrow {
  color: #CBD5E1;
  transition: transform 0.2s;

  &.rotated { transform: rotate(180deg); }
}

.nav-sub {
  display: flex;
  flex-direction: column;
  gap: 1px;
  margin: 2px 0 4px 42px;
  padding-left: 12px;
  border-left: 2px solid #E2E8F0;
}

.nav-sub-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 10px;
  border-radius: 7px;
  font-size: 12.5px;
  font-weight: 500;
  color: #64748B;
  background: transparent;
  border: none;
  cursor: pointer;
  text-align: left;
  width: 100%;
  transition: all 0.15s;

  &:hover {
    background: #F8FAFC;
    color: #1E293B;
  }

  &.active {
    color: $primary;
    font-weight: 600;

    .nav-sub-dot { background: $primary; }
  }
}

.nav-sub-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: #CBD5E1;
  flex-shrink: 0;
  transition: background 0.15s;
}

/* ── Overlay ── */
.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.3);
  z-index: 998;
  backdrop-filter: blur(1px);

  @media (min-width: 1024px) { display: none; }
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.25s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* ── Main ── */
.layout-main {
  padding-top: $header-h;
  min-height: 100vh;
  background: #F1F5F9;

  @media (min-width: 1024px) {
    padding-left: $sidebar-w;
  }
}
</style>
