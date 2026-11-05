import { useEffect, useState } from 'react';
import { Minus, Plus, TrendingDown } from 'lucide-react';
import { cn, initials, t } from '../support/lib';
import { addable, config, money, tierIndex, unitPrice } from './lib';
import type { WsProduct } from './types';

export function Thumb({ product }: { product: WsProduct }) {
  const [broken, setBroken] = useState(false);
  if (product.image && !broken) {
    return <img src={product.image} alt="" loading="lazy" onError={() => setBroken(true)} className="h-12 w-12 shrink-0 rounded-xl border border-line bg-subtle object-cover" />;
  }
  return (
    <span aria-hidden="true" className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-[13px] font-semibold text-brand">
      {initials(product.name)}
    </span>
  );
}

function StockBadge({ product }: { product: WsProduct }) {
  const low = config().settings?.lowStock ?? 10;
  if (!product.buyable) {
    return <span className="text-[12px] text-faint">{t('notOrderable', 'Not orderable yet')}</span>;
  }
  const [tone, label] =
    product.stock <= 0
      ? ['bg-red-500/10 text-red-700 ring-red-500/25 dark:text-red-300', t('outOfStock', 'Out of stock')]
      : product.stock <= low
        ? ['bg-amber-500/15 text-amber-800 ring-amber-500/30 dark:text-amber-300', t('onlyLeft', 'Only %d left', product.stock)]
        : ['bg-emerald-500/10 text-emerald-700 ring-emerald-500/25 dark:text-emerald-300', t('inStock', '%d in stock', product.stock)];
  return <span className={cn('inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset', tone)}>{label}</span>;
}

/** Price breaks; the one the order currently falls in is highlighted. */
function Tiers({ product, total }: { product: WsProduct; total: number }) {
  const active = total > 0 ? tierIndex(product.tiers, total) : -1;
  const next = product.tiers[active + 1];
  return (
    <div className="min-w-0">
      <div className="flex flex-wrap gap-1.5">
        {product.tiers.map((tier, i) => (
          <span
            key={tier.min_qty}
            className={cn(
              'inline-flex items-baseline gap-1 rounded-lg px-2 py-1 text-[12px] tabular-nums ring-1 ring-inset transition-colors',
              i === active ? 'bg-brand/10 text-brand ring-brand/30' : 'bg-subtle text-muted ring-line',
            )}
          >
            <span className={cn(i === active ? 'text-brand/80' : 'text-faint')}>{tier.min_qty}+</span>
            <span className="font-semibold">{money(tier.price)}</span>
          </span>
        ))}
      </div>
      {total > 0 && next ? (
        <p className="mt-1.5 flex items-center gap-1 text-[12px] text-emerald-700 dark:text-emerald-400">
          <TrendingDown className="h-3.5 w-3.5" />
          {t('nextTier', 'Add %d more for %s per key', next.min_qty - total, money(next.price))}
        </p>
      ) : null}
    </div>
  );
}

/**
 * − [qty] + : the first step jumps to the product's minimum (counting what's already in the
 * cart), and the value never goes above what can still be added.
 */
