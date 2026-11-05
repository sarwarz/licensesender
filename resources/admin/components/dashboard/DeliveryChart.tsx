import { useMemo, useState } from 'react';
import { BarChart3 } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import { numberFormat, type TrendPoint } from './types';

const RANGES = [7, 14, 30] as const;
type Range = (typeof RANGES)[number];

function label(date: string, long = false) {
  const d = new Date(`${date}T00:00:00`);
  return d.toLocaleDateString(undefined, long ? { weekday: 'short', month: 'short', day: 'numeric' } : { month: 'short', day: 'numeric' });
}

export function DeliveryChart({ trend, i18n }: { trend: TrendPoint[]; i18n: Record<string, string> }) {
  const [range, setRange] = useState<Range>(14);
  const points = useMemo(() => trend.slice(-range), [trend, range]);
  const total = points.reduce((sum, p) => sum + p.count, 0);
  const max = Math.max(1, ...points.map((p) => p.count));
  const best = points.reduce<TrendPoint | null>((top, p) => (p.count > (top?.count ?? 0) ? p : top), null);
  const average = total / Math.max(1, points.length);
  // Show roughly 6 axis labels whatever the range.
  const every = Math.ceil(points.length / 6);

  return (
    <Card className="border-slate-200 shadow-sm">
      <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 space-y-0 pb-2">
        <div className="space-y-1">
          <CardTitle className="flex items-center gap-2 text-base font-semibold">
            <BarChart3 className="h-4 w-4 text-indigo-500" />
            {i18n.deliveries || 'Key deliveries'}
          </CardTitle>
          <CardDescription>{i18n.deliveriesDesc || 'Keys delivered to customers per day.'}</CardDescription>
        </div>
        <Tabs value={String(range)} onValueChange={(v) => setRange(Number(v) as Range)}>
          <TabsList className="h-8">
            {RANGES.map((r) => (
              <TabsTrigger key={r} value={String(r)} className="h-6 px-2.5 text-xs">
                {r}d
              </TabsTrigger>
            ))}
          </TabsList>
        </Tabs>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid grid-cols-3 gap-3 text-sm">
          <div>
            <p className="text-xs text-slate-500">{i18n.total || 'Total'}</p>
            <p className="text-xl font-semibold text-slate-900">{numberFormat.format(total)}</p>
          </div>
          <div>
            <p className="text-xs text-slate-500">{i18n.dailyAverage || 'Daily average'}</p>
            <p className="text-xl font-semibold text-slate-900">{average.toFixed(average < 10 && average % 1 ? 1 : 0)}</p>
          </div>
          <div>
            <p className="text-xs text-slate-500">{i18n.bestDay || 'Best day'}</p>
            <p className="truncate text-xl font-semibold text-slate-900">{best ? label(best.date) : '—'}</p>
          </div>
        </div>

        <div className="relative">
          {total === 0 ? (
            <div className="absolute inset-0 z-10 flex items-center justify-center">
              <p className="rounded-md bg-white/90 px-3 py-1.5 text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
                {i18n.noDeliveries || 'No keys delivered in this period.'}
              </p>
            </div>
          ) : null}
          <div className="flex h-48 items-end gap-[3px] border-b border-slate-200 sm:gap-1" role="img" aria-label={`${total} keys delivered in the last ${range} days`}>
            {points.map((p, i) => (
              <div key={p.date} className="group relative flex h-full flex-1 items-end">
                <div
                  className={cn(
                    'w-full rounded-t-[4px] transition-colors',
                    p.count > 0 ? 'bg-indigo-500/85 group-hover:bg-indigo-600' : 'bg-slate-100 group-hover:bg-slate-200'
                  )}
                  style={{ height: p.count > 0 ? `${Math.max(4, (p.count / max) * 100)}%` : '4px' }}
                />
                <div
                  className={cn(
                    'pointer-events-none invisible absolute bottom-full z-20 mb-1 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs text-white shadow group-hover:visible',
                    // Keep the tooltip inside the card at both edges.
                    i < points.length * 0.2 ? 'left-0' : i > points.length * 0.8 ? 'right-0' : 'left-1/2 -translate-x-1/2'
                  )}
                >
                  <span className="font-semibold">{numberFormat.format(p.count)}</span> · {label(p.date, true)}
                </div>
              </div>
            ))}
          </div>
          <div className="mt-1.5 flex gap-[3px] sm:gap-1">
            {points.map((p, i) => (
              <div key={p.date} className="flex-1 text-center text-[10px] text-slate-400">
                {i % every === 0 ? label(p.date) : ''}
              </div>
            ))}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
