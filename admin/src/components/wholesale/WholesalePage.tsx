import { useCallback, useEffect, useState } from 'react';
import { Building2, Check, Eye, Package, RefreshCw, Trash2, X } from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { getBootstrap } from '@/lib/bootstrap';
import { PageHeader } from '@/components/shared/PageHeader';
import { StatCard } from '@/components/licenses/StatCard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Checkbox } from '@/components/ui/checkbox';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { cn } from '@/lib/utils';

type ApplicationStatus = 'pending' | 'approved' | 'rejected' | '';

interface WholesaleApplication {
  id: number;
  user_id: number;
  applicant_name: string;
  user_edit_url: string;
  company_name: string;
  business_email: string;
  phone: string;
  messenger_link: string;
  website: string;
  message: string;
  status: 'pending' | 'approved' | 'rejected';
  admin_note: string;
  created_at: string;
  reviewed_at: string;
}

interface CatalogSummary {
  tier: string;
  count: number;
  message?: string;
  success?: boolean;
}

const statusFilters: { value: ApplicationStatus; label: string }[] = [
  { value: '', label: 'All' },
  { value: 'pending', label: 'Pending' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
];

function StatusBadge({ status }: { status: WholesaleApplication['status'] }) {
  return (
    <Badge
      variant="outline"
      className={cn(
        'border-0 font-semibold capitalize',
        status === 'pending' && 'bg-amber-50 text-amber-800',
        status === 'approved' && 'bg-emerald-50 text-emerald-800',
        status === 'rejected' && 'bg-red-50 text-red-800',
      )}
    >
      {status}
    </Badge>
  );
}

function DetailRow({
  label,
  value,
  href,
}: {
  label: string;
  value?: string;
  href?: string;
}) {
  if (!value) {
    return null;
  }

  return (
    <div className="grid gap-1 border-b border-slate-100 py-3 last:border-b-0 sm:grid-cols-[140px_1fr] sm:gap-4">
      <dt className="text-sm font-medium text-muted-foreground">{label}</dt>
      <dd className="text-sm text-foreground break-words">
        {href ? (
          <a href={href} className="text-primary hover:underline" target="_blank" rel="noopener noreferrer">
            {value}
          </a>
        ) : (
          value
        )}
      </dd>
    </div>
  );
}

export function WholesalePage() {
  const { i18n } = getBootstrap();
  const [statusFilter, setStatusFilter] = useState<ApplicationStatus>('');
  const [applications, setApplications] = useState<WholesaleApplication[]>([]);
  const [catalog, setCatalog] = useState<CatalogSummary>({ tier: '—', count: 0 });
  const [loading, setLoading] = useState(true);
  const [viewTarget, setViewTarget] = useState<WholesaleApplication | null>(null);
  const [rejectTarget, setRejectTarget] = useState<WholesaleApplication | null>(null);
  const [rejectNote, setRejectNote] = useState('');
  const [reviewing, setReviewing] = useState(false);
  const [refreshingCatalog, setRefreshingCatalog] = useState(false);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [deleteTarget, setDeleteTarget] = useState<WholesaleApplication | null>(null);
  const [bulkDeleteOpen, setBulkDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const loadCatalog = useCallback(async (refresh = false) => {
    const catalogData = await apiRequest<CatalogSummary>(
      `wholesale/catalog-summary${refresh ? '?refresh=1' : ''}`,
    );
    setCatalog(catalogData);
    return catalogData;
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [appsData] = await Promise.all([
        apiRequest<{ items: WholesaleApplication[] }>(
          `wholesale/applications${statusFilter ? `?status=${statusFilter}` : ''}`,
        ),
        loadCatalog(),
      ]);
      setApplications(appsData.items);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setLoading(false);
    }
  }, [i18n.error, loadCatalog, statusFilter]);

  const refreshCatalog = async () => {
    setRefreshingCatalog(true);
    try {
      const catalogData = await loadCatalog(true);
      if (catalogData.count > 0) {
        toast.success(i18n.catalogRefreshed ?? 'Catalog refreshed.');
      } else {
        toast.message(i18n.catalogEmpty ?? 'Catalog refreshed, but no wholesale products were returned by the API.');
      }
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setRefreshingCatalog(false);
    }
  };

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    setSelectedIds((current) => current.filter((id) => applications.some((row) => row.id === id)));
  }, [applications]);

  const toggleSelected = (id: number, checked: boolean) => {
    setSelectedIds((current) => (
      checked ? Array.from(new Set([...current, id])) : current.filter((item) => item !== id)
    ));
  };

  const toggleSelectAll = (checked: boolean) => {
    setSelectedIds(checked ? applications.map((row) => row.id) : []);
  };

  const allSelected = applications.length > 0 && selectedIds.length === applications.length;
  const someSelected = selectedIds.length > 0 && !allSelected;

  const deleteApplications = async (ids: number[]) => {
    if (!ids.length) {
      return;
    }

    setDeleting(true);
    try {
      if (ids.length === 1) {
        await apiRequest(`wholesale/applications/${ids[0]}`, { method: 'DELETE' });
      } else {
        await apiRequest('wholesale/applications/bulk-delete', {
          method: 'POST',
          body: JSON.stringify({ ids }),
        });
      }
      toast.success(i18n.deleted ?? 'Application deleted.');
      setDeleteTarget(null);
      setBulkDeleteOpen(false);
      setSelectedIds((current) => current.filter((id) => !ids.includes(id)));
      setViewTarget((current) => (current && ids.includes(current.id) ? null : current));
      setRejectTarget((current) => (current && ids.includes(current.id) ? null : current));
      load();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setDeleting(false);
    }
  };

  const review = async (id: number, status: 'approved' | 'rejected', adminNote = '') => {
    setReviewing(true);
    try {
      await apiRequest(`wholesale/applications/${id}/review`, {
        method: 'POST',
        body: JSON.stringify({ status, admin_note: adminNote }),
      });
      toast.success(i18n.success);
      setRejectTarget(null);
      setViewTarget(null);
      setRejectNote('');
      load();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setReviewing(false);
    }
  };

  const pendingCount = applications.filter((row) => row.status === 'pending').length;

  return (
    <div className="ls-admin-app mx-auto max-w-7xl px-1 pb-10">
      <PageHeader
        title={i18n.title ?? 'Wholesale Applications'}
        subtitle={i18n.subtitle}
        actions={(
          <Button variant="outline" onClick={refreshCatalog} disabled={refreshingCatalog}>
            <RefreshCw className={cn('mr-2 h-4 w-4', refreshingCatalog && 'animate-spin')} />
            {i18n.refreshCatalog ?? 'Refresh catalog'}
          </Button>
        )}
      />

      {catalog.count === 0 ? (
        <Card className="mb-6 border-amber-200 bg-amber-50 shadow-sm">
          <CardContent className="p-5 text-sm text-amber-900">
            <p className="font-medium">{i18n.catalogEmptyTitle ?? 'No wholesale products from API'}</p>
            <p className="mt-1">
              {i18n.catalogEmptyHint
                ?? `Tier "${catalog.tier || 'default'}" returned 0 products. Enable wholesale on products in the licensesender app, set wholesale prices, then click Refresh catalog.`}
            </p>
          </CardContent>
        </Card>
      ) : null}

      <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <StatCard
          label={i18n.catalogTier ?? 'Catalog tier'}
          value={catalog.tier || 'default'}
          icon={Package}
          tone="sky"
        />
        <StatCard
          label={i18n.wholesaleProducts ?? 'Wholesale products'}
          value={catalog.count}
          icon={Building2}
          tone="indigo"
        />
        {statusFilter === '' ? (
          <StatCard
            label={i18n.pending ?? 'Pending'}
            value={pendingCount}
            icon={Check}
            tone="amber"
          />
        ) : null}
      </div>

      <Tabs
        value={statusFilter || 'all'}
        onValueChange={(value) => setStatusFilter(value === 'all' ? '' : (value as ApplicationStatus))}
        className="mb-4"
      >
        <TabsList>
          {statusFilters.map((filter) => (
            <TabsTrigger key={filter.value || 'all'} value={filter.value || 'all'}>
              {filter.label}
            </TabsTrigger>
          ))}
        </TabsList>
      </Tabs>

      {selectedIds.length > 0 ? (
        <div className="mb-4 flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
          <p className="text-sm text-muted-foreground">
            {selectedIds.length} {i18n.selected ?? 'selected'}
          </p>
          <Button variant="destructive" size="sm" onClick={() => setBulkDeleteOpen(true)} disabled={deleting}>
            <Trash2 className="mr-2 h-4 w-4" />
            {i18n.bulkDelete ?? 'Delete selected'}
          </Button>
        </div>
      ) : null}

      <Card className="border-slate-200 shadow-sm">
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-10">
                  <Checkbox
                    checked={allSelected ? true : someSelected ? 'indeterminate' : false}
                    onCheckedChange={(checked) => toggleSelectAll(checked === true)}
                    aria-label={i18n.selectAll ?? 'Select all'}
                  />
                </TableHead>
                <TableHead>{i18n.applicant ?? 'Applicant'}</TableHead>
                <TableHead>{i18n.email ?? 'Email'}</TableHead>
                <TableHead>{i18n.status ?? 'Status'}</TableHead>
                <TableHead>{i18n.submitted ?? 'Submitted'}</TableHead>
                <TableHead className="text-right">{i18n.actions ?? 'Actions'}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {loading ? (
                <TableRow>
                  <TableCell colSpan={6} className="py-10 text-center text-muted-foreground">
                    {i18n.loading}
                  </TableCell>
                </TableRow>
              ) : applications.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="py-10 text-center text-muted-foreground">
                    {i18n.empty ?? 'No applications found.'}
                  </TableCell>
                </TableRow>
              ) : (
                applications.map((row) => (
                  <TableRow key={row.id}>
                    <TableCell>
                      <Checkbox
                        checked={selectedIds.includes(row.id)}
                        onCheckedChange={(checked) => toggleSelected(row.id, checked === true)}
                        aria-label={`${i18n.select ?? 'Select'} ${row.applicant_name || row.id}`}
                      />
                    </TableCell>
                    <TableCell>
                      {row.user_edit_url ? (
                        <a
                          href={row.user_edit_url}
                          className="font-medium text-primary hover:underline"
                        >
                          {row.applicant_name || `#${row.user_id}`}
                        </a>
                      ) : (
                        <span className="text-muted-foreground">{i18n.deletedUser ?? '(Deleted user)'}</span>
                      )}
                    </TableCell>
                    <TableCell>
                      <a href={`mailto:${row.business_email}`} className="text-primary hover:underline">
                        {row.business_email}
                      </a>
                    </TableCell>
                    <TableCell>
                      <StatusBadge status={row.status} />
                    </TableCell>
                    <TableCell className="whitespace-nowrap text-sm text-muted-foreground">
                      {row.created_at}
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-2">
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => setViewTarget(row)}
                        >
                          <Eye className="mr-1 h-4 w-4" />
                          {i18n.view ?? 'View'}
                        </Button>
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => setDeleteTarget(row)}
                          disabled={deleting}
                        >
                          <Trash2 className="mr-1 h-4 w-4" />
                          {i18n.delete ?? 'Delete'}
                        </Button>
                        {row.status === 'pending' ? (
                          <>
                            <Button
                              size="sm"
                              onClick={() => review(row.id, 'approved')}
                              disabled={reviewing}
                            >
                              <Check className="mr-1 h-4 w-4" />
                              {i18n.approve ?? 'Approve'}
                            </Button>
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => {
                                setRejectTarget(row);
                                setRejectNote('');
                              }}
                              disabled={reviewing}
                            >
                              <X className="mr-1 h-4 w-4" />
                              {i18n.reject ?? 'Reject'}
                            </Button>
                          </>
                        ) : null}
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Dialog open={!!viewTarget} onOpenChange={(open) => !open && setViewTarget(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>{i18n.viewApplication ?? 'Application details'}</DialogTitle>
          </DialogHeader>
          {viewTarget ? (
            <dl className="px-1">
              <DetailRow
                label={i18n.applicant ?? 'Applicant'}
                value={viewTarget.applicant_name || `#${viewTarget.user_id}`}
                href={viewTarget.user_edit_url || undefined}
              />
              <DetailRow label={i18n.company ?? 'Company'} value={viewTarget.company_name} />
              <DetailRow
                label={i18n.email ?? 'Email'}
                value={viewTarget.business_email}
                href={`mailto:${viewTarget.business_email}`}
              />
              <DetailRow label={i18n.phone ?? 'Phone'} value={viewTarget.phone} />
              <DetailRow
                label={i18n.website ?? 'Website'}
                value={viewTarget.website}
                href={viewTarget.website || undefined}
              />
              <DetailRow
                label={i18n.messenger ?? 'Telegram / WhatsApp'}
                value={viewTarget.messenger_link}
                href={viewTarget.messenger_link || undefined}
              />
              <DetailRow label={i18n.status ?? 'Status'} value={viewTarget.status} />
              <DetailRow label={i18n.submitted ?? 'Submitted'} value={viewTarget.created_at} />
              <DetailRow label={i18n.reviewed ?? 'Reviewed'} value={viewTarget.reviewed_at} />
              <DetailRow label={i18n.adminNote ?? 'Admin note'} value={viewTarget.admin_note} />
              {viewTarget.message ? (
                <div className="grid gap-1 border-b border-slate-100 py-3 last:border-b-0 sm:grid-cols-[140px_1fr] sm:gap-4">
                  <dt className="text-sm font-medium text-muted-foreground">{i18n.message ?? 'Message'}</dt>
                  <dd className="whitespace-pre-wrap text-sm text-foreground">{viewTarget.message}</dd>
                </div>
              ) : null}
            </dl>
          ) : null}
          <DialogFooter>
            {viewTarget?.status === 'pending' ? (
              <>
                <Button
                  variant="outline"
                  onClick={() => viewTarget && review(viewTarget.id, 'approved')}
                  disabled={reviewing}
                >
                  {i18n.approve ?? 'Approve'}
                </Button>
                <Button
                  variant="destructive"
                  onClick={() => {
                    if (viewTarget) {
                      setRejectTarget(viewTarget);
                      setViewTarget(null);
                      setRejectNote('');
                    }
                  }}
                  disabled={reviewing}
                >
                  {i18n.reject ?? 'Reject'}
                </Button>
              </>
            ) : (
              <Button variant="outline" onClick={() => setViewTarget(null)}>
                {i18n.cancel}
              </Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={!!rejectTarget} onOpenChange={(open) => !open && setRejectTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{i18n.rejectApplication ?? 'Reject application'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <Label htmlFor="reject-note">{i18n.rejectNote ?? 'Optional note'}</Label>
            <textarea
              id="reject-note"
              value={rejectNote}
              onChange={(event) => setRejectNote(event.target.value)}
              rows={3}
              className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              placeholder={i18n.rejectNotePlaceholder ?? 'Reason for rejection (optional)'}
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setRejectTarget(null)} disabled={reviewing}>
              {i18n.cancel}
            </Button>
            <Button
              variant="destructive"
              disabled={reviewing || !rejectTarget}
              onClick={() => rejectTarget && review(rejectTarget.id, 'rejected', rejectNote)}
            >
              {i18n.reject ?? 'Reject'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{i18n.deleteApplication ?? 'Delete application?'}</AlertDialogTitle>
            <AlertDialogDescription>
              {i18n.deleteApplicationConfirm
                ?? 'This will permanently delete the selected wholesale application. This action cannot be undone.'}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleting}>{i18n.cancel}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              disabled={deleting || !deleteTarget}
              onClick={(event) => {
                event.preventDefault();
                if (deleteTarget) {
                  deleteApplications([deleteTarget.id]);
                }
              }}
            >
              {i18n.delete ?? 'Delete'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={bulkDeleteOpen} onOpenChange={setBulkDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{i18n.bulkDeleteTitle ?? 'Delete selected applications?'}</AlertDialogTitle>
            <AlertDialogDescription>
              {(i18n.bulkDeleteConfirm
                ?? 'This will permanently delete %d wholesale applications. This action cannot be undone.')
                .replace('%d', String(selectedIds.length))}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleting}>{i18n.cancel}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              disabled={deleting || selectedIds.length === 0}
              onClick={(event) => {
                event.preventDefault();
                deleteApplications(selectedIds);
              }}
            >
              {i18n.bulkDelete ?? 'Delete selected'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
