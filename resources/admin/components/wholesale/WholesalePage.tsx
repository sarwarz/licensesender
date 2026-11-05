import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  AlertTriangle,
  Building2,
  Check,
  CheckCircle2,
  Clock,
  Eye,
  Inbox,
  Mail,
  MoreHorizontal,
  Package,
  RefreshCw,
  Search,
  ShieldOff,
  Trash2,
  UserRound,
  X,
  XCircle,
} from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { getBootstrap } from '@/lib/bootstrap';
import { PageHeader } from '@/components/shared/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Checkbox } from '@/components/ui/checkbox';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { ApplicationSheet, StatusBadge } from './ApplicationSheet';
import { UnmappedSkus } from './UnmappedSkus';
import {
  avatarTone,
  displayName,
  formatDate,
  initials,
  timeAgo,
  groupLabel,
  type ApplicationStatus,
  type BuyerGroup,
  type WholesaleApplication,
} from './types';

type Filter = ApplicationStatus | 'all';

interface CatalogSummary {
  tier: string;
  count: number;
  message?: string;
  success?: boolean;
}

export function WholesalePage() {
  const { i18n } = getBootstrap();
  const [filter, setFilter] = useState<Filter>('pending');
  const [search, setSearch] = useState('');
  const [applications, setApplications] = useState<WholesaleApplication[]>([]);
  const [catalog, setCatalog] = useState<CatalogSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [viewId, setViewId] = useState<number | null>(null);
  const [rejectTarget, setRejectTarget] = useState<WholesaleApplication | null>(null);
  const [rejectNote, setRejectNote] = useState('');
  const [reviewing, setReviewing] = useState(false);
  const [refreshingCatalog, setRefreshingCatalog] = useState(false);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [deleteTarget, setDeleteTarget] = useState<WholesaleApplication | null>(null);
  const [bulkDeleteOpen, setBulkDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [initialised, setInitialised] = useState(false);
  const [groups, setGroups] = useState<BuyerGroup[]>([]);
  const [defaultGroup, setDefaultGroup] = useState('');
  const [catalogReload, setCatalogReload] = useState(0);

  const loadCatalog = useCallback(async (refresh = false) => {
    const catalogData = await apiRequest<CatalogSummary>(
      `wholesale/catalog-summary${refresh ? '?refresh=1' : ''}`,
    );
    setCatalog(catalogData);
    return catalogData;
  }, []);

  // All applications are loaded once and filtered client-side so tab counts stay accurate.
  const loadApplications = useCallback(async () => {
    try {
      const data = await apiRequest<{ items: WholesaleApplication[] }>('wholesale/applications');
      setApplications(data.items);
      return data.items;
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
      return null;
    } finally {
      setLoading(false);
    }
  }, [i18n.error]);

  useEffect(() => {
    loadApplications().then((items) => {
      // Land on "All" when nothing is waiting, so the page is never empty by default.
      if (items && !items.some((row) => row.status === 'pending')) {
        setFilter('all');
      }
      setInitialised(true);
    });
    loadCatalog().catch(() => setCatalog({ tier: 'default', count: 0, success: false }));
    apiRequest<{ items: BuyerGroup[]; default_group: string }>('wholesale/groups')
      .then((data) => {
        setGroups(data.items);
        setDefaultGroup(data.default_group);
      })
      .catch(() => setGroups([]));
  }, [loadApplications, loadCatalog]);

  const refreshCatalog = async () => {
    setRefreshingCatalog(true);
    try {
      const catalogData = await loadCatalog(true);
      setCatalogReload((n) => n + 1);
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

  const counts = useMemo(() => {
    const result = { all: applications.length, pending: 0, approved: 0, rejected: 0 };
    applications.forEach((row) => {
      result[row.status] += 1;
    });
    return result;
  }, [applications]);

  const visible = useMemo(() => {
    const q = search.trim().toLowerCase();
    return applications.filter((row) => {
      if (filter !== 'all' && row.status !== filter) return false;
      if (!q) return true;
      return [row.applicant_name, row.company_name, row.business_email, row.phone, row.website]
        .some((value) => value?.toLowerCase().includes(q));
    });
  }, [applications, filter, search]);

  const viewTarget = applications.find((row) => row.id === viewId) ?? null;

  useEffect(() => {
    setSelectedIds((current) => current.filter((id) => visible.some((row) => row.id === id)));
  }, [visible]);

  const toggleSelected = (id: number, checked: boolean) => {
    setSelectedIds((current) => (
      checked ? Array.from(new Set([...current, id])) : current.filter((item) => item !== id)
    ));
  };

  const allSelected = visible.length > 0 && selectedIds.length === visible.length;
  const someSelected = selectedIds.length > 0 && !allSelected;
  const selectedPending = applications.filter((row) => selectedIds.includes(row.id) && row.status === 'pending');

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
      toast.success(
        ids.length === 1
          ? i18n.deleted ?? 'Application deleted.'
          : (i18n.deletedMany ?? '%d applications deleted.').replace('%d', String(ids.length)),
      );
      setDeleteTarget(null);
      setBulkDeleteOpen(false);
      setSelectedIds((current) => current.filter((id) => !ids.includes(id)));
      setViewId((current) => (current && ids.includes(current) ? null : current));
      await loadApplications();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setDeleting(false);
    }
  };

  const review = async (id: number, status: 'approved' | 'rejected', adminNote = '', group?: string) => {
    setReviewing(true);
    try {
      await apiRequest(`wholesale/applications/${id}/review`, {
        method: 'POST',
        body: JSON.stringify(group === undefined ? { status, admin_note: adminNote } : { status, admin_note: adminNote, group }),
      });
      toast.success(
        status === 'approved'
          ? i18n.approvedToast ?? 'Application approved. The customer now has wholesale access.'
          : i18n.rejectedToast ?? 'Application rejected. The customer has been notified.',
      );
      setRejectTarget(null);
      setRejectNote('');
      await loadApplications();
      return true;
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
      return false;
    } finally {
      setReviewing(false);
    }
  };

  const changeGroup = async (id: number, group: string) => {
    setReviewing(true);
    try {
      await apiRequest(`wholesale/applications/${id}/group`, { method: 'POST', body: JSON.stringify({ group }) });
      toast.success(i18n.groupSaved ?? 'Buyer group updated.');
      await loadApplications();
      return true;
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
      return false;
    } finally {
      setReviewing(false);
    }
  };

  const approveSelected = async () => {
    setReviewing(true);
    let done = 0;
    for (const row of selectedPending) {
      try {
        await apiRequest(`wholesale/applications/${row.id}/review`, {
          method: 'POST',
          body: JSON.stringify({ status: 'approved', admin_note: '', group: defaultGroup }),
        });
        done += 1;
      } catch (err) {
        toast.error(`${displayName(row)}: ${err instanceof Error ? err.message : i18n.error}`);
      }
    }
    if (done) {
      toast.success((i18n.approvedMany ?? '%d applications approved.').replace('%d', String(done)));
    }
    setSelectedIds([]);
    await loadApplications();
    setReviewing(false);
  };

  const openReject = (row: WholesaleApplication) => {
    setRejectTarget(row);
    setRejectNote('');
  };

  const tabs: { value: Filter; label: string; icon: typeof Inbox }[] = [
    { value: 'pending', label: i18n.pending ?? 'Pending', icon: Clock },
    { value: 'approved', label: i18n.approved ?? 'Approved', icon: CheckCircle2 },
    { value: 'rejected', label: i18n.rejected ?? 'Rejected', icon: XCircle },
    { value: 'all', label: i18n.all ?? 'All', icon: Inbox },
  ];

  const statCards = [
    { key: 'pending' as const, label: i18n.awaitingReview ?? 'Awaiting review', value: counts.pending, icon: Clock, tone: 'bg-amber-50 text-amber-600' },
    { key: 'approved' as const, label: i18n.activeResellers ?? 'Approved resellers', value: counts.approved, icon: CheckCircle2, tone: 'bg-emerald-50 text-emerald-600' },
    { key: 'rejected' as const, label: i18n.rejected ?? 'Rejected', value: counts.rejected, icon: XCircle, tone: 'bg-red-50 text-red-600' },
  ];

  const isFiltered = search.trim() !== '' || filter !== 'all';
  const showSkeleton = loading || !initialised;

  return (
    <div className="ls-admin-app mx-auto max-w-7xl space-y-6 px-1 pb-10">
      <PageHeader
        className="mb-0"
        title={i18n.title ?? 'Wholesale Applications'}
        subtitle={i18n.subtitle}
        badge={counts.pending > 0 ? (
          <Badge className="border-0 bg-amber-100 font-medium text-amber-800">
            {(i18n.pendingBadge ?? '%d pending').replace('%d', String(counts.pending))}
          </Badge>
        ) : null}
        actions={(
          <Button
            variant="outline"
            size="sm"
            className="bg-white"
            onClick={() => {
              setLoading(true);
              loadApplications();
            }}
            disabled={loading}
          >
            <RefreshCw className={cn('h-4 w-4', loading && 'animate-spin')} />
            {i18n.refresh ?? 'Refresh'}
          </Button>
        )}
      />

      <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        {statCards.map((card) => (
          <Card
            key={card.key}
            className={cn(
              'border-slate-200 shadow-sm transition-shadow hover:border-slate-300 hover:shadow-md',
              filter === card.key && 'border-indigo-300 ring-1 ring-indigo-200',
            )}
          >
            <button
              type="button"
              className="block w-full rounded-[inherit] focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
              onClick={() => setFilter(card.key)}
            >
              <CardContent className="flex items-center gap-3 p-4 sm:gap-4 sm:p-5">
                <span className={cn('hidden h-11 w-11 shrink-0 items-center justify-center rounded-xl sm:flex', card.tone)}>
                  <card.icon className="h-5 w-5" />
                </span>
                <div className="min-w-0 text-left">
                  <p className="text-sm font-medium text-slate-500">{card.label}</p>
                  {showSkeleton ? (
                    <Skeleton className="mt-1 h-7 w-12" />
                  ) : (
                    <p className="text-2xl font-semibold tracking-tight text-slate-900">{card.value.toLocaleString()}</p>
                  )}
                </div>
              </CardContent>
            </button>
          </Card>
        ))}

        <Card className={cn('border-slate-200 shadow-sm', catalog && catalog.count === 0 && 'border-amber-200 bg-amber-50/60')}>
          <CardContent className="flex items-center gap-3 p-4 sm:gap-4 sm:p-5">
            <span className="hidden h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600 sm:flex">
              <Package className="h-5 w-5" />
            </span>
            <div className="min-w-0 flex-1 text-left">
              <p className="text-sm font-medium text-slate-500">{i18n.wholesaleProducts ?? 'Wholesale products'}</p>
              {catalog ? (
                <p className="text-2xl font-semibold tracking-tight text-slate-900">
                  {catalog.count.toLocaleString()}
                  <span className="ml-2 align-middle text-xs font-medium text-slate-500">
                    {(i18n.tierLabel ?? 'tier: %s').replace('%s', catalog.tier || 'default')}
                  </span>
                </p>
              ) : (
                <Skeleton className="mt-1 h-7 w-20" />
              )}
            </div>
            <Button
              variant="ghost"
              size="icon"
              className="h-8 w-8 shrink-0 text-slate-400 hover:text-slate-700"
              onClick={refreshCatalog}
              disabled={refreshingCatalog}
              title={i18n.refreshCatalog ?? 'Refresh catalog'}
              aria-label={i18n.refreshCatalog ?? 'Refresh catalog'}
            >
              <RefreshCw className={cn('h-4 w-4', refreshingCatalog && 'animate-spin')} />
            </Button>
          </CardContent>
        </Card>
      </div>

      {catalog && catalog.count === 0 ? (
        <div className="flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3.5 sm:flex-row sm:items-center">
          <AlertTriangle className="hidden h-5 w-5 shrink-0 text-amber-600 sm:block" />
          <div className="min-w-0 flex-1 text-sm text-amber-900">
            <p className="m-0 font-medium">{i18n.catalogEmptyTitle ?? 'No wholesale products from API'}</p>
            <p className="m-0 mt-0.5 text-amber-800">
              {i18n.catalogEmptyHint
                ?? 'Enable wholesale on products in the licensesender app, set wholesale prices for your tier, then click Refresh catalog.'}
            </p>
          </div>
          <Button variant="outline" size="sm" className="shrink-0 border-amber-300 bg-white" onClick={refreshCatalog} disabled={refreshingCatalog}>
            <RefreshCw className={cn('h-4 w-4', refreshingCatalog && 'animate-spin')} />
            {i18n.refreshCatalog ?? 'Refresh catalog'}
          </Button>
        </div>
      ) : null}

      <UnmappedSkus reloadKey={catalogReload} />

      <Card className="overflow-hidden border-slate-200 shadow-sm">
        <CardContent className="p-0">
          <div className="flex flex-col gap-3 border-b border-slate-200 px-4 pt-3 lg:flex-row lg:items-end lg:justify-between">
            <div className="flex gap-1 overflow-x-auto overflow-y-hidden">
              {tabs.map((tab) => (
                <button
                  key={tab.value}
                  type="button"
                  onClick={() => setFilter(tab.value)}
                  className={cn(
                    '-mb-px inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-3 pb-3 pt-1 text-sm font-medium transition-colors',
                    filter === tab.value ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800',
                  )}
                >
                  <tab.icon className="h-4 w-4" />
                  {tab.label}
                  <span
                    className={cn(
                      'rounded-full px-1.5 py-0.5 text-xs',
                      filter === tab.value
                        ? 'bg-indigo-50 text-indigo-700'
                        : tab.value === 'pending' && counts.pending > 0
                          ? 'bg-amber-100 text-amber-800'
                          : 'bg-slate-100 text-slate-600',
                    )}
                  >
                    {counts[tab.value].toLocaleString()}
                  </span>
                </button>
              ))}
            </div>
            <div className="relative pb-3 lg:w-72">
              <Search className="absolute left-3 top-[18px] h-4 w-4 -translate-y-1/2 text-slate-400" />
              <Input
                className="h-9 border-slate-200 bg-slate-50 pl-9 pr-8"
                placeholder={i18n.searchPlaceholder ?? 'Search name, company, email…'}
                value={search}
                onChange={(event) => setSearch(event.target.value)}
              />
              {search ? (
                <button
                  type="button"
                  className="absolute right-2 top-[18px] -translate-y-1/2 rounded p-1 text-slate-400 hover:text-slate-700"
                  onClick={() => setSearch('')}
                  aria-label={i18n.clearSearch ?? 'Clear search'}
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              ) : null}
            </div>
          </div>

          {selectedIds.length > 0 ? (
            <div className="flex flex-wrap items-center gap-2 border-b border-indigo-100 bg-indigo-50/70 px-4 py-2.5">
              <span className="mr-2 text-sm font-medium text-indigo-900">
                {(i18n.selectedCount ?? '%d selected').replace('%d', String(selectedIds.length))}
              </span>
              {selectedPending.length > 0 ? (
                <Button size="sm" className="bg-emerald-600 text-white hover:bg-emerald-700" onClick={approveSelected} disabled={reviewing}>
                  <Check className="h-4 w-4" />
                  {(i18n.approveSelected ?? 'Approve %d pending').replace('%d', String(selectedPending.length))}
                </Button>
              ) : null}
              <Button size="sm" variant="outline" className="bg-white text-red-700 hover:bg-red-50 hover:text-red-800" onClick={() => setBulkDeleteOpen(true)} disabled={deleting}>
                <Trash2 className="h-4 w-4" />
                {i18n.bulkDelete ?? 'Delete selected'}
              </Button>
              <Button size="sm" variant="ghost" className="ml-auto text-slate-600" onClick={() => setSelectedIds([])}>
                {i18n.clearSelection ?? 'Clear selection'}
              </Button>
            </div>
          ) : null}

          <Table>
            <TableHeader>
              <TableRow className="bg-slate-50/80 hover:bg-slate-50/80">
                <TableHead className="w-10 pl-4">
                  <Checkbox
                    checked={allSelected ? true : someSelected ? 'indeterminate' : false}
                    onCheckedChange={(checked) => setSelectedIds(checked === true ? visible.map((row) => row.id) : [])}
                    aria-label={i18n.selectAll ?? 'Select all'}
                  />
                </TableHead>
                <TableHead>{i18n.applicant ?? 'Applicant'}</TableHead>
                <TableHead className="hidden md:table-cell">{i18n.contact ?? 'Contact'}</TableHead>
                <TableHead>{i18n.status ?? 'Status'}</TableHead>
                <TableHead className="hidden sm:table-cell">{i18n.submitted ?? 'Submitted'}</TableHead>
                <TableHead className="pr-4 text-right">
                  <span className="sr-only">{i18n.actions ?? 'Actions'}</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {showSkeleton ? (
                Array.from({ length: 5 }).map((_, i) => (
                  <TableRow key={i}>
                    <TableCell className="pl-4"><Skeleton className="h-4 w-4" /></TableCell>
                    <TableCell>
                      <div className="flex items-center gap-3">
                        <Skeleton className="h-9 w-9 rounded-lg" />
                        <div className="space-y-1.5"><Skeleton className="h-4 w-36" /><Skeleton className="h-3 w-24" /></div>
                      </div>
                    </TableCell>
                    <TableCell className="hidden md:table-cell"><Skeleton className="h-4 w-44" /></TableCell>
                    <TableCell><Skeleton className="h-5 w-20 rounded-full" /></TableCell>
                    <TableCell className="hidden sm:table-cell"><Skeleton className="h-4 w-24" /></TableCell>
                    <TableCell className="pr-4"><Skeleton className="ml-auto h-8 w-32" /></TableCell>
                  </TableRow>
                ))
              ) : visible.length === 0 ? (
                <TableRow className="hover:bg-transparent">
                  <TableCell colSpan={6} className="py-16 text-center">
                    {filter === 'pending' && !search ? (
                      <CheckCircle2 className="mx-auto h-9 w-9 text-emerald-400" />
                    ) : (
                      <Building2 className="mx-auto h-9 w-9 text-slate-300" />
                    )}
                    <p className="mt-3 text-base font-medium text-slate-900">
                      {search
                        ? i18n.noMatches ?? 'No applications match your search'
                        : filter === 'pending'
                          ? i18n.inboxZero ?? "You're all caught up"
                          : counts.all === 0
                            ? i18n.noApplications ?? 'No wholesale applications yet'
                            : i18n.empty ?? 'No applications found.'}
                    </p>
                    <p className="mt-1 text-sm text-slate-500">
                      {isFiltered && counts.all > 0 ? (
                        <button
                          type="button"
                          className="font-medium text-indigo-600 hover:underline"
                          onClick={() => {
                            setSearch('');
                            setFilter('all');
                          }}
                        >
                          {i18n.showAll ?? 'Show all applications'}
                        </button>
                      ) : (
                        i18n.noApplicationsHint ?? 'Requests submitted through the wholesale application form will appear here.'
                      )}
                    </p>
                  </TableCell>
                </TableRow>
              ) : (
                visible.map((row) => (
                  <TableRow
                    key={row.id}
                    data-state={selectedIds.includes(row.id) ? 'selected' : undefined}
                    className="cursor-pointer bg-white"
                    onClick={() => setViewId(row.id)}
                  >
                    <TableCell className="pl-4" onClick={(event) => event.stopPropagation()}>
                      <Checkbox
                        checked={selectedIds.includes(row.id)}
                        onCheckedChange={(checked) => toggleSelected(row.id, checked === true)}
                        aria-label={`${i18n.select ?? 'Select'} ${displayName(row)}`}
                      />
                    </TableCell>
                    <TableCell className="max-w-[280px]">
                      <div className="flex items-center gap-3">
                        <span className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-xs font-semibold', avatarTone(row))}>
                          {initials(row)}
                        </span>
                        <div className="min-w-0">
                          <p className="m-0 truncate font-medium text-slate-900">
                            {row.company_name || displayName(row)}
                          </p>
                          <p className="m-0 truncate text-xs text-slate-500">
                            {row.user_edit_url
                              ? row.company_name ? displayName(row) : row.business_email
                              : i18n.deletedUser ?? '(Deleted user)'}
                          </p>
                        </div>
                      </div>
                    </TableCell>
                    <TableCell className="hidden max-w-[240px] md:table-cell">
                      <p className="m-0 truncate text-sm text-slate-700" title={row.business_email}>{row.business_email}</p>
                      {row.phone ? <p className="m-0 truncate text-xs text-slate-500">{row.phone}</p> : null}
                    </TableCell>
                    <TableCell>
                      <StatusBadge status={row.status} />
                      {row.status === 'approved' && groups.length > 0 ? (
                        <p className="m-0 mt-1 text-xs text-slate-500">{groupLabel(row.group, groups, i18n.defaultGroup ?? 'Default group')}</p>
                      ) : null}
                    </TableCell>
                    <TableCell className="hidden whitespace-nowrap sm:table-cell">
                      <p className="m-0 text-sm text-slate-700" title={formatDate(row.created_at)}>{timeAgo(row.created_at) || formatDate(row.created_at)}</p>
                    </TableCell>
                    <TableCell className="pr-4 text-right" onClick={(event) => event.stopPropagation()}>
                      <div className="flex items-center justify-end gap-1.5">
                        {row.status === 'pending' ? (
                          <>
                            <Button
                              size="sm"
                              variant="outline"
                              className="hidden h-8 bg-white text-red-700 hover:bg-red-50 hover:text-red-800 lg:inline-flex"
                              onClick={() => openReject(row)}
                              disabled={reviewing}
                            >
                              <X className="h-4 w-4" />
                              {i18n.reject ?? 'Reject'}
                            </Button>
                            <Button
                              size="sm"
                              className="h-8 bg-emerald-600 text-white hover:bg-emerald-700"
                              onClick={() => review(row.id, 'approved')}
                              disabled={reviewing}
                            >
                              <Check className="h-4 w-4" />
                              <span className="hidden sm:inline">{i18n.approve ?? 'Approve'}</span>
                            </Button>
                          </>
                        ) : null}
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="h-8 w-8" aria-label={i18n.actions ?? 'Actions'}>
                              <MoreHorizontal className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end" className="w-52">
                            <DropdownMenuLabel className="truncate text-xs font-normal text-slate-500">
                              {row.company_name || displayName(row)}
                            </DropdownMenuLabel>
                            <DropdownMenuItem onClick={() => setViewId(row.id)}>
                              <Eye className="mr-2 h-4 w-4" /> {i18n.viewApplication ?? 'Application details'}
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                              <a href={`mailto:${row.business_email}`}>
                                <Mail className="mr-2 h-4 w-4" /> {i18n.emailApplicant ?? 'Email applicant'}
                              </a>
                            </DropdownMenuItem>
                            {row.user_edit_url ? (
                              <DropdownMenuItem asChild>
                                <a href={row.user_edit_url}>
                                  <UserRound className="mr-2 h-4 w-4" /> {i18n.openUser ?? 'Open WordPress user'}
                                </a>
                              </DropdownMenuItem>
                            ) : null}
                            <DropdownMenuSeparator />
                            {row.status === 'pending' ? (
                              <DropdownMenuItem onClick={() => openReject(row)}>
                                <X className="mr-2 h-4 w-4" /> {i18n.reject ?? 'Reject'}
                              </DropdownMenuItem>
                            ) : row.status === 'approved' ? (
                              <DropdownMenuItem onClick={() => openReject(row)}>
                                <ShieldOff className="mr-2 h-4 w-4" /> {i18n.revoke ?? 'Revoke access'}
                              </DropdownMenuItem>
                            ) : (
                              <DropdownMenuItem onClick={() => review(row.id, 'approved')}>
                                <Check className="mr-2 h-4 w-4" /> {i18n.approveInstead ?? 'Approve instead'}
                              </DropdownMenuItem>
                            )}
                            <DropdownMenuItem className="text-red-600 focus:bg-red-50 focus:text-red-700" onClick={() => setDeleteTarget(row)}>
                              <Trash2 className="mr-2 h-4 w-4" /> {i18n.delete ?? 'Delete'}
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>

          {!showSkeleton && visible.length > 0 ? (
            <div className="border-t border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-slate-500">
              {(i18n.showingCount ?? 'Showing %1$d of %2$d applications')
                .replace('%1$d', String(visible.length))
                .replace('%2$d', String(counts.all))}
            </div>
          ) : null}
        </CardContent>
      </Card>

      <ApplicationSheet
        application={viewTarget}
        onOpenChange={(open) => !open && setViewId(null)}
        onReview={review}
        onGroupChange={changeGroup}
        groups={groups}
        defaultGroup={defaultGroup}
        onDelete={setDeleteTarget}
        reviewing={reviewing}
      />

      <Dialog open={!!rejectTarget} onOpenChange={(open) => !open && setRejectTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {rejectTarget?.status === 'approved'
                ? i18n.revokeTitle ?? 'Revoke wholesale access?'
                : i18n.rejectApplication ?? 'Reject application'}
            </DialogTitle>
            <DialogDescription>
              {(rejectTarget?.status === 'approved'
                ? i18n.revokeDesc ?? '%s will lose wholesale pricing and receive the rejection email.'
                : i18n.rejectDesc ?? '%s will be notified by email that the application was declined.'
              ).replace('%s', rejectTarget ? rejectTarget.company_name || displayName(rejectTarget) : '')}
            </DialogDescription>
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
              {rejectTarget?.status === 'approved' ? i18n.revoke ?? 'Revoke access' : i18n.reject ?? 'Reject'}
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
                ?? 'This will permanently delete this wholesale application. This action cannot be undone.'}
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
