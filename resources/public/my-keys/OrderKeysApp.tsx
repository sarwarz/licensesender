import { useRef, useState } from 'react';
import { FileSpreadsheet, KeyRound, ShieldCheck } from 'lucide-react';
import { ajax, cn, getConfig, t } from '../support/lib';
import { brandStyle, useSiteDark, useWide } from '../support/shell';
import type { ThemeMode } from '../support/types';
import { Alert, Button, Card } from '../support/ui';
import { rowKey } from './MyKeysApp';
import { ItemVisual, KeyRow, afterDelivery, type KeysApi } from './parts';
import type { KeyItem, KeysPayload, OrderKeysConfig } from './types';

/**
 * First visit, nothing delivered yet: one focused card. One main action gets everything;
 * with several products each line can also be collected on its own.
 */
function DeliveryCard({
  items,
  email,
  progress,
  error,
  onDismissError,
  onConfirm,
}: {
  items: KeyItem[];
  email: string;
  progress: { done: number; total: number; current: string } | null;
  error: string;
  onDismissError: () => void;
  onConfirm: (selection: KeyItem[]) => void;
}) {
  // What the customer is about to collect: all lines, or one they picked.
  const [confirming, setConfirming] = useState<KeyItem[] | null>(null);
  const totalKeys = items.reduce((sum, item) => sum + item.qty, 0);
  const several = items.length > 1;
  const products = (confirming ?? items).map((item) => item.product_name).join(', ');
  const confirmText = t('confirmText', 'We will fetch your license keys for {product}. Continue?').replace(/\{product\}/g, products);
  const working = !!progress;
  const percent = progress ? Math.round(((progress.done + 0.5) / progress.total) * 100) : 0;

  return (
    <section className="overflow-hidden rounded-card border border-line bg-surface shadow-card" aria-label={t('sectionTitle', 'License keys')}>
      {/* Soft brand wash behind the header */}
      <div className="bg-gradient-to-br from-brand/[0.09] via-brand/[0.03] to-transparent px-5 pb-5 pt-6 sm:px-7">
        <div className="flex items-center gap-2 text-[12px] font-semibold uppercase tracking-wider text-brand">
          <KeyRound className="h-4 w-4" />
          {t('sectionTitle', 'License keys')}
        </div>
        <h2 className="mt-2 text-xl font-semibold tracking-tight text-fg">{t('readyTitle', 'Your license keys are ready')}</h2>
        <p className="mt-1 max-w-xl text-sm leading-relaxed text-muted">
          {email
            ? t('readyLead', 'Get them now — they appear right here, and we email a copy to %s.', email)
            : t('readyLeadNoEmail', 'Get them now — they appear right here.')}
        </p>
      </div>

      <ul className="divide-y divide-line border-y border-line">
        {items.map((item) => (
          <li key={`${item.order_id}-${item.product_id}`} className="flex items-center gap-4 px-5 py-4 sm:px-7">
            <ItemVisual item={item} size="lg" />
            <div className="min-w-0 flex-1">
              <p className="truncate text-[15px] font-semibold text-fg">{item.product_name}</p>
              <p className="mt-0.5 text-[13px] text-muted">
                {item.qty === 1 ? t('oneLicense', '1 license key') : t('licensesCount', '%d license keys', item.qty)}
              </p>
            </div>
            {several ? (
              <Button
                size="sm"
                icon={KeyRound}
                loading={progress?.current === `${item.order_id}-${item.product_id}`}
                disabled={working}
                onClick={() => setConfirming([item])}
                className="shrink-0"
              >
                {t('getKeys', 'Get keys')}
              </Button>
            ) : null}
          </li>
        ))}
      </ul>

      <div className="space-y-3 px-5 py-5 sm:px-7">
        {working ? (
          <div className="space-y-2" role="status">
            <div className="flex items-center justify-between text-[13px]">
              <span className="font-medium text-fg">{t('delivering', 'Delivering your keys…')}</span>
              {progress && progress.total > 1 ? <span className="tabular-nums text-muted">{t('stepOf', '%d of %d', progress.done + 1, progress.total)}</span> : null}
            </div>
            <div className="h-1.5 overflow-hidden rounded-full bg-fg/[0.07]">
              <div className="h-full rounded-full bg-brand transition-[width] duration-500" style={{ width: `${Math.max(12, percent)}%` }} />
            </div>
          </div>
        ) : confirming ? (
          <div className="flex animate-fade-in flex-col gap-3 rounded-ctl border border-brand/25 bg-brand/[0.06] p-4 sm:flex-row sm:items-center">
            <div className="min-w-0 flex-1">
              <p className="text-sm font-semibold text-fg">{t('confirmTitle', 'Get license keys?')}</p>
              <p className="mt-0.5 text-[13px] text-muted">{confirmText}</p>
            </div>
            <div className="flex shrink-0 gap-2">
              <Button size="sm" variant="ghost" onClick={() => setConfirming(null)}>
                {t('cancelBtn', 'Cancel')}
              </Button>
              <Button
                size="sm"
                variant="primary"
                onClick={() => {
                  const selection = confirming;
                  setConfirming(null);
                  onConfirm(selection);
                }}
              >
                {t('confirmBtn', 'Yes, get keys')}
              </Button>
            </div>
          </div>
        ) : (
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="flex items-center gap-1.5 text-[13px] text-muted">
              <ShieldCheck className="h-4 w-4 text-emerald-500" />
              {t('deliveryNote', 'Delivered instantly to your order.')}
            </p>
            <Button variant="primary" icon={KeyRound} onClick={() => setConfirming(items)} className="h-11 px-6 text-[15px]">
              {several
                ? t('getAllCount', 'Get all %d keys', totalKeys)
                : totalKeys === 1
                  ? t('getMyKey', 'Get my key')
                  : t('getMyKeys', 'Get my %d keys', totalKeys)}
            </Button>
          </div>
        )}

        {error ? (
          <Alert tone="error" onClose={onDismissError}>
            {error}
          </Alert>
        ) : null}
      </div>
    </section>
  );
}

