import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  root: resolve(__dirname, 'src'),
  // stackedWP layout: WP_CONTENT_URL is <home>/content (app/wp-config.php),
  // so built assets are served from /content/themes/<slug>/dist/.
  // (The Bedrock default would be /app/themes/<slug>/dist/ — wrong here.)
  base: '/content/themes/josworld/dist/',

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
