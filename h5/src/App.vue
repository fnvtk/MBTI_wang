<template>
  <router-view v-slot="{ Component, route }">
    <transition name="page" mode="out-in">
      <component :is="Component" :key="route.path" />
    </transition>
  </router-view>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

onMounted(() => {
  // URL 中携带 token 参数（企业扫码入口）
  const params = new URLSearchParams(window.location.search)
  const urlToken = params.get('token')
  if (urlToken && !auth.token) {
    auth.setToken(urlToken)
  }
})
</script>
