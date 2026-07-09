import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { getBootstrap } from '@/lib/bootstrap';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
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
  product_id: number;
  product_name: string;
}

interface LicenseChangeSheetProps {
  licenseId: number | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSaved: () => void;
}

export function LicenseChangeSheet({ licenseId, open, onOpenChange, onSaved }: LicenseChangeSheetProps) {
  const { i18n } = getBootstrap();
  const [loading, setLoading] = useState(false);
  const [fetching, setFetching] = useState(false);
  const [license, setLicense] = useState<LicenseDetail | null>(null);
  const [newKey, setNewKey] = useState('');
  const [newLink, setNewLink] = useState('');
  const [newGuide, setNewGuide] = useState('');
  const [notifyUser, setNotifyUser] = useState(true);

  useEffect(() => {
    if (!open || !licenseId) return;
    setLoading(true);
    apiRequest<LicenseDetail>(`licenses/${licenseId}`)
      .then((data) => {
        setLicense(data);
        setNotifyUser(!!data.email);
      })
      .catch((err) => toast.error(err instanceof Error ? err.message : i18n.error))
      .finally(() => setLoading(false));
  }, [open, licenseId, i18n.error]);

  const fetchNew = async () => {
    if (!license?.sku) return;
    setFetching(true);
    try {
      const data = await apiRequest<{
        key_value: string;
        download_link: string;
        activation_guide: string;
      }>('licenses/fetch-by-sku', {
        method: 'POST',
        body: JSON.stringify({ sku: license.sku }),
      });
      setNewKey(data.key_value);
      setNewLink(data.download_link || '');
      setNewGuide(data.activation_guide || '');
      toast.success('License fetched.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setFetching(false);
    }
  };

  const apply = async () => {
    if (!licenseId) return;
    try {
      await apiRequest(`licenses/${licenseId}/change`, {
        method: 'POST',
        body: JSON.stringify({
          new_key_value: newKey,
          new_download_link: newLink,
          new_activation_guide: newGuide,
          notify_user: notifyUser,
        }),
      });
      toast.success('License changed successfully.');
      onSaved();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    }
  };

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="overflow-y-auto sm:max-w-xl">
        <SheetHeader>
          <SheetTitle>Change License</SheetTitle>
          <SheetDescription>Fetch a new license by SKU, preview, and apply.</SheetDescription>
        </SheetHeader>

        {loading || !license ? (
          <p className="py-8 text-muted-foreground">{i18n.loading}</p>
        ) : (
          <div className="mt-6 space-y-4">
            <div className="space-y-2">
              <Label>Current License</Label>
              <Input value={license.license_key} readOnly />
            </div>
            <div className="space-y-2">
              <Label>SKU</Label>
              <Input value={license.sku} readOnly />
            </div>
            <div className="space-y-2">
              <Label>New License (fetched)</Label>
              <div className="flex gap-2">
                <Input value={newKey} onChange={(e) => setNewKey(e.target.value)} placeholder="Click Fetch New License" />
                <Button variant="outline" onClick={fetchNew} disabled={fetching}>
                  {fetching ? 'Fetching…' : 'Fetch'}
                </Button>
              </div>
            </div>
            <div className="space-y-2">
              <Label>New Download Link</Label>
              <Input type="url" value={newLink} onChange={(e) => setNewLink(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label>New Activation Guide</Label>
              <textarea
                className="flex min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                value={newGuide}
                onChange={(e) => setNewGuide(e.target.value)}
              />
            </div>
            <div className="flex items-center gap-2">
              <Checkbox id="notify" checked={notifyUser} onCheckedChange={(v) => setNotifyUser(!!v)} />
              <Label htmlFor="notify">Email the customer about this change</Label>
            </div>
          </div>
        )}

        <SheetFooter className="mt-6">
          <Button variant="outline" onClick={() => onOpenChange(false)}>{i18n.cancel}</Button>
          <Button onClick={apply} disabled={loading || !newKey}>{i18n.save || 'Apply Change'}</Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
