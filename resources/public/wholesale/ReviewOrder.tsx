import { useState } from 'react';
import { ArrowLeft, CreditCard, Lock, Pencil, Trash2, Wallet } from 'lucide-react';
import { ajax, cn, t } from '../support/lib';
import { Alert, Button, Card, Field, Input } from '../support/ui';
import { Stepper, Thumb } from './CatalogRow';
import { config, money, unitPrice } from './lib';
import { errorDetails } from './PortalView';
import type { PlaceOrderResponse, WsProduct } from './types';

type Pay = 'wallet' | 'gateway';

/**
 * Last step before paying: the lines at their price breaks, who it's billed to, and how
 * to pay. "Place order" creates the WooCommerce order and holds the keys; the wallet pays
 * on the spot, anything else continues on the store's payment page.
 */
export function ReviewOrder({
  products,
  qty,
  shortBy,
  minKeys,
  onChange,
  onBack,
  onPlaced,
}: {
  products: WsProduct[];
  qty: Record<string, number>;
  shortBy: number;
  minKeys: number;
  onChange: (sku: string, value: number) => void;
  onBack: () => void;
  onPlaced: () => void;
}) {
  const cfg = config();
  const lines = products.filter((p) => (qty[p.sku] ?? 0) > 0);
  const keys = lines.reduce((sum, p) => sum + qty[p.sku], 0);
  const subtotal = lines.reduce((sum, p) => sum + qty[p.sku] * unitPrice(p, qty[p.sku]), 0);
  const wallet = cfg.wallet;
  const walletCovers = !!wallet?.available && wallet.balance + 0.00001 >= subtotal;
  const [pay, setPay] = useState<Pay>(walletCovers ? 'wallet' : 'gateway');
  const [note, setNote] = useState('');
  const [placing, setPlacing] = useState(false);
  const [error, setError] = useState<{ text: string; details: string[] } | null>(null);
  const billing = cfg.billing;

  const place = async () => {
    setPlacing(true);
    setError(null);
    try {
      const res = await ajax<PlaceOrderResponse>('ls_wholesale_place_order', {
        nonce: cfg.nonce,
        items: JSON.stringify(lines.map((p) => ({ sku: p.sku, qty: qty[p.sku] }))),
        note,
        pay: walletCovers && pay === 'wallet' ? 'wallet' : 'gateway',
      });
      onPlaced();
      window.location.href = res.redirect;
    } catch (err) {
      const data = (err as { data?: { errors?: Record<string, string> } }).data;
      setError({ text: (err as Error).message, details: errorDetails(data?.errors, products) });
      setPlacing(false);
    }
  };

  const payLabel = walletCovers && pay === 'wallet' ? t('payWallet', 'Pay %s from wallet', money(subtotal)) : t('placeOrder', 'Place order');

  return (
    <div className="space-y-4">
      <button type="button" onClick={onBack} className="inline-flex items-center gap-1.5 text-[13px] font-medium text-muted hover:text-fg">
        <ArrowLeft className="h-4 w-4" /> {t('backToCatalog', 'Back to catalog')}
      </button>

      {error ? (
        <Alert tone="error" onClose={() => setError(null)}>
          {error.text}
          {error.details.length ? (
            <ul className="mt-1.5 list-disc space-y-0.5 pl-4">
              {error.details.map((line) => (
                <li key={line}>{line}</li>
              ))}
            </ul>
          ) : null}
        </Alert>
      ) : null}

      <Card className="overflow-hidden">
        <div className="flex items-center justify-between border-b border-line px-5 py-3.5">
          <h3 className="text-base font-semibold text-fg">{t('reviewTitle', 'Review your order')}</h3>
          <span className="text-[13px] text-muted">{keys === 1 ? t('oneKey', '1 key') : t('nKeys', '%d keys', keys)}</span>
        </div>
        <ul className="divide-y divide-line">
          {lines.map((p) => (
            <li key={p.sku} className="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-3.5">
              <div className="flex min-w-0 flex-1 items-center gap-3">
                <Thumb product={p} />
                <div className="min-w-0">
                  <p className="truncate text-sm font-semibold text-fg">{p.name}</p>
                  <p className="text-[12px] tabular-nums text-muted">{t('perKey', '%s / key', money(unitPrice(p, qty[p.sku])))}</p>
                </div>
              </div>
              <Stepper product={p} value={qty[p.sku]} inCart={0} onChange={(v) => onChange(p.sku, v)} />
              <span className="w-24 text-right text-sm font-semibold tabular-nums text-fg">{money(qty[p.sku] * unitPrice(p, qty[p.sku]))}</span>
              <button type="button" onClick={() => onChange(p.sku, 0)} className="rounded-ctl p-2 text-faint hover:bg-red-500/10 hover:text-red-600" aria-label={t('removeLine', 'Remove %s', p.name)}>
                <Trash2 className="h-4 w-4" />
              </button>
            </li>
          ))}
        </ul>
        <div className="space-y-1 border-t border-line bg-subtle px-5 py-3.5">
          <div className="flex items-center justify-between text-sm">
            <span className="text-muted">{t('subtotal', 'Subtotal')}</span>
            <span className="text-base font-semibold tabular-nums text-fg">{money(subtotal)}</span>
          </div>
          <p className="text-[12px] text-faint">{t('taxNote', 'Taxes, if any, are added when the order is created.')}</p>
        </div>
      </Card>

      {shortBy > 0 ? (
        <Alert tone="warning">{t('minShort', 'Add %d more keys to reach the %d-key minimum', shortBy, minKeys)}</Alert>
      ) : null}

      <div className="grid gap-4 md:grid-cols-2">
        <Card className="p-5">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-semibold text-fg">{t('billedTo', 'Billed to')}</h3>
            {billing?.editUrl ? (
              <a href={billing.editUrl} className="inline-flex items-center gap-1 text-[13px] font-medium text-brand no-underline hover:underline">
                <Pencil className="h-3.5 w-3.5" /> {t('edit', 'Edit')}
              </a>
            ) : null}
          </div>
          <div className="mt-2 space-y-0.5 text-sm">
            <p className="font-medium text-fg">{billing?.company || billing?.name || cfg.user.name}</p>
            {billing?.company ? <p className="text-muted">{billing.name}</p> : null}
            <p className="text-muted">{billing?.email || cfg.user.email}</p>
            {billing?.country ? <p className="text-muted">{billing.country}</p> : null}
          </div>
          <div className="mt-4">
            <Field label={t('orderNote', 'Order note or PO number')} htmlFor="ls-ws-note" optional>
              <Input id="ls-ws-note" value={note} onChange={(e) => setNote(e.target.value)} maxLength={500} className="h-10 px-3" />
            </Field>
          </div>
        </Card>

        <Card className="p-5">
          <h3 className="text-sm font-semibold text-fg">{t('payment', 'Payment')}</h3>
          {wallet?.available ? (
            <div className="mt-3 space-y-2" role="radiogroup" aria-label={t('payment', 'Payment')}>
              {(
                [
                  {
                    id: 'wallet' as Pay,
                    icon: Wallet,
                    title: t('payWithWallet', 'Wallet balance'),
                    text: walletCovers ? t('walletBalance', 'Balance %s · paid instantly', money(wallet.balance)) : t('walletLow', 'Balance %s is not enough for this order', money(wallet.balance)),
                    disabled: !walletCovers,
                  },
                  { id: 'gateway' as Pay, icon: CreditCard, title: t('payOther', 'Another payment method'), text: t('payOtherText', 'Choose on the next page.'), disabled: false },
                ]
              )
                // A balance that covers the order is the only method the payment page offers.
                .filter((option) => !(walletCovers && option.id === 'gateway'))
                .map((option) => {
                const active = (walletCovers ? pay : 'gateway') === option.id;
                return (
                  <button
                    key={option.id}
                    type="button"
                    role="radio"
                    aria-checked={active}
                    disabled={option.disabled}
                    onClick={() => setPay(option.id)}
                    className={cn(
                      'flex w-full items-center gap-3 rounded-ctl border px-3.5 py-3 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-55',
                      active ? 'border-brand bg-brand/[0.06] ring-4 ring-brand/10' : 'border-line hover:bg-subtle',
                    )}
                  >
                    <option.icon className={cn('h-5 w-5 shrink-0', active ? 'text-brand' : 'text-muted')} />
                    <span className="min-w-0">
                      <span className="block text-sm font-medium text-fg">{option.title}</span>
                      <span className="block text-[12px] text-muted">{option.text}</span>
                    </span>
                  </button>
                );
              })}
            </div>
          ) : (
            <p className="mt-2 text-sm text-muted">{t('payNextPage', 'You choose how to pay on the next page.')}</p>
          )}

          <Button variant="primary" className="mt-4 h-11 w-full text-[15px]" loading={placing} disabled={!lines.length || shortBy > 0} onClick={place}>
            {payLabel}
          </Button>
          <p className="mt-2.5 flex items-center justify-center gap-1.5 text-[12px] text-faint">
            <Lock className="h-3.5 w-3.5" /> {t('holdNote', 'Your keys are reserved while you pay.')}
          </p>
        </Card>
      </div>
    </div>
  );
}
