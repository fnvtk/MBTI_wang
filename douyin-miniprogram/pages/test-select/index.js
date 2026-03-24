// pages/test-select/index.js - 人脸测试后的详情性格测试入口（MBTI / PDP / DISC）
Page({
  data: {},

  onLoad() {},

  // 进入 MBTI 测试
  goMBTI() {
    tt.navigateTo({ url: '/pages/test/mbti' })
  },

  // 进入 PDP 测试
  goPDP() {
    tt.navigateTo({ url: '/pages/test/pdp' })
  },

  // 进入 DISC 测试
  goDISC() {
    tt.navigateTo({ url: '/pages/test/disc' })
  }
})
