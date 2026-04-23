import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers'

export default defineConfig({
  css: {
    preprocessorOptions: {
      scss: { silenceDeprecations: ['legacy-js-api'] },
      sass: { silenceDeprecations: ['legacy-js-api'] }
    }
  },
  build: {
    chunkSizeWarningLimit: 1200
  },
  plugins: [
    vue(),
    AutoImport({
      imports: ['vue', 'vue-router', 'pinia'],
      resolvers: [ElementPlusResolver()],
      dts: 'src/auto-imports.d.ts',
    }),
    Components({
      resolvers: [ElementPlusResolver()],
      dts: 'src/components.d.ts',
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },
  server: {
    host: '0.0.0.0', // 允许局域网访问
    port: Number(process.env.MBTI_ADMIN_PORT) || 5173,
    // false：与其它 Vite 项目（如万推同用 5173）并存时，占用则自动用 5174、5175…；请以终端打印的 Local 为准
    strictPort: false,
    proxy: {
      '/api': {
        // 与 .env.development 配合：VITE_API_BASE_URL 留空时，浏览器请求 /api/* 由这里转发到本机 ThinkPHP
        target: process.env.VITE_DEV_API_PROXY ?? 'http://127.0.0.1:8787',
        changeOrigin: true,
        rewrite: (path) => path,
        // 云库首次连库较慢时避免代理提前断开（毫秒）
        timeout: Number(process.env.VITE_PROXY_TIMEOUT_MS) || 180000,
        proxyTimeout: Number(process.env.VITE_PROXY_TIMEOUT_MS) || 180000,
      },
    },
  }
})

