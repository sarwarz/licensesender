import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { setConfig } from '../support/lib';
import type { BaseConfig, ThemeMode } from '../support/types';
import css from '../support/styles.css?inline';
import { MyKeysApp } from './MyKeysApp';
import { OrderKeysApp } from './OrderKeysApp';
import type { MyKeysConfig, OrderKeysConfig } from './types';

declare global {
  interface Window {
    LSMyKeysApp?: MyKeysConfig;
    LSOrderKeysApp?: OrderKeysConfig;
  }
}

type AppProps = { host: HTMLElement; theme: ThemeMode };

function mount(host: HTMLElement, App: ComponentType<AppProps>) {
  if (host.shadowRoot || host.dataset.lsMounted) return;
  host.dataset.lsMounted = '1';

  // Same isolation as the support app: theme CSS stays out, ours stays in.
  const shadow = host.attachShadow({ mode: 'open' });
  const style = document.createElement('style');
  style.textContent = css;
  shadow.appendChild(style);

  const container = document.createElement('div');
  shadow.appendChild(container);
  const theme = host.dataset.theme as ThemeMode | undefined;
  createRoot(container).render(<App host={host} theme={theme === 'light' || theme === 'dark' ? theme : 'auto'} />);
}

/** One bundle, two screens: My Account → My Keys, and the order / thank-you License Keys section. */
function boot() {
  const screens: Array<[BaseConfig | undefined, string, ComponentType<AppProps>]> = [
    [window.LSMyKeysApp, '.ls-my-keys-app', MyKeysApp],
    [window.LSOrderKeysApp, '.ls-order-keys-app', OrderKeysApp],
  ];
  for (const [config, selector, App] of screens) {
    if (!config) continue;
    setConfig(config);
    window.LSSupportBooted = true;
    document.querySelectorAll<HTMLElement>(selector).forEach((host) => mount(host, App));
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
