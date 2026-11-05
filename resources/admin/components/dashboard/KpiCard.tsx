import type { LucideIcon } from 'lucide-react';
import { Minus, TrendingDown, TrendingUp } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { numberFormat } from './types';

const tones = {
  indigo: 'bg-indigo-50 text-indigo-600 ring-indigo-100',
  emerald: 'bg-emerald-50 text-emerald-600 ring-emerald-100',
  amber: 'bg-amber-50 text-amber-600 ring-amber-100',
  sky: 'bg-sky-50 text-sky-600 ring-sky-100',
};

interface KpiCardProps {
  label: string;
  value: number;
  icon: LucideIcon;
  tone?: keyof typeof tones;
  hint?: React.ReactNode;
  /** Value for the previous period; shows a % change badge when given. */
  previous?: number;
  /** 0–1, draws a thin progress bar under the value. */
  progress?: number;
  href?: string;
}

function Change({ value, previous }: { value: number; previous: number }) {
  if (previous === 0 && value === 0) {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">
        <Minus className="h-3 w-3" /> 0%
      </span>
    );
  }
  const pct = previous === 0 ? 100 : Math.round(((value - previous) / previous) * 100);
  const up = pct >= 0;
  const Icon = up ? TrendingUp : TrendingDown;
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
        up ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
      )}
      title={`${numberFormat.format(previous)} in the previous period`}
    >
      <Icon className="h-3 w-3" />
      {up ? '+' : ''}
      {pct}%
    </span>
  );
}

export function KpiCard({ label, value, icon: Icon, tone = 'indigo', hint, previous, progress, href }: KpiCardProps) {
  const body = (
    <CardContent className="space-y-3 p-5">
      <div className="flex items-start justify-between gap-3">
        <p className="text-sm font-medium text-slate-500">{label}</p>
        <span className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1', tones[tone])}>
          <Icon className="h-[18px] w-[18px]" />
        </span>
      </div>
      <div className="flex items-end gap-2">
        <p className="text-3xl font-semibold leading-none tracking-tight text-slate-900">{numberFormat.format(value)}</p>
        {previous !== undefined ? <Change value={value} previous={previous} /> : null}
      </div>
      {progress !== undefined ? (
        <div className="h-1.5 overflow-hidden rounded-full bg-slate-100">
          <div className={cn('h-full rounded-full', tone === 'amber' ? 'bg-amber-500' : 'bg-indigo-500')} style={{ width: `${Math.min(100, Math.max(0, progress * 100))}%` }} />
        </div>
      ) : null}
      {hint ? <p className="text-xs text-slate-500">{hint}</p> : null}
    </CardContent>
  );

  return (
    <Card className={cn('border-slate-200 shadow-sm transition-shadow', href && 'hover:border-slate-300 hover:shadow-md')}>
      {href ? (
        <a href={href} className="block rounded-[inherit] text-inherit no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
          {body}
        </a>
      ) : (
        body
      )}
    </Card>
  );
}
