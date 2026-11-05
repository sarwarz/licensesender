import { CreditCard, ExternalLink, History, RotateCcw } from 'lucide-react';
import { fullDate, relativeTime, t } from '../support/lib';
import { Button, Card, EmptyState } from '../support/ui';
import type { WsOrder } from './types';

/** Recent wholesale orders: pay the unpaid ones, or start a new order from any of them. */
export function PastOrders({ orders, onReorder }: { orders: WsOrder[]; onReorder: (lines: Array<{ sku: string; qty: number }>) => void }) {
  if (!orders.length) {
    return (
      <Card>
        <EmptyState icon={History} title={t('noOrders', 'No wholesale orders yet')}>
          {t('noOrdersHint', 'Your wholesale orders will appear here, ready to order again.')}
        </EmptyState>
      </Card>
    );
  }

  return (
    <Card className="overflow-hidden">
      <ul className="divide-y divide-line">
        {orders.map((order) => {
          const keys = order.lines.reduce((sum, l) => sum + l.qty, 0);
          return (
            <li key={order.id} className="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4">
              <div className="min-w-0 flex-1">
                <p className="flex flex-wrap items-center gap-x-2 text-sm">
                  <span className="font-semibold text-fg">{t('orderNumber', 'Order #%s', order.number)}</span>
                  <span className={order.payUrl ? 'rounded-full bg-amber-500/15 px-2 py-0.5 text-[11px] font-medium text-amber-800 ring-1 ring-inset ring-amber-500/30 dark:text-amber-300' : 'rounded-full bg-fg/5 px-2 py-0.5 text-[11px] font-medium text-muted ring-1 ring-inset ring-line'}>
                    {order.status}
                  </span>
                  <span className="text-[12px] text-faint" title={fullDate(order.date)}>
                    {relativeTime(order.date)}
                  </span>
                </p>
                <p className="mt-1 truncate text-[13px] text-muted" title={order.lines.map((l) => `${l.name} ×${l.qty}`).join(', ')}>
                  {order.lines.map((l) => `${l.name} ×${l.qty}`).join(' · ')}
                </p>
              </div>
              <div className="text-right">
                <p className="text-sm font-semibold tabular-nums text-fg">{order.total}</p>
                <p className="text-[12px] text-muted">{keys === 1 ? t('oneKey', '1 key') : t('nKeys', '%d keys', keys)}</p>
              </div>
              <div className="flex gap-2">
                <a href={order.url} className="inline-flex h-8 items-center gap-1.5 rounded-ctl px-3 text-[13px] font-medium text-muted no-underline hover:bg-fg/5 hover:text-fg">
                  <ExternalLink className="h-3.5 w-3.5" /> {t('viewOrder', 'View')}
                </a>
                {order.payUrl ? (
                  <a href={order.payUrl} className="inline-flex h-8 items-center gap-1.5 rounded-ctl bg-brand px-3 text-[13px] font-medium text-brand-fg no-underline shadow-sm hover:bg-brand/90">
                    <CreditCard className="h-3.5 w-3.5" /> {t('payNow', 'Pay now')}
                  </a>
                ) : (
                  <Button size="sm" icon={RotateCcw} disabled={!order.lines.length} onClick={() => onReorder(order.lines.map((l) => ({ sku: l.sku, qty: l.qty })))}>
                    {t('orderAgain', 'Order again')}
                  </Button>
                )}
              </div>
            </li>
          );
        })}
      </ul>
    </Card>
  );
}
