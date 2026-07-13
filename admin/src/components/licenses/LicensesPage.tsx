import { useCallback, useEffect, useState } from 'react';
import {
  Clipboard,
  Key,
  Mail,
  MoreHorizontal,
  Package,
  Pencil,
  RefreshCw,
  Search,
  ShoppingCart,
} from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { getBootstrap } from '@/lib/bootstrap';
import { PageHeader } from '@/components/shared/PageHeader';
import { StatCard } from '@/components/licenses/StatCard';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
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
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { LicenseEditSheet } from '@/components/licenses/LicenseEditSheet';
import { LicenseReportSheet } from '@/components/licenses/LicenseReportSheet';

interface LicenseItem {
  id: number;
  license_key: string;
  order_id: number;
  order_link: string;
  product_id: number;
  product_name: string;
  product_link: string;
  sku: string;
  email: string;
  sold_at: string;
}

interface LicenseStats {
  total: number;
  orders: number;
  products: number;
  emails: number;
}

interface LicenseListResponse {
  items: LicenseItem[];
  total: number;
  page: number;
  per_page: number;
}

export function LicensesPage() {
  const { i18n, licenseId } = getBootstrap();
  const [stats, setStats] = useState<LicenseStats | null>(null);
  const [items, setItems] = useState<LicenseItem[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [perPage] = useState(25);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [editId, setEditId] = useState<number | null>(licenseId || null);
  const [reportId, setReportId] = useState<number | null>(null);

  const loadStats = useCallback(async () => {
    const data = await apiRequest<LicenseStats>('licenses/stats');
    setStats(data);
  }, []);

  const loadLicenses = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        page: String(page),
        per_page: String(perPage),
        search,
      });
      const data = await apiRequest<LicenseListResponse>(`licenses?${params}`);
      setItems(data.items);
      setTotal(data.total);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setLoading(false);
    }
  }, [page, perPage, search, i18n.error]);

  useEffect(() => {
    loadStats().catch(() => undefined);
  }, [loadStats]);

  useEffect(() => {
    loadLicenses().catch(() => undefined);
  }, [loadLicenses]);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const pageParam = params.get('page');
    const searchParam = params.get('ls_search');
    if (searchParam) {
      setSearch(searchParam);
      setPage(1);
    }
    if (pageParam === 'ls-licensesender-edit' && licenseId) {
      setEditId(licenseId);
    }
    if (pageParam === 'ls-licensesender-report' && licenseId) {
      setReportId(licenseId);
    }
  }, [licenseId]);

  const copyKey = async (key: string) => {
    try {
      await navigator.clipboard.writeText(key);
      toast.success(i18n.copied || 'Copied!');
    } catch {
      toast.error(i18n.error);
    }
  };

  const exportCsv = () => {
    const header = ['License Key', 'Order ID', 'Product', 'SKU', 'Email', 'Sold Date'];
    const rows = items.map((item) => [
      item.license_key,
      item.order_id,
      item.product_name,
      item.sku,
      item.email,
      item.sold_at,
    ]);
    const csv = [header, ...rows].map((row) => row.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'license-keys.csv';
    a.click();
    URL.revokeObjectURL(url);
  };

  const totalPages = Math.max(1, Math.ceil(total / perPage));
  const showingFrom = total === 0 ? 0 : (page - 1) * perPage + 1;
  const showingTo = Math.min(page * perPage, total);

  return (
    <div className="mx-auto max-w-[1400px] space-y-6 pb-8">
      <PageHeader
        title={i18n.title || 'License Keys'}
        subtitle={i18n.subtitle}
        actions={
          <>
            <Button variant="outline" size="sm" className="bg-white" onClick={() => { loadStats(); loadLicenses(); }}>
              <RefreshCw className="h-4 w-4" />
              {i18n.refresh || 'Refresh'}
            </Button>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="bg-white">{i18n.export || 'Export'}</Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem onClick={exportCsv}>Export CSV</DropdownMenuItem>
                <DropdownMenuItem onClick={() => items.forEach((i) => copyKey(i.license_key))}>
                  Copy visible keys
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </>
        }
      />

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {stats ? (
          <>
            <StatCard label={i18n.totalKeys || 'Total Keys'} value={stats.total.toLocaleString()} icon={Key} tone="indigo" />
            <StatCard label={i18n.orders || 'Orders'} value={stats.orders.toLocaleString()} icon={ShoppingCart} tone="emerald" />
            <StatCard label={i18n.products || 'Products'} value={stats.products.toLocaleString()} icon={Package} tone="amber" />
            <StatCard label={i18n.emails || 'Unique Emails'} value={stats.emails.toLocaleString()} icon={Mail} tone="sky" />
          </>
        ) : (
          Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-[92px] w-full rounded-xl" />)
        )}
      </div>

      <Card className="overflow-hidden border-slate-200 shadow-sm">
        <CardContent className="space-y-5 p-0">
          <div className="border-b bg-white px-6 py-4">
            <div className="relative max-w-md">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                className="border-slate-200 bg-slate-50 pl-9"
                placeholder={i18n.search || 'Search…'}
                value={search}
                onChange={(e) => { setSearch(e.target.value); setPage(1); }}
              />
            </div>
          </div>

          <div className="px-2 pb-2">
            <Table>
              <TableHeader>
                <TableRow className="hover:bg-transparent">
                  <TableHead className="ls-table-head">License Key</TableHead>
                  <TableHead className="ls-table-head">Order</TableHead>
                  <TableHead className="ls-table-head">Product</TableHead>
                  <TableHead className="ls-table-head">SKU</TableHead>
                  <TableHead className="ls-table-head">Email</TableHead>
                  <TableHead className="ls-table-head">Sold Date</TableHead>
                  <TableHead className="ls-table-head text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {loading ? (
                  Array.from({ length: 5 }).map((_, i) => (
                    <TableRow key={i}>
                      {Array.from({ length: 7 }).map((__, j) => (
                        <TableCell key={j}><Skeleton className="h-4 w-full" /></TableCell>
                      ))}
                    </TableRow>
                  ))
                ) : items.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={7} className="py-16 text-center">
                      <p className="text-base font-medium text-foreground">No license keys yet</p>
                      <p className="mt-1 text-sm text-muted-foreground">{i18n.empty}</p>
                    </TableCell>
                  </TableRow>
                ) : (
                  items.map((item) => (
                    <TableRow key={item.id} className="bg-white">
                      <TableCell>
                        <div className="flex items-center gap-2">
                          <span className="ls-license-key">{item.license_key}</span>
                          <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => copyKey(item.license_key)}>
                            <Clipboard className="h-4 w-4" />
                          </Button>
                        </div>
                      </TableCell>
                      <TableCell>
                        {item.order_link ? (
                          <a href={item.order_link} className="font-medium text-primary hover:underline">#{item.order_id}</a>
                        ) : (
                          item.order_id || '—'
                        )}
                      </TableCell>
                      <TableCell>
                        <div className="max-w-[280px] font-medium leading-snug">{item.product_name || '—'}</div>
                        {item.product_id ? <div className="text-xs text-muted-foreground">#{item.product_id}</div> : null}
                      </TableCell>
                      <TableCell className="font-mono text-xs text-slate-600">{item.sku || '—'}</TableCell>
                      <TableCell className="text-muted-foreground">{item.email || '—'}</TableCell>
                      <TableCell className="whitespace-nowrap text-sm text-slate-600">{item.sold_at || '—'}</TableCell>
                      <TableCell className="text-right">
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="h-8 w-8"><MoreHorizontal className="h-4 w-4" /></Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem onClick={() => setEditId(item.id)}>
                              <Pencil className="mr-2 h-4 w-4" /> {i18n.edit || 'Edit'}
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => setReportId(item.id)}>
                              <RefreshCw className="mr-2 h-4 w-4" /> {i18n.report || 'Report Key'}
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </div>

          <div className="flex flex-col gap-3 border-t bg-slate-50 px-6 py-4 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
            <span>Showing {showingFrom}–{showingTo} of {total}</span>
            <div className="flex gap-2">
              <Button variant="outline" size="sm" className="bg-white" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                Previous
              </Button>
              <Button variant="outline" size="sm" className="bg-white" disabled={page >= totalPages} onClick={() => setPage((p) => p + 1)}>
                Next
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <LicenseEditSheet
        licenseId={editId}
        open={editId !== null}
        onOpenChange={(open) => { if (!open) setEditId(null); }}
        onSaved={() => { loadLicenses(); setEditId(null); }}
      />

      <LicenseReportSheet
        licenseId={reportId}
        open={reportId !== null}
        onOpenChange={(open) => { if (!open) setReportId(null); }}
        onSaved={() => { loadLicenses(); setReportId(null); }}
      />
    </div>
  );
}
