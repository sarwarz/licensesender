import { useState } from 'react';
import { CircleCheck, Plus, TriangleAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export interface Coverage {
  enabled: number;
  covered: number;
  missing: { id: number; name: string; edit_url: string }[];
}

/** License products without a download link / activation guide, each with a quick "Add" button. */
export function CoverageCard({
  coverage,
  noun,
  onAdd,
}: {
  coverage: Coverage | null;
  /** e.g. "download link" */
  noun: string;
  onAdd: (product: { id: number; name: string }) => void;
}) {
  const [expanded, setExpanded] = useState(false);
  if (!coverage || coverage.enabled === 0) return null;

  const article = /^[aeiou]/i.test(noun) ? 'an' : 'a';
  const pct = Math.round((coverage.covered / coverage.enabled) * 100);
  const complete = coverage.missing.length === 0;
  const visible = expanded ? coverage.missing : coverage.missing.slice(0, 6);

  return (
    <Card className={cn('border shadow-sm', complete ? 'border-emerald-200' : 'border-amber-200')}>
      <CardContent className="space-y-4 p-5">
        <div className="flex flex-wrap items-start gap-4">
          <span className={cn('flex h-10 w-10 shrink-0 items-center justify-center rounded-xl', complete ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600')}>
            {complete ? <CircleCheck className="h-5 w-5" /> : <TriangleAlert className="h-5 w-5" />}
          </span>
          <div className="min-w-0 flex-1">
            <p className="text-sm font-semibold text-slate-900">
              {complete
                ? `Every license product has ${article} ${noun}`
                : `${coverage.missing.length} license product${coverage.missing.length === 1 ? '' : 's'} without ${article} ${noun}`}
            </p>
            <p className="mt-0.5 text-xs text-slate-500">
              {coverage.covered} of {coverage.enabled} products covered. Customers of products without one won't see it after buying.
            </p>
            <div className="mt-2 h-1.5 max-w-sm overflow-hidden rounded-full bg-slate-100">
              <div className={cn('h-full rounded-full', complete ? 'bg-emerald-500' : 'bg-amber-500')} style={{ width: `${pct}%` }} />
            </div>
          </div>
          <span className={cn('text-2xl font-semibold', complete ? 'text-emerald-600' : 'text-amber-600')}>{pct}%</span>
        </div>

        {!complete ? (
          <div className="flex flex-wrap gap-2">
            {visible.map((p) => (
              <span key={p.id} className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white py-1 pl-3 pr-1 text-sm text-slate-700">
                <span className="max-w-[220px] truncate" title={p.name}>{p.name}</span>
                <Button size="sm" variant="ghost" className="h-7 gap-1 px-2 text-indigo-600 hover:text-indigo-700" onClick={() => onAdd(p)}>
                  <Plus className="h-3.5 w-3.5" /> Add
                </Button>
              </span>
            ))}
            {coverage.missing.length > 6 ? (
              <Button size="sm" variant="ghost" className="text-slate-500" onClick={() => setExpanded((v) => !v)}>
                {expanded ? 'Show less' : `Show all ${coverage.missing.length}`}
              </Button>
            ) : null}
          </div>
        ) : null}
      </CardContent>
    </Card>
  );
}
