import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import {
  migrateLegacyAuthStorage,
  getAdminToken,
  getAdminRole,
  getSuperadminToken,
  getSuperadminRole,
  clearAdminAuthKeys,
  clearSuperadminAuthKeys
} from '@/utils/authStorage'

const routes: RouteRecordRaw[] = [
  // 普通管理后台路由
  {
    path: '/admin/login',
    name: 'AdminLogin',
    component: () => import('@/views/admin/Login.vue'),
    meta: { title: '管理员登录' }
  },
  {
    path: '/admin',
    component: () => import('@/layouts/AdminLayout.vue'),
    redirect: '/admin/dashboard',
    children: [
      {
        path: 'dashboard',
        name: 'AdminDashboard',
        component: () => import('@/views/admin/Dashboard.vue'),
        meta: { title: '企业概览' }
      },
      {
        path: 'users',
        name: 'AdminUsers',
        component: () => import('@/views/admin/UsersHub.vue'),
        meta: { title: '用户运营' }
      },
      {
        path: 'orders',
        name: 'AdminOrders',
        component: () => import('@/views/admin/OrdersHub.vue'),
        meta: { title: '订单运营' }
      },
      {
        path: 'distribution',
        name: 'AdminDistribution',
        component: () => import('@/views/admin/Distribution.vue'),
        meta: { title: '分销推广' }
      },
      {
        // 合作意向已合并到用户运营 Tab，旧路径自动重定向
        path: 'cooperation-choices',
        name: 'AdminCooperationChoices',
        redirect: { path: '/admin/users', query: { tab: 'cooperation' } }
      },
      {
        path: 'questions',
        redirect: { path: '/admin/orders', query: { tab: 'questions' } }
      },
      {
        path: 'pricing',
        redirect: { path: '/admin/orders', query: { tab: 'pricing' } }
      },
      {
        path: 'finance',
        redirect: { path: '/admin/settings', query: { tab: 'finance' } }
      },
      {
        path: 'settings',
        name: 'AdminSettings',
        component: () => import('@/views/admin/Settings.vue'),
        meta: { title: '企业设置' }
      },
    ]
  },

  // 超级管理后台路由
  {
    path: '/superadmin/login',
    name: 'SuperAdminLogin',
    component: () => import('@/views/superadmin/Login.vue'),
    meta: { title: '超级管理员登录' }
  },
  {
    path: '/superadmin',
    component: () => import('@/layouts/SuperAdminLayout.vue'),
    redirect: '/superadmin/ops',
    children: [
      {
        path: 'ops',
        name: 'SuperAdminOps',
        component: () => import('@/views/superadmin/OpsHub.vue'),
        meta: { title: '总览' },
        beforeEnter: (to, _from, next) => {
          const t = to.query.tab
          if (t === 'distribution') {
            next({ path: '/superadmin/distribution' })
            return
          }
          if (t === 'analytics') {
            next({ path: '/superadmin/distribution', query: { tab: 'analytics' } })
            return
          }
          next()
        }
      },
      {
        path: 'users',
        name: 'SuperAdminUsers',
        redirect: to => ({
          path: '/superadmin/enterprises',
          query: { ...to.query, tab: 'users' }
        })
      },
      {
        path: 'enterprises',
        name: 'SuperAdminEnterprises',
        component: () => import('@/views/superadmin/EnterpriseHub.vue'),
        meta: { title: '企业管理' }
      },
      {
        path: 'commerce',
        name: 'SuperAdminCommerce',
        component: () => import('@/views/superadmin/CommerceHub.vue'),
        meta: { title: '订单和财务' }
      },
      {
        path: 'distribution',
        name: 'SuperAdminDistribution',
        component: () => import('@/views/superadmin/DistributionStandalone.vue'),
        meta: { title: '分销管理' }
      },
      {
        path: 'analytics',
        name: 'SuperAdminMpAnalytics',
        redirect: to => ({
          path: '/superadmin/distribution',
          query: { ...to.query, tab: 'analytics' }
        })
      },
      {
        path: 'ai-config',
        name: 'SuperAdminAIConfig',
        component: () => import('@/views/superadmin/AIConfig.vue'),
        meta: { title: '智能算力' }
      },
      {
        path: 'soul-articles',
        name: 'SuperAdminSoulArticles',
        redirect: to => ({
          path: '/superadmin/enterprises',
          query: { ...to.query, tab: 'soulArticles' }
        })
      },
      {
        path: 'mp-tabbar',
        name: 'SuperAdminMpTabBar',
        redirect: to => {
          const q = { ...to.query } as Record<string, unknown>
          delete q.tab
          return { path: '/superadmin/enterprises', query: q }
        }
      },
      {
        path: 'profit-rules',
        name: 'SuperAdminProfitRules',
        redirect: to => {
          const q = { ...to.query } as Record<string, unknown>
          delete q.tab
          return { path: '/superadmin/enterprises', query: q }
        }
      },
      {
        path: 'settings',
        name: 'SuperAdminSettings',
        component: () => import('@/views/superadmin/Settings.vue'),
        meta: { title: '系统设置' }
      },
      {
        path: 'overview',
        redirect: to => ({ path: '/superadmin/ops', query: { ...to.query, tab: 'overview' } })
      },
      {
        path: 'questions',
        redirect: to => ({ path: '/superadmin/settings', query: { ...to.query, tab: 'questions' } })
      },
      {
        path: 'pricing',
        redirect: to => ({ path: '/superadmin/commerce', query: { ...to.query, tab: 'pricing' } })
      },
      {
        path: 'database',
        redirect: to => ({ path: '/superadmin/settings', query: { ...to.query, tab: 'database' } })
      },
      {
        path: 'finance',
        redirect: to => ({ path: '/superadmin/commerce', query: { ...to.query, tab: 'finance' } })
      }
    ]
  },

  // 小程序界面预览（无需鉴权）
  {
    path: '/miniprogram-preview',
    name: 'MiniProgramPreview',
    component: () => import('@/views/MiniProgramPreview.vue'),
    meta: { title: '小程序界面预览' }
  },

  // 默认重定向到小程序预览（v0 预览窗口入口）
  {
    path: '/',
    redirect: '/miniprogram-preview'
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

// 路由守卫：管理端仅 admin / enterprise_admin；超管端仅 superadmin；两套 Token 分存
router.beforeEach((to, _from, next) => {
  migrateLegacyAuthStorage()

  document.title = to.meta.title ? `${to.meta.title} - MBTI 管理后台` : 'MBTI 管理后台'

  const adminToken = getAdminToken()
  const adminRole = getAdminRole()
  const saToken = getSuperadminToken()
  const saRole = getSuperadminRole()

  // 小程序预览页无需鉴权
  if (to.path === '/miniprogram-preview') {
    next()
    return
  }

  if (to.path.startsWith('/admin') && to.path !== '/admin/login') {
    if (!adminToken || !adminRole || !['admin', 'enterprise_admin'].includes(adminRole)) {
      clearAdminAuthKeys()
      next('/admin/login')
      return
    }
  }

  if (to.path.startsWith('/superadmin') && to.path !== '/superadmin/login') {
    if (!saToken || saRole !== 'superadmin') {
      clearSuperadminAuthKeys()
      next('/superadmin/login')
      return
    }
  }

  if (to.path === '/admin/login' && adminToken && adminRole && ['admin', 'enterprise_admin'].includes(adminRole)) {
    next('/admin/dashboard')
    return
  }

  if (to.path === '/superadmin/login' && saToken && saRole === 'superadmin') {
    next('/superadmin/ops')
    return
  }

  next()
})

export default router

