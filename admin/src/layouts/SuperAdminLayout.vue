<template>
  <div class="superadmin-layout">
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
            <span class="logo-text">超级管理后台</span>
          </div>
        </div>

        <div class="header-right">
          <el-button
            text
            class="nav-link"
            @click="router.push('/admin/dashboard')"
          >
            <el-icon><HomeFilled /></el-icon>
            <span>管理台</span>
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
  TrendCharts,
  OfficeBuilding,
  User,
  Document,
  Cpu,
  Money,
  DataLine,
  Setting,
  Share
} from '@element-plus/icons-vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const sidebarOpen = ref(false)

const navItems = [
  { path: '/superadmin/overview', icon: TrendCharts, label: '概览' },
  { path: '/superadmin/enterprises', icon: OfficeBuilding, label: '企业管理' },
  { path: '/superadmin/users', icon: User, label: '用户总览' },
  { path: '/superadmin/questions', icon: Document, label: '题库管理' },
  { path: '/superadmin/ai-config', icon: Cpu, label: 'AI 服务配置' },
  { path: '/superadmin/pricing', icon: Money, label: '全局定价' },
  { path: '/superadmin/distribution', icon: Share, label: '分销管理' },
  { path: '/superadmin/finance', icon: Money, label: '财务数据' },
  { path: '/superadmin/database', icon: DataLine, label: '数据库管理' },
  { path: '/superadmin/settings', icon: Setting, label: '系统设置' },
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
    await authStore.superAdminLogout()
    router.push('/superadmin/login')
  } catch (error) {
    console.error('退出登录失败:', error)
    // 即使API调用失败，也清除本地状态并跳转
    router.push('/superadmin/login')
  }
}
</script>

<style scoped lang="scss">
.superadmin-layout {
  min-height: 100vh;
  background-color: #f9fafb;
}

.layout-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 64px;
  background-color: #ffffff;
  border-bottom: 1px solid #f3f4f6;
  z-index: 1000;

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
    gap: 16px;

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
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background-color: #ef4444;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 18px;
      }

      .logo-text {
        font-weight: 700;
        font-size: 16px;
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
    gap: 12px;

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

      &.logout {
        color: #ef4444;
      }

      .el-icon {
        font-size: 16px;
      }
    }
  }
}

.layout-sidebar {
  position: fixed;
  left: 0;
  top: 64px;
  bottom: 0;
  width: 240px;
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
    padding: 16px 0;
    display: flex;
    flex-direction: column;

    .nav-item {
      width: 100%;
      height: 48px;
      display: flex;
      align-items: center;
      justify-content: flex-start;
      gap: 12px;
      padding: 0 24px;
      border-radius: 0;
      font-size: 14px;
      color: #4b5563;
      transition: all 0.2s;
      position: relative;
      margin-left: 0!important;

      &:hover {
        background-color: #f9fafb;
        color: #111827;
      }

      &.active {
        background-color: #fef2f2;
        color: #ef4444;
        font-weight: 600;

        &::before {
          content: '';
          position: absolute;
          left: 0;
          top: 0;
          bottom: 0;
          width: 4px;
          background-color: #ef4444;
        }
      }

      .nav-icon {
        font-size: 18px;
      }

      .nav-label {
        flex: 1;
        text-align: left;
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
  padding-top: 64px;
  min-height: 100vh;
  background-color: #f9fafb;

  @media (min-width: 1024px) {
    padding-left: 240px;
  }
}
</style>

