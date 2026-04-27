import { defineConfig } from 'vite';

export default defineConfig({
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:3000',
        changeOrigin: true,
      },
    },
    watch: {
      usePolling: true, // Useful for some environments
    },
    hmr: {
      overlay: true, // Show errors in browser
    }
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
});
