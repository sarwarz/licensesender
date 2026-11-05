import { useMemo, useRef, useState } from 'react';
import { AlertTriangle, CheckCircle2, FileUp, ListPlus } from 'lucide-react';
import { cn, t } from '../support/lib';
import { Button, Card, Textarea } from '../support/ui';
import { addable, money, parseOrderText, unitPrice } from './lib';
import type { WsProduct } from './types';

interface Row {
  sku: string;
  qty: number;
  product?: WsProduct;
  problem: string;
}

/**
 * Paste "SKU, quantity" lines (or load a CSV exported from another system), check them
 * against the catalog, and add everything that's fine to the order in one go.
 */
export function QuickOrder({
  products,
  order,
  onAdd,
}: {
  products: WsProduct[];
  /** Keys already in the order being built. */
  order: Record<string, number>;
  onAdd: (items: Array<{ sku: string; qty: number }>) => void;
}) {
  const [text, setText] = useState('');
  const fileRef = useRef<HTMLInputElement>(null);
  const bySku = useMemo(() => new Map(products.map((p) => [p.sku.toLowerCase(), p])), [products]);

  const { rows, invalid } = useMemo(() => {
    const parsed = parseOrderText(text);
    const checked: Row[] = parsed.lines.map(({ sku, qty }) => {
      const product = bySku.get(sku.toLowerCase());
      const inCart = product ? order[product.sku] ?? 0 : 0;
      let problem = '';
      if (!product) problem = t('qoUnknown', 'Not in the wholesale catalog');
      else if (!product.buyable) problem = t('notOrderable', 'Not orderable yet');
      else if (qty + inCart < product.minQty) problem = t('qoBelowMin', 'Minimum is %d keys', product.minQty);
      else if (addable(product, inCart) === 0) problem = inCart > 0 ? t('qoAllInCart', 'No more available: %d already in your order', inCart) : t('outOfStock', 'Out of stock');
      else if (qty > addable(product, inCart)) problem = t('qoTooMany', 'Only %d can be added', addable(product, inCart));
      return { sku: product?.sku ?? sku, qty, product, problem };
    });
    return { rows: checked, invalid: parsed.invalid };
  }, [text, bySku, order]);

  const good = rows.filter((r) => !r.problem && r.product);
  const total = good.reduce((sum, r) => sum + r.qty * unitPrice(r.product!, r.qty + (order[r.sku] ?? 0)), 0);

  const loadFile = async (file: File | undefined) => {
    if (!file) return;
    setText((await file.text()).trim());
    if (fileRef.current) fileRef.current.value = '';
  };

  return (
    <div className="space-y-4">
      <Card className="p-5 sm:p-6">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 className="text-base font-semibold text-fg">{t('qoTitle', 'Quick order')}</h3>
            <p className="mt-0.5 text-sm text-muted">{t('qoLead', 'One product per line: SKU and quantity, e.g. "WIN11PRO, 50". You can also load a CSV file.')}</p>
          </div>
          <div>
            <input ref={fileRef} type="file" accept=".csv,.txt,text/csv,text/plain" className="hidden" onChange={(e) => loadFile(e.target.files?.[0])} />
            <Button size="sm" icon={FileUp} onClick={() => fileRef.current?.click()}>
              {t('qoLoadCsv', 'Load CSV')}
            </Button>
          </div>
        </div>
        <Textarea
          value={text}
          onChange={(e) => setText(e.target.value)}
          rows={7}
          spellCheck={false}
          placeholder={'WIN11PRO, 50\nOFFICE2024, 20'}
          className="mt-4 font-mono text-[13px]"
          aria-label={t('qoTitle', 'Quick order')}
        />
        {invalid.length ? (
          <p className="mt-2 flex items-start gap-1.5 text-[13px] text-amber-700 dark:text-amber-300">
            <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
            {t('qoInvalid', 'Skipped lines without a SKU and quantity: %s', invalid.slice(0, 3).join(' · ') + (invalid.length > 3 ? ' …' : ''))}
          </p>
        ) : null}
      </Card>

      {rows.length ? (
        <Card className="overflow-hidden">
          <ul className="divide-y divide-line">
            {rows.map((row) => (
              <li key={row.sku} className="flex items-center gap-3 px-5 py-3">
                {row.problem ? <AlertTriangle className="h-4 w-4 shrink-0 text-amber-500" /> : <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-500" />}
                <div className="min-w-0 flex-1">
                  <p className={cn('truncate text-sm font-medium', row.product ? 'text-fg' : 'text-muted')}>{row.product?.name ?? row.sku}</p>
                  <p className="text-[12px] text-muted">
                    <span className="font-mono">{row.sku}</span>
                    {row.problem ? <span className="text-amber-700 dark:text-amber-300"> · {row.problem}</span> : null}
                  </p>
                </div>
                <span className="text-sm tabular-nums text-muted">×{row.qty}</span>
                <span className="w-24 text-right text-sm font-semibold tabular-nums text-fg">
                  {row.product && !row.problem ? money(row.qty * unitPrice(row.product, row.qty + (order[row.sku] ?? 0))) : '—'}
                </span>
              </li>
            ))}
          </ul>
          <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line bg-subtle px-5 py-3">
            <p className="text-sm text-muted">
              {t('qoReady', '%d of %d lines ready', good.length, rows.length)}
              {good.length ? (
                <>
                  {' · '}
                  <span className="font-semibold tabular-nums text-fg">{money(total)}</span>
                </>
              ) : null}
            </p>
            <Button
              variant="primary"
              icon={ListPlus}
              disabled={!good.length}
              onClick={() => {
                onAdd(good.map((r) => ({ sku: r.sku, qty: r.qty })));
                setText('');
              }}
            >
              {good.length === 1 ? t('qoAddOne', 'Add 1 product to order') : t('qoAddMany', 'Add %d products to order', good.length)}
            </Button>
          </div>
        </Card>
      ) : null}
    </div>
  );
}
