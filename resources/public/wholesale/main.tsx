import { createRoot } from 'react-dom/client';
import { setConfig } from '../support/lib';
import type { ThemeMode } from '../support/types';
import css from '../support/styles.css?inline';
import { WholesaleApp } from './App';
import type { WholesaleConfig } from './types';

declare global {
  interface Window {
    LSWholesaleApp?: WholesaleConfig;
  }
}

function mount(host: HTMLElement) {
  if (host.shadowRoot || host.dataset.lsMounted) return;
  host.dataset.lsMounted = '1';

  // Same isolation as the other storefront apps: theme CSS stays out, ours stays in.
  const shadow = host.attachShadow({ mode: 'open' });
  const style = document.createElement('style');
  style.textContent = css;
  shadow.appendChild(style);

  const container = document.createElement('div');
  shadow.appendChild(container);
  const theme = host.dataset.theme as ThemeMode | undefined;
  createRoot(container).render(<WholesaleApp host={host} theme={theme === 'light' || theme === 'dark' ? theme : 'auto'} />);
}

function boot() {
  const config = window.LSWholesaleApp;
  if (!config) return;
  setConfig(config);
  window.LSSupportBooted = true;
  document.querySelectorAll<HTMLElement>('.ls-wholesale-app').forEach(mount);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
