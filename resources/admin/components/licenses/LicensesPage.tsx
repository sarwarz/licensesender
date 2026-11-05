import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  ChevronLeft,
  ChevronRight,
  Copy,
  Download,
  ExternalLink,
  Eye,
  EyeOff,
  FileText,
  Filter,
  Flag,
  KeyRound,
  Mail,
  MailCheck,
  MailWarning,
  MoreHorizontal,
  Package,
  Pencil,
  RefreshCw,
  Search,
  ShoppingCart,
  Users,
  X,
} from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { getBootstrap } from '@/lib/bootstrap';
import { cn } from '@/lib/utils';
import { PageHeader } from '@/components/shared/PageHeader';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { DatePicker } from '@/components/ui/date-picker';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { LicenseDetailsSheet } from './LicenseDetailsSheet';
import { LicenseEditSheet } from './LicenseEditSheet';
import { LicenseReportSheet } from './LicenseReportSheet';
import { StockCheckDialog } from './StockCheckDialog';
import {
  downloadFile,
  formatDate,
  maskKey,
  orderStatusTone,
  productLabel,
  toCsv,
  type BulkResult,
  type LicenseCounts,
  type LicenseItem,
  type LicenseListResponse,
  type LicenseProduct,
  type SortKey,
  type StatusFilter,
} from './types';

interface LicenseStats {
  total: number;
  orders: number;
  products: number;
  emails: number;
}

type Range = 'all' | 'today' | '7' | '30' | '90' | 'custom';

const PAGE_SIZES = [25, 50, 100];

