import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'
import { resolve } from 'path'

export default defineConfig({
  plugins: [
    vue(),
    {
      name: 'html-transform',
      configureServer(server) {
        server.middlewares.use((req, _res, next) => {
          if (req.url && !req.url.startsWith('/api') && !req.url.includes('.')) {
            req.url = '/mobile-admin.html'
          }
          next()
        })
      }
    }
  ],
  resolve: {
    alias: {
      '@shared': fileURLToPath(new URL('./src/shared', import.meta.url)),
      '@user': fileURLToPath(new URL('./src/user', import.meta.url)),
      '@admin': fileURLToPath(new URL('./src/admin', import.meta.url)),
      '@mobile-admin': fileURLToPath(new URL('./src/mobile-admin', import.meta.url)),
    }
  },
  server: {
    port: 5176,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        secure: false,
      }
    }
  },
  publicDir: 'public-mobile-admin',
  build: {
    outDir: '../slowok_back/public/mobile-admin',
    emptyOutDir: true,
    rollupOptions: {
      input: resolve(__dirname, 'mobile-admin.html')
    }
  }
})
