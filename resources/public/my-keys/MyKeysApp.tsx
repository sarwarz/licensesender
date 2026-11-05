import { useEffect, useRef, useState } from 'react';
import { ChevronLeft, ChevronRight, KeyRound, Search, X } from 'lucide-react';
import { ajax, cn, getConfig, t } from '../support/lib';
import { brandStyle, useSiteDark, useWide } from '../support/shell';
import type { ThemeMode } from '../support/types';
import { Alert, Button, Card, EmptyState } from '../support/ui';
import { KeyRow, ListSkeleton, type KeysApi } from './parts';
import type { KeyItem, KeyPage, KeysPayload, MyKeysConfig } from './types';

export const rowKey = (item: KeyItem) => `${item.order_id}-${item.product_id}`;

/** My Account → My Keys: the logged-in customer's keys across all their orders. */
export function MyKeysApp({ host, theme }: { host: HTMLElement; theme: ThemeMode }) {
  const config = getConfig<MyKeysConfig>();
  const rootRef = useRef<HTMLDivElement>(null);
  const dark = useSiteDark(host, theme);
  const wide = useWide(rootRef, 600);
  const [data, setData] = useState<KeyPage>(config.initial);
  const [payloads, setPayloads] = useState<Record<string, KeysPayload>>({});
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [openRow, setOpenRow] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [query, setQuery] = useState(''); // the search the current results are for
  const requestId = useRef(0);

  const api: KeysApi = {
    view: (item) => ajax<KeysPayload>('licensesender_view_key', { nonce: config.viewNonce, order_id: item.order_id, product_id: item.product_id }),
    get: (item) => ajax<KeysPayload>('licensesender_get_key', { nonce: config.getNonce, order_id: item.order_id, product_id: item.product_id }),
  };

  const updateRow = (item: KeyItem, payload: KeysPayload) => {
    setData((current) => ({ ...current, items: current.items.map((row) => (rowKey(row) === rowKey(item) ? item : row)) }));
    setPayloads((current) => ({ ...current, [rowKey(item)]: payload }));
  };

  const load = async (page: number, term: string, scroll = false) => {
    const id = ++requestId.current;
    setLoading(true);
    setError('');
    try {
      const result = await ajax<KeyPage>('ls_my_keys_list', { page, search: term });
      if (id !== requestId.current) return; // a newer search replaced this one
      setData(result);
      setQuery(term);
      setOpenRow(null);
      if (scroll) rootRef.current?.scrollIntoView({ block: 'start', behavior: 'smooth' });
    } catch (err) {
      if (id === requestId.current) setError((err as Error).message);
    } finally {
      if (id === requestId.current) setLoading(false);
    }
  };

  // Search as the customer types, once they pause.
  useEffect(() => {
    const term = search.trim();
    if (term === query) return undefined;
    const timer = window.setTimeout(() => load(1, term), 350);
    return () => window.clearTimeout(timer);
  }, [search]);

  const goTo = (page: number) => load(page, query, true);

  return (
    <div ref={rootRef} className={cn('ls-root', dark && 'dark')} style={brandStyle()}>
      <div className="space-y-5">
        <header className={cn('flex gap-4', wide ? 'items-center justify-between' : 'flex-col')}>
          <div className="flex min-w-0 items-start gap-3">
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-brand">
              <KeyRound className="h-5 w-5" />
            </span>
            <div className="min-w-0">
              <h2 className="text-xl font-semibold tracking-tight text-fg">{t('title', 'My keys')}</h2>
              <p className="mt-0.5 text-sm text-muted">{t('lead', 'Your license keys from your orders. View, copy or fetch them any time.')}</p>
            </div>
          </div>
          <div className={cn('relative shrink-0', wide ? 'w-64' : 'w-full')}>
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
            <input
              type="search"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={t('searchOrders', 'Search product or order…')}
              aria-label={t('searchOrders', 'Search product or order…')}
              className="h-10 w-full rounded-ctl border border-line bg-surface pl-9 pr-9 text-sm text-fg shadow-sm placeholder:text-faint focus:border-brand focus:outline-none focus:ring-4 focus:ring-brand/15 [&::-webkit-search-cancel-button]:hidden"
            />
            {search ? (
              <button type="button" onClick={() => setSearch('')} className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-faint hover:text-fg" aria-label={t('clearSearch', 'Clear search')}>
                <X className="h-3.5 w-3.5" />
              </button>
            ) : null}
          </div>
        </header>

        {error ? (
          <Alert tone="error" onClose={() => setError('')}>
            {error}
          </Alert>
        ) : null}

        <Card className="overflow-hidden">
          {loading ? (
            <ListSkeleton />
          ) : data.items.length === 0 && query ? (
            <EmptyState
              icon={Search}
              title={t('noResults', 'Nothing matches "%s"', query)}
              action={
                <Button size="sm" onClick={() => setSearch('')}>
                  {t('clearSearch', 'Clear search')}
                </Button>
              }
            >
              {t('noResultsHint', 'Try the order number (e.g. 1042) or part of the product name.')}
            </EmptyState>
          ) : data.items.length === 0 ? (
            <EmptyState
              icon={KeyRound}
              title={t('emptyTitle', 'No license keys yet')}
              action={
                config.shopUrl ? (
                  <a href={config.shopUrl} className="inline-flex h-10 items-center gap-2 rounded-ctl bg-brand px-4 text-sm font-medium text-brand-fg no-underline shadow-sm hover:bg-brand/90">
                    {t('browseShop', 'Browse the shop')}
                  </a>
                ) : null
              }
            >
              {t('emptyHint', 'Keys from your orders appear here once they are ready.')}
            </EmptyState>
          ) : (
            <ul className="divide-y divide-line">
              {data.items.map((item) => (
                <KeyRow
                  key={rowKey(item)}
                  item={item}
                  payload={payloads[rowKey(item)] ?? null}
                  onUpdate={updateRow}
                  api={api}
                  wide={wide}
                  open={openRow === rowKey(item)}
                  onOpenChange={(next) => setOpenRow(next ? rowKey(item) : null)}
                />
              ))}
            </ul>
          )}

          {data.total_pages > 1 ? (
            <div className="flex items-center justify-between gap-3 border-t border-line bg-subtle px-5 py-3 text-[13px] text-muted">
              <span>{t('pageOf', 'Page %d of %d', data.page, data.total_pages)}</span>
              <div className="flex items-center gap-1">
                <Button size="sm" variant="ghost" icon={ChevronLeft} disabled={data.page <= 1 || loading} onClick={() => goTo(data.page - 1)}>
                  {t('prev', 'Previous')}
                </Button>
                <Button size="sm" variant="ghost" disabled={data.page >= data.total_pages || loading} onClick={() => goTo(data.page + 1)}>
                  {t('next', 'Next')}
                  <ChevronRight className="h-4 w-4" />
                </Button>
              </div>
            </div>
          ) : null}
        </Card>
      </div>
    </div>
  );
}
