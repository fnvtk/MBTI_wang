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
        // 如果 VITE_API_BASE_URL 是完整URL，则不使用代理
        // 否则代理到测试服务器
        // 注意：在vite.config.ts中无法直接访问import.meta.env
        // 这里使用默认代理配置，实际请求会根据VITE_API_BASE_URL决定
        target: 'http://test.mbti.com', // 本地开发代理到测试环境
        changeOrigin: true,
        rewrite: (path) => path, // 保持路径不变
      }
    }
  }
})

