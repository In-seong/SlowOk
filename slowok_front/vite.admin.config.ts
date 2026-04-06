import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'
import { resolve } from 'path'
import { copyFileSync, mkdirSync } from 'fs'

export default defineConfig({
  plugins: [
    vue(),
    {
      name: 'html-transform',
      configureServer(server) {
        server.middlewares.use((req, _res, next) => {
          if (req.url === '/' || req.url === '/index.html') {
            req.url = '/admin.html'
          }
          next()
        })
      }
    },
    {
      name: 'copy-docs',
      closeBundle() {
        const docsDir = resolve(__dirname, '../docs')
        const outDir = resolve(__dirname, '../slowok_back/public/admin/docs')
        mkdirSync(outDir, { recursive: true })
        copyFileSync(resolve(docsDir, 'business_proposal.html'), resolve(outDir, 'business_proposal.html'))
        copyFileSync(resolve(docsDir, 'admin_guide.html'), resolve(outDir, 'admin_guide.html'))
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
    port: 5175,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        secure: false,
      }
    }
  },
  publicDir: 'public-admin',
  build: {
    outDir: '../slowok_back/public/admin',
    emptyOutDir: true,
    rollupOptions: {
      input: resolve(__dirname, 'admin.html')
    }
  }
})
