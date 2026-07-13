import { useState } from 'react';
import { FilePlus2 } from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { SettingsSection } from '@/components/settings/SettingsSection';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

export type PageChoice = {
  id: string;
  title: string;
};

export type PaymentGatewayChoice = {
  id: string;
  title: string;
  enabled: boolean;
  is_wallet: boolean;
};

type GeneratePagesResponse = {
  message: string;
  apply_page_id: number;
  catalog_page_id: number;
  apply_page_url?: string;
  catalog_page_url?: string;
  pages?: PageChoice[];
};

type WholesaleSettingsFieldsProps = {
  settings: Record<string, string>;
  pages: PageChoice[];
  paymentGateways: PaymentGatewayChoice[];
  onChange: (key: string, value: string) => void;
  onPagesGenerated?: (pages: PageChoice[]) => void;
};

export function WholesaleSettingsFields({
  settings,
  pages,
  paymentGateways,
  onChange,
  onPagesGenerated,
}: WholesaleSettingsFieldsProps) {
  const [generating, setGenerating] = useState(false);
  const yesNo = (key: string) => settings[key] === 'yes';
  const paymentMode = settings.lship_wholesale_payment_mode || 'all';
  const selectedGateways = (settings.lship_wholesale_payment_gateways || '')
    .split(',')
    .map((id) => id.trim())
    .filter(Boolean);
  const walletGateways = paymentGateways.filter((gateway) => gateway.is_wallet);
  const hasWalletGateway = walletGateways.length > 0;

  const toggleGateway = (gatewayId: string, checked: boolean) => {
    const next = new Set(selectedGateways);
    if (checked) {
      next.add(gatewayId);
    } else {
      next.delete(gatewayId);
    }
    onChange('lship_wholesale_payment_gateways', Array.from(next).join(','));
  };

  const generatePages = async () => {
    setGenerating(true);
    try {
      const result = await apiRequest<GeneratePagesResponse>('wholesale/generate-pages', {
        method: 'POST',
        body: JSON.stringify({}),
      });

      onChange('lship_wholesale_apply_page_id', String(result.apply_page_id));
      onChange('lship_wholesale_catalog_page_id', String(result.catalog_page_id));

      if (result.pages) {
        onPagesGenerated?.(result.pages);
      }

      toast.success(result.message || 'Wholesale pages generated successfully.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Could not generate wholesale pages.');
    } finally {
      setGenerating(false);
    }
  };

  return (
    <div className="space-y-8">
      <SettingsSection
        title="Wholesale module"
        description="Enable B2B wholesale applications, catalog access, and tier pricing."
      >
        <div className="md:col-span-2 flex items-center justify-between rounded-lg border p-4">
          <div className="space-y-1 pr-4">
            <Label htmlFor="lship_wholesale_enabled">Enable wholesale</Label>
            <p className="text-xs text-muted-foreground">
              When disabled, wholesale shortcodes show a notice instead of the apply form or catalog.
            </p>
          </div>
          <Switch
            id="lship_wholesale_enabled"
            checked={yesNo('lship_wholesale_enabled')}
            onCheckedChange={(v) => onChange('lship_wholesale_enabled', v ? 'yes' : 'no')}
          />
        </div>
      </SettingsSection>

      <SettingsSection
        title="Catalog display"
        description="Control how the wholesale product catalog behaves on the storefront."
      >
        <div className="space-y-2">
          <Label htmlFor="lship_wholesale_catalog_per_page">Products per page</Label>
          <Input
            id="lship_wholesale_catalog_per_page"
            type="number"
            min={1}
            max={100}
            value={settings.lship_wholesale_catalog_per_page || '10'}
            onChange={(e) => onChange('lship_wholesale_catalog_per_page', e.target.value)}
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="lship_wholesale_low_stock_threshold">Low stock threshold</Label>
          <Input
            id="lship_wholesale_low_stock_threshold"
            type="number"
            min={1}
            value={settings.lship_wholesale_low_stock_threshold || '10'}
            onChange={(e) => onChange('lship_wholesale_low_stock_threshold', e.target.value)}
          />
          <p className="text-xs text-muted-foreground">
            Stock below this number shows a warning badge in the catalog. Zero stock shows as out of stock.
          </p>
        </div>
      </SettingsSection>

      <SettingsSection
        title="Order requirements"
        description="Set checkout rules for wholesale customers placing catalog orders."
        singleColumn
      >
        <div className="space-y-2">
          <Label htmlFor="lship_wholesale_min_order_quantity">Minimum order quantity</Label>
          <Input
            id="lship_wholesale_min_order_quantity"
            type="number"
            min={0}
            value={settings.lship_wholesale_min_order_quantity || '0'}
            onChange={(e) => onChange('lship_wholesale_min_order_quantity', e.target.value)}
          />
          <p className="text-xs text-muted-foreground">
            Minimum total units of wholesale products required to checkout. Set to 0 to disable. Mixed carts count only wholesale item quantities.
          </p>
        </div>
        <div className="flex items-center justify-between rounded-lg border p-4">
          <div className="space-y-1 pr-4">
            <Label htmlFor="lship_wholesale_allow_backorders">Allow backorders</Label>
            <p className="text-xs text-muted-foreground">
              Let wholesale customers place orders when keys are out of stock or below the ordered quantity. Restock keys later, then deliver as usual.
            </p>
          </div>
          <Switch
            id="lship_wholesale_allow_backorders"
            checked={yesNo('lship_wholesale_allow_backorders')}
            onCheckedChange={(v) => onChange('lship_wholesale_allow_backorders', v ? 'yes' : 'no')}
          />
        </div>
      </SettingsSection>

      <SettingsSection
        title="Storefront pages"
        description="Create or link the wholesale apply and catalog pages used on your storefront."
      >
        <div className="md:col-span-2 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="space-y-1">
              <p className="text-sm font-medium text-foreground">Auto-generate wholesale pages</p>
              <p className="text-xs text-muted-foreground">
                Creates a Wholesale Application page and a Wholesale catalog page with the correct shortcodes, then selects them below.
              </p>
            </div>
            <Button type="button" variant="outline" onClick={generatePages} disabled={generating}>
              <FilePlus2 className="mr-2 h-4 w-4" />
              {generating ? 'Generating…' : 'Generate wholesale pages'}
            </Button>
          </div>
        </div>

        <div className="space-y-2">
          <Label>Apply page</Label>
          <Select
            value={settings.lship_wholesale_apply_page_id || '0'}
            onValueChange={(v) => onChange('lship_wholesale_apply_page_id', v === '0' ? '' : v)}
          >
            <SelectTrigger>
              <SelectValue placeholder="Select a page" />
            </SelectTrigger>
            <SelectContent>
              {pages.map((page) => (
                <SelectItem key={page.id || '0'} value={page.id || '0'}>
                  {page.title}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-2">
          <Label>Catalog page</Label>
          <Select
            value={settings.lship_wholesale_catalog_page_id || '0'}
            onValueChange={(v) => onChange('lship_wholesale_catalog_page_id', v === '0' ? '' : v)}
          >
            <SelectTrigger>
              <SelectValue placeholder="Select a page" />
            </SelectTrigger>
            <SelectContent>
              {pages.map((page) => (
                <SelectItem key={`catalog-${page.id || '0'}`} value={page.id || '0'}>
                  {page.title}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </SettingsSection>

      <SettingsSection
        title="Checkout payments"
        description="Control which payment methods wholesale customers can use when their cart contains wholesale products."
        singleColumn
      >
        <div className="space-y-2">
          <Label>Payment method mode</Label>
          <Select
            value={paymentMode}
            onValueChange={(value) => onChange('lship_wholesale_payment_mode', value)}
          >
            <SelectTrigger>
              <SelectValue placeholder="Select payment mode" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All payment methods</SelectItem>
              <SelectItem value="wallet">TeraWallet preferred</SelectItem>
              <SelectItem value="custom">Custom payment methods</SelectItem>
            </SelectContent>
          </Select>
          <p className="text-xs text-muted-foreground">
            Applies only to logged-in wholesale customers checking out with wholesale catalog products in the cart.
            When TeraWallet is active, wallet balance is used first: full balance pays by wallet, partial balance is
            applied automatically and the rest uses another method, and zero balance hides wallet.
          </p>
        </div>

        {paymentMode === 'wallet' && (
          <div className="md:col-span-2 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            {hasWalletGateway ? (
              <p>
                Wholesale checkout prefers TeraWallet
                {walletGateways.length === 1 ? ` (${walletGateways[0].title})` : ''}.
                If balance is not enough, other active payment methods stay available for the remaining amount.
              </p>
            ) : (
              <p>
                No TeraWallet gateway was detected. Install and enable the TeraWallet plugin, or choose another payment mode.
              </p>
            )}
          </div>
        )}

        {paymentMode === 'custom' && (
          <div className="md:col-span-2 space-y-3 rounded-lg border p-4">
            <p className="text-sm font-medium text-foreground">Allowed payment methods</p>
            {paymentGateways.length === 0 ? (
              <p className="text-sm text-muted-foreground">No WooCommerce payment gateways are available.</p>
            ) : (
              <div className="grid gap-3 sm:grid-cols-2">
                {paymentGateways.map((gateway) => {
                  const checked = selectedGateways.includes(gateway.id);
                  const inputId = `wholesale-gateway-${gateway.id}`;

                  return (
                    <label
                      key={gateway.id}
                      htmlFor={inputId}
                      className="flex items-start gap-3 rounded-md border p-3"
                    >
                      <Checkbox
                        id={inputId}
                        checked={checked}
                        onCheckedChange={(value) => toggleGateway(gateway.id, value === true)}
                      />
                      <span className="space-y-1">
                        <span className="block text-sm font-medium text-foreground">{gateway.title}</span>
                        <span className="block text-xs text-muted-foreground">
                          {gateway.is_wallet ? 'TeraWallet' : gateway.id}
                          {!gateway.enabled ? ' · Disabled in WooCommerce' : ''}
                        </span>
                      </span>
                    </label>
                  );
                })}
              </div>
            )}
          </div>
        )}
      </SettingsSection>

      <SettingsSection
        title="Notifications"
        description="Optional email alerts for new wholesale applications."
      >
        <div className="md:col-span-2 space-y-2">
          <Label htmlFor="lship_wholesale_notify_email">New application email</Label>
          <Input
            id="lship_wholesale_notify_email"
            type="email"
            placeholder={settings.admin_email || 'admin@example.com'}
            value={settings.lship_wholesale_notify_email || ''}
            onChange={(e) => onChange('lship_wholesale_notify_email', e.target.value)}
          />
          <p className="text-xs text-muted-foreground">
            Leave empty to use the WordPress admin email. A notification is sent when someone submits a wholesale application.
          </p>
        </div>
      </SettingsSection>
    </div>
  );
}
