import { build } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const srcDir = path.resolve(root, 'admin/src');
const entriesDir = path.resolve(srcDir, 'entries');
const outDir = path.resolve(root, 'admin/build');

const entries = ['licenses', 'settings', 'download-links', 'activation-guides'];

for (let index = 0; index < entries.length; index++) {
  const entry = entries[index];
  console.log(`Building ${entry}…`);

  await build({
    plugins: [react()],
    resolve: {
      alias: {
        '@': srcDir,
      },
    },
    build: {
      outDir,
      emptyOutDir: index === 0,
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
}

console.log('All admin bundles built.');
