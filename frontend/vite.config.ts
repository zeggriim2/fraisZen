import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  base: process.env.NODE_ENV === 'production' ? '/app/' : '/',
  resolve: {
    alias: { '@': resolve(__dirname, './src') },
  },
  server: {
    port: 5173,
    host: '0.0.0.0',
    allowedHosts: ['node'],
    proxy: {
      '/api': {
        // En Docker : API_TARGET=http://php (réseau interne)
        // En local  : non défini → https://localhost (FrankenPHP exposé)
        target: process.env.API_TARGET ?? 'https://localhost',
        secure: false,
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: '../public/app',
    emptyOutDir: true,
  },
})
