import { Package } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { numberFormat, type TopProduct } from './types';

export function TopProducts({ items, i18n }: { items: TopProduct[]; i18n: Record<string, string> }) {
  const max = Math.max(1, ...items.map((i) => i.count));

  return (
    <Card className="border-slate-200 shadow-sm">
      <CardHeader className="pb-3">
        <CardTitle className="text-base font-semibold">{i18n.topProducts || 'Top products'}</CardTitle>
        <CardDescription>{i18n.topProductsDesc || 'Most keys delivered, last 30 days.'}</CardDescription>
      </CardHeader>
      <CardContent>
        {items.length === 0 ? (
          <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed border-slate-200 py-6 text-center">
            <Package className="h-5 w-5 text-slate-300" />
            <p className="text-xs text-slate-500">{i18n.noTopProducts || 'No deliveries in the last 30 days.'}</p>
          </div>
        ) : (
          <ol className="m-0 list-none space-y-3 p-0">
            {items.map((item, index) => (
              <li key={item.product_id} className="m-0 space-y-1.5">
                <div className="flex items-center justify-between gap-3 text-sm">
                  <span className="flex min-w-0 items-center gap-2">
                    <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-slate-100 text-[11px] font-semibold text-slate-600">{index + 1}</span>
                    {item.edit_url ? (
                      <a href={item.edit_url} className="truncate text-slate-800 no-underline hover:text-indigo-600">
                        {item.name}
                      </a>
                    ) : (
                      <span className="truncate text-slate-800">{item.name}</span>
                    )}
                  </span>
                  <span className="shrink-0 font-semibold text-slate-900">{numberFormat.format(item.count)}</span>
                </div>
                <div className="h-1.5 overflow-hidden rounded-full bg-slate-100">
                  <div className="h-full rounded-full bg-indigo-500" style={{ width: `${(item.count / max) * 100}%` }} />
                </div>
              </li>
            ))}
          </ol>
        )}
      </CardContent>
    </Card>
  );
}
