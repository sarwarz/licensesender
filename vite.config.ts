import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

const root = __dirname;
const srcDir = path.resolve(root, 'resources/admin');
const entriesDir = path.resolve(srcDir, 'entries');
const outDir = path.resolve(root, 'build/admin');

const entry = process.env.LS_ENTRY || 'licenses';

export default defineConfig({
  plugins: [react()],
  publicDir: false,
  resolve: {
    alias: {
      '@': srcDir,
    },
  },
  build: {
    outDir,
    emptyOutDir: process.env.LS_EMPTY_OUT === '1',
    cssCodeSplit: false,
    rollupOptions: {
      input: path.resolve(entriesDir, `${entry}.tsx`),
      output: {
        format: 'iife',
        name: `LsAdmin_${entry.replace(/-/g, '_')}`,
        entryFileNames: `${entry}.js`,
        inlineDynamicImports: true,
        assetFileNames: 'assets/[name][extname]',
      },
    },
  },
});
