import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  publicDir: false,
  server: {
    host: '192.168.0.4',
    port: 5173,
    strictPort: false,
    hmr: {
      host: '192.168.0.4',
      port: 5173
    },
    proxy: {
      '/api': {
        target: process.env.VITE_API_ECORUTA || 'http://192.168.0.4:8000',
        changeOrigin: true,
        secure: false
      }
    }
  },
  preview: {
    host: '0.0.0.0',
    port: 4173,
    strictPort: false
  }
})
