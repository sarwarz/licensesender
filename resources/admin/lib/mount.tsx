import type { ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import { hexToHsl } from '@/lib/utils';
import { getBootstrap } from '@/lib/bootstrap';
import { AdminNotices } from '@/components/shared/AdminNotices';
import '@/styles/globals.css';

function showMountError(rootEl: HTMLElement, message: string) {
  rootEl.innerHTML = `
    <div style="padding:24px;border:1px solid #fecaca;background:#fef2f2;border-radius:12px;color:#991b1b;font-size:14px;">
      <strong>licensesender UI failed to load.</strong><br />
      ${message}<br />
      <span style="color:#64748b;">Try hard refresh (Ctrl+Shift+R) or run <code>npm run build</code> in the plugin folder.</span>
    </div>
  `;
}

export function mountApp(render: () => ReactNode) {
  const rootEl = document.getElementById('ls-app-root');
  if (!rootEl) {
    return;
  }

  try {
    if (!window.lsAdmin) {
      throw new Error('Admin bootstrap data is missing.');
    }

    const { brandColor } = getBootstrap();
    rootEl.innerHTML = '';
    rootEl.style.setProperty('--primary', hexToHsl(brandColor || '#4f46e5'));
    rootEl.style.setProperty('--ring', hexToHsl(brandColor || '#4f46e5'));

    const app = document.createElement('div');
    app.className = 'ls-admin-app';
    rootEl.appendChild(app);

    const portalRoot = document.createElement('div');
    portalRoot.id = 'ls-portal-root';
    rootEl.appendChild(portalRoot);

    createRoot(app).render(
      <>
        <AdminNotices />
        {render()}
      </>,
    );
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Unknown error';
    showMountError(rootEl, message);
  }
}
