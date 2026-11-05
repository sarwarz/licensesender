import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { getBootstrap } from '@/lib/bootstrap';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet';

interface LicenseDetail {
  id: number;
  license_key: string;
  email: string;
  sku: string;
  order_id: number;
  product_id: number;
  product_name: string;
  sold_at: string;
  download_link: string;
  activation_guide: string;
  source: string;
}

interface LicenseEditSheetProps {
  licenseId: number | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSaved: () => void;
}

export function LicenseEditSheet({ licenseId, open, onOpenChange, onSaved }: LicenseEditSheetProps) {
  const { i18n } = getBootstrap();
  const [loading, setLoading] = useState(false);
  const [license, setLicense] = useState<LicenseDetail | null>(null);
  const [keyValue, setKeyValue] = useState('');
  const [downloadLink, setDownloadLink] = useState('');

  useEffect(() => {
    if (!open || !licenseId) return;
    setLoading(true);
    apiRequest<LicenseDetail>(`licenses/${licenseId}`)
      .then((data) => {
        setLicense(data);
        setKeyValue(data.license_key);
        setDownloadLink(data.download_link || '');
      })
      .catch((err) => toast.error(err instanceof Error ? err.message : i18n.error))
      .finally(() => setLoading(false));
  }, [open, licenseId, i18n.error]);

  const save = async () => {
    if (!licenseId) return;
    try {
      await apiRequest(`licenses/${licenseId}`, {
        method: 'PUT',
        body: JSON.stringify({
          key_value: keyValue,
          download_link: downloadLink,
        }),
      });
      toast.success(i18n.success || 'Saved successfully.');
      onSaved();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    }
  };

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="overflow-y-auto sm:max-w-xl">
        <SheetHeader>
          <SheetTitle>Edit License</SheetTitle>
          <SheetDescription>Update license details and customer info.</SheetDescription>
        </SheetHeader>

        {loading || !license ? (
          <p className="py-8 text-muted-foreground">{i18n.loading}</p>
        ) : (
          <div className="mt-6 space-y-4">
            <div className="space-y-2">
              <Label htmlFor="key_value">License Key</Label>
              <Input id="key_value" value={keyValue} onChange={(e) => setKeyValue(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="download_link">Download Link</Label>
              <Input id="download_link" type="url" value={downloadLink} onChange={(e) => setDownloadLink(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label>Customer Email</Label>
              <Input value={license.email} readOnly />
            </div>
            <div className="space-y-2">
              <Label>SKU</Label>
              <Input value={license.sku} readOnly />
            </div>
            <div className="space-y-2">
              <Label>Order ID</Label>
              <Input value={String(license.order_id)} readOnly />
            </div>
            <div className="space-y-2">
              <Label>Product</Label>
              <Input value={`${license.product_id}${license.product_name ? ` – ${license.product_name}` : ''}`} readOnly />
            </div>
            <div className="space-y-2">
              <Label>Sold Date</Label>
              <Input value={license.sold_at} readOnly />
            </div>
          </div>
        )}

        <SheetFooter className="mt-6">
          <Button variant="outline" onClick={() => onOpenChange(false)}>{i18n.cancel}</Button>
          <Button onClick={save} disabled={loading}>{i18n.save}</Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
