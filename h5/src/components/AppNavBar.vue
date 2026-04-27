<template>
  <header class="app-navbar" :class="{ 'app-navbar--transparent': transparent, 'app-navbar--dark': dark }">
    <div class="app-navbar__left">
      <button v-if="showBack" class="app-navbar__back" @click="handleBack">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
          <path d="M19 12H5M12 5l-7 7 7 7" :stroke="dark||transparent?'#fff':'#1A1A2E'" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
    <div class="app-navbar__title">{{ title }}</div>
    <div class="app-navbar__right">
      <slot name="right" />
    </div>
  </header>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
const router = useRouter()
const props = defineProps<{
  title?: string
  showBack?: boolean
  transparent?: boolean
  dark?: boolean
  backPath?: string
}>()
const handleBack = () => {
  if (props.backPath) router.push(props.backPath)
  else router.back()
}
</script>

<style scoped>
.app-navbar {
  position: sticky;
  top: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  height: 52px;
  padding: 0 16px;
  background: rgba(255,255,255,0.96);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(108,62,246,0.08);
}

.app-navbar--transparent {
  background: transparent;
  border-bottom-color: transparent;
  box-shadow: none;
}

.app-navbar--dark {
  background: transparent;
}

.app-navbar__left { width: 44px; display: flex; align-items: center; }
.app-navbar__right { width: 44px; display: flex; align-items: center; justify-content: flex-end; }

.app-navbar__back {
  width: 36px; height: 36px;
  border: none; background: rgba(0,0,0,0.06); border-radius: 50%;
  display: flex; align-items: center; justify-content: center; cursor: pointer;
}

.app-navbar--dark .app-navbar__back,
.app-navbar--transparent .app-navbar__back {
  background: rgba(255,255,255,0.18);
}

.app-navbar__title {
  flex: 1; text-align: center;
  font-size: 16px; font-weight: 700; color: #1A1A2E;
}

.app-navbar--dark .app-navbar__title,
.app-navbar--transparent .app-navbar__title {
  color: #fff;
}
</style>
