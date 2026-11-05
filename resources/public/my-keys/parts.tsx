import { useState } from 'react';
import type { LucideIcon } from 'lucide-react';
import {
  BookOpen,
  Check,
  ChevronDown,
  Clock,
  Copy,
  Download,
  Eye,
  FileDown,
  EyeOff,
  KeyRound,
  Mail,
  Search,
  ShieldAlert,
  Sparkles,
  X,
} from 'lucide-react';
import { cn, t } from '../support/lib';
import { Alert, Button, Skeleton } from '../support/ui';
import type { KeyItem, KeysPayload, KeyState } from './types';

function isHttpUrl(value: string): boolean {
  return /^https?:\/\//i.test(value.trim());
}

/** Visual identity per state: the row's icon tile carries the status, so rows need no extra pill. */
const stateStyle: Record<KeyState, { icon: LucideIcon; tile: string; dot: string; label: () => string }> = {
  ready: {
    icon: KeyRound,
    tile: 'bg-emerald-500/10 text-emerald-600 ring-emerald-500/20 dark:text-emerald-400',
    dot: 'bg-emerald-500',
    label: () => t('stateReady', 'Keys ready'),
  },
  fetch: {
    icon: Sparkles,
    tile: 'bg-brand/10 text-brand ring-brand/20',
    dot: 'bg-brand',
    label: () => t('stateFetch', 'Ready to deliver'),
  },
  pending: {
    icon: Clock,
    tile: 'bg-amber-500/10 text-amber-600 ring-amber-500/25 dark:text-amber-400',
    dot: 'bg-amber-500',
    label: () => t('statePending', 'Pending completion'),
  },
  blocked: {
    icon: ShieldAlert,
    tile: 'bg-fg/5 text-faint ring-line',
    dot: 'bg-faint',
    label: () => t('stateBlocked', 'Unavailable'),
  },
};

export function ItemVisual({ item, size = 'md' }: { item: KeyItem; size?: 'md' | 'lg' }) {
  const { icon: Icon, tile, dot, label } = stateStyle[item.state];
  const box = size === 'lg' ? 'h-14 w-14 rounded-2xl' : 'h-11 w-11 rounded-xl';
  if (item.thumb) {
    return (
      <span className={cn('relative shrink-0', box)} title={label()}>
        <img src={item.thumb} alt="" loading="lazy" className={cn('bg-subtle object-cover ring-1 ring-line', box)} />
        <span className={cn('absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full ring-2 ring-surface', dot)} />
      </span>
    );
  }
  return (
    <span className={cn('flex shrink-0 items-center justify-center ring-1 ring-inset', box, tile)} title={label()}>
      <Icon className={size === 'lg' ? 'h-6 w-6' : 'h-5 w-5'} />
    </span>
  );
}