/**
 * License Keys section on the order details and thank-you pages. One order, so rows hide
 * order info; guests are allowed through with the order key + billing email.
 */
export function OrderKeysApp({ host, theme }: { host: HTMLElement; theme: ThemeMode }) {
  const config = getConfig<OrderKeysConfig>();
  const rootRef = useRef<HTMLDivElement>(null);
  const dark = useSiteDark(host, theme);
  const wide = useWide(rootRef, 600);
  const [items, setItems] = useState<KeyItem[]>(config.items);
  const [payloads, setPayloads] = useState<Record<string, KeysPayload>>({});
  const [openRow, setOpenRow] = useState<string | null>(null);
  const [progress, setProgress] = useState<{ done: number; total: number; current: string } | null>(null);
  const [error, setError] = useState('');
  const [summary, setSummary] = useState('');

  // Every request proves access the same way the old section did.
  const proof = { order_id: config.orderId, order_key: config.orderKey, email: config.email, nonce: config.nonce };
  const api: KeysApi = {
    view: (item) => ajax<KeysPayload>('ls_order_keys_view', { ...proof, product_id: item.product_id }),
    get: (item) => ajax<KeysPayload>('ls_order_keys_get', { ...proof, product_id: item.product_id }),
  };

  const updateRow = (item: KeyItem, payload: KeysPayload) => {
    setItems((current) => current.map((row) => (rowKey(row) === rowKey(item) ? item : row)));
    setPayloads((current) => ({ ...current, [rowKey(item)]: payload }));
  };

  const pending = items.filter((item) => item.state === 'fetch');

  /** Get keys for the given lines (default: all waiting), one at a time (the API locks per order). */
  const getAll = async (selection: KeyItem[] = pending) => {
    const queue = [...selection];
    setError('');
    setSummary('');
    let delivered = 0;
    let firstDone: string | null = null;
    for (let i = 0; i < queue.length; i++) {
      const item = queue[i];
      setProgress({ done: i, total: queue.length, current: rowKey(item) });
      try {
        const payload = await api.get(item);
        const next = afterDelivery(item, payload);
        updateRow(next.item, payload);
        if (!next.partial) delivered++;
        firstDone = firstDone ?? rowKey(item);
      } catch (err) {
        setError(`${item.product_name}: ${(err as Error).message}`);
        break;
      }
    }
    setProgress(null);
    if (firstDone) setOpenRow(firstDone);
    if (delivered === queue.length) {
      setSummary(queue.length === 1 ? t('allDeliveredOne', 'Your keys are ready.') : t('allDelivered', 'Keys delivered for all %d products.', queue.length));
    }
  };

  const hasKeys = items.some((item) => item.key_count > 0);
  // Nothing delivered yet: one clear call to action instead of a row plus header buttons.
  const firstDelivery = config.canFetch && pending.length > 0 && pending.length === items.length && !hasKeys;

  if (firstDelivery) {
    return (
      <div ref={rootRef} className={cn('ls-root', dark && 'dark')} style={brandStyle()}>
        <DeliveryCard items={pending} email={config.email} progress={progress} error={error} onDismissError={() => setError('')} onConfirm={getAll} />
      </div>
    );
  }

  return (
    <div ref={rootRef} className={cn('ls-root', dark && 'dark')} style={brandStyle()}>
      <section className="space-y-4" aria-label={t('sectionTitle', 'License keys')}>
        <header className={cn('flex gap-3', wide ? 'items-center justify-between' : 'flex-col')}>
          <div className="flex items-center gap-2.5">
            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand/10 text-brand">
              <KeyRound className="h-[18px] w-[18px]" />
            </span>
            <h2 className="text-lg font-semibold tracking-tight text-fg">{t('sectionTitle', 'License keys')}</h2>
          </div>
          <div className="flex flex-wrap gap-2">
            {hasKeys && config.exportUrl ? (
              <a
                href={config.exportUrl}
                className="inline-flex h-9 items-center gap-1.5 rounded-ctl border border-line bg-surface px-3 text-[13px] font-medium text-fg no-underline shadow-sm transition-colors hover:bg-subtle"
              >
                <FileSpreadsheet className="h-4 w-4" />
                {t('downloadAll', 'Download all (CSV)')}
              </a>
            ) : null}
            {/* With one waiting product its own "Get keys" button is enough. */}
            {config.canFetch && pending.length > 1 ? (
              <Button size="sm" variant="primary" icon={KeyRound} loading={!!progress} onClick={() => getAll()} className="h-9">
                {progress ? t('gettingAll', 'Getting keys %d of %d…', progress.done + 1, progress.total) : t('getAll', 'Get all keys')}
              </Button>
            ) : null}
          </div>
        </header>

        {!config.canFetch && config.blockMessage && items.some((item) => item.state === 'blocked') ? (
          <Alert tone="warning">{config.blockMessage}</Alert>
        ) : null}
        {error ? (
          <Alert tone="error" onClose={() => setError('')}>
            {error}
          </Alert>
        ) : null}
        {summary ? (
          <Alert tone="success" onClose={() => setSummary('')}>
            {summary}
          </Alert>
        ) : null}

        <Card className="overflow-hidden">
          <ul className="divide-y divide-line">
            {items.map((item) => (
              <KeyRow
                key={rowKey(item)}
                item={item}
                payload={payloads[rowKey(item)] ?? null}
                onUpdate={updateRow}
                api={api}
                wide={wide}
                showOrder={false}
                busyExternally={progress?.current === rowKey(item)}
                open={openRow === rowKey(item)}
                onOpenChange={(next) => setOpenRow(next ? rowKey(item) : null)}
              />
            ))}
          </ul>
        </Card>
      </section>
    </div>
  );
}
