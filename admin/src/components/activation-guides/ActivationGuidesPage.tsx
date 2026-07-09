import { useCallback, useEffect, useRef, useState } from 'react';
import { ExternalLink, FileUp, Pencil, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { getBootstrap } from '@/lib/bootstrap';
import { PageHeader } from '@/components/shared/PageHeader';
import { ProductSearchSelect, type ProductOption } from '@/components/shared/ProductSearchSelect';
import { getWordPressEditorContent, removeWordPressEditor, WordPressEditor } from '@/components/shared/WordPressEditor';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

interface ActivationGuide {
  id: number;
  product_id: number;
  product_name: string;
  type: string;
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

const emptyForm = {
  id: 0,
  product_id: '',
  type: 'text',
  content: '',
  pdf_link: '',
};

export function ActivationGuidesPage() {
  const { i18n } = getBootstrap();
  const [items, setItems] = useState<ActivationGuide[]>([]);
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [selectedProduct, setSelectedProduct] = useState<ProductOption | null>(null);
  const [pdfFile, setPdfFile] = useState<File | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const handledUrlAction = useRef(false);

  const params = new URLSearchParams(window.location.search);
  const action = params.get('action');
  const editId = Number(params.get('id') || 0);

  const clearUrlParams = () => {
    window.history.replaceState({}, '', `${window.location.pathname}?page=ls-activation-guides`);
  };

  const resetForm = () => {
    setForm(emptyForm);
    setSelectedProduct(null);
    setPdfFile(null);
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const data = await apiRequest<{ items: ActivationGuide[] }>('activation-guides');
      setItems(data.items);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setLoading(false);
    }
  }, [i18n.error]);

  useEffect(() => { load(); }, [load]);

  const openCreate = () => {
    resetForm();
    setDialogOpen(true);
  };

  const openEdit = async (id: number) => {
    try {
      const guide = await apiRequest<ActivationGuideDetail>(`activation-guides/${id}`);
      setForm({
        id: guide.id,
        product_id: String(guide.product_id),
        type: guide.type,
        content: guide.html_content,
        pdf_link: guide.pdf_link,
      });
      setSelectedProduct({ id: guide.product_id, name: guide.product_name });
      setPdfFile(null);
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
      setDialogOpen(true);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    }
  };

  useEffect(() => {
    if (handledUrlAction.current) {
      return;
    }
    if (action === 'add') {
      handledUrlAction.current = true;
      resetForm();
      setDialogOpen(true);
    }
    if (action === 'edit' && editId) {
      handledUrlAction.current = true;
      openEdit(editId);
    }
  }, [action, editId]);

  const handleDialogChange = (open: boolean) => {
    if (!open) {
      removeWordPressEditor();
      clearUrlParams();
    }
    setDialogOpen(open);
  };

  const save = async () => {
    if (!form.product_id) {
      toast.error('Please select a product.');
      return;
    }

    const content = form.type === 'text' ? getWordPressEditorContent() : '';

    if (form.type === 'text' && !content.trim()) {
      toast.error('Please enter activation guide content.');
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
    formData.append('product_id', form.product_id);
    formData.append('type', form.type);
    if (form.type === 'text') {
      formData.append('content', content);
    }
    if (form.type === 'pdf' && pdfFile) {
      formData.append('pdf_file', pdfFile);
    }

    setSaving(true);
    try {
      const response = await fetch(getBootstrap().ajaxUrl || '/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });
      const result = await response.json();
      if (!result.success) {
        throw new Error(result.data || i18n.error);
      }
      toast.success(i18n.success);
      handleDialogChange(false);
      resetForm();
      load();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setSaving(false);
    }
  };

  const remove = async () => {
    if (!deleteId) return;
    try {
      await apiRequest(`activation-guides/${deleteId}`, { method: 'DELETE' });
      toast.success('Deleted successfully.');
      setDeleteId(null);
      load();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    }
  };

  return (
    <div className="mx-auto max-w-[1400px] space-y-6 pb-8">
      <PageHeader
        title="Activation Guides"
        subtitle="Manage product activation guides."
        actions={<Button size="sm" onClick={openCreate}><Plus className="mr-2 h-4 w-4" />Add New</Button>}
      />

      <Card className="overflow-hidden border-slate-200 shadow-sm">
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="ls-table-head">ID</TableHead>
                <TableHead className="ls-table-head">Product</TableHead>
                <TableHead className="ls-table-head">Type</TableHead>
                <TableHead className="ls-table-head">Created</TableHead>
                <TableHead className="ls-table-head text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {loading ? (
                <TableRow><TableCell colSpan={5} className="py-8 text-center">Loading…</TableCell></TableRow>
              ) : items.length === 0 ? (
                <TableRow><TableCell colSpan={5} className="py-8 text-center text-muted-foreground">No activation guides found.</TableCell></TableRow>
              ) : (
                items.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell>{item.id}</TableCell>
                    <TableCell>{item.product_name}</TableCell>
                    <TableCell>{item.type}</TableCell>
                    <TableCell>{item.created_at}</TableCell>
                    <TableCell className="text-right">
                      <Button variant="ghost" size="icon" onClick={() => openEdit(item.id)}><Pencil className="h-4 w-4" /></Button>
                      <Button variant="ghost" size="icon" onClick={() => setDeleteId(item.id)}><Trash2 className="h-4 w-4 text-destructive" /></Button>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Dialog open={dialogOpen} onOpenChange={handleDialogChange}>
        <DialogContent className="max-w-4xl">
          <DialogHeader>
            <DialogTitle>{form.id ? 'Edit Activation Guide' : 'Add Activation Guide'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <ProductSearchSelect
              value={selectedProduct?.id ?? null}
              selectedName={selectedProduct?.name ?? ''}
              onChange={(product) => {
                setSelectedProduct(product);
                setForm((f) => ({ ...f, product_id: product ? String(product.id) : '' }));
              }}
            />

            <div className="space-y-2">
              <Label>Type</Label>
              <Select value={form.type} onValueChange={(v) => setForm((f) => ({ ...f, type: v }))}>
                <SelectTrigger>
                  <SelectValue placeholder="Select activation type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="text">Text (converted to PDF)</SelectItem>
                  <SelectItem value="pdf">PDF Upload</SelectItem>
                </SelectContent>
              </Select>
            </div>

            {form.type === 'text' ? (
              <WordPressEditor
                key={`activation-guide-editor-${form.id}-${dialogOpen ? 'open' : 'closed'}`}
                value={form.content}
                onChange={(content) => setForm((f) => ({ ...f, content }))}
                helpText="Content will be converted to a PDF automatically when saved."
              />
            ) : (
              <div className="space-y-3">
                <div className="space-y-2">
                  <Label>Upload PDF</Label>
                  <div className="flex items-center gap-3">
                    <Input
                      ref={fileInputRef}
                      type="file"
                      accept="application/pdf,.pdf"
                      className="bg-white file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1 file:text-sm file:font-medium"
                      onChange={(e) => setPdfFile(e.target.files?.[0] ?? null)}
                    />
                  </div>
                  {pdfFile ? (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                      <FileUp className="h-4 w-4" />
                      {pdfFile.name}
                    </p>
                  ) : null}
                </div>

                {form.pdf_link ? (
                  <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <p className="text-sm font-medium text-foreground">Current PDF</p>
                    <a
                      href={form.pdf_link}
                      className="mt-1 inline-flex items-center gap-1 text-sm text-primary hover:underline"
                      target="_blank"
                      rel="noreferrer"
                    >
                      View current PDF
                      <ExternalLink className="h-3.5 w-3.5" />
                    </a>
                  </div>
                ) : (
                  <p className="text-sm text-muted-foreground">No PDF uploaded yet.</p>
                )}
              </div>
            )}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => handleDialogChange(false)}>{i18n.cancel}</Button>
            <Button onClick={save} disabled={saving}>{saving ? 'Saving…' : i18n.save}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleteId !== null} onOpenChange={(open) => !open && setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{i18n.confirm}</AlertDialogTitle>
            <AlertDialogDescription>This will permanently delete the activation guide.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{i18n.cancel}</AlertDialogCancel>
            <AlertDialogAction onClick={remove}>{i18n.delete}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
