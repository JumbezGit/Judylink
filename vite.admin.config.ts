import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import path from 'node:path'

export default defineConfig({
  plugins: [tailwindcss()],
  build: {
    outDir: 'backend/assets',
    emptyOutDir: false,
    minify: true,
    rollupOptions: {
      input: path.resolve(import.meta.dirname, 'src/admin.css'),
      output: {
        assetFileNames: 'admin.css',
      },
    },
  },
})
