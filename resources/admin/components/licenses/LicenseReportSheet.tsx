import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { getBootstrap } from '@/lib/bootstrap';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet';

interface LicenseDetail {
  id: number;
  license_key: string;
  email: string;
  sku: string;
  order_id: number;
  product_id: number;
  product_name: string;
}

interface ReportResponse {
  message?: string;
  report?: {
    status?: string;
    mode?: string;
    replacement_key?: string | null;
    reason?: string;
  };
}

interface LicenseReportSheetProps {
  licenseId: number | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSaved: () => void;
}

const REASONS = [
  { value: 'dead_key', label: 'Dead key' },
  { value: 'activation_failed', label: 'Activation failed' },
  { value: 'invalid_key', label: 'Invalid key' },
  { value: 'customer_request', label: 'Customer request' },
  { value: 'other', label: 'Other' },
] as const;

export function LicenseReportSheet({ licenseId, open, onOpenChange, onSaved }: LicenseReportSheetProps) {
  const { i18n } = getBootstrap();
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [license, setLicense] = useState<LicenseDetail | null>(null);
  const [reason, setReason] = useState<string>('dead_key');
  const [mode, setMode] = useState<'auto' | 'manual'>('auto');
  const [notes, setNotes] = useState('');

  useEffect(() => {
    if (!open || !licenseId) return;
    setLoading(true);
    setReason('dead_key');
    setMode('auto');
    setNotes('');
    apiRequest<LicenseDetail>(`licenses/${licenseId}`)
      .then((data) => setLicense(data))
      .catch((err) => toast.error(err instanceof Error ? err.message : i18n.error))
      .finally(() => setLoading(false));
  }, [open, licenseId, i18n.error]);

  const submit = async () => {
    if (!licenseId) return;
    setSubmitting(true);
    try {
      const data = await apiRequest<ReportResponse>(`licenses/${licenseId}/report`, {
        method: 'POST',
        body: JSON.stringify({ reason, mode, notes }),
      });

      const replacement = data.report?.replacement_key;
      if (replacement) {
        toast.success(data.message || `Key replaced: ${replacement}`);
      } else if (data.report?.status === 'pending') {
        toast.success(data.message || 'Issue logged. Complete replacement from Report Keys.');
      } else {
        toast.success(data.message || 'License reported successfully.');
      }
      onSaved();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="flex w-full flex-col overflow-y-auto sm:max-w-xl">
        <SheetHeader className="w-full shrink-0">
          <SheetTitle>{i18n.report || 'Report Key'}</SheetTitle>
          <SheetDescription>
            Report a dead or issue key to LicenseSender. Auto mode replaces immediately when stock allows.
          </SheetDescription>
        </SheetHeader>

        {loading || !license ? (
          <p className="py-8 text-muted-foreground">{i18n.loading}</p>
        ) : (
          <div className="mt-6 grid w-full grid-cols-1 gap-4">
            <div className="grid w-full gap-2">
              <Label>Current License</Label>
              <Input className="w-full max-w-none" value={license.license_key} readOnly />
            </div>
            <div className="grid w-full gap-2">
              <Label>Order</Label>
              <Input className="w-full max-w-none" value={`#${license.order_id}`} readOnly />
            </div>
            <div className="grid w-full gap-2">
              <Label>Product</Label>
              <Input className="w-full max-w-none" value={license.product_name || license.sku || '—'} readOnly />
            </div>
            <div className="grid w-full gap-2">
              <Label htmlFor="ls-report-reason">Reason</Label>
              <select
                id="ls-report-reason"
                className="box-border flex h-9 w-full max-w-none rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                value={reason}
                onChange={(e) => setReason(e.target.value)}
              >
                {REASONS.map((item) => (
                  <option key={item.value} value={item.value}>{item.label}</option>
                ))}
              </select>
            </div>
            <div className="grid w-full gap-2">
              <Label htmlFor="ls-report-mode">Mode</Label>
              <select
                id="ls-report-mode"
                className="box-border flex h-9 w-full max-w-none rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                value={mode}
                onChange={(e) => setMode(e.target.value as 'auto' | 'manual')}
              >
                <option value="auto">Auto (replace now if available)</option>
                <option value="manual">Manual (log issue for later)</option>
              </select>
            </div>
            <div className="grid w-full gap-2">
              <Label htmlFor="ls-report-notes">Notes (optional)</Label>
              <textarea
                id="ls-report-notes"
                className="box-border flex min-h-[120px] w-full max-w-none rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder="Customer reported activation failed"
              />
            </div>
          </div>
        )}

        <SheetFooter className="mt-auto w-full shrink-0 pt-6">
          <Button variant="outline" onClick={() => onOpenChange(false)}>{i18n.cancel}</Button>
          <Button onClick={submit} disabled={loading || submitting || !license}>
            {submitting ? 'Reporting…' : (i18n.report || 'Report Key')}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
