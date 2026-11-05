import { useState } from 'react';
import { CircleCheck, PackageSearch, TriangleAlert } from 'lucide-react';
import { apiRequest } from '@/api/client';
import { getBootstrap } from '@/lib/bootstrap';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface StockCounts {
  available?: number;
  assigned?: number;
  redeemed?: number;
  total?: number;
}

interface StockResult {
  key_value: string;
  meta?: { total?: number; total_items?: number; count?: number; counts?: StockCounts };
}

/** Looks up the next available key for a SKU in the LicenseSender inventory. Read-only: nothing is reserved. */
export function StockCheckDialog() {
  const { i18n } = getBootstrap();
  const [open, setOpen] = useState(false);
  const [sku, setSku] = useState('');
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<{ ok: boolean; available?: number; counts?: StockCounts; message?: string } | null>(null);

  const check = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!sku.trim()) return;
    setLoading(true);
    setResult(null);
    try {
      const data = await apiRequest<StockResult>('licenses/fetch-by-sku', { method: 'POST', body: JSON.stringify({ sku: sku.trim() }) });
      const available = data.meta?.counts?.available ?? data.meta?.total ?? data.meta?.total_items ?? data.meta?.count;
      setResult({ ok: true, available: typeof available === 'number' ? available : undefined, counts: data.meta?.counts });
    } catch (err) {
      setResult({ ok: false, message: err instanceof Error ? err.message : i18n.error });
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog
      open={open}
      onOpenChange={(v) => {
        setOpen(v);
        if (!v) setResult(null);
      }}
    >
      <DialogTrigger asChild>
        <Button variant="outline" size="sm" className="bg-white">
          <PackageSearch className="h-4 w-4" />
          {i18n.checkStock || 'Check stock'}
        </Button>
      </DialogTrigger>
      <DialogContent className="sm:max-w-md">
        <form onSubmit={check} className="space-y-4">
          <DialogHeader>
            <DialogTitle>{i18n.checkStockTitle || 'Check stock by SKU'}</DialogTitle>
            <DialogDescription>
              {i18n.checkStockDesc || 'See whether your LicenseSender inventory has an unsold key for a SKU. Nothing is reserved or sold.'}
            </DialogDescription>
          </DialogHeader>
          <div className="flex items-end gap-2">
            <div className="flex-1 space-y-1.5">
              <Label htmlFor="ls-stock-sku">SKU</Label>
              <Input id="ls-stock-sku" value={sku} onChange={(e) => setSku(e.target.value)} placeholder="LSP-XXXXXX" autoFocus />
            </div>
            <Button type="submit" disabled={loading || !sku.trim()}>
              {loading ? i18n.checking || 'Checking…' : i18n.check || 'Check'}
            </Button>
          </div>
          {result ? (
            result.ok ? (
              <div className="space-y-3">
                <div className="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                  <CircleCheck className="mt-0.5 h-4 w-4 shrink-0" />
                  <span>
                    {result.available !== undefined
                      ? (i18n.inStockCount || '%d keys available for this SKU.').replace('%d', String(result.available))
                      : i18n.inStock || 'Keys are available for this SKU.'}
                  </span>
                </div>
                {result.counts ? (
                  <div className="grid grid-cols-4 gap-2 text-center">
                    {(
                      [
                        ['available', i18n.stockAvailable || 'Available', 'text-emerald-700'],
                        ['assigned', i18n.stockAssigned || 'Assigned', 'text-slate-900'],
                        ['redeemed', i18n.stockRedeemed || 'Redeemed', 'text-slate-900'],
                        ['total', i18n.stockTotal || 'Total', 'text-slate-900'],
                      ] as const
                    ).map(([key, label, tone]) => (
                      <div key={key} className="rounded-lg border border-slate-200 p-2">
                        <p className={`text-lg font-semibold ${tone}`}>{(result.counts?.[key] ?? 0).toLocaleString()}</p>
                        <p className="text-[11px] text-slate-500">{label}</p>
                      </div>
                    ))}
                  </div>
                ) : null}
              </div>
            ) : (
              <div className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" />
                <span>{result.message}</span>
              </div>
            )
          ) : null}
        </form>
      </DialogContent>
    </Dialog>
  );
}
