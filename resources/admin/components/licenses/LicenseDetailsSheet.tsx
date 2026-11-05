import { useEffect, useState } from 'react';
import type { LucideIcon } from 'lucide-react';
import { Copy, Eye, EyeOff, ExternalLink, FileText, Flag, Mail, Pencil, RefreshCw } from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { getBootstrap } from '@/lib/bootstrap';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { formatDate, maskKey, orderStatusTone, productLabel, type LicenseDetail } from './types';

interface Props {
  licenseId: number | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onEdit: (id: number) => void;
  onReport: (id: number) => void;
  onBulk: (action: 'resend_email' | 'sync', ids: number[]) => Promise<void>;
  /** Bumped by the page after edits so an open sheet reloads. */
  reloadToken: number;
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="grid grid-cols-[120px_1fr] gap-3 py-2.5 text-sm">
      <dt className="text-slate-500">{label}</dt>
      <dd className="m-0 min-w-0 break-words text-slate-900">{children}</dd>
    </div>
  );
}

export function LicenseDetailsSheet({ licenseId, open, onOpenChange, onEdit, onReport, onBulk, reloadToken }: Props) {
  const { i18n } = getBootstrap();
  const [license, setLicense] = useState<LicenseDetail | null>(null);
  const [reveal, setReveal] = useState(false);
  const [busy, setBusy] = useState<string | null>(null);

  useEffect(() => {
    if (!open || !licenseId) return;
    setLicense(null);
    setReveal(false);
    apiRequest<LicenseDetail>(`licenses/${licenseId}`)
      .then(setLicense)
      .catch((err) => toast.error(err instanceof Error ? err.message : i18n.error));
  }, [open, licenseId, reloadToken, i18n.error]);

  const run = async (action: 'resend_email' | 'sync') => {
    if (!license) return;
    setBusy(action);
    try {
      await onBulk(action, [license.id]);
      setLicense(await apiRequest<LicenseDetail>(`licenses/${license.id}`));
    } finally {
      setBusy(null);
    }
  };

  const copy = async () => {
    if (!license) return;
    await navigator.clipboard.writeText(license.license_key);
    toast.success(i18n.copied || 'Copied!');
  };

  const action = (Icon: LucideIcon, label: string, onClick: () => void, key?: string) => (
    <Button variant="outline" size="sm" className="justify-start bg-white" onClick={onClick} disabled={busy !== null}>
      <Icon className={cn('h-4 w-4', busy === key && 'animate-spin')} /> {label}
    </Button>
  );

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="overflow-y-auto sm:max-w-lg">
        <SheetHeader>
          <SheetTitle>{i18n.licenseDetails || 'License details'}</SheetTitle>
          <SheetDescription>{license ? productLabel(license) : ' '}</SheetDescription>
        </SheetHeader>

        {!license ? (
          <div className="mt-6 space-y-3">
            <Skeleton className="h-16 w-full" />
            <Skeleton className="h-40 w-full" />
          </div>
        ) : (
          <div className="mt-6 space-y-6">
            <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
              <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{i18n.licenseKey || 'License key'}</p>
              <div className="mt-2 flex items-center gap-2">
                <code className="min-w-0 flex-1 break-all font-mono text-base font-semibold text-slate-900">
                  {reveal ? license.license_key : maskKey(license.license_key)}
                </code>
                <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => setReveal((v) => !v)} aria-label={reveal ? 'Hide key' : 'Show key'}>
                  {reveal ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </Button>
                <Button variant="ghost" size="icon" className="h-8 w-8" onClick={copy} aria-label={i18n.copy || 'Copy'}>
                  <Copy className="h-4 w-4" />
                </Button>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-2">
              {action(Mail, i18n.resendEmail || 'Resend email', () => run('resend_email'), 'resend_email')}
              {action(RefreshCw, i18n.syncFromApi || 'Sync from API', () => run('sync'), 'sync')}
              {action(Pencil, i18n.edit || 'Edit', () => onEdit(license.id))}
              {action(Flag, i18n.report || 'Report / replace', () => onReport(license.id))}
            </div>

            <dl className="m-0 divide-y divide-slate-100 border-y border-slate-100">
              <Row label={i18n.order || 'Order'}>
                <span className="flex flex-wrap items-center gap-2">
                  {license.order_link ? (
                    <a href={license.order_link} className="inline-flex items-center gap-1 font-medium text-indigo-600 no-underline hover:underline">
                      #{license.order_number} <ExternalLink className="h-3 w-3" />
                    </a>
                  ) : (
                    <span>#{license.order_id}</span>
                  )}
                  {license.order_status ? (
                    <Badge className={cn('border-0 font-medium capitalize', orderStatusTone[license.order_status] || 'bg-slate-100 text-slate-600')}>
                      {license.order_status.replace('-', ' ')}
                    </Badge>
                  ) : null}
                </span>
              </Row>
              <Row label={i18n.product || 'Product'}>
                {license.product_link ? (
                  <a href={license.product_link} className="text-indigo-600 no-underline hover:underline">
                    {productLabel(license)}
                  </a>
                ) : (
                  productLabel(license)
                )}
              </Row>
              <Row label="SKU">
                <code className="font-mono text-xs">{license.sku || '—'}</code>
              </Row>
              <Row label={i18n.customer || 'Customer'}>
                {license.email ? (
                  <a href={`mailto:${license.email}`} className="text-indigo-600 no-underline hover:underline">
                    {license.email}
                  </a>
                ) : (
                  '—'
                )}
              </Row>
              <Row label={i18n.emailStatus || 'Email'}>
                <Badge className={cn('border-0 font-medium', license.email_sent ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700')}>
                  {license.email_sent ? i18n.emailed || 'Emailed' : i18n.notEmailed || 'Not emailed'}
                </Badge>
              </Row>
              <Row label={i18n.soldAt || 'Sold'}>{formatDate(license.sold_at)}</Row>
              <Row label={i18n.lastSynced || 'Last synced'}>{license.last_synced_at && !license.last_synced_at.startsWith('0000') ? formatDate(license.last_synced_at) : '—'}</Row>
              <Row label={i18n.source || 'Source'}>{license.source || '—'}</Row>
              {license.remote_license_id ? <Row label={i18n.appLicenseId || 'App license ID'}>#{license.remote_license_id}</Row> : null}
              <Row label={i18n.downloadLink || 'Download link'}>
                {license.download_link ? (
                  <a href={license.download_link} target="_blank" rel="noreferrer" className="break-all text-indigo-600 no-underline hover:underline">
                    {license.download_link}
                  </a>
                ) : (
                  '—'
                )}
              </Row>
            </dl>

            {/^https?:\/\/\S+$/.test(license.activation_guide.trim()) ? (
              <Button asChild variant="outline" size="sm" className="w-full bg-white">
                <a href={license.activation_guide.trim()} target="_blank" rel="noreferrer">
                  <FileText className="h-4 w-4" /> {i18n.openActivationGuide || 'Open activation guide'}
                </a>
              </Button>
            ) : license.activation_guide ? (
              <div className="space-y-2">
                <p className="text-sm font-medium text-slate-900">{i18n.activationGuide || 'Activation guide'}</p>
                <div
                  className="prose prose-sm max-h-64 max-w-none overflow-auto rounded-lg border border-slate-200 bg-white p-3 text-sm text-slate-700"
                  // Stored with wp_kses_post on save.
                  dangerouslySetInnerHTML={{ __html: license.activation_guide }}
                />
              </div>
            ) : null}
          </div>
        )}
      </SheetContent>
    </Sheet>
  );
}
