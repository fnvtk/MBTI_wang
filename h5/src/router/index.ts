import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(),
  scrollBehavior: () => ({ top: 0 }),
  routes: [
    // 首页 / 面相拍摄流程
    { path: '/', redirect: '/home' },
    { path: '/home', name: 'Home', component: () => import('@/pages/home/index.vue') },
    { path: '/camera', name: 'Camera', component: () => import('@/pages/camera/index.vue') },
    { path: '/face-result', name: 'FaceResult', component: () => import('@/pages/face-result/index.vue') },

    // 测试
    { path: '/test-select', name: 'TestSelect', component: () => import('@/pages/test-select/index.vue') },
    { path: '/test/mbti', name: 'TestMbti', component: () => import('@/pages/test/mbti.vue') },
    { path: '/test/disc', name: 'TestDisc', component: () => import('@/pages/test/disc.vue') },
    { path: '/test/pdp', name: 'TestPdp', component: () => import('@/pages/test/pdp.vue') },
    { path: '/test/sbti', name: 'TestSbti', component: () => import('@/pages/test/sbti.vue') },
    { path: '/result/:type', name: 'TestResult', component: () => import('@/pages/result/index.vue') },

    // 高考版
    { path: '/gaokao', name: 'Gaokao', component: () => import('@/pages/gaokao/index.vue') },
    { path: '/gaokao/camera', name: 'GaokaoCamera', component: () => import('@/pages/gaokao/camera.vue') },
    { path: '/gaokao/score', name: 'GaokaoScore', component: () => import('@/pages/gaokao/score.vue') },
    { path: '/gaokao/analyze', name: 'GaokaoAnalyze', component: () => import('@/pages/gaokao/analyze.vue') },
    { path: '/gaokao/result', name: 'GaokaoResult', component: () => import('@/pages/gaokao/result.vue') },

    // 个人中心
    { path: '/profile', name: 'Profile', component: () => import('@/pages/profile/index.vue') },
    // 购买 & AI 对话 & 分销
    { path: '/purchase', name: 'Purchase', component: () => import('@/pages/purchase/index.vue') },
    { path: '/ai-chat', name: 'AiChat', component: () => import('@/pages/ai-chat/index.vue') },
    { path: '/promo', name: 'Promo', component: () => import('@/pages/promo/index.vue') },

    // 登录
    { path: '/login', name: 'Login', component: () => import('@/pages/login/index.vue') },
  ]
})

// 需要登录的页面
const authRequired = ['/profile', '/ai-chat', '/purchase', '/promo', '/result', '/test']

router.beforeEach((to, _from, next) => {
  const token = localStorage.getItem('token')
  const needAuth = authRequired.some(p => to.path.startsWith(p))
  if (needAuth && !token) {
    next({ path: '/login', query: { redirect: to.fullPath } })
  } else {
    next()
  }
})

export default router
