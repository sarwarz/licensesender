import { createRoot } from 'react-dom/client';
import { App } from './App';
import type { InstanceProps, ThemeMode } from './types';
import css from './styles.css?inline';

function readProps(el: HTMLElement): InstanceProps {
  const theme = el.dataset.theme as ThemeMode | undefined;
  return {
    view: el.dataset.view === 'open' || el.dataset.view === 'auth' ? el.dataset.view : 'manage',
    ticket: el.dataset.ticket || '',
    theme: theme === 'light' || theme === 'dark' ? theme : 'auto',
    context: el.dataset.context || '',
    created: el.dataset.created === '1',
    auth: {
      title: el.dataset.title || '',
      lead: el.dataset.lead || '',
      login: el.dataset.login || '',
      register: el.dataset.register || '',
    },
  };
}

function mount(host: HTMLElement) {
  if (host.shadowRoot || host.dataset.lsMounted) return;
  host.dataset.lsMounted = '1';

  // The Shadow DOM keeps theme CSS out (and ours in), so the app looks the same on every theme.
  // Light-DOM children (the PHP loading placeholder) stop rendering once the shadow root exists.
  const shadow = host.attachShadow({ mode: 'open' });
  const style = document.createElement('style');
  style.textContent = css;
  shadow.appendChild(style);

  const container = document.createElement('div');
  shadow.appendChild(container);
  createRoot(container).render(<App props={readProps(host)} host={host} />);
}

function boot() {
  if (!window.LSSupportApp) return;
  window.LSSupportBooted = true;
  document.querySelectorAll<HTMLElement>('.ls-support-app').forEach(mount);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
