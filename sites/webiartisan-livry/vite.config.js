import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

const proxyTarget = process.env.VITE_API_PROXY_TARGET || 'http://localhost:8080'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, '../artisans-shared/src'),
      '@shared': resolve(__dirname, '../artisans-shared/src'),
      pinia: resolve(__dirname, 'node_modules/pinia'),
    }
  },
  server: {
    port: 5173,
    fs: {
      allow: ['..']
    },
    proxy: {
      '/api': {
        target: proxyTarget,
        changeOrigin: true,
      }
    }
  },
  publicDir: resolve(__dirname, '../artisans-shared/public'),
  build: {
    outDir: 'dist',
    assetsDir: 'assets',
    emptyOutDir: true
  }
})