export function Stepper({ product, value, inCart, onChange }: { product: WsProduct; value: number; inCart: number; onChange: (v: number) => void }) {
  const max = product.buyable ? addable(product, inCart) : 0;
  const first = Math.min(max, Math.max(1, product.minQty - inCart));
  const [text, setText] = useState(String(value || ''));
  useEffect(() => setText(value ? String(value) : ''), [value]);

  const commit = (raw: number) => {
    let next = Number.isFinite(raw) ? Math.floor(raw) : 0;
    if (next <= 0) next = 0;
    else next = Math.min(max, Math.max(first, next));
    onChange(next);
    setText(next ? String(next) : '');
  };

  const disabled = max <= 0;
  return (
    <div className={cn('inline-flex h-10 items-stretch overflow-hidden rounded-ctl border bg-surface shadow-sm', value > 0 ? 'border-brand/50 ring-4 ring-brand/10' : 'border-line', disabled && 'opacity-50')}>
      <button type="button" disabled={disabled || value <= 0} onClick={() => commit(value <= first ? 0 : value - 1)} className="flex w-9 items-center justify-center text-muted hover:bg-fg/5 hover:text-fg disabled:pointer-events-none disabled:opacity-40" aria-label={t('decrease', 'Decrease')}>
        <Minus className="h-3.5 w-3.5" />
      </button>
      <input
        type="text"
        inputMode="numeric"
        value={text}
        placeholder="0"
        disabled={disabled}
        onChange={(e) => setText(e.target.value.replace(/[^\d]/g, ''))}
        onBlur={() => commit(Number(text || 0))}
        onKeyDown={(e) => {
          if (e.key === 'Enter') commit(Number(text || 0));
        }}
        className="w-12 border-x border-line bg-transparent text-center text-sm font-semibold tabular-nums text-fg outline-none placeholder:font-normal placeholder:text-faint"
        aria-label={t('quantityFor', 'Quantity for %s', product.name)}
      />
      <button type="button" disabled={disabled || value >= max} onClick={() => commit(value <= 0 ? first : value + 1)} className="flex w-9 items-center justify-center text-muted hover:bg-fg/5 hover:text-fg disabled:pointer-events-none disabled:opacity-40" aria-label={t('increase', 'Increase')}>
        <Plus className="h-3.5 w-3.5" />
      </button>
    </div>
  );
}

export function CatalogRow({ product, wide, qty, inCart, onChange }: { product: WsProduct; wide: boolean; qty: number; inCart: number; onChange: (v: number) => void }) {
  const total = qty + inCart;
  const lineTotal = qty > 0 ? qty * unitPrice(product, total) : 0;
  const notes = [
    product.minQty > 1 ? t('minNote', 'Min. %d keys', product.minQty) : '',
    product.maxQty > 0 ? t('maxNote', 'Max. %d per order', product.maxQty) : '',
    inCart > 0 ? t('inCartNote', '%d in cart', inCart) : '',
  ].filter(Boolean);

  const info = (
    <div className="flex min-w-0 items-center gap-3">
      <Thumb product={product} />
      <div className="min-w-0">
        <p className="truncate text-[15px] font-semibold text-fg" title={product.name}>
          {product.name}
        </p>
        <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
          <span className="font-mono text-[11px] text-faint">{product.sku}</span>
          <StockBadge product={product} />
        </div>
        {notes.length ? <p className="mt-1 text-[12px] text-muted">{notes.join(' · ')}</p> : null}
      </div>
    </div>
  );

  const totalCell = (
    <div className="text-right">
      <p className={cn('text-sm font-semibold tabular-nums', qty > 0 ? 'text-fg' : 'text-faint')}>{qty > 0 ? money(lineTotal) : '—'}</p>
      {qty > 0 ? <p className="text-[11px] tabular-nums text-muted">{t('perKey', '%s / key', money(unitPrice(product, total)))}</p> : null}
    </div>
  );

  if (wide) {
    return (
      <li className={cn('grid grid-cols-[minmax(0,1.6fr)_minmax(0,1.4fr)_132px_96px] items-center gap-4 px-5 py-4 transition-colors', qty > 0 && 'bg-brand/[0.03]')}>
        {info}
        <Tiers product={product} total={total} />
        <Stepper product={product} value={qty} inCart={inCart} onChange={onChange} />
        {totalCell}
      </li>
    );
  }

  return (
    <li className={cn('space-y-3 px-4 py-4 transition-colors', qty > 0 && 'bg-brand/[0.03]')}>
      {info}
      <Tiers product={product} total={total} />
      <div className="flex items-center justify-between gap-3">
        <Stepper product={product} value={qty} inCart={inCart} onChange={onChange} />
        {totalCell}
      </div>
    </li>
  );
}
