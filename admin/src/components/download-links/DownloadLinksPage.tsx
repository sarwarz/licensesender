import { useCallback, useEffect, useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { getBootstrap } from '@/lib/bootstrap';
import { PageHeader } from '@/components/shared/PageHeader';
import { ProductSearchSelect, type ProductOption } from '@/components/shared/ProductSearchSelect';
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

interface DownloadLink {
  id: number;
  product_id: number;
  product_name: string;
  link: string;
}

export function DownloadLinksPage() {
  const { i18n } = getBootstrap();
  const [items, setItems] = useState<DownloadLink[]>([]);
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [form, setForm] = useState({ id: 0, product_id: '', link: '' });
  const [selectedProduct, setSelectedProduct] = useState<ProductOption | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const data = await apiRequest<{ items: DownloadLink[] }>('download-links');
      setItems(data.items);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setLoading(false);
    }
  }, [i18n.error]);

  useEffect(() => { load(); }, [load]);

  const openCreate = () => {
    setForm({ id: 0, product_id: '', link: '' });
    setSelectedProduct(null);
    setDialogOpen(true);
  };

  const openEdit = async (id: number) => {
    const item = await apiRequest<DownloadLink>(`download-links/${id}`);
    setForm({ id: item.id, product_id: String(item.product_id), link: item.link });
    setSelectedProduct({ id: item.product_id, name: item.product_name });
    setDialogOpen(true);
  };

  const save = async () => {
    if (!form.product_id) {
      toast.error('Please select a product.');
      return;
    }

    try {
      const payload = {
        id: form.id || undefined,
        product_id: Number(form.product_id),
        link: form.link,
      };
      if (form.id) {
        await apiRequest(`download-links/${form.id}`, { method: 'PUT', body: JSON.stringify(payload) });
      } else {
        await apiRequest('download-links', { method: 'POST', body: JSON.stringify(payload) });
      }
      toast.success(i18n.success);
      setDialogOpen(false);
      load();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    }
  };

  const remove = async () => {
    if (!deleteId) return;
    try {
      await apiRequest(`download-links/${deleteId}`, { method: 'DELETE' });
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
        title="Download Links"
        subtitle="Manage product download links."
        actions={<Button size="sm" onClick={openCreate}><Plus className="mr-2 h-4 w-4" />Add New</Button>}
      />

      <Card className="overflow-hidden border-slate-200 shadow-sm">
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="ls-table-head">ID</TableHead>
                <TableHead className="ls-table-head">Product</TableHead>
                <TableHead className="ls-table-head">Link</TableHead>
                <TableHead className="ls-table-head text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {loading ? (
                <TableRow><TableCell colSpan={4} className="py-8 text-center">Loading…</TableCell></TableRow>
              ) : items.length === 0 ? (
                <TableRow><TableCell colSpan={4} className="py-8 text-center text-muted-foreground">No download links found.</TableCell></TableRow>
              ) : (
                items.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell>{item.id}</TableCell>
                    <TableCell>{item.product_name}</TableCell>
                    <TableCell><a href={item.link} className="text-primary hover:underline" target="_blank" rel="noreferrer">{item.link}</a></TableCell>
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

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{form.id ? 'Edit Download Link' : 'Add Download Link'}</DialogTitle>
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
              <Label>Download Link</Label>
              <Input type="url" value={form.link} onChange={(e) => setForm((f) => ({ ...f, link: e.target.value }))} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>{i18n.cancel}</Button>
            <Button onClick={save}>{i18n.save}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleteId !== null} onOpenChange={(open) => !open && setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{i18n.confirm}</AlertDialogTitle>
            <AlertDialogDescription>This will permanently delete the download link.</AlertDialogDescription>
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
