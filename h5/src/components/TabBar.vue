<template>
  <nav class="tabbar" :class="{ 'tabbar--hidden': hidden }">
    <button
      v-for="tab in tabs"
      :key="tab.path"
      class="tabbar__item"
      :class="{ 'tabbar__item--center': tab.center, 'tabbar__item--active': isActive(tab.path) }"
      @click="navigate(tab)"
    >
      <div v-if="tab.center" class="tabbar__center-btn">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
          <path d="M12 3a9 9 0 110 18A9 9 0 0112 3zm0 2a7 7 0 100 14A7 7 0 0012 5zm0 2a5 5 0 110 10A5 5 0 0112 7zm0 2a3 3 0 100 6 3 3 0 000-6z" fill="currentColor"/>
        </svg>
      </div>
      <template v-else>
        <div class="tabbar__icon">
          <component :is="tab.icon" :active="isActive(tab.path)" />
        </div>
        <span class="tabbar__label">{{ tab.label }}</span>
      </template>
    </button>
  </nav>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import IconHome from './icons/IconHome.vue'
import IconTest from './icons/IconTest.vue'
import IconScan from './icons/IconScan.vue'
import IconAI from './icons/IconAI.vue'
import IconProfile from './icons/IconProfile.vue'

const route = useRoute()
const router = useRouter()

defineProps<{ hidden?: boolean }>()

const tabs = [
  { path: '/home',      label: '首页',   icon: IconHome },
  { path: '/test-select', label: '测试', icon: IconTest },
  { path: '/camera',    label: '',       icon: null, center: true },
  { path: '/ai-chat',   label: 'AI对话', icon: IconAI },
  { path: '/profile',   label: '我的',   icon: IconProfile },
]

const isActive = (path: string) => {
  if (path === '/home') return route.path === '/home' || route.path === '/'
  return route.path.startsWith(path)
}

const navigate = (tab: any) => {
  if (tab.center) {
    router.push('/camera')
  } else {
    router.push(tab.path)
  }
}
</script>

<style scoped>
.tabbar {
  position: fixed;
  bottom: 0;
  left: 0; right: 0;
  height: calc(60px + env(safe-area-inset-bottom, 0px));
  padding-bottom: env(safe-area-inset-bottom, 0px);
  background: rgba(255,255,255,0.96);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-top: 1px solid rgba(108,62,246,0.1);
  display: flex;
  align-items: center;
  z-index: 100;
  box-shadow: 0 -4px 20px rgba(108,62,246,0.08);
}

.tabbar--hidden {
  transform: translateY(100%);
  pointer-events: none;
}

.tabbar__item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 3px;
  height: 60px;
  background: none;
  border: none;
  cursor: pointer;
  color: #9CA3AF;
  transition: color 0.2s;
  padding: 0;
}

.tabbar__item--active {
  color: var(--primary);
}

.tabbar__icon {
  width: 24px; height: 24px;
  display: flex; align-items: center; justify-content: center;
}

.tabbar__label {
  font-size: 11px;
  font-weight: 500;
  line-height: 1;
}

.tabbar__item--center {
  flex: 1.2;
}

.tabbar__center-btn {
  width: 52px; height: 52px;
  background: linear-gradient(135deg, #6C3EF6 0%, #4C1D95 100%);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #fff;
  box-shadow: 0 4px 16px rgba(108,62,246,0.45);
  margin-top: -16px;
  position: relative;
}
</style>
