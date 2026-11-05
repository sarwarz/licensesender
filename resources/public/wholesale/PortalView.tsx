import { useEffect, useMemo, useState } from 'react';
import { ArrowRight, Boxes, ChevronLeft, ChevronRight, ClipboardList, History, PackageSearch, Search, Tag, X } from 'lucide-react';
import { cn, t } from '../support/lib';
import { Alert, Button, Card, EmptyState, Input } from '../support/ui';
import { CatalogRow } from './CatalogRow';
import { addable, config, money, unitPrice } from './lib';
import { PastOrders } from './PastOrders';
import { QuickOrder } from './QuickOrder';
import { ReviewOrder } from './ReviewOrder';
import type { WsProduct } from './types';

type Tab = 'catalog' | 'quick' | 'orders';

export interface Notice {
  tone: 'success' | 'error' | 'warning';
  text: string;
  details?: string[];
}

/** Turns per-SKU server errors into "Name: message" lines. */
export function errorDetails(errors: Record<string, string> | undefined, products: WsProduct[]): string[] {
  return Object.entries(errors ?? {}).map(([sku, message]) => `${products.find((p) => p.sku === sku)?.name ?? sku}: ${message}`);
}

// The order being built survives a reload (per browser; storage may be unavailable).
const STORAGE_KEY = 'ls-wholesale-order';

function loadSaved(products: WsProduct[]): Record<string, number> {
  try {
    const saved = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '{}') as Record<string, number>;
    const known = new Set(products.map((p) => p.sku));
    return Object.fromEntries(Object.entries(saved).filter(([sku, n]) => known.has(sku) && Number.isInteger(n) && n > 0));
  } catch {
    return {};
  }
}

function save(qty: Record<string, number>) {
  try {
    const entries = Object.entries(qty).filter(([, n]) => n > 0);
    if (entries.length) window.localStorage.setItem(STORAGE_KEY, JSON.stringify(Object.fromEntries(entries)));
    else window.localStorage.removeItem(STORAGE_KEY);
  } catch {
    // Private mode or blocked storage: the order just won't survive a reload.
  }
}

/**
 * Approved buyers build one order (catalog, quick order or a past order), review it and
 * pay. No WooCommerce cart in between.
 */
