import { build } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from 'tailwindcss';
import autoprefixer from 'autoprefixer';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const srcDir = path.resolve(root, 'resources/admin');
const entriesDir = path.resolve(srcDir, 'entries');
const outDir = path.resolve(root, 'build/admin');

const entries = ['licenses', 'settings', 'download-links', 'activation-guides', 'wholesale', 'dashboard', 'setup'];

for (let index = 0; index < entries.length; index++) {
  const entry = entries[index];
  console.log(`Building ${entry}…`);

  await build({
    plugins: [react()],
    // public/ is not a Vite public folder; don't copy anything into the build.
    publicDir: false,
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

// emptyOutDir wipes the folder; keep the directory-listing guard.
fs.writeFileSync(path.resolve(outDir, 'index.php'), '<?php // Silence is golden\n');

console.log('All admin bundles built.');

// Storefront apps. They render inside a Shadow DOM, so their CSS is inlined into the
// JS bundle (imported with ?inline) instead of being emitted as a stylesheet.
const publicSrcDir = path.resolve(root, 'resources/public');
const publicOutDir = path.resolve(root, 'build/public');
const publicEntries = ['support', 'my-keys', 'wholesale'];

// Themes often set html { font-size: 62.5% }; rem units would shrink the whole app.
// Pixels keep sizes identical on every theme.
const remToPx = {
  postcssPlugin: 'ls-rem-to-px',
  Declaration(decl) {
    if (decl.value.includes('rem')) {
      decl.value = decl.value.replace(/(-?\d*\.?\d+)rem\b/g, (_, n) => `${parseFloat(n) * 16}px`);
    }
  },
};

for (let index = 0; index < publicEntries.length; index++) {
  const entry = publicEntries[index];
  console.log(`Building public ${entry}…`);

  await build({
    plugins: [react()],
    publicDir: false,
    configFile: false,
    define: { 'process.env.NODE_ENV': JSON.stringify('production') },
    css: {
      postcss: {
        plugins: [
          tailwindcss({ config: path.resolve(root, 'tailwind.public.config.ts') }),
          autoprefixer(),
          remToPx,
        ],
      },
    },
    build: {
      outDir: publicOutDir,
      emptyOutDir: index === 0,
      cssCodeSplit: false,
      rollupOptions: {
        input: path.resolve(publicSrcDir, entry, 'main.tsx'),
        output: {
          format: 'iife',
          name: `LsPublic_${entry.replace(/-/g, '_')}`,
          entryFileNames: `${entry}.js`,
          inlineDynamicImports: true,
        },
      },
    },
  });
}

fs.writeFileSync(path.resolve(publicOutDir, 'index.php'), '<?php // Silence is golden\n');

console.log('All public bundles built.');
