<template>
  <div class="admin-layout">
    <!-- 顶部导航栏 -->
    <header class="layout-header">
      <div class="header-content">
        <div class="header-left">
          <el-button
            class="menu-toggle"
            :icon="sidebarOpen ? Close : Menu"
            circle
            @click="toggleSidebar"
          />
          <div class="logo">
            <div class="logo-icon">
              <el-icon><Lock /></el-icon>
            </div>
            <span class="logo-text">管理后台</span>
          </div>
        </div>

        <div class="header-right">
          <el-button
            text
            class="nav-link super-admin"
            @click="router.push('/superadmin')"
          >
            超管端
          </el-button>
          <el-button
            text
            class="nav-link"
            @click="router.push('/')"
          >
            <el-icon><HomeFilled /></el-icon>
            <span>前台</span>
          </el-button>
          <el-button
            text
            class="nav-link logout"
            @click="handleLogout"
          >
            <el-icon><SwitchButton /></el-icon>
            <span>退出</span>
          </el-button>
        </div>
      </div>
    </header>

    <!-- 侧边栏 -->
    <aside :class="['layout-sidebar', { 'sidebar-open': sidebarOpen }]">
      <nav class="sidebar-nav">
        <el-button
          v-for="item in navItems"
          :key="item.path"
          :class="['nav-item', { active: isActive(item.path) }]"
          text
          @click="navigateTo(item.path)"
        >
          <el-icon class="nav-icon">
            <component :is="item.icon" />
          </el-icon>
          <span class="nav-label">{{ item.label }}</span>
        </el-button>
      </nav>
    </aside>

    <!-- 遮罩层（移动端） -->
    <div
      v-if="sidebarOpen"
      class="sidebar-overlay"
      @click="toggleSidebar"
    />

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
import {
  Menu,
  Close,
  Lock,
  HomeFilled,
  SwitchButton,
  DataLine,
  User,
  ShoppingCart,
  Share,
  Document,
  Setting,
  PriceTag,
  WalletFilled
} from '@element-plus/icons-vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const sidebarOpen = ref(false)

const navItems = [
  { path: '/admin/dashboard', icon: DataLine, label: '数据概览' },
  { path: '/admin/users', icon: User, label: '用户管理' },
  { path: '/admin/orders', icon: ShoppingCart, label: '订单管理' },
  { path: '/admin/distribution', icon: Share, label: '分销管理' },
  { path: '/admin/questions', icon: Document, label: '题库管理' },
  { path: '/admin/pricing', icon: PriceTag, label: '价格设置' },
  { path: '/admin/finance', icon: WalletFilled, label: '企业余额' },
  { path: '/admin/settings', icon: Setting, label: '系统设置' },
]

const isActive = (path: string) => {
  return route.path === path
}

const toggleSidebar = () => {
  sidebarOpen.value = !sidebarOpen.value
}

const navigateTo = (path: string) => {
  router.push(path)
  // 移动端导航后关闭侧边栏
  if (window.innerWidth < 1024) {
    sidebarOpen.value = false
  }
}

const handleLogout = async () => {
  try {
    await authStore.adminLogout()
    router.push('/admin/login')
  } catch (error) {
    console.error('退出登录失败:', error)
    // 即使API调用失败，也清除本地状态并跳转
    router.push('/admin/login')
  }
}
</script>

<style scoped lang="scss">
.admin-layout {
  min-height: 100vh;
  background-color: #f9fafb;
}

.layout-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 56px;
  background-color: #ffffff;
  border-bottom: 1px solid #f3f4f6;
  z-index: 1000;

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

    .menu-toggle {
      @media (min-width: 1024px) {
        display: none;
      }
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 10px;

      .logo-icon {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background-color: #7c3aed;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 18px;
      }

      .logo-text {
        font-weight: 600;
        font-size: 17px;
        color: #111827;

        @media (max-width: 640px) {
          display: none;
        }
      }
    }
  }

  .header-right {
    display: flex;
    align-items: center;
    gap: 10px;

    .nav-link {
      font-size: 13px;
      color: #6b7280;
      padding: 4px 8px;
      display: flex;
      align-items: center;
      gap: 4px;
      height: auto;

      &:hover {
        background-color: transparent;
        color: #111827;
      }

      &.super-admin {
        color: #7c3aed;
        font-weight: 500;
        padding-right: 14px;
      }

      &.logout {
        color: #ef4444;
      }

      .el-icon {
        font-size: 15px;
      }
    }
  }
}

.layout-sidebar {
  position: fixed;
  left: 0;
  top: 56px;
  bottom: 0;
  width: 210px;
  background-color: #ffffff;
  border-right: 1px solid #f3f4f6;
  z-index: 999;
  overflow-y: auto;
  transition: transform 0.3s ease;

  @media (max-width: 1023px) {
    transform: translateX(-100%);

    &.sidebar-open {
      transform: translateX(0);
    }
  }

  .sidebar-nav {
    padding: 12px 0;
    display: flex;
    flex-direction: column;

    .nav-item {
      width: 100%;
      height: 48px;
      display: flex;
      align-items: center;
      justify-content: flex-start;
      gap: 14px;
      padding: 0 20px;
      border-radius: 0;
      font-size: 14px;
      color: #374151;
      transition: all 0.2s;
      position: relative;
      border: none;
      background: transparent;
      cursor: pointer;
      margin-left: 0!important;

      &:hover {
        background-color: #f9fafb;
        color: #111827;
      }

      &.active {
        background-color: #f5f3ff;
        color: #7c3aed;
        font-weight: 500;

        &::before {
          content: '';
          position: absolute;
          left: 0;
          top: 0;
          bottom: 0;
          width: 3px;
          background-color: #7c3aed;
        }

        .nav-icon {
          color: #7c3aed;
        }
      }

      .nav-icon {
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: #6b7280;
      }

      .nav-label {
        flex: 1;
        text-align: left;
        margin-left: 0;
        line-height: 1;
        display: flex;
        align-items: center;
      }
    }
  }
}

.sidebar-overlay {
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.2);
  z-index: 998;

  @media (min-width: 1024px) {
    display: none;
  }
}

.layout-main {
  padding-top: 56px;
  min-height: 100vh;
  background-color: #f9fafb;

  @media (min-width: 1024px) {
    padding-left: 210px;
  }
}
</style>