function isoDaysAgo(days: number) {
  const d = new Date();
  d.setDate(d.getDate() - days);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function rangeDates(range: Range, from: string, to: string): { date_from?: string; date_to?: string } {
  switch (range) {
    case 'today':
      return { date_from: isoDaysAgo(0) };
    case '7':
    case '30':
    case '90':
      return { date_from: isoDaysAgo(Number(range) - 1) };
    case 'custom':
      return { date_from: from || undefined, date_to: to || undefined };
    default:
      return {};
  }
}

/** Initial filters from the URL, so filtered views survive reloads and can be bookmarked. */
function readUrl() {
  const p = new URLSearchParams(window.location.search);
  const status = p.get('ls_status');
  return {
    search: p.get('ls_search') || '',
    status: (status === 'sent' || status === 'pending' ? status : 'all') as StatusFilter,
    product: p.get('ls_product') || 'all',
    range: (p.get('ls_range') as Range) || 'all',
  };
}

export function LicensesPage() {
  const { i18n, licenseId } = getBootstrap();
  const initial = useMemo(readUrl, []);

  const [stats, setStats] = useState<LicenseStats | null>(null);
  const [products, setProducts] = useState<LicenseProduct[]>([]);
  const [items, setItems] = useState<LicenseItem[]>([]);
  const [counts, setCounts] = useState<LicenseCounts>({ all: 0, sent: 0, pending: 0 });
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);

  const [searchInput, setSearchInput] = useState(initial.search);
  const [search, setSearch] = useState(initial.search);
  const [status, setStatus] = useState<StatusFilter>(initial.status);
  const [product, setProduct] = useState(initial.product);
  const [range, setRange] = useState<Range>(initial.range);
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [sort, setSort] = useState<{ key: SortKey; dir: 'asc' | 'desc' }>({ key: 'id', dir: 'desc' });
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);

  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [revealAll, setRevealAll] = useState(false);
  const [revealed, setRevealed] = useState<Set<number>>(new Set());
  const [bulkBusy, setBulkBusy] = useState(false);
  const [confirmResend, setConfirmResend] = useState<number[] | null>(null);

  const [detailsId, setDetailsId] = useState<number | null>(null);
  const [editId, setEditId] = useState<number | null>(null);
  const [reportId, setReportId] = useState<number | null>(null);
  const [reloadToken, setReloadToken] = useState(0);

  const filters = useMemo(() => {
    const f: Record<string, string> = { search };
    if (status !== 'all') f.status = status;
    if (product !== 'all') f.product_id = product;
    Object.entries(rangeDates(range, dateFrom, dateTo)).forEach(([k, v]) => v && (f[k] = v));
    return f;
  }, [search, status, product, range, dateFrom, dateTo]);

  const activeFilterCount = (product !== 'all' ? 1 : 0) + (range !== 'all' ? 1 : 0) + (search ? 1 : 0);

  // Debounce typing in the search box.
  useEffect(() => {
    const t = window.setTimeout(() => {
      setSearch(searchInput.trim());
      setPage(1);
    }, 300);
    return () => window.clearTimeout(t);
  }, [searchInput]);

  useEffect(() => {
    const p = new URLSearchParams(window.location.search);
    const set = (k: string, v: string, empty: string) => (v && v !== empty ? p.set(k, v) : p.delete(k));
    set('ls_search', search, '');
    set('ls_status', status, 'all');
    set('ls_product', product, 'all');
    set('ls_range', range === 'custom' ? 'all' : range, 'all');
    window.history.replaceState(null, '', `${window.location.pathname}?${p}`);
  }, [search, status, product, range]);

  const loadStats = useCallback(async () => {
    try {
      setStats(await apiRequest<LicenseStats>('licenses/stats'));
    } catch {
      /* stats are decorative */
    }
  }, []);

  const loadProducts = useCallback(async () => {
    try {
      setProducts((await apiRequest<{ items: LicenseProduct[] }>('licenses/products')).items);
    } catch {
      /* filter just stays empty */
    }
  }, []);

  const requestId = useRef(0);
  const loadLicenses = useCallback(async () => {
    const id = ++requestId.current;
    setLoading(true);
    try {
      const params = new URLSearchParams({ ...filters, page: String(page), per_page: String(perPage), orderby: sort.key, order: sort.dir });
      const data = await apiRequest<LicenseListResponse>(`licenses?${params}`);
      // Ignore answers to requests that a newer filter change replaced.
      if (id !== requestId.current) return;
      setItems(data.items);
      setTotal(data.total);
      setCounts(data.counts);
    } catch (err) {
      if (id === requestId.current) toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      if (id === requestId.current) setLoading(false);
    }
  }, [filters, page, perPage, sort, i18n.error]);

  useEffect(() => {
    loadStats();
    loadProducts();
  }, [loadStats, loadProducts]);

  useEffect(() => {
    loadLicenses();
  }, [loadLicenses]);

  useEffect(() => {
    setSelected(new Set());
  }, [filters, page, perPage, sort]);

  // Old edit/report URLs (and links from other screens) open the matching sheet.
  useEffect(() => {
    const pageParam = new URLSearchParams(window.location.search).get('page');
    if (!licenseId) return;
    if (pageParam === 'ls-licensesender-edit') setEditId(licenseId);
    else if (pageParam === 'ls-licensesender-report') setReportId(licenseId);
    else setDetailsId(licenseId);
  }, [licenseId]);

  const refreshAll = () => {
    loadStats();
    loadProducts();
    loadLicenses();
    setReloadToken((t) => t + 1);
  };

  const resetFilters = () => {
    setSearchInput('');
    setSearch('');
    setProduct('all');
    setRange('all');
    setDateFrom('');
    setDateTo('');
    setStatus('all');
    setPage(1);
  };

  const toggleSort = (key: SortKey) => {
    setSort((s) => (s.key === key ? { key, dir: s.dir === 'asc' ? 'desc' : 'asc' } : { key, dir: key === 'created_at' || key === 'id' ? 'desc' : 'asc' }));
    setPage(1);
  };

  const copyText = async (text: string, message?: string) => {
    try {
      await navigator.clipboard.writeText(text);
      toast.success(message || i18n.copied || 'Copied!');
    } catch {
      toast.error(i18n.error);
    }
  };

  const runBulk = async (action: 'resend_email' | 'sync', ids: number[]) => {
    setBulkBusy(true);
    try {
      const result = await apiRequest<BulkResult>('licenses/bulk', { method: 'POST', body: JSON.stringify({ action, ids }) });
      if (result.done > 0) toast.success(result.message);
      if (result.failed > 0) {
        toast.error(result.errors.join('\n') || i18n.error, { duration: 8000 });
      }
      refreshAll();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setBulkBusy(false);
    }
  };

  const exportCsv = async (onlySelected = false) => {
    try {
      const rows = onlySelected
        ? items.filter((i) => selected.has(i.id))
        : (await apiRequest<{ items: LicenseItem[] }>(`licenses/export?${new URLSearchParams(filters)}`)).items;
      if (!rows.length) {
        toast.error(i18n.nothingToExport || 'Nothing to export.');
        return;
      }
      downloadFile(`license-keys-${new Date().toISOString().slice(0, 10)}.csv`, toCsv(rows));
      toast.success((i18n.exported || '%d keys exported.').replace('%d', String(rows.length)));
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    }
  };

  const selectedItems = items.filter((i) => selected.has(i.id));
  const allOnPage = items.length > 0 && items.every((i) => selected.has(i.id));
  const someOnPage = items.some((i) => selected.has(i.id));
  const totalPages = Math.max(1, Math.ceil(total / perPage));
  const from = total === 0 ? 0 : (page - 1) * perPage + 1;
  const to = Math.min(page * perPage, total);

  const isRevealed = (id: number) => revealAll || revealed.has(id);
  const toggleReveal = (id: number) =>
    setRevealed((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });

  const SortHead = ({ k, children, className }: { k: SortKey; children: React.ReactNode; className?: string }) => (
    <TableHead className={cn('ls-table-head', className)}>
      <button type="button" onClick={() => toggleSort(k)} className="inline-flex items-center gap-1 font-inherit hover:text-slate-900">
        {children}
        {sort.key === k ? (
          sort.dir === 'asc' ? <ArrowUp className="h-3.5 w-3.5" /> : <ArrowDown className="h-3.5 w-3.5" />
        ) : (
          <ArrowUpDown className="h-3.5 w-3.5 opacity-40" />
        )}
      </button>
    </TableHead>
  );

  const statCards = [
    { label: i18n.totalKeys || 'Total keys', value: stats?.total, icon: KeyRound, tone: 'bg-indigo-50 text-indigo-600', onClick: () => setStatus('all') },
    { label: i18n.notEmailedKeys || 'Not emailed', value: counts.pending, icon: MailWarning, tone: 'bg-amber-50 text-amber-600', onClick: () => setStatus('pending'), hint: i18n.filtered || 'in current filter' },
    { label: i18n.orders || 'Orders', value: stats?.orders, icon: ShoppingCart, tone: 'bg-sky-50 text-sky-600' },
    { label: i18n.customers || 'Customers', value: stats?.emails, icon: Users, tone: 'bg-emerald-50 text-emerald-600' },
  ];

  const pageNumbers = useMemo(() => {
    const pages = new Set([1, totalPages, page - 1, page, page + 1].filter((n) => n >= 1 && n <= totalPages));
    return [...pages].sort((a, b) => a - b);
  }, [page, totalPages]);

  return (
    <div className="mx-auto max-w-[1400px] space-y-6 pb-8">
      <PageHeader
        title={i18n.title || 'License Keys'}
        subtitle={i18n.subtitle || 'Every key delivered to your customers.'}
        actions={
          <>
            <StockCheckDialog />
            <Button variant="outline" size="sm" className="bg-white" onClick={() => exportCsv(false)}>
              <Download className="h-4 w-4" />
              {i18n.exportCsv || 'Export CSV'}
            </Button>
            <Button variant="outline" size="sm" className="bg-white" onClick={refreshAll} disabled={loading}>
              <RefreshCw className={cn('h-4 w-4', loading && 'animate-spin')} />
              {i18n.refresh || 'Refresh'}
            </Button>
          </>
        }
      />

      <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        {statCards.map((c) => {
          const body = (
            <CardContent className="flex items-center gap-3 p-4 sm:gap-4 sm:p-5">
              <span className={cn('hidden h-11 w-11 shrink-0 items-center justify-center rounded-xl sm:flex', c.tone)}>
                <c.icon className="h-5 w-5" />
              </span>
              <div className="min-w-0 text-left">
                <p className="text-sm font-medium text-slate-500">{c.label}</p>
                {c.value === undefined ? <Skeleton className="mt-1 h-7 w-16" /> : <p className="text-2xl font-semibold tracking-tight text-slate-900">{c.value.toLocaleString()}</p>}
              </div>
            </CardContent>
          );
          return (
            <Card key={c.label} className={cn('border-slate-200 shadow-sm', c.onClick && 'transition-shadow hover:border-slate-300 hover:shadow-md')}>
              {c.onClick ? (
                <button type="button" className="block w-full rounded-[inherit] focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" onClick={() => { c.onClick?.(); setPage(1); }}>
                  {body}
                </button>
              ) : (
                body
              )}
            </Card>
          );
        })}
      </div>

      <Card className="overflow-hidden border-slate-200 shadow-sm">
        <CardContent className="p-0">
          {/* Status tabs */}
          <div className="flex gap-1 overflow-x-auto overflow-y-hidden border-b border-slate-200 px-4 pt-3">
            {(
              [
                ['all', i18n.all || 'All', counts.all, KeyRound],
                ['pending', i18n.notEmailed || 'Not emailed', counts.pending, MailWarning],
                ['sent', i18n.emailed || 'Emailed', counts.sent, MailCheck],
              ] as const
            ).map(([value, label, count, Icon]) => (
              <button
                key={value}
                type="button"
                onClick={() => { setStatus(value); setPage(1); }}
                className={cn(
                  '-mb-px inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-3 pb-3 pt-1 text-sm font-medium transition-colors',
                  status === value ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800'
                )}
              >
                <Icon className="h-4 w-4" />
                {label}
                <span className={cn('rounded-full px-1.5 py-0.5 text-xs', status === value ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600')}>{count.toLocaleString()}</span>
              </button>
            ))}
          </div>

          {/* Filters */}
          <div className="flex flex-col gap-3 border-b border-slate-200 bg-white px-4 py-3 lg:flex-row lg:items-center">
            <div className="relative min-w-0 flex-1 lg:max-w-sm">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <Input
                className="border-slate-200 bg-slate-50 pl-9 pr-8"
                placeholder={i18n.searchPlaceholder || 'Search key, order, email, SKU…'}
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
              />
              {searchInput ? (
                <button type="button" className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-slate-400 hover:text-slate-700" onClick={() => setSearchInput('')} aria-label="Clear search">
                  <X className="h-3.5 w-3.5" />
                </button>
              ) : null}
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <Select value={product} onValueChange={(v) => { setProduct(v); setPage(1); }}>
                <SelectTrigger className="h-9 w-full bg-white sm:w-[220px]">
                  <Package className="mr-2 h-4 w-4 shrink-0 text-slate-400" />
                  <SelectValue placeholder={i18n.allProducts || 'All products'} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{i18n.allProducts || 'All products'}</SelectItem>
                  {products.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)}>
                      {productLabel({ product_id: p.id, product_name: p.name })} ({p.count})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Select value={range} onValueChange={(v) => { setRange(v as Range); setPage(1); }}>
                <SelectTrigger className="h-9 w-full bg-white sm:w-[160px]">
                  <Filter className="mr-2 h-4 w-4 shrink-0 text-slate-400" />
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{i18n.allTime || 'All time'}</SelectItem>
                  <SelectItem value="today">{i18n.today || 'Today'}</SelectItem>
                  <SelectItem value="7">{i18n.last7 || 'Last 7 days'}</SelectItem>
                  <SelectItem value="30">{i18n.last30 || 'Last 30 days'}</SelectItem>
                  <SelectItem value="90">{i18n.last90 || 'Last 90 days'}</SelectItem>
                  <SelectItem value="custom">{i18n.customRange || 'Custom range…'}</SelectItem>
                </SelectContent>
              </Select>
              {range === 'custom' ? (
                <div className="flex items-center gap-2">
                  <DatePicker value={dateFrom} onChange={(v) => { setDateFrom(v); setPage(1); }} placeholder={i18n.from || 'From'} className="w-[150px]" />
                  <span className="text-slate-400">–</span>
                  <DatePicker value={dateTo} onChange={(v) => { setDateTo(v); setPage(1); }} placeholder={i18n.to || 'To'} className="w-[150px]" />
                </div>
              ) : null}
              {activeFilterCount > 0 ? (
                <Button variant="ghost" size="sm" onClick={resetFilters} className="text-slate-500">
                  <X className="h-4 w-4" /> {i18n.clearFilters || 'Clear filters'}
                </Button>
              ) : null}
            </div>
            <div className="lg:ml-auto">
              <Button variant="ghost" size="sm" onClick={() => setRevealAll((v) => !v)} className="text-slate-600">
                {revealAll ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                {revealAll ? i18n.hideKeys || 'Hide keys' : i18n.showKeys || 'Show keys'}
              </Button>
            </div>
          </div>

          {/* Bulk bar */}
          {selected.size > 0 ? (
            <div className="flex flex-wrap items-center gap-2 border-b border-indigo-100 bg-indigo-50/70 px-4 py-2.5">
              <span className="mr-2 text-sm font-medium text-indigo-900">
                {(i18n.selectedCount || '%d selected').replace('%d', String(selected.size))}
              </span>
              <Button size="sm" variant="outline" className="bg-white" onClick={() => copyText(selectedItems.map((i) => i.license_key).join('\n'), (i18n.keysCopied || '%d keys copied.').replace('%d', String(selectedItems.length)))}>
                <Copy className="h-4 w-4" /> {i18n.copyKeys || 'Copy keys'}
              </Button>
              <Button size="sm" variant="outline" className="bg-white" onClick={() => exportCsv(true)}>
                <FileText className="h-4 w-4" /> {i18n.exportSelected || 'Export'}
              </Button>
              <Button size="sm" variant="outline" className="bg-white" disabled={bulkBusy} onClick={() => setConfirmResend([...selected])}>
                <Mail className="h-4 w-4" /> {i18n.resendEmails || 'Resend emails'}
              </Button>
              <Button size="sm" variant="outline" className="bg-white" disabled={bulkBusy} onClick={() => runBulk('sync', [...selected])}>
                <RefreshCw className={cn('h-4 w-4', bulkBusy && 'animate-spin')} /> {i18n.syncFromApi || 'Sync from API'}
              </Button>
              <Button size="sm" variant="ghost" className="ml-auto text-slate-600" onClick={() => setSelected(new Set())}>
                {i18n.clearSelection || 'Clear selection'}
              </Button>
            </div>
          ) : null}

          <Table>
            <TableHeader>
              <TableRow className="bg-slate-50/80 hover:bg-slate-50/80">
                <TableHead className="w-10 pl-4">
                  <Checkbox
                    checked={allOnPage ? true : someOnPage ? 'indeterminate' : false}
                    onCheckedChange={(v) => setSelected(v ? new Set(items.map((i) => i.id)) : new Set())}
                    aria-label="Select all on this page"
                  />
                </TableHead>
                <SortHead k="key_value">{i18n.licenseKey || 'License key'}</SortHead>
                <SortHead k="product_id">{i18n.product || 'Product'}</SortHead>
                <SortHead k="order_id">{i18n.order || 'Order'}</SortHead>
                <SortHead k="email" className="hidden md:table-cell">{i18n.customer || 'Customer'}</SortHead>
                <SortHead k="email_sent" className="hidden lg:table-cell">{i18n.emailStatus || 'Email'}</SortHead>
                <SortHead k="created_at" className="hidden sm:table-cell">{i18n.soldAt || 'Sold'}</SortHead>
                <TableHead className="ls-table-head w-12 pr-4 text-right">
                  <span className="sr-only">{i18n.actions || 'Actions'}</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {loading && items.length === 0 ? (
                Array.from({ length: 6 }).map((_, i) => (
                  <TableRow key={i}>
                    {Array.from({ length: 8 }).map((__, j) => (
                      <TableCell key={j} className={cn(j === 0 && 'pl-4', j > 3 && j < 7 && 'hidden md:table-cell')}>
                        <Skeleton className="h-4 w-full" />
                      </TableCell>
                    ))}
                  </TableRow>
                ))
              ) : items.length === 0 ? (
                <TableRow className="hover:bg-transparent">
                  <TableCell colSpan={8} className="py-16 text-center">
                    <KeyRound className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-3 text-base font-medium text-slate-900">
                      {activeFilterCount || status !== 'all' ? i18n.noMatches || 'No keys match these filters' : i18n.noKeys || 'No license keys yet'}
                    </p>
                    <p className="mt-1 text-sm text-slate-500">
                      {activeFilterCount || status !== 'all' ? (
                        <button type="button" className="font-medium text-indigo-600 hover:underline" onClick={resetFilters}>
                          {i18n.clearFilters || 'Clear filters'}
                        </button>
                      ) : (
                        i18n.empty || 'Keys appear here after customers buy LicenseSender products.'
                      )}
                    </p>
                  </TableCell>
                </TableRow>
              ) : (
                items.map((item) => (
                  <TableRow key={item.id} data-state={selected.has(item.id) ? 'selected' : undefined} className={cn('bg-white', loading && 'opacity-60')}>
                    <TableCell className="pl-4">
                      <Checkbox
                        checked={selected.has(item.id)}
                        onCheckedChange={(v) =>
                          setSelected((prev) => {
                            const next = new Set(prev);
                            if (v) next.add(item.id);
                            else next.delete(item.id);
                            return next;
                          })
                        }
                        aria-label={`Select key ${item.id}`}
                      />
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-1">
                        <button type="button" onClick={() => setDetailsId(item.id)} className="rounded font-mono text-[13px] font-medium text-slate-900 hover:text-indigo-600" title={i18n.viewDetails || 'View details'}>
                          {isRevealed(item.id) ? item.license_key : maskKey(item.license_key)}
                        </button>
                        <Button variant="ghost" size="icon" className="h-7 w-7 text-slate-400 hover:text-slate-700" onClick={() => toggleReveal(item.id)} aria-label={isRevealed(item.id) ? 'Hide key' : 'Show key'}>
                          {isRevealed(item.id) ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                        </Button>
                        <Button variant="ghost" size="icon" className="h-7 w-7 text-slate-400 hover:text-slate-700" onClick={() => copyText(item.license_key)} aria-label={i18n.copy || 'Copy'}>
                          <Copy className="h-3.5 w-3.5" />
                        </Button>
                      </div>
                    </TableCell>
                    <TableCell className="max-w-[240px]">
                      <div className="truncate font-medium text-slate-800" title={productLabel(item)}>{productLabel(item)}</div>
                      {item.sku ? <div className="truncate font-mono text-xs text-slate-400">{item.sku}</div> : null}
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-col items-start gap-1">
                        {item.order_link ? (
                          <a href={item.order_link} className="font-medium text-indigo-600 no-underline hover:underline">#{item.order_number}</a>
                        ) : (
                          <span className="text-slate-500">#{item.order_id}</span>
                        )}
                        {item.order_status ? (
                          <Badge className={cn('whitespace-nowrap border-0 px-1.5 py-0 text-[11px] font-medium capitalize', orderStatusTone[item.order_status] || 'bg-slate-100 text-slate-600')}>
                            {item.order_status.replace('-', ' ')}
                          </Badge>
                        ) : null}
                      </div>
                    </TableCell>
                    <TableCell className="hidden max-w-[220px] md:table-cell">
                      <span className="block truncate text-slate-600" title={item.email}>{item.email || '—'}</span>
                    </TableCell>
                    <TableCell className="hidden lg:table-cell">
                      <Badge className={cn('gap-1 whitespace-nowrap border-0 font-medium', item.email_sent ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700')}>
                        {item.email_sent ? <MailCheck className="h-3 w-3" /> : <MailWarning className="h-3 w-3" />}
                        {item.email_sent ? i18n.emailed || 'Emailed' : i18n.notEmailed || 'Not emailed'}
                      </Badge>
                    </TableCell>
                    <TableCell className="hidden whitespace-nowrap text-sm text-slate-500 sm:table-cell">{formatDate(item.sold_at)}</TableCell>
                    <TableCell className="pr-4 text-right">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon" className="h-8 w-8" aria-label={i18n.actions || 'Actions'}>
                            <MoreHorizontal className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-52">
                          <DropdownMenuLabel className="truncate text-xs font-normal text-slate-500">{maskKey(item.license_key)}</DropdownMenuLabel>
                          <DropdownMenuItem onClick={() => setDetailsId(item.id)}>
                            <KeyRound className="mr-2 h-4 w-4" /> {i18n.viewDetails || 'View details'}
                          </DropdownMenuItem>
                          <DropdownMenuItem onClick={() => copyText(item.license_key)}>
                            <Copy className="mr-2 h-4 w-4" /> {i18n.copyKey || 'Copy key'}
                          </DropdownMenuItem>
                          <DropdownMenuItem onClick={() => setEditId(item.id)}>
                            <Pencil className="mr-2 h-4 w-4" /> {i18n.edit || 'Edit'}
                          </DropdownMenuItem>
                          <DropdownMenuSeparator />
                          <DropdownMenuItem onClick={() => setConfirmResend([item.id])}>
                            <Mail className="mr-2 h-4 w-4" /> {i18n.resendEmail || 'Resend email'}
                          </DropdownMenuItem>
                          <DropdownMenuItem onClick={() => runBulk('sync', [item.id])}>
                            <RefreshCw className="mr-2 h-4 w-4" /> {i18n.syncFromApi || 'Sync from API'}
                          </DropdownMenuItem>
                          <DropdownMenuItem onClick={() => setReportId(item.id)}>
                            <Flag className="mr-2 h-4 w-4" /> {i18n.report || 'Report / replace'}
                          </DropdownMenuItem>
                          {item.order_link ? (
                            <>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem asChild>
                                <a href={item.order_link}>
                                  <ExternalLink className="mr-2 h-4 w-4" /> {i18n.openOrder || 'Open order'}
                                </a>
                              </DropdownMenuItem>
                            </>
                          ) : null}
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>

          {/* Pagination */}
          <div className="flex flex-col gap-3 border-t border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-3">
              <span>
                {(i18n.showing || 'Showing %1$s–%2$s of %3$s').replace('%1$s', from.toLocaleString()).replace('%2$s', to.toLocaleString()).replace('%3$s', total.toLocaleString())}
              </span>
              <Select value={String(perPage)} onValueChange={(v) => { setPerPage(Number(v)); setPage(1); }}>
                <SelectTrigger className="h-8 w-[110px] bg-white">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {PAGE_SIZES.map((n) => (
                    <SelectItem key={n} value={String(n)}>
                      {n} / {i18n.page || 'page'}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex items-center gap-1">
              <Button variant="outline" size="icon" className="h-8 w-8 bg-white" disabled={page <= 1} onClick={() => setPage((p) => p - 1)} aria-label="Previous page">
                <ChevronLeft className="h-4 w-4" />
              </Button>
              {pageNumbers.map((n, i) => (
                <span key={n} className="flex items-center gap-1">
                  {i > 0 && n - pageNumbers[i - 1] > 1 ? <span className="px-1 text-slate-400">…</span> : null}
                  <Button variant={n === page ? 'default' : 'outline'} size="icon" className={cn('h-8 w-8', n !== page && 'bg-white')} onClick={() => setPage(n)}>
                    {n}
                  </Button>
                </span>
              ))}
              <Button variant="outline" size="icon" className="h-8 w-8 bg-white" disabled={page >= totalPages} onClick={() => setPage((p) => p + 1)} aria-label="Next page">
                <ChevronRight className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <AlertDialog open={confirmResend !== null} onOpenChange={(v) => !v && setConfirmResend(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{i18n.resendConfirmTitle || 'Resend license emails?'}</AlertDialogTitle>
            <AlertDialogDescription>
              {(i18n.resendConfirmDesc || 'Customers of the %d selected order(s) will get their license email again, with all keys from the order.').replace(
                '%d',
                String(new Set(items.filter((i) => confirmResend?.includes(i.id)).map((i) => i.order_id)).size || confirmResend?.length || 0)
              )}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{i18n.cancel || 'Cancel'}</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                if (confirmResend) runBulk('resend_email', confirmResend);
                setConfirmResend(null);
              }}
            >
              {i18n.resendEmails || 'Resend emails'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <LicenseDetailsSheet
        licenseId={detailsId}
        open={detailsId !== null}
        onOpenChange={(open) => !open && setDetailsId(null)}
        onEdit={(id) => { setDetailsId(null); setEditId(id); }}
        onReport={(id) => { setDetailsId(null); setReportId(id); }}
        onBulk={runBulk}
        reloadToken={reloadToken}
      />

      <LicenseEditSheet
        licenseId={editId}
        open={editId !== null}
        onOpenChange={(open) => { if (!open) setEditId(null); }}
        onSaved={() => { refreshAll(); setEditId(null); }}
      />

      <LicenseReportSheet
        licenseId={reportId}
        open={reportId !== null}
        onOpenChange={(open) => { if (!open) setReportId(null); }}
        onSaved={() => { refreshAll(); setReportId(null); }}
      />
    </div>
  );
}
