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
    port: 5173, // 首选端口
    strictPort: false, // 如果端口被占用，自动尝试下一个可用端口
    proxy: {
      '/api': {
        // 与 .env.development 配合：VITE_API_BASE_URL 留空时，浏览器请求 /api/* 由这里转发到本机 ThinkPHP
        target: process.env.VITE_DEV_API_PROXY ?? 'http://127.0.0.1:8787',
        changeOrigin: true,
        rewrite: (path) => path,
      },
    },
  }
})

