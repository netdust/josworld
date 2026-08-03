import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  root: resolve(__dirname, 'src'),
  base: '/app/themes/josworld/dist/',

  build: {
    outDir: resolve(__dirname, 'dist'),
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: resolve(__dirname, 'src/main.js'),
      output: {
        entryFileNames: '[name].[hash].js',
        chunkFileNames: '[name].[hash].js',
        assetFileNames: '[name].[hash][extname]',
      },
    },
  },

  server: {
    origin: 'http://localhost:5173',
    cors: true,
  },

  css: {
    devSourcemap: true,
  },

  // Vitest rides this config; defaults (node env, **/*.test.js under root) are exactly what we want.
});
