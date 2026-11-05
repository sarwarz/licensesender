import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  BookOpenText,
  CircleDashed,
  ExternalLink,
  FileText,
  FileType2,
  FileUp,
  MoreHorizontal,
  Pencil,
  Plus,
  Search,
  ShieldCheck,
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
import { getWordPressEditorContent, removeWordPressEditor, WordPressEditor } from '@/components/shared/WordPressEditor';
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

interface ActivationGuide {
  id: number;
  product_id: number;
  product_name: string;
  type: string;
  type_key: string;
  pdf_link: string;
  has_text: boolean;
  edit_url: string;
  created_at: string;
}

interface ActivationGuideDetail {
  id: number;
  product_id: number;
  product_name: string;
  type: string;
  html_content: string;
  pdf_link: string;
}

type GuideType = 'text' | 'pdf';
type Filter = 'all' | GuideType;

const MAX_PDF_MB = 10;

const emptyForm = { id: 0, type: 'text' as GuideType, content: '', pdf_link: '' };

export function ActivationGuidesPage() {
  const { i18n } = getBootstrap();
  const [items, setItems] = useState<ActivationGuide[]>([]);
  const [coverage, setCoverage] = useState<Coverage | null>(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState<Filter>('all');
  const [selected, setSelected] = useState<Set<number>>(new Set());

  const [dialogOpen, setDialogOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [product, setProduct] = useState<ProductOption | null>(null);
  const [pdfFile, setPdfFile] = useState<File | null>(null);
  const [dragging, setDragging] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState<number[] | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const handledUrlAction = useRef(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [list, cov] = await Promise.all([
        apiRequest<{ items: ActivationGuide[] }>('activation-guides'),
        apiRequest<Coverage>('activation-guides/coverage').catch(() => null),
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

  const resetForm = () => {
    setForm(emptyForm);
    setProduct(null);
    setPdfFile(null);
    if (fileInputRef.current) fileInputRef.current.value = '';
  };

  const openCreate = (preset?: { id: number; name: string }) => {
    resetForm();
    if (preset) setProduct({ id: preset.id, name: preset.name });
    setDialogOpen(true);
  };

  const openEdit = async (id: number) => {
    try {
      const guide = await apiRequest<ActivationGuideDetail>(`activation-guides/${id}`);
      setForm({ id: guide.id, type: guide.type === 'pdf' ? 'pdf' : 'text', content: guide.html_content, pdf_link: guide.pdf_link });
      setProduct({ id: guide.product_id, name: guide.product_name });
      setPdfFile(null);
      setDialogOpen(true);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    }
  };

  // Old ?action=add / ?action=edit&id= links still open the form.
  useEffect(() => {
    if (handledUrlAction.current) return;
    const params = new URLSearchParams(window.location.search);
    const action = params.get('action');
    const id = Number(params.get('id') || 0);
    if (action === 'add') {
      handledUrlAction.current = true;
      openCreate();
    } else if (action === 'edit' && id) {
      handledUrlAction.current = true;
      openEdit(id);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleDialogChange = (open: boolean) => {
    if (!open) {
      removeWordPressEditor();
      window.history.replaceState({}, '', `${window.location.pathname}?page=ls-activation-guides`);
    }
    setDialogOpen(open);
  };

  const pickFile = (file: File | null | undefined) => {
    if (!file) return;
    if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
      toast.error('Only PDF files can be uploaded.');
      return;
    }
    if (file.size > MAX_PDF_MB * 1024 * 1024) {
      toast.error(`The PDF must be ${MAX_PDF_MB} MB or smaller.`);
      return;
    }
    setPdfFile(file);
  };

  const save = async () => {
    if (!product) {
      toast.error('Please select a product.');
      return;
    }
    const content = form.type === 'text' ? getWordPressEditorContent() : '';
    if (form.type === 'text' && !content.trim()) {
      toast.error('Please write the activation steps.');
      return;
    }
    if (form.type === 'pdf' && !pdfFile && !form.pdf_link) {
      toast.error('Please upload a PDF file.');
      return;
    }

    const formData = new FormData();
    formData.append('action', 'ls_save_activation_guide');
    formData.append('_wpnonce', getBootstrap().activationNonce || '');
    if (form.id) formData.append('id', String(form.id));
    formData.append('product_id', String(product.id));
    formData.append('type', form.type);
    if (form.type === 'text') formData.append('content', content);
    if (form.type === 'pdf' && pdfFile) formData.append('pdf_file', pdfFile);

    setSaving(true);
    try {
      const response = await fetch(getBootstrap().ajaxUrl || '/wp-admin/admin-ajax.php', { method: 'POST', body: formData, credentials: 'same-origin' });
      const result = await response.json();
      if (!result.success) {
        throw new Error((typeof result.data === 'string' ? result.data : result.data?.message) || i18n.error);
      }
      toast.success(form.id ? 'Activation guide updated.' : 'Activation guide added.');
      handleDialogChange(false);
      resetForm();
      load();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setSaving(false);
    }
  };

  const remove = async (ids: number[]) => {
    try {
      const result = await apiRequest<{ message: string }>('activation-guides/bulk-delete', { method: 'POST', body: JSON.stringify({ ids }) });
      toast.success(result.message);
      setSelected(new Set());
      load();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    }
  };

  const visible = useMemo(() => {
    const q = search.trim().toLowerCase();
    return items.filter((i) => (filter === 'all' || i.type_key === filter) && (!q || i.product_name.toLowerCase().includes(q) || String(i.product_id) === q));
  }, [items, search, filter]);

  const counts = { text: items.filter((i) => i.type_key === 'text').length, pdf: items.filter((i) => i.type_key === 'pdf').length };
  const allSelected = visible.length > 0 && visible.every((i) => selected.has(i.id));
  const someSelected = visible.some((i) => selected.has(i.id));

  return (
    <div className="mx-auto max-w-[1400px] space-y-6 pb-8">
      <PageHeader
        title="Activation Guides"
        subtitle="Step-by-step instructions customers get as a PDF with their key."
        actions={
          <Button size="sm" onClick={() => openCreate()}>
            <Plus className="h-4 w-4" /> Add guide
          </Button>
        }
      />

      <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        {[
          { label: 'Guides', value: items.length, tone: 'bg-indigo-50 text-indigo-600', icon: BookOpenText },
          { label: 'Products covered', value: coverage ? `${coverage.covered}/${coverage.enabled}` : '—', tone: 'bg-emerald-50 text-emerald-600', icon: ShieldCheck },
          { label: 'Written guides', value: counts.text, tone: 'bg-sky-50 text-sky-600', icon: FileText },
          { label: 'Uploaded PDFs', value: counts.pdf, tone: 'bg-amber-50 text-amber-600', icon: FileType2 },
        ].map((s) => (
          <Card key={s.label} className="border-slate-200 shadow-sm">
            <CardContent className="flex items-center gap-3 p-4 sm:gap-4 sm:p-5">
              <span className={cn('hidden h-10 w-10 shrink-0 items-center justify-center rounded-xl sm:flex', s.tone)}>
                <s.icon className="h-5 w-5" />
              </span>
              <div className="min-w-0">
                <p className="text-sm font-medium text-slate-500">{s.label}</p>
                {loading ? <Skeleton className="mt-1 h-7 w-12" /> : <p className="text-2xl font-semibold tracking-tight text-slate-900">{s.value}</p>}
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      <CoverageCard coverage={coverage} noun="activation guide" onAdd={(p) => openCreate(p)} />

      <Card className="overflow-hidden border-slate-200 shadow-sm">
        <CardContent className="p-0">
          <div className="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center">
            <div className="relative flex-1 sm:max-w-sm">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <Input className="border-slate-200 bg-slate-50 pl-9 pr-8" placeholder="Search product…" value={search} onChange={(e) => setSearch(e.target.value)} />
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
                  ['text', `Written (${counts.text})`],
                  ['pdf', `Uploaded PDF (${counts.pdf})`],
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
                <TableHead className="ls-table-head">Type</TableHead>
                <TableHead className="ls-table-head hidden md:table-cell">Customer PDF</TableHead>
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
                    <BookOpenText className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-3 text-base font-medium text-slate-900">{items.length ? 'No guides match' : 'No activation guides yet'}</p>
                    <p className="mt-1 text-sm text-slate-500">
                      {items.length ? 'Try another search or filter.' : 'Write the activation steps once; customers get them as a PDF with their key.'}
                    </p>
                    {!items.length ? (
                      <Button size="sm" className="mt-4" onClick={() => openCreate()}>
                        <Plus className="h-4 w-4" /> Add guide
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
                        aria-label={`Select guide ${item.id}`}
                      />
                    </TableCell>
                    <TableCell className="max-w-[280px]">
                      <button type="button" onClick={() => openEdit(item.id)} className="block max-w-full truncate text-left font-medium text-slate-900 hover:text-indigo-600" title={item.product_name}>
                        {item.product_name}
                      </button>
                      <span className="text-xs text-slate-400">#{item.product_id}</span>
                      {!item.edit_url ? <Badge className="ml-2 border-0 bg-amber-50 px-1.5 py-0 text-[11px] text-amber-700">Deleted</Badge> : null}
                    </TableCell>
                    <TableCell>
                      <Badge className={cn('gap-1 whitespace-nowrap border-0 font-medium', item.type_key === 'pdf' ? 'bg-amber-50 text-amber-700' : 'bg-sky-50 text-sky-700')}>
                        {item.type_key === 'pdf' ? <FileType2 className="h-3 w-3" /> : <FileText className="h-3 w-3" />}
                        {item.type_key === 'pdf' ? 'Uploaded PDF' : 'Written → PDF'}
                      </Badge>
                    </TableCell>
                    <TableCell className="hidden md:table-cell">
                      {item.pdf_link ? (
                        <a href={item.pdf_link} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-sm text-indigo-600 no-underline hover:underline">
                          View PDF <ExternalLink className="h-3 w-3" />
                        </a>
                      ) : (
                        <span className="inline-flex items-center gap-1 text-xs text-rose-600" title="Customers get no guide until a PDF exists. Edit and save to regenerate it.">
                          <CircleDashed className="h-3.5 w-3.5" /> No PDF yet
                        </span>
                      )}
                    </TableCell>
                    <TableCell className="hidden whitespace-nowrap text-sm text-slate-500 lg:table-cell">{item.created_at || '—'}</TableCell>
                    <TableCell className="pr-4 text-right">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon" className="h-8 w-8" aria-label="Actions">
                            <MoreHorizontal className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-44">
                          <DropdownMenuItem onClick={() => openEdit(item.id)}>
                            <Pencil className="mr-2 h-4 w-4" /> Edit
                          </DropdownMenuItem>
                          {item.pdf_link ? (
                            <DropdownMenuItem asChild>
                              <a href={item.pdf_link} target="_blank" rel="noreferrer">
                                <ExternalLink className="mr-2 h-4 w-4" /> View PDF
                              </a>
                            </DropdownMenuItem>
                          ) : null}
                          {item.edit_url ? (
                            <DropdownMenuItem asChild>
                              <a href={item.edit_url}>
                                <FileText className="mr-2 h-4 w-4" /> Open product
                              </a>
                            </DropdownMenuItem>
                          ) : null}
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

      <Dialog open={dialogOpen} onOpenChange={handleDialogChange}>
        <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-4xl">
          <DialogHeader>
            <DialogTitle>{form.id ? 'Edit activation guide' : 'Add activation guide'}</DialogTitle>
            <DialogDescription>Customers get this as a PDF link with their license key and in the license email. One guide per product.</DialogDescription>
          </DialogHeader>

          <div className="space-y-5">
            <ProductSearchSelect value={product?.id ?? null} selectedName={product?.name ?? ''} onChange={setProduct} />

            <div className="space-y-2">
              <Label>How do you want to provide it?</Label>
              <div className="grid gap-3 sm:grid-cols-2">
                {(
                  [
                    ['text', FileText, 'Write the steps', 'Use the editor; we turn it into a branded PDF when you save.'],
                    ['pdf', FileUp, 'Upload a PDF', `Use your own PDF (max ${MAX_PDF_MB} MB).`],
                  ] as const
                ).map(([value, Icon, title, hint]) => (
                  <button
                    key={value}
                    type="button"
                    onClick={() => setForm((f) => ({ ...f, type: value }))}
                    className={cn(
                      'flex items-start gap-3 rounded-xl border p-4 text-left transition-colors',
                      form.type === value ? 'border-indigo-400 bg-indigo-50/60 ring-1 ring-indigo-400' : 'border-slate-200 bg-white hover:border-slate-300'
                    )}
                  >
                    <span className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-lg', form.type === value ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600')}>
                      <Icon className="h-4 w-4" />
                    </span>
                    <span>
                      <span className="block text-sm font-semibold text-slate-900">{title}</span>
                      <span className="mt-0.5 block text-xs text-slate-500">{hint}</span>
                    </span>
                  </button>
                ))}
              </div>
            </div>

            {form.type === 'text' ? (
              <WordPressEditor
                key={`activation-guide-editor-${form.id}-${dialogOpen ? 'open' : 'closed'}`}
                value={form.content}
                onChange={(content) => setForm((f) => ({ ...f, content }))}
                helpText="Tip: numbered steps and screenshots work best. Images must use public URLs."
              />
            ) : (
              <div className="space-y-3">
                <label
                  onDragOver={(e) => {
                    e.preventDefault();
                    setDragging(true);
                  }}
                  onDragLeave={() => setDragging(false)}
                  onDrop={(e) => {
                    e.preventDefault();
                    setDragging(false);
                    pickFile(e.dataTransfer.files?.[0]);
                  }}
                  className={cn(
                    'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-10 text-center transition-colors',
                    dragging ? 'border-indigo-400 bg-indigo-50' : 'border-slate-200 bg-slate-50 hover:border-slate-300'
                  )}
                >
                  <FileUp className="h-8 w-8 text-slate-400" />
                  {pdfFile ? (
                    <>
                      <span className="text-sm font-medium text-slate-900">{pdfFile.name}</span>
                      <span className="text-xs text-slate-500">{(pdfFile.size / 1024 / 1024).toFixed(2)} MB · click to choose another</span>
                    </>
                  ) : (
                    <>
                      <span className="text-sm font-medium text-slate-900">Drop a PDF here or click to browse</span>
                      <span className="text-xs text-slate-500">PDF only, up to {MAX_PDF_MB} MB</span>
                    </>
                  )}
                  <input ref={fileInputRef} type="file" accept="application/pdf,.pdf" className="sr-only" onChange={(e) => pickFile(e.target.files?.[0])} />
                </label>

                {form.pdf_link ? (
                  <div className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3">
                    <span className="flex items-center gap-2 text-sm text-slate-700">
                      <FileType2 className="h-4 w-4 text-amber-600" />
                      {pdfFile ? 'Current PDF (replaced when you save)' : 'Current PDF'}
                    </span>
                    <a href={form.pdf_link} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-sm text-indigo-600 no-underline hover:underline">
                      View <ExternalLink className="h-3.5 w-3.5" />
                    </a>
                  </div>
                ) : null}
              </div>
            )}
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => handleDialogChange(false)}>
              {i18n.cancel || 'Cancel'}
            </Button>
            <Button onClick={save} disabled={saving}>
              {saving ? (form.type === 'text' ? 'Creating PDF…' : 'Uploading…') : form.id ? 'Save changes' : 'Add guide'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={confirmDelete !== null} onOpenChange={(open) => !open && setConfirmDelete(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete {confirmDelete && confirmDelete.length > 1 ? `${confirmDelete.length} activation guides` : 'this activation guide'}?</AlertDialogTitle>
            <AlertDialogDescription>Customers of these products will no longer get an activation guide. This can't be undone.</AlertDialogDescription>
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
