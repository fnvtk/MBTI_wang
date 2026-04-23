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
          <el-button text class="nav-link admin-entry" @click="goAdminConsole">
            <el-icon><Monitor /></el-icon>
            <span>管理后台</span>
          </el-button>
          <el-button text class="nav-link logout" @click="handleLogout">
            <el-icon><SwitchButton /></el-icon>
            <span>退出</span>
          </el-button>
        </div>
      </div>
    </header>

    <!-- 侧边栏 -->
    <aside :class="['layout-sidebar', { 'sidebar-open': sidebarOpen }]">
      <nav class="sidebar-nav">
        <div class="nav-main">
          <div class="nav-group-label">运营总控</div>
          <template v-for="item in navMainItems" :key="item.path">
            <!-- 一级条目 -->
            <el-button
              :class="['nav-item', { active: isActive(item.path) || hasActiveChild(item) }]"
              text
              @click="onNavClick(item)"
            >
              <el-icon class="nav-icon">
                <component :is="item.icon" />
              </el-icon>
              <span class="nav-label">{{ item.label }}</span>
              <el-icon v-if="item.children && item.children.length" class="nav-arrow">
                <component :is="isExpanded(item.path) ? ArrowDown : ArrowRight" />
              </el-icon>
            </el-button>
            <!-- 二级子菜单 -->
            <div
              v-if="item.children && item.children.length && (isExpanded(item.path) || hasActiveChild(item))"
              class="nav-sub"
            >
              <el-button
                v-for="sub in item.children"
                :key="sub.path"
                :class="['nav-item nav-item--sub', { active: isActive(sub.path) }]"
                text
                @click="navigateTo(sub.path)"
              >
                <span class="nav-sub-dot"></span>
                <span class="nav-label">{{ sub.label }}</span>
              </el-button>
            </div>
          </template>
        </div>

        <div class="nav-footer-block">
          <div class="nav-group-label">系统</div>
          <el-button
            v-for="item in navFooterItems"
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
        </div>
      </nav>
    </aside>

    <!-- 遮罩层（移动端） -->
    <div v-if="sidebarOpen" class="sidebar-overlay" @click="toggleSidebar" />

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
  SwitchButton,
  TrendCharts,
  ShoppingCart,
  Cpu,
  Setting,
  OfficeBuilding,
  Share,
  Monitor,
  ArrowDown,
  ArrowRight
} from '@element-plus/icons-vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const sidebarOpen = ref(false)

interface NavItem {
  path: string
  icon: any
  label: string
  children?: NavItem[]
}

const navMainItems: NavItem[] = [
  { path: '/superadmin/ops', icon: TrendCharts, label: '总览' },
  { path: '/superadmin/enterprises', icon: OfficeBuilding, label: '企业管理' },
  { path: '/superadmin/commerce', icon: ShoppingCart, label: '订单和财务' },
  { path: '/superadmin/distribution', icon: Share, label: '分销管理' },
  { path: '/superadmin/ai-config', icon: Cpu, label: '智能算力' }
]

const navFooterItems: NavItem[] = [
  { path: '/superadmin/settings', icon: Setting, label: '系统设置' }
]

const expandedPaths = ref<Set<string>>(new Set())

const isActive = (path: string) => {
  return route.path === path || route.path.startsWith(`${path}/`)
}

const hasActiveChild = (item: NavItem): boolean => {
  if (!item.children) return false
  return item.children.some(c => isActive(c.path))
}

const isExpanded = (p: string) => expandedPaths.value.has(p)

const onNavClick = (item: NavItem) => {
  if (item.children && item.children.length) {
    // 有子菜单 → 切换展开
    if (expandedPaths.value.has(item.path)) {
      expandedPaths.value.delete(item.path)
    } else {
      expandedPaths.value.add(item.path)
    }
    // 首次展开时，若当前没选中任何子项，就默认跳第一个子项
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
    router.push('/superadmin/login')
  }
}
</script>

<style scoped lang="scss">
.superadmin-layout {
  min-height: 100vh;
  background-color: #f8fafc;
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
        background-image: linear-gradient(135deg, #1E40AF 0%, #3730A3 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 18px;
        box-shadow: 0 2px 8px rgba(30, 64, 175, 0.3);
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
    gap: 4px;

    .nav-link {
      font-size: 13px;
      color: #6b7280;
      padding: 4px 10px;
      display: flex;
      align-items: center;
      gap: 4px;
      height: auto;

      &:hover {
        background-color: transparent;
        color: #111827;
      }

      &.admin-entry {
        color: #1E40AF;
        font-weight: 600;
      }

      &.logout {
        color: #64748b;
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
    padding: 12px 0 20px;
    display: flex;
    flex-direction: column;
    min-height: calc(100vh - 64px - 32px);
  }

  .nav-main {
    flex: 1;
  }

  .nav-footer-block {
    border-top: 1px solid #f3f4f6;
    padding-top: 10px;
    margin-top: 8px;
  }

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
    margin-left: 0 !important;

    &:hover {
      background-color: #f9fafb;
      color: #111827;
    }

    &.active {
      background-color: #E0E7FF;
      color: #1E40AF;
      font-weight: 600;

      &::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background-color: #1E40AF;
      }

      .nav-icon {
        color: #1E40AF;
      }
    }

    .nav-icon {
      font-size: 18px;
    }

    .nav-label {
      flex: 1;
      text-align: left;
    }

    .nav-arrow {
      font-size: 12px;
      color: #9CA3AF;
    }
  }

  .nav-sub {
    display: flex;
    flex-direction: column;
    background: #f9fafb;
    border-left: 2px solid #e5e7eb;
    margin: 0 0 4px 24px;
  }

  .nav-item--sub {
    height: 40px;
    padding: 0 20px;
    font-size: 13px;
    color: #6B7280;

    .nav-sub-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #D1D5DB;
    }

    &.active {
      background-color: #EEF2FF;
      color: #1E40AF;

      .nav-sub-dot {
        background: #1E40AF;
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
