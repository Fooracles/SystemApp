import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  root: '.',
  base: './', // so built app works when served from any path
  build: {
    rollupOptions: {
      output: {
        // Predictable filenames (no hashes) so PHP can include them directly
        entryFileNames: 'assets/fms-builder.js',
        chunkFileNames: 'assets/[name].js',
        assetFileNames: 'assets/fms-builder.[ext]',
      },
    },
  },
});
