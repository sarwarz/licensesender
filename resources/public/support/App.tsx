import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { cn, getConfig } from './lib';
import { brandStyle, useSiteDark, useWide } from './shell';
import type { InstanceProps, Route } from './types';
import { TicketListView } from './views/TicketListView';
import { TicketView } from './views/TicketView';
import { NewTicketView } from './views/NewTicketView';
import { AuthView } from './views/AuthView';

interface AppContextValue {
  wide: boolean;
  navigate: (route: Route) => void;
  hrefFor: (route: Route) => string;
  isManagePage: boolean;
}

const AppContext = createContext<AppContextValue | null>(null);

export function useApp(): AppContextValue {
  const ctx = useContext(AppContext);
  if (!ctx) throw new Error('useApp outside App');
  return ctx;
}

function samePage(url: string): boolean {
  if (!url) return false;
  try {
    const a = new URL(url, window.location.href);
    return a.origin === window.location.origin && a.pathname.replace(/\/$/, '') === window.location.pathname.replace(/\/$/, '');
  } catch {
    return false;
  }
}

function routeFromLocation(fallback: Route): Route {
  const params = new URLSearchParams(window.location.search);
  const ticket = params.get('ls_ticket');
  if (ticket) return { name: 'ticket', number: ticket };
  if (params.get('ls_new') === '1') return { name: 'new' };
  return fallback;
}

/** Remove one-time and secret params (magic-link tokens) from the address bar. */
function cleanUrl() {
  const url = new URL(window.location.href);
  let changed = false;
  for (const key of ['access_token', 'ticket_access_token', 'portal_token', 'token', 'ls_support_created']) {
    if (url.searchParams.has(key)) {
      url.searchParams.delete(key);
      changed = true;
    }
  }
  if (changed) window.history.replaceState(window.history.state, '', url.toString());
}

export function App({ props, host }: { props: InstanceProps; host: HTMLElement }) {
  const cfg = getConfig();
  const rootRef = useRef<HTMLDivElement>(null);
  const isManagePage = props.view === 'manage';
  const initialRoute: Route = isManagePage ? { name: 'list' } : { name: 'new' };

  const [route, setRoute] = useState<Route>(() => {
    if (props.ticket) return { name: 'ticket', number: props.ticket, created: props.created };
    return isManagePage ? routeFromLocation(initialRoute) : initialRoute;
  });

  const dark = useSiteDark(host, props.theme);
  const wide = useWide(rootRef);

  /* ----- Routing ----- */
  useEffect(() => {
    cleanUrl();
    if (!isManagePage) return undefined;
    const onPop = () => setRoute(routeFromLocation(initialRoute));
    window.addEventListener('popstate', onPop);
    return () => window.removeEventListener('popstate', onPop);
  }, [isManagePage]);

  // In My Account (or when no tickets page is set) the list and tickets live on the current page.
  const listBase = useMemo(() => {
    if (isManagePage && (props.context === 'my_account' || !cfg.manageUrl)) {
      const url = new URL(window.location.href);
      ['ls_ticket', 'ls_new'].forEach((key) => url.searchParams.delete(key));
      return url.toString();
    }
    return cfg.manageUrl || window.location.href.split('?')[0];
  }, [cfg.manageUrl, isManagePage, props.context]);

  const hrefFor = useCallback(
    (target: Route): string => {
      const url = new URL(listBase, window.location.href);
      if (target.name === 'ticket') {
        url.searchParams.set('ls_ticket', target.number);
        return url.toString();
      }
      if (target.name === 'new') {
        if (cfg.openUrl && !(isManagePage && samePage(cfg.openUrl))) return cfg.openUrl;
        url.searchParams.set('ls_new', '1');
        return url.toString();
      }
      return url.toString();
    },
    [cfg.openUrl, isManagePage, listBase],
  );

  const navigate = useCallback(
    (target: Route) => {
      const href = hrefFor(target);
      // Stay in the app when the target lives on this page; otherwise load the other page.
      if (isManagePage && samePage(href)) {
        const url = new URL(href, window.location.href);
        window.history.pushState({}, '', url.toString());
        setRoute(target);
        rootRef.current?.scrollIntoView({ block: 'start', behavior: 'smooth' });
        return;
      }
      window.location.href = href;
    },
    [hrefFor, isManagePage],
  );

  return (
    <AppContext.Provider value={{ wide, navigate, hrefFor, isManagePage }}>
      <div ref={rootRef} className={cn('ls-root', dark && 'dark')} style={brandStyle()} data-context={props.context || undefined}>
        {props.view === 'auth' ? (
          <AuthView auth={props.auth} />
        ) : route.name === 'ticket' ? (
          <TicketView key={route.number} number={route.number} created={!!route.created} />
        ) : route.name === 'new' ? (
          <NewTicketView />
        ) : (
          <TicketListView />
        )}
      </div>
    </AppContext.Provider>
  );
}
