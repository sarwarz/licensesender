import { useCallback, useEffect, useState } from 'react';
import { Link2, Loader2, PackageX } from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { getBootstrap } from '@/lib/bootstrap';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { ProductSearchSelect, type ProductOption } from '@/components/shared/ProductSearchSelect';

interface UnmappedSku {
  sku: string;
  name: string;
  available_stock: number;
}

function MapRow({ item, onMapped }: { item: UnmappedSku; onMapped: () => void }) {
  const { i18n } = getBootstrap();
  const [product, setProduct] = useState<ProductOption | null>(null);
  const [saving, setSaving] = useState(false);

  const save = async () => {
    if (!product) return;
    setSaving(true);
    try {
      const res = await apiRequest<{ message: string }>('wholesale/unmapped', {
        method: 'POST',
        body: JSON.stringify({ sku: item.sku, product_id: product.id }),
      });
      toast.success(res.message);
      onMapped();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="grid gap-3 px-4 py-3.5 md:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_auto] md:items-end">
      <div className="min-w-0">
        <p className="m-0 truncate text-sm font-medium text-slate-900">{item.name}</p>
        <p className="m-0 mt-0.5 text-xs text-slate-500">
          <span className="font-mono">{item.sku}</span>
          {' · '}
          {(i18n.keysAvailable ?? '%d keys available').replace('%d', item.available_stock.toLocaleString())}
        </p>
      </div>
      <ProductSearchSelect
        label={i18n.sellAs ?? 'Sell as WooCommerce product'}
        value={product?.id ?? null}
        selectedName={product?.name ?? ''}
        onChange={setProduct}
        placeholder={i18n.searchProducts ?? 'Search products…'}
        variations
      />
      <Button onClick={save} disabled={!product || saving} className="md:mb-0.5">
        {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Link2 className="h-4 w-4" />}
        {i18n.mapSku ?? 'Map'}
      </Button>
    </div>
  );
}

/**
 * Catalog products resellers can see but can't buy, because no WooCommerce product is
 * mapped to their SKU. Shown only when there are some.
 */
export function UnmappedSkus({ reloadKey }: { reloadKey: number }) {
  const { i18n } = getBootstrap();
  const [items, setItems] = useState<UnmappedSku[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    try {
      const data = await apiRequest<{ items: UnmappedSku[] }>('wholesale/unmapped');
      setItems(data.items);
    } catch {
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load, reloadKey]);

  if (loading || items.length === 0) return null;

  return (
    <Card className="overflow-hidden border-amber-200 shadow-sm">
      <CardContent className="p-0">
        <div className="flex items-start gap-3 border-b border-amber-200 bg-amber-50/70 px-4 py-3.5">
          <PackageX className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
          <div className="min-w-0 text-sm">
            <p className="m-0 font-medium text-amber-900">
              {items.length === 1
                ? i18n.unmappedOne ?? '1 catalog product can\'t be ordered yet'
                : (i18n.unmappedMany ?? '%d catalog products can\'t be ordered yet').replace('%d', String(items.length))}
            </p>
            <p className="m-0 mt-0.5 text-amber-800">
              {i18n.unmappedHint ?? 'Resellers see these in the catalog, but no WooCommerce product sells their SKU. Pick the product (or variation) to sell each one as.'}
            </p>
          </div>
        </div>
        <div className="divide-y divide-slate-100">
          {items.map((item) => (
            <MapRow key={item.sku} item={item} onMapped={() => setItems((all) => all.filter((x) => x.sku !== item.sku))} />
          ))}
        </div>
      </CardContent>
    </Card>
  );
}
