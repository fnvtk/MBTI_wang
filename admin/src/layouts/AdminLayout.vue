<template>
  <div class="admin-layout">
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
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path d="M12 2C8.5 2 5.5 4.5 5 8c-.3 2 .5 3.9 1.9 5.3L5 21l7-3 7 3-1.9-7.7C18.5 11.9 19.3 10 19 8c-.5-3.5-3.5-6-7-6z" fill="white" opacity=".9"/>
              </svg>
            </div>
            <div class="logo-text-block">
              <span class="logo-text">MBTI 管理后台</span>
              <span class="logo-badge">企业版</span>
            </div>
          </div>
        </div>

        <div class="header-right">
          <div class="header-user">
            <div class="user-dot"></div>
            <span class="user-name">{{ adminRoleLabel }}</span>
          </div>
          <button class="logout-btn" @click="handleLogout">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>退出</span>
          </button>
        </div>
      </div>
    </header>

    <!-- 侧边栏 -->
    <aside :class="['layout-sidebar', { 'sidebar-open': sidebarOpen }]">
      <nav class="sidebar-nav">
        <div class="nav-section">
          <div class="nav-section-label">主要功能</div>
          <button
            v-for="item in navItems"
            :key="item.path"
            :class="['nav-item', { active: isActive(item.path) }]"
            @click="navigateTo(item.path)"
          >
            <span class="nav-icon-wrap">
              <component :is="item.icon" class="nav-icon" />
            </span>
            <span class="nav-label">{{ item.label }}</span>
            <span v-if="item.badge" class="nav-badge">{{ item.badge }}</span>
          </button>
        </div>
      </nav>

      <div class="sidebar-footer">
        <button class="sidebar-logout" @click="handleLogout">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          退出登录
        </button>
      </div>
    </aside>

    <!-- 遮罩层（移动端） -->
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
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getAdminRole } from '@/utils/authStorage'
import {
  DataLine,
  User,
  ShoppingCart,
  Share,
  Setting
} from '@element-plus/icons-vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const sidebarOpen = ref(false)

const adminRoleLabel = computed(() => {
  const r = getAdminRole()
  if (r === 'admin') return '管理员'
  if (r === 'enterprise_admin') return '企业管理员'
  return '管理员'
})

const navItems = [
  { path: '/admin/dashboard', icon: DataLine, label: '企业概览' },
  { path: '/admin/users', icon: User, label: '用户运营' },
  { path: '/admin/orders', icon: ShoppingCart, label: '订单运营' },
  { path: '/admin/distribution', icon: Share, label: '分销推广' },
  { path: '/admin/settings', icon: Setting, label: '企业设置' },
]

const isActive = (path: string) => {
  if (path === '/admin/users') {
    // 合作意向也归属到用户运营高亮
    return route.path === '/admin/users' || route.path === '/admin/cooperation-choices'
  }
  return route.path === path
}

const toggleSidebar = () => {
  sidebarOpen.value = !sidebarOpen.value
}

const navigateTo = (path: string) => {
  router.push(path)
  if (window.innerWidth < 1024) {
    sidebarOpen.value = false
  }
}

const handleLogout = async () => {
  try {
    await authStore.adminLogout()
    router.push('/admin/login')
  } catch (error) {
    router.push('/admin/login')
  }
}
</script>

<style scoped lang="scss">
$primary: #4F46E5;
$primary-soft: #EEF2FF;
$sidebar-w: 220px;
$header-h: 56px;

.admin-layout {
  min-height: 100vh;
  background-color: #F4F6FB;
}

/* ── Header ── */
.layout-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: $header-h;
  background: #fff;
  border-bottom: 1px solid #EAECF0;
  z-index: 1000;
  box-shadow: 0 1px 3px rgba(16,24,40,0.05);

  .header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 100%;
    padding: 0 20px;
  }

  .header-left {
    display: flex;
    align-items: center;
    gap: 14px;
  }

  .header-right {
    display: flex;
    align-items: center;
    gap: 12px;
  }
}

.menu-toggle {
  display: none;
  width: 36px;
  height: 36px;
  border: none;
  background: transparent;
  border-radius: 8px;
  cursor: pointer;
  color: #6B7280;
  align-items: center;
  justify-content: center;
  transition: background 0.15s;

  &:hover { background: #F3F4F6; }

  @media (max-width: 1023px) {
    display: flex;
  }
}

.logo {
  display: flex;
  align-items: center;
  gap: 10px;

  .logo-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(79,70,229,0.28);
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
    color: #111827;
    letter-spacing: -0.01em;
  }

  .logo-badge {
    font-size: 10px;
    font-weight: 600;
    color: $primary;
    background: $primary-soft;
    border-radius: 5px;
    padding: 2px 7px;
  }
}

.header-user {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 5px 12px;
  background: #F9FAFB;
  border: 1px solid #EAECF0;
  border-radius: 20px;

  .user-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #10B981;
    flex-shrink: 0;
  }

  .user-name {
    font-size: 12.5px;
    font-weight: 500;
    color: #374151;
  }

  @media (max-width: 640px) { display: none; }
}

.logout-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border: 1px solid #FECACA;
  background: #FFF5F5;
  color: #EF4444;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;

  &:hover {
    background: #FEE2E2;
    border-color: #FCA5A5;
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
  border-right: 1px solid #EAECF0;
  z-index: 999;
  display: flex;
  flex-direction: column;
  transition: transform 0.28s cubic-bezier(0.4,0,0.2,1);

  @media (max-width: 1023px) {
    transform: translateX(-100%);
    box-shadow: 4px 0 20px rgba(0,0,0,0.08);

    &.sidebar-open {
      transform: translateX(0);
    }
  }
}

.sidebar-nav {
  flex: 1;
  overflow-y: auto;
  padding: 16px 10px 8px;
}

.nav-section {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.nav-section-label {
  font-size: 10.5px;
  font-weight: 600;
  color: #9CA3AF;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  padding: 0 10px;
  margin-bottom: 6px;
  margin-top: 4px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
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
    background: #F9FAFB;
    color: #111827;

    .nav-icon-wrap {
      background: #F3F4F6;
    }
  }

  &.active {
    background: $primary-soft;
    color: $primary;
    font-weight: 600;

    .nav-icon-wrap {
      background: rgba(79,70,229,0.12);
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
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: #F3F4F6;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #6B7280;
  transition: all 0.15s;

  .nav-icon {
    font-size: 16px;
  }
}

.nav-label {
  flex: 1;
}

.nav-badge {
  font-size: 10px;
  font-weight: 700;
  background: #FEE2E2;
  color: #EF4444;
  border-radius: 10px;
  padding: 2px 7px;
}

.sidebar-footer {
  padding: 12px 10px;
  border-top: 1px solid #F3F4F6;

  .sidebar-logout {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 8px 12px;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: #9CA3AF;
    font-size: 12.5px;
    cursor: pointer;
    transition: all 0.15s;

    &:hover {
      background: #FFF5F5;
      color: #EF4444;
    }
  }
}

/* ── Overlay ── */
.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.25);
  z-index: 998;
  backdrop-filter: blur(1px);

  @media (min-width: 1024px) { display: none; }
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* ── Main ── */
.layout-main {
  padding-top: $header-h;
  min-height: 100vh;
  background: #F4F6FB;

  @media (min-width: 1024px) {
    padding-left: $sidebar-w;
  }
}
</style>
