import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'

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
        meta: { title: '数据概览' }
      },
      {
        path: 'users',
        name: 'AdminUsers',
        component: () => import('@/views/admin/Users.vue'),
        meta: { title: '用户管理' }
      },
      {
        path: 'orders',
        name: 'AdminOrders',
        component: () => import('@/views/admin/Orders.vue'),
        meta: { title: '订单管理' }
      },
      {
        path: 'distribution',
        name: 'AdminDistribution',
        component: () => import('@/views/admin/Distribution.vue'),
        meta: { title: '分销管理' }
      },
      {
        path: 'questions',
        name: 'AdminQuestions',
        component: () => import('@/views/admin/Questions.vue'),
        meta: { title: '题库管理' }
      },
      {
        path: 'pricing',
        name: 'AdminPricing',
        component: () => import('@/views/admin/Pricing.vue'),
        meta: { title: '价格设置' }
      },
      {
        path: 'finance',
        name: 'AdminFinance',
        component: () => import('@/views/admin/Finance.vue'),
        meta: { title: '企业余额' }
      },
      {
        path: 'settings',
        name: 'AdminSettings',
        component: () => import('@/views/admin/Settings.vue'),
        meta: { title: '系统设置' }
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
    redirect: '/superadmin/overview',
    children: [
      {
        path: 'overview',
        name: 'SuperAdminOverview',
        component: () => import('@/views/superadmin/Overview.vue'),
        meta: { title: '概览' }
      },
      {
        path: 'enterprises',
        name: 'SuperAdminEnterprises',
        component: () => import('@/views/superadmin/Enterprises.vue'),
        meta: { title: '企业管理' }
      },
      {
        path: 'users',
        name: 'SuperAdminUsers',
        component: () => import('@/views/superadmin/Users.vue'),
        meta: { title: '用户总览' }
      },
      {
        path: 'questions',
        name: 'SuperAdminQuestions',
        component: () => import('@/views/superadmin/Questions.vue'),
        meta: { title: '题库管理' }
      },
      {
        path: 'ai-config',
        name: 'SuperAdminAIConfig',
        component: () => import('@/views/superadmin/AIConfig.vue'),
        meta: { title: 'AI 服务配置' }
      },
      {
        path: 'pricing',
        name: 'SuperAdminPricing',
        component: () => import('@/views/superadmin/Pricing.vue'),
        meta: { title: '全局定价' }
      },
      {
        path: 'distribution',
        name: 'SuperAdminDistribution',
        component: () => import('@/views/superadmin/Distribution.vue'),
        meta: { title: '分销管理' }
      },
      {
        path: 'finance',
        name: 'SuperAdminFinance',
        component: () => import('@/views/superadmin/Finance.vue'),
        meta: { title: '财务数据' }
      },
      {
        path: 'database',
        name: 'SuperAdminDatabase',
        component: () => import('@/views/superadmin/Database.vue'),
        meta: { title: '数据库管理' }
      },
      {
        path: 'settings',
        name: 'SuperAdminSettings',
        component: () => import('@/views/superadmin/Settings.vue'),
        meta: { title: '系统设置' }
      },
    ]
  },

  // 默认重定向
  {
    path: '/',
    redirect: '/admin/login'
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

// 路由守卫
router.beforeEach((to, _from, next) => {
  // 设置页面标题
  document.title = to.meta.title ? `${to.meta.title} - MBTI 管理后台` : 'MBTI 管理后台'

  // 检查登录状态（使用Token）
  const token = localStorage.getItem('authToken')
  const userRole = localStorage.getItem('userRole')

  // 管理后台路由守卫
  if (to.path.startsWith('/admin') && to.path !== '/admin/login') {
    if (!token || !userRole || !['admin', 'enterprise_admin', 'superadmin'].includes(userRole)) {
      // 清除登录状态
      localStorage.removeItem('authToken')
      localStorage.removeItem('userRole')
      localStorage.removeItem('adminLoggedIn')
      next('/admin/login')
      return
    }
  }

  // 超级管理后台路由守卫
  if (to.path.startsWith('/superadmin') && to.path !== '/superadmin/login') {
    if (!token || userRole !== 'superadmin') {
      // 清除登录状态
      localStorage.removeItem('authToken')
      localStorage.removeItem('userRole')
      localStorage.removeItem('superAdminLoggedIn')
      next('/superadmin/login')
      return
    }
  }

  // 如果已登录，访问登录页则跳转到对应首页
  if (to.path === '/admin/login' && token && userRole && ['admin', 'enterprise_admin', 'superadmin'].includes(userRole)) {
    next('/admin/dashboard')
    return
  }

  if (to.path === '/superadmin/login' && token && userRole === 'superadmin') {
    next('/superadmin/overview')
    return
  }

  next()
})

export default router

