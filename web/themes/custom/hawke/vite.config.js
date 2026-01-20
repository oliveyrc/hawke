import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [
    tailwindcss(),
  ],
  build: {
    outDir: 'dist',
    rollupOptions: {
      input: 'src/css/styles.css',
      output: {
        assetFileNames: '[name][extname]',
      },
    },
  },
});