export function PortalView({ wide }: { wide: boolean }) {
  const cfg = config();
  const products = useMemo(() => cfg.catalog?.products ?? [], [cfg.catalog]);
  const perPage = Math.max(1, cfg.settings?.perPage ?? 20);
  const [tab, setTab] = useState<Tab>('catalog');
  const [reviewing, setReviewing] = useState(false);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [qty, setQty] = useState<Record<string, number>>(() => loadSaved(products));
  const [notice, setNotice] = useState<Notice | null>(null);

  useEffect(() => save(qty), [qty]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return q ? products.filter((p) => `${p.name} ${p.sku}`.toLowerCase().includes(q)) : products;
  }, [products, search]);
  const pages = Math.max(1, Math.ceil(filtered.length / perPage));
  const current = Math.min(page, pages);
  const visible = filtered.slice((current - 1) * perPage, current * perPage);

  const selected = products.filter((p) => (qty[p.sku] ?? 0) > 0);
  const selectedKeys = selected.reduce((sum, p) => sum + qty[p.sku], 0);
  const selectedTotal = selected.reduce((sum, p) => sum + qty[p.sku] * unitPrice(p, qty[p.sku]), 0);

  // Minimum order: the stricter of the group's and the store's rule.
  const group = cfg.group ?? null;
  const minKeys = Math.max(group?.minQty ?? 0, cfg.settings?.minOrderQty ?? 0);
  const shortBy = minKeys > 0 ? Math.max(0, minKeys - selectedKeys) : 0;

  const setLine = (sku: string, value: number) => setQty((prev) => ({ ...prev, [sku]: value }));

  /** Merge lines into the order (quick order, order again), capped to what can be ordered. */
  const addLines = (lines: Array<{ sku: string; qty: number }>, review = false) => {
    const skipped: string[] = [];
    const next = { ...qty };
    for (const line of lines) {
      const product = products.find((p) => p.sku === line.sku);
      if (!product || !product.buyable) {
        skipped.push(product?.name ?? line.sku);
        continue;
      }
      const wanted = (next[product.sku] ?? 0) + line.qty;
      const cap = addable(product, 0);
      if (cap <= 0) {
        skipped.push(product.name);
        continue;
      }
      next[product.sku] = Math.min(wanted, cap);
    }
    setQty(next);
    setNotice({
      tone: skipped.length ? 'warning' : 'success',
      text: skipped.length ? t('addedSomeToOrder', 'Added to your order. Some products could not be added:') : t('addedToOrder', 'Added to your order.'),
      details: skipped,
    });
    if (review && skipped.length < lines.length) {
      setReviewing(true);
    } else {
      setTab('catalog');
    }
  };

  if (reviewing && selected.length) {
    return (
      <ReviewOrder
        products={products}
        qty={qty}
        shortBy={shortBy}
        minKeys={minKeys}
        onChange={setLine}
        onBack={() => setReviewing(false)}
        onPlaced={() => save({})}
      />
    );
  }

  const tabs: Array<{ id: Tab; label: string; icon: typeof Boxes; count?: number }> = [
    { id: 'catalog', label: t('tabCatalog', 'Catalog'), icon: Boxes, count: products.length },
    { id: 'quick', label: t('tabQuick', 'Quick order'), icon: ClipboardList },
    { id: 'orders', label: t('tabOrders', 'Past orders'), icon: History, count: cfg.orders?.length || undefined },
  ];

  return (
    <div className="space-y-4">
      <header className="overflow-hidden rounded-card border border-line bg-surface shadow-card">
        <div className="bg-gradient-to-br from-brand/[0.09] via-brand/[0.03] to-transparent px-5 py-5 sm:px-6">
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-[12px] font-semibold uppercase tracking-wider text-brand">{t('eyebrow', 'Wholesale')}</span>
            {group ? (
              <span className="inline-flex items-center gap-1 rounded-full bg-brand/10 px-2 py-0.5 text-[12px] font-medium text-brand">
                <Tag className="h-3 w-3" />
                {group.discount > 0 ? t('groupBadgeDiscount', '%s pricing · %s off', group.name, `${group.discount}%`) : t('groupBadge', '%s pricing', group.name)}
              </span>
            ) : null}
          </div>
          <h2 className="mt-1.5 text-xl font-semibold tracking-tight text-fg">{t('portalTitle', 'Order in bulk')}</h2>
          <p className="mt-1 max-w-xl text-sm leading-relaxed text-muted">
            {t('portalLead', 'Prices drop as you order more. Keys are delivered to your order as soon as it is paid.')}
          </p>
        </div>

        <div className={cn('flex gap-3 border-t border-line px-3 py-2.5 sm:px-4', wide ? 'items-center justify-between' : 'flex-col')}>
          <nav className={cn('gap-1', wide ? 'flex' : 'grid grid-cols-3')} aria-label={t('sections', 'Sections')}>
            {tabs.map((item) => (
              <button
                key={item.id}
                type="button"
                onClick={() => setTab(item.id)}
                className={cn(
                  'inline-flex h-9 min-w-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-ctl text-[13px] font-medium transition-colors',
                  wide ? 'shrink-0 px-3' : 'px-1.5',
                  tab === item.id ? 'bg-brand/10 text-brand' : 'text-muted hover:bg-fg/5 hover:text-fg',
                )}
                aria-current={tab === item.id ? 'page' : undefined}
              >
                {wide ? <item.icon className="h-4 w-4" /> : null}
                {item.label}
                {item.count !== undefined ? <span className="tabular-nums opacity-70">{item.count}</span> : null}
              </button>
            ))}
          </nav>
          {tab === 'catalog' ? (
            <div className={cn('relative', wide ? 'w-64' : 'w-full')}>
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
              <Input
                value={search}
                onChange={(e) => {
                  setSearch(e.target.value);
                  setPage(1);
                }}
                placeholder={t('searchPlaceholder', 'Search products or SKU…')}
                className="h-9 pl-9 pr-8"
                aria-label={t('searchPlaceholder', 'Search products or SKU…')}
              />
              {search ? (
                <button type="button" onClick={() => setSearch('')} className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-faint hover:text-fg" aria-label={t('clearSearch', 'Clear search')}>
                  <X className="h-3.5 w-3.5" />
                </button>
              ) : null}
            </div>
          ) : null}
        </div>
      </header>

      {notice ? (
        <Alert tone={notice.tone} onClose={() => setNotice(null)}>
          {notice.text}
          {notice.details?.length ? (
            <ul className="mt-1.5 list-disc space-y-0.5 pl-4">
              {notice.details.map((line) => (
                <li key={line}>{line}</li>
              ))}
            </ul>
          ) : null}
        </Alert>
      ) : null}

      {tab === 'catalog' ? (
        !cfg.catalog?.ok ? (
          <Alert tone="error" title={t('catalogErrorTitle', 'The catalog could not load')}>
            {t('catalogErrorText', 'Please reload the page in a moment.')}
          </Alert>
        ) : (
          <Card className="overflow-hidden">
            {visible.length === 0 ? (
              <EmptyState icon={PackageSearch} title={search ? t('noMatches', 'No products match your search') : t('emptyCatalog', 'No wholesale products yet')}>
                {search ? t('noMatchesHint', 'Try another name or SKU.') : t('emptyCatalogHint', 'Check back soon. New products appear here as soon as they are added.')}
              </EmptyState>
            ) : (
              <>
                {wide ? (
                  <div className="grid grid-cols-[minmax(0,1.6fr)_minmax(0,1.4fr)_132px_96px] gap-4 border-b border-line bg-subtle px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-faint">
                    <span>{t('colProduct', 'Product')}</span>
                    <span>{t('colPrice', 'Price per key')}</span>
                    <span>{t('colQty', 'Quantity')}</span>
                    <span className="text-right">{t('colTotal', 'Total')}</span>
                  </div>
                ) : null}
                <ul className="divide-y divide-line">
                  {visible.map((product) => (
                    <CatalogRow key={product.sku} product={product} wide={wide} qty={qty[product.sku] ?? 0} inCart={0} onChange={(value) => setLine(product.sku, value)} />
                  ))}
                </ul>
                {pages > 1 ? (
                  <div className="flex items-center justify-between gap-3 border-t border-line px-4 py-3 text-[13px] text-muted">
                    <span className="tabular-nums">{t('pageOf', 'Page %d of %d', current, pages)}</span>
                    <div className="flex gap-1.5">
                      <Button size="icon-sm" onClick={() => setPage(current - 1)} disabled={current <= 1} aria-label={t('prevPage', 'Previous page')}>
                        <ChevronLeft className="h-4 w-4" />
                      </Button>
                      <Button size="icon-sm" onClick={() => setPage(current + 1)} disabled={current >= pages} aria-label={t('nextPage', 'Next page')}>
                        <ChevronRight className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                ) : null}
              </>
            )}
          </Card>
        )
      ) : null}

      {tab === 'quick' ? <QuickOrder products={products} order={qty} onAdd={(lines) => addLines(lines)} /> : null}
      {tab === 'orders' ? <PastOrders orders={cfg.orders ?? []} onReorder={(lines) => addLines(lines, true)} /> : null}

      {selected.length > 0 ? (
        <div className="sticky bottom-3 z-10 animate-fade-in">
          <div className={cn('flex gap-3 rounded-card border border-line bg-surface px-4 py-3 shadow-pop sm:px-5', wide ? 'items-center gap-x-5' : 'flex-col')}>
            <div className="min-w-0 flex-1">
              <p className="text-sm text-fg">
                <span className="font-semibold">{selected.length === 1 ? t('oneProduct', '1 product') : t('nProducts', '%d products', selected.length)}</span>
                <span className="text-muted"> · {selectedKeys === 1 ? t('oneKey', '1 key') : t('nKeys', '%d keys', selectedKeys)} · </span>
                <span className="font-semibold tabular-nums">{money(selectedTotal)}</span>
              </p>
              {minKeys > 0 ? (
                <div className="mt-1.5 flex items-center gap-2.5">
                  <div className="h-1.5 w-24 shrink-0 overflow-hidden rounded-full bg-fg/[0.07]">
                    <div className={cn('h-full rounded-full transition-[width] duration-300', shortBy > 0 ? 'bg-amber-500' : 'bg-emerald-500')} style={{ width: `${Math.min(100, (selectedKeys / minKeys) * 100)}%` }} />
                  </div>
                  <span className="text-[12px] text-muted">
                    {shortBy > 0 ? t('minShort', 'Add %d more keys to reach the %d-key minimum', shortBy, minKeys) : t('minMet', 'Minimum order reached')}
                  </span>
                </div>
              ) : null}
            </div>
            <div className={cn('flex gap-2', !wide && 'justify-end')}>
              <Button variant="ghost" size="sm" onClick={() => setQty({})}>
                {t('clear', 'Clear')}
              </Button>
              <Button variant="primary" onClick={() => setReviewing(true)} disabled={shortBy > 0}>
                {t('reviewOrder', 'Review order')} <ArrowRight className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
