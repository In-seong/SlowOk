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
          if (req.url && !req.url.startsWith('/api') && !req.url.startsWith('/src') && !req.url.startsWith('/@') && !req.url.startsWith('/node_modules') && !req.url.includes('.')) {
            req.url = '/user.html'
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
    }
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        secure: false,
      }
    }
  },
  publicDir: 'public-user',
  build: {
    outDir: '../slowok_back/public/user',
    emptyOutDir: true,
    rollupOptions: {
      input: resolve(__dirname, 'user.html')
    }
  }
})