function CopyButton({ text, label, className, compact }: { text: string; label?: string; className?: string; compact?: boolean }) {
  const [copied, setCopied] = useState(false);
  const copy = async () => {
    try {
      await navigator.clipboard.writeText(text);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1500);
    } catch {
      // Clipboard blocked: the key is still selectable on screen.
    }
  };
  const name = label || t('copy', 'Copy');

  // Compact: icon-only, borderless — used per key so long lists stay quiet.
  if (compact) {
    return (
      <button
        type="button"
        onClick={copy}
        title={copied ? t('copied', 'Copied') : name}
        aria-label={name}
        className={cn(
          'flex h-8 w-8 shrink-0 items-center justify-center rounded-ctl transition-colors',
          copied ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'text-faint hover:bg-fg/5 hover:text-fg',
          className,
        )}
      >
        {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
      </button>
    );
  }

  return (
    <button
      type="button"
      onClick={copy}
      className={cn(
        'inline-flex h-8 shrink-0 items-center gap-1.5 rounded-ctl border px-2.5 text-xs font-medium shadow-sm transition-colors',
        copied
          ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
          : 'border-line bg-surface text-fg hover:bg-subtle',
        className,
      )}
      aria-label={name}
    >
      {copied ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
      {copied ? t('copied', 'Copied') : name}
    </button>
  );
}

const linkButton =
  'inline-flex h-8 items-center gap-1.5 rounded-ctl border border-line bg-surface px-3 text-xs font-medium text-fg no-underline shadow-sm transition-colors hover:bg-subtle';

/* ---------- Keys panel (inset card under a row) ---------- */

/** Above this many keys the list gets a search box and scrolls inside its own box. */
const LARGE_LIST = 8;

/** Plain-text file the customer can keep: a short header, then one key per line. */
function downloadKeys(item: KeyItem, keys: string[]) {
  const header = [
    item.product_name,
    `${t('orderShort', 'Order %s', `#${item.order_number}`)}${item.order_date ? ` · ${item.order_date}` : ''}`,
    keys.length === 1 ? t('oneKey', '1 key') : t('keysCount', '%d keys', keys.length),
  ];
  // CRLF so the file reads correctly in Windows Notepad too.
  const text = [...header, '', ...keys].join('\r\n') + '\r\n';
  const url = URL.createObjectURL(new Blob([text], { type: 'text/plain;charset=utf-8' }));
  const link = document.createElement('a');
  link.href = url;
  link.download = `license-keys-order-${String(item.order_number).replace(/[^\w-]+/g, '')}.txt`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function KeysPanel({ payload, item }: { payload: KeysPayload; item: KeyItem }) {
  const [showGuide, setShowGuide] = useState(false);
  const [query, setQuery] = useState('');
  const keys = payload.keys.map((k) => k.key_value).filter(Boolean);
  const meta = payload.meta;
  const download = isHttpUrl(meta.download_link || '') ? meta.download_link : '';
  const guideUrl = isHttpUrl(meta.guide_url || '') ? meta.guide_url : isHttpUrl(meta.activation_guide || '') ? meta.activation_guide : '';
  const guideText = !guideUrl && meta.activation_guide ? meta.activation_guide : '';
  const large = keys.length > LARGE_LIST;

  const needle = query.trim().toLowerCase().replace(/[\s-]/g, '');
  const visible = keys
    .map((key, index) => ({ key, index }))
    .filter(({ key }) => !needle || key.toLowerCase().replace(/[\s-]/g, '').includes(needle));

  return (
    <div className="animate-fade-in px-4 pb-4 sm:px-5">
      <div className="overflow-hidden rounded-card border border-line bg-subtle">
        {/* Toolbar */}
        <div className="flex flex-wrap items-center justify-between gap-2 px-4 pt-3.5">
          <div className="flex items-center gap-2">
            <p className="text-[11px] font-semibold uppercase tracking-wider text-muted">
              {keys.length > 1 ? t('viewTitleMany', 'Your license keys') : t('viewTitle', 'Your license key')}
            </p>
            <span className="rounded-full bg-fg/5 px-2 py-px text-[11px] font-semibold tabular-nums text-muted">{keys.length}</span>
          </div>
          <div className="flex items-center gap-2">
            {keys.length > 1 ? <CopyButton text={keys.join('\n')} label={t('copyAll', 'Copy all')} /> : null}
            <button type="button" onClick={() => downloadKeys(item, keys)} className={linkButton}>
              <FileDown className="h-3.5 w-3.5" />
              {t('downloadTxt', 'Download .txt')}
            </button>
          </div>
        </div>

        <div className="p-4 pt-3">
          {large ? (
            <div className="mb-2.5 flex items-center gap-3">
              <div className="relative min-w-0 flex-1">
                <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-faint" />
                <input
                  type="search"
                  value={query}
                  onChange={(e) => setQuery(e.target.value)}
                  placeholder={t('searchKeys', 'Search keys…')}
                  aria-label={t('searchKeys', 'Search keys…')}
                  className="h-8 w-full rounded-ctl border border-line bg-surface pl-8 pr-8 text-[13px] text-fg placeholder:text-faint focus:border-brand focus:outline-none focus:ring-4 focus:ring-brand/15 [&::-webkit-search-cancel-button]:hidden"
                />
                {query ? (
                  <button type="button" onClick={() => setQuery('')} className="absolute right-1.5 top-1/2 -translate-y-1/2 rounded p-1 text-faint hover:text-fg" aria-label={t('clearSearch', 'Clear search')}>
                    <X className="h-3.5 w-3.5" />
                  </button>
                ) : null}
              </div>
              <span className="shrink-0 text-xs tabular-nums text-muted">
                {needle ? t('showingKeys', '%d of %d', visible.length, keys.length) : t('keysCount', '%d keys', keys.length)}
              </span>
            </div>
          ) : null}

          {/* One bordered list; tall lists scroll inside it so the page stays short. */}
          <ol className={cn('divide-y divide-line overflow-hidden rounded-ctl border border-line bg-surface shadow-sm', large && 'max-h-[360px] overflow-y-auto overscroll-contain')}>
            {visible.map(({ key, index }) => (
              <li key={`${key}-${index}`} className="group flex items-center gap-3 py-1.5 pl-3 pr-1.5 transition-colors hover:bg-subtle">
                {keys.length > 1 ? (
                  <span className="w-7 shrink-0 text-right text-[11px] font-semibold tabular-nums text-faint">{index + 1}</span>
                ) : null}
                <code className="min-w-0 flex-1 select-all break-all py-1 font-mono text-[12px] font-semibold text-fg sm:text-[14px] sm:tracking-wide">{key}</code>
                <CopyButton text={key} compact />
              </li>
            ))}
            {visible.length === 0 ? <li className="px-3 py-6 text-center text-[13px] text-muted">{t('noKeyMatch', 'No keys match your search.')}</li> : null}
          </ol>
        </div>

        {download || guideUrl || guideText || meta.email ? (
          <div className="flex flex-wrap items-center gap-2 border-t border-line px-4 py-3">
            {download ? (
              <a href={download} target="_blank" rel="noopener noreferrer" className={linkButton}>
                <Download className="h-3.5 w-3.5" />
                {t('downloadSoftware', 'Download software')}
              </a>
            ) : null}
            {guideUrl ? (
              <a href={guideUrl} target="_blank" rel="noopener noreferrer" className={linkButton}>
                <BookOpen className="h-3.5 w-3.5" />
                {t('activationGuide', 'Activation guide')}
              </a>
            ) : guideText ? (
              <button type="button" onClick={() => setShowGuide((v) => !v)} className={linkButton} aria-expanded={showGuide}>
                <BookOpen className="h-3.5 w-3.5" />
                {t('activationGuide', 'Activation guide')}
                <ChevronDown className={cn('h-3.5 w-3.5 transition-transform', showGuide && 'rotate-180')} />
              </button>
            ) : null}
            {meta.email ? (
              <span className="ml-auto inline-flex items-center gap-1.5 text-xs text-muted">
                <Mail className="h-3.5 w-3.5" />
                {t('sentTo', 'Sent to %s', meta.email)}
              </span>
            ) : null}
          </div>
        ) : null}

        {showGuide && guideText ? (
          <div className="whitespace-pre-wrap border-t border-line bg-surface px-4 py-3 text-[13px] leading-relaxed text-fg">{guideText}</div>
        ) : null}
      </div>
    </div>
  );
}

/* ---------- One order line ---------- */

//** How a list talks to the server: My Keys (logged-in) and the order page (owner or guest) differ. */
export interface KeysApi {
  view: (item: KeyItem) => Promise<KeysPayload>;
  get: (item: KeyItem) => Promise<KeysPayload>;
}

/** Item state after keys arrive: a short delivery keeps "Get keys" so the rest can follow. */
export function afterDelivery(item: KeyItem, payload: KeysPayload): { item: KeyItem; partial: boolean } {
  const expected = payload.expected ?? item.qty;
  const partial = payload.count < expected;
  return { item: { ...item, state: partial ? 'fetch' : 'ready', key_count: payload.count }, partial };
}

export function partialNotice(payload: KeysPayload, item: KeyItem): string {
  return t('partialDelivery', '%d of %d keys delivered. The rest will be ready soon — try Get keys again later.', payload.count, payload.expected ?? item.qty);
}

interface KeyRowProps {
  item: KeyItem;
  payload: KeysPayload | null;
  /** Called when keys load or arrive; the list stores both. */
  onUpdate: (item: KeyItem, payload: KeysPayload) => void;
  /** Only one row is open at a time (the list owns which), so big key lists never stack up. */
  open: boolean;
  onOpenChange: (open: boolean) => void;
  api: KeysApi;
  wide: boolean;
  /** My Keys lists many orders; the order page is a single order, so it hides order info. */
  showOrder?: boolean;
  /** Set while the list's "Get all keys" is working on this row. */
  busyExternally?: boolean;
}

export function KeyRow({ item, payload, onUpdate, open, onOpenChange, api, wide, showOrder = true, busyExternally = false }: KeyRowProps) {
  const [confirming, setConfirming] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const working = busy || busyExternally;

  const view = async () => {
    if (payload) {
      onOpenChange(!open);
      return;
    }
    setBusy(true);
    setError('');
    try {
      onUpdate(item, await api.view(item));
      onOpenChange(true);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  };

  const fetchKeys = async () => {
    setConfirming(false);
    setBusy(true);
    setError('');
    try {
      const result = await api.get(item);
      const next = afterDelivery(item, result);
      onUpdate(next.item, result);
      setNotice(next.partial ? partialNotice(result, item) : '');
      onOpenChange(true);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  };

  const confirmText = t('confirmText', 'We will fetch your license keys for {product}. Continue?').replace(/\{product\}/g, item.product_name);
  const status = stateStyle[item.state];
  const keyCount = item.key_count > 0 ? (item.key_count === 1 ? t('oneKey', '1 key') : t('keysCount', '%d keys', item.key_count)) : '';

  return (
    <li className={cn('transition-colors', open && 'bg-subtle/40')}>
      <div className={cn('flex gap-3.5 px-4 py-4 sm:px-5', wide ? 'items-center' : 'flex-col')}>
        <div className="flex min-w-0 flex-1 items-center gap-3.5">
          <ItemVisual item={item} />
          <div className="min-w-0">
            <p className="truncate text-[15px] font-semibold text-fg">{item.product_name}</p>
            <p className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[13px] text-muted">
              {showOrder ? (
                <>
                  {item.order_url ? (
                    <a href={item.order_url} className="font-medium text-fg no-underline hover:text-brand hover:underline">
                      {t('orderShort', 'Order %s', `#${item.order_number}`)}
                    </a>
                  ) : (
                    <span className="font-medium text-fg">{t('orderShort', 'Order %s', `#${item.order_number}`)}</span>
                  )}
                  {item.order_date ? (
                    <>
                      <span className="text-faint">·</span>
                      <span>{item.order_date}</span>
                    </>
                  ) : null}
                  <span className="text-faint">·</span>
                </>
              ) : null}
              <span>{t('qty', 'Qty %d', item.qty)}</span>
              {keyCount ? (
                <>
                  <span className="text-faint">·</span>
                  <span className="inline-flex items-center gap-1 font-medium text-emerald-700 dark:text-emerald-400">
                    <Check className="h-3.5 w-3.5" />
                    {item.state === 'fetch' ? t('keysOf', '%d of %d keys', item.key_count, item.qty) : keyCount}
                  </span>
                </>
              ) : null}
            </p>
          </div>
        </div>

        {/* Fixed width on wide screens so buttons line up from row to row. */}
        <div className={cn('flex shrink-0', wide ? 'w-36 justify-end' : 'pl-[58px]')}>
          {item.state === 'ready' ? (
            <Button size="sm" icon={open ? EyeOff : Eye} loading={working} onClick={view} aria-expanded={open} className={cn(wide && 'w-full')}>
              {open ? t('hideKeys', 'Hide keys') : t('viewKeys', 'View keys')}
            </Button>
          ) : item.state === 'fetch' ? (
            <Button size="sm" variant="primary" icon={KeyRound} loading={working} onClick={() => setConfirming(true)} disabled={confirming} className={cn(wide && 'w-full')}>
              {t('getKeys', 'Get keys')}
            </Button>
          ) : (
            <span className="inline-flex items-center gap-1.5 whitespace-nowrap text-xs font-medium text-muted">
              <span className={cn('h-1.5 w-1.5 rounded-full', status.dot)} />
              {status.label()}
            </span>
          )}
        </div>
      </div>

      {item.note && (item.state === 'pending' || item.state === 'blocked') ? (
        <p className="-mt-2 px-4 pb-4 pl-[74px] text-xs text-muted sm:px-5 sm:pl-[78px]">{item.note}</p>
      ) : null}

      {confirming ? (
        <div className="px-4 pb-4 sm:px-5">
          <div className="flex animate-fade-in flex-col gap-3 rounded-card border border-brand/25 bg-brand/[0.06] p-4 sm:flex-row sm:items-center">
            <div className="min-w-0 flex-1">
              <p className="text-sm font-semibold text-fg">{t('confirmTitle', 'Get license keys?')}</p>
              <p className="mt-0.5 text-[13px] text-muted">{confirmText}</p>
            </div>
            <div className="flex shrink-0 gap-2">
              <Button size="sm" variant="ghost" onClick={() => setConfirming(false)}>
                {t('cancelBtn', 'Cancel')}
              </Button>
              <Button size="sm" variant="primary" onClick={fetchKeys}>
                {t('confirmBtn', 'Yes, get keys')}
              </Button>
            </div>
          </div>
        </div>
      ) : null}

      {error ? (
        <div className="px-4 pb-4 sm:px-5">
          <Alert tone="error" onClose={() => setError('')}>
            {error}
          </Alert>
        </div>
      ) : null}

      {notice ? (
        <div className="px-4 pb-4 sm:px-5">
          <Alert tone="warning" onClose={() => setNotice('')}>
            {notice}
          </Alert>
        </div>
      ) : null}

      {open && payload ? <KeysPanel payload={payload} item={item} /> : null}
    </li>
  );
}

export function ListSkeleton() {
  return (
    <ul className="divide-y divide-line">
      {Array.from({ length: 4 }).map((_, i) => (
        <li key={i} className="flex items-center gap-3.5 px-5 py-4">
          <Skeleton className="h-11 w-11 rounded-xl" />
          <div className="flex-1 space-y-2">
            <Skeleton className="h-4 w-1/2 max-w-[260px]" />
            <Skeleton className="h-3 w-1/3 max-w-[220px]" />
          </div>
          <Skeleton className="h-8 w-28 rounded-ctl" />
        </li>
      ))}
    </ul>
  );
}
