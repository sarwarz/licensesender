import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  CircleAlert,
  CircleCheck,
  CircleDashed,
  Copy,
  Download,
  ExternalLink,
  Link2,
  MoreHorizontal,
  Pencil,
  Plus,
  Radar,
  Search,
  Trash2,
  X,
} from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { getBootstrap } from '@/lib/bootstrap';
import { cn } from '@/lib/utils';
import { PageHeader } from '@/components/shared/PageHeader';
import { CoverageCard, type Coverage } from '@/components/shared/CoverageCard';
import { ProductSearchSelect, type ProductOption } from '@/components/shared/ProductSearchSelect';
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
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface DownloadLink {
  id: number;
  product_id: number;
  product_name: string;
  product_found: boolean;
  edit_url: string;
  link: string;
  host: string;
  created_at: string;
}

interface CheckResult {
  id: number;
  ok: boolean;
  status: number;
  message: string;
}

type Filter = 'all' | 'broken' | 'orphan';

const isUrl = (value: string) => /^https?:\/\/[^\s/$.?#].[^\s]*$/i.test(value.trim());

function formatDate(value: string) {
  const d = new Date(value.replace(' ', 'T'));
  return Number.isNaN(d.getTime()) ? value : d.toLocaleDateString(undefined, { dateStyle: 'medium' });
}

export function DownloadLinksPage() {
  const { i18n } = getBootstrap();
  const [items, setItems] = useState<DownloadLink[]>([]);
  const [coverage, setCoverage] = useState<Coverage | null>(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState<Filter>('all');
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [checks, setChecks] = useState<Record<number, CheckResult | 'checking'>>({});

  const [dialogOpen, setDialogOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({ id: 0, link: '' });
  const [product, setProduct] = useState<ProductOption | null>(null);
  const [confirmDelete, setConfirmDelete] = useState<number[] | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [list, cov] = await Promise.all([
        apiRequest<{ items: DownloadLink[] }>('download-links'),
        apiRequest<Coverage>('download-links/coverage').catch(() => null),
      ]);
      setItems(list.items);
      setCoverage(cov);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setLoading(false);
    }
  }, [i18n.error]);

  useEffect(() => {
    load();
  }, [load]);

  // ?action=add&product_id=… opens the form (used by links from other screens).
  useEffect(() => {
    const p = new URLSearchParams(window.location.search);
    if (p.get('action') === 'add') openCreate();
  }, []);

  const broken = items.filter((i) => {
    const c = checks[i.id];
    return c && c !== 'checking' && !c.ok;
  });

  const visible = useMemo(() => {
    const q = search.trim().toLowerCase();
    return items.filter((i) => {
      if (filter === 'broken') {
        const c = checks[i.id];
        if (!c || c === 'checking' || c.ok) return false;
      }
      if (filter === 'orphan' && i.product_found) return false;
      return !q || i.product_name.toLowerCase().includes(q) || i.link.toLowerCase().includes(q) || String(i.product_id) === q;
    });
  }, [items, search, filter, checks]);

  const orphans = items.filter((i) => !i.product_found).length;

  const openCreate = (preset?: { id: number; name: string }) => {
    setForm({ id: 0, link: '' });
    setProduct(preset ? { id: preset.id, name: preset.name } : null);
    setDialogOpen(true);
  };

  const openEdit = (item: DownloadLink) => {
    setForm({ id: item.id, link: item.link });
    setProduct({ id: item.product_id, name: item.product_name });
    setDialogOpen(true);
  };

  const save = async () => {
    if (!product) {
      toast.error('Please select a product.');
      return;
    }
    if (!isUrl(form.link)) {
      toast.error('Enter a full URL starting with http:// or https://');
      return;
    }
    setSaving(true);
    try {
      const payload = { id: form.id || undefined, product_id: product.id, link: form.link.trim() };
      if (form.id) {
        await apiRequest(`download-links/${form.id}`, { method: 'PUT', body: JSON.stringify(payload) });
      } else {
        await apiRequest('download-links', { method: 'POST', body: JSON.stringify(payload) });
      }
      toast.success(form.id ? 'Download link updated.' : 'Download link added.');
      setDialogOpen(false);
      setChecks((c) => {
        const next = { ...c };
        delete next[form.id];
        return next;
      });
      load();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setSaving(false);
    }
  };

  const remove = async (ids: number[]) => {
    try {
      const result = await apiRequest<{ message: string }>('download-links/bulk-delete', { method: 'POST', body: JSON.stringify({ ids }) });
      toast.success(result.message);
      setSelected(new Set());
      load();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    }
  };

  const check = async (ids: number[]) => {
    if (!ids.length) return;
    setChecks((c) => ({ ...c, ...Object.fromEntries(ids.map((id) => [id, 'checking' as const])) }));
    // The server checks up to 25 links per request.
    let bad = 0;
    for (let i = 0; i < ids.length; i += 25) {
      const chunk = ids.slice(i, i + 25);
      try {
        const { items: results } = await apiRequest<{ items: CheckResult[] }>('download-links/check', { method: 'POST', body: JSON.stringify({ ids: chunk }) });
        bad += results.filter((r) => !r.ok).length;
        setChecks((c) => ({ ...c, ...Object.fromEntries(results.map((r) => [r.id, r])) }));
      } catch (err) {
        setChecks((c) => {
          const next = { ...c };
          chunk.forEach((id) => delete next[id]);
          return next;
        });
        toast.error(err instanceof Error ? err.message : i18n.error);
        return;
      }
    }
    if (bad) toast.error(`${bad} link${bad === 1 ? '' : 's'} not reachable.`);
    else toast.success(ids.length === 1 ? 'Link is working.' : `All ${ids.length} links are working.`);
  };

  const copy = async (text: string) => {
    await navigator.clipboard.writeText(text);
    toast.success('Link copied.');
  };

  const allSelected = visible.length > 0 && visible.every((i) => selected.has(i.id));
  const someSelected = visible.some((i) => selected.has(i.id));
  const checking = Object.values(checks).includes('checking');

  const statusCell = (item: DownloadLink) => {
    const c = checks[item.id];
    if (c === 'checking') {
      return (
        <span className="inline-flex items-center gap-1.5 text-xs text-slate-500">
          <Radar className="h-3.5 w-3.5 animate-pulse" /> Checking…
        </span>
      );
    }
    if (!c) {
      return (
        <span className="inline-flex items-center gap-1.5 text-xs text-slate-400">
          <CircleDashed className="h-3.5 w-3.5" /> Not checked
        </span>
      );
    }
    return c.ok ? (
      <Badge className="gap-1 whitespace-nowrap border-0 bg-emerald-50 font-medium text-emerald-700">
        <CircleCheck className="h-3 w-3" /> Working · {c.status}
      </Badge>
    ) : (
      <Badge className="gap-1 whitespace-nowrap border-0 bg-rose-50 font-medium text-rose-700" title={c.message}>
        <CircleAlert className="h-3 w-3" /> {c.status ? `Broken · ${c.status}` : 'Unreachable'}
      </Badge>
    );
  };

  return (
    <div className="mx-auto max-w-[1400px] space-y-6 pb-8">
      <PageHeader
        title="Download Links"
        subtitle="The download link customers see next to their license key."
        actions={
          <>
            <Button variant="outline" size="sm" className="bg-white" disabled={!items.length || checking} onClick={() => check(items.map((i) => i.id))}>
              <Radar className={cn('h-4 w-4', checking && 'animate-pulse')} />
              {checking ? 'Checking…' : 'Check all links'}
            </Button>
            <Button size="sm" onClick={() => openCreate()}>
              <Plus className="h-4 w-4" /> Add link
            </Button>
          </>
        }
      />

      <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        {[
          { label: 'Download links', value: items.length, tone: 'bg-indigo-50 text-indigo-600', icon: Link2 },
          { label: 'Products covered', value: coverage ? `${coverage.covered}/${coverage.enabled}` : '—', tone: 'bg-emerald-50 text-emerald-600', icon: Download },
          { label: 'Broken', value: Object.keys(checks).length ? broken.length : '—', tone: 'bg-rose-50 text-rose-600', icon: CircleAlert, hint: Object.keys(checks).length ? undefined : 'Run a check' },
          { label: 'Deleted products', value: orphans, tone: 'bg-amber-50 text-amber-600', icon: CircleDashed },
        ].map((s) => (
          <Card key={s.label} className="border-slate-200 shadow-sm">
            <CardContent className="flex items-center gap-3 p-4 sm:gap-4 sm:p-5">
              <span className={cn('hidden h-10 w-10 shrink-0 items-center justify-center rounded-xl sm:flex', s.tone)}>
                <s.icon className="h-5 w-5" />
              </span>
              <div className="min-w-0">
                <p className="text-sm font-medium text-slate-500">{s.label}</p>
                {loading ? <Skeleton className="mt-1 h-7 w-12" /> : <p className="text-2xl font-semibold tracking-tight text-slate-900">{s.value}</p>}
                {s.hint && !loading ? <p className="text-[11px] text-slate-400">{s.hint}</p> : null}
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      <CoverageCard coverage={coverage} noun="download link" onAdd={(p) => openCreate(p)} />

      <Card className="overflow-hidden border-slate-200 shadow-sm">
        <CardContent className="p-0">
          <div className="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center">
            <div className="relative flex-1 sm:max-w-sm">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <Input className="border-slate-200 bg-slate-50 pl-9 pr-8" placeholder="Search product or URL…" value={search} onChange={(e) => setSearch(e.target.value)} />
              {search ? (
                <button type="button" className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-slate-400 hover:text-slate-700" onClick={() => setSearch('')} aria-label="Clear search">
                  <X className="h-3.5 w-3.5" />
                </button>
              ) : null}
            </div>
            <div className="flex gap-1 overflow-x-auto">
              {(
                [
                  ['all', `All (${items.length})`],
                  ['broken', `Broken (${broken.length})`],
                  ['orphan', `Deleted product (${orphans})`],
                ] as const
              ).map(([value, label]) => (
                <Button
                  key={value}
                  size="sm"
                  variant={filter === value ? 'secondary' : 'ghost'}
                  className={cn('whitespace-nowrap', filter === value && 'bg-indigo-50 text-indigo-700 hover:bg-indigo-50')}
                  onClick={() => setFilter(value)}
                >
                  {label}
                </Button>
              ))}
            </div>
          </div>

          {selected.size > 0 ? (
            <div className="flex flex-wrap items-center gap-2 border-b border-indigo-100 bg-indigo-50/70 px-4 py-2.5">
              <span className="mr-2 text-sm font-medium text-indigo-900">{selected.size} selected</span>
              <Button size="sm" variant="outline" className="bg-white" disabled={checking} onClick={() => check([...selected])}>
                <Radar className="h-4 w-4" /> Check links
              </Button>
              <Button size="sm" variant="outline" className="bg-white text-rose-600 hover:text-rose-700" onClick={() => setConfirmDelete([...selected])}>
                <Trash2 className="h-4 w-4" /> Delete
              </Button>
              <Button size="sm" variant="ghost" className="ml-auto text-slate-600" onClick={() => setSelected(new Set())}>
                Clear selection
              </Button>
            </div>
          ) : null}

          <Table>
            <TableHeader>
              <TableRow className="bg-slate-50/80 hover:bg-slate-50/80">
                <TableHead className="w-10 pl-4">
                  <Checkbox
                    checked={allSelected ? true : someSelected ? 'indeterminate' : false}
                    onCheckedChange={(v) => setSelected(v ? new Set(visible.map((i) => i.id)) : new Set())}
                    aria-label="Select all"
                  />
                </TableHead>
                <TableHead className="ls-table-head">Product</TableHead>
                <TableHead className="ls-table-head">Link</TableHead>
                <TableHead className="ls-table-head hidden md:table-cell">Status</TableHead>
                <TableHead className="ls-table-head hidden lg:table-cell">Added</TableHead>
                <TableHead className="ls-table-head w-12 pr-4 text-right">
                  <span className="sr-only">Actions</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {loading ? (
                Array.from({ length: 4 }).map((_, i) => (
                  <TableRow key={i}>
                    {Array.from({ length: 6 }).map((__, j) => (
                      <TableCell key={j} className={cn(j === 0 && 'pl-4', j > 2 && 'hidden md:table-cell')}>
                        <Skeleton className="h-4 w-full" />
                      </TableCell>
                    ))}
                  </TableRow>
                ))
              ) : visible.length === 0 ? (
                <TableRow className="hover:bg-transparent">
                  <TableCell colSpan={6} className="py-16 text-center">
                    <Link2 className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-3 text-base font-medium text-slate-900">{items.length ? 'No links match' : 'No download links yet'}</p>
                    <p className="mt-1 text-sm text-slate-500">
                      {items.length ? 'Try another search or filter.' : 'Add one so customers can download the software with their key.'}
                    </p>
                    {!items.length ? (
                      <Button size="sm" className="mt-4" onClick={() => openCreate()}>
                        <Plus className="h-4 w-4" /> Add link
                      </Button>
                    ) : null}
                  </TableCell>
                </TableRow>
              ) : (
                visible.map((item) => (
                  <TableRow key={item.id} data-state={selected.has(item.id) ? 'selected' : undefined} className="bg-white">
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
                        aria-label={`Select link ${item.id}`}
                      />
                    </TableCell>
                    <TableCell className="max-w-[260px]">
                      {item.edit_url ? (
                        <a href={item.edit_url} className="block truncate font-medium text-slate-900 no-underline hover:text-indigo-600" title={item.product_name}>
                          {item.product_name}
                        </a>
                      ) : (
                        <span className="block truncate font-medium text-slate-500">{item.product_name}</span>
                      )}
                      <span className="text-xs text-slate-400">#{item.product_id}</span>
                      {!item.product_found ? <Badge className="ml-2 border-0 bg-amber-50 px-1.5 py-0 text-[11px] text-amber-700">Deleted</Badge> : null}
                    </TableCell>
                    <TableCell className="max-w-[360px]">
                      <div className="flex items-center gap-1">
                        <a href={item.link} target="_blank" rel="noreferrer" className="min-w-0 truncate text-sm text-slate-700 no-underline hover:text-indigo-600" title={item.link}>
                          <span className="font-medium text-slate-900">{item.host || item.link}</span>
                          <span className="text-slate-400">{item.host ? item.link.slice(item.link.indexOf(item.host) + item.host.length) : ''}</span>
                        </a>
                        <Button variant="ghost" size="icon" className="h-7 w-7 shrink-0 text-slate-400 hover:text-slate-700" onClick={() => copy(item.link)} aria-label="Copy link">
                          <Copy className="h-3.5 w-3.5" />
                        </Button>
                      </div>
                      <div className="mt-1 md:hidden">{statusCell(item)}</div>
                    </TableCell>
                    <TableCell className="hidden md:table-cell">{statusCell(item)}</TableCell>
                    <TableCell className="hidden whitespace-nowrap text-sm text-slate-500 lg:table-cell">{item.created_at ? formatDate(item.created_at) : '—'}</TableCell>
                    <TableCell className="pr-4 text-right">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon" className="h-8 w-8" aria-label="Actions">
                            <MoreHorizontal className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-44">
                          <DropdownMenuItem onClick={() => openEdit(item)}>
                            <Pencil className="mr-2 h-4 w-4" /> Edit
                          </DropdownMenuItem>
                          <DropdownMenuItem onClick={() => check([item.id])}>
                            <Radar className="mr-2 h-4 w-4" /> Check link
                          </DropdownMenuItem>
                          <DropdownMenuItem onClick={() => copy(item.link)}>
                            <Copy className="mr-2 h-4 w-4" /> Copy link
                          </DropdownMenuItem>
                          <DropdownMenuItem asChild>
                            <a href={item.link} target="_blank" rel="noreferrer">
                              <ExternalLink className="mr-2 h-4 w-4" /> Open link
                            </a>
                          </DropdownMenuItem>
                          <DropdownMenuSeparator />
                          <DropdownMenuItem className="text-rose-600 focus:text-rose-700" onClick={() => setConfirmDelete([item.id])}>
                            <Trash2 className="mr-2 h-4 w-4" /> Delete
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{form.id ? 'Edit download link' : 'Add download link'}</DialogTitle>
            <DialogDescription>Shown to customers with their license key and in the license email. One link per product.</DialogDescription>
          </DialogHeader>
          <form
            className="space-y-4"
            onSubmit={(e) => {
              e.preventDefault();
              save();
            }}
          >
            <ProductSearchSelect value={product?.id ?? null} selectedName={product?.name ?? ''} onChange={setProduct} />
            <div className="space-y-2">
              <Label htmlFor="ls-dl-url">Download URL</Label>
              <Input
                id="ls-dl-url"
                type="url"
                inputMode="url"
                placeholder="https://example.com/download/setup.exe"
                value={form.link}
                onChange={(e) => setForm((f) => ({ ...f, link: e.target.value }))}
                className={cn(form.link && !isUrl(form.link) && 'border-rose-300 focus-visible:ring-rose-400')}
              />
              {form.link && !isUrl(form.link) ? (
                <p className="text-xs text-rose-600">Enter a full URL starting with http:// or https://</p>
              ) : form.link ? (
                <a href={form.link} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-xs text-indigo-600 no-underline hover:underline">
                  Open to test <ExternalLink className="h-3 w-3" />
                </a>
              ) : null}
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                {i18n.cancel || 'Cancel'}
              </Button>
              <Button type="submit" disabled={saving}>
                {saving ? 'Saving…' : form.id ? 'Save changes' : 'Add link'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <AlertDialog open={confirmDelete !== null} onOpenChange={(open) => !open && setConfirmDelete(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete {confirmDelete && confirmDelete.length > 1 ? `${confirmDelete.length} download links` : 'this download link'}?</AlertDialogTitle>
            <AlertDialogDescription>Customers of these products will no longer see a download link. This can't be undone.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{i18n.cancel || 'Cancel'}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-rose-600 hover:bg-rose-700"
              onClick={() => {
                if (confirmDelete) remove(confirmDelete);
                setConfirmDelete(null);
              }}
            >
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
