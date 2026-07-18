import { useState } from 'react';
import { ExternalLink, FilePlus2, Headphones, LifeBuoy, Ticket } from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import type { PageChoice } from '@/components/settings/WholesaleSettingsFields';

type GeneratePagesResponse = {
  message: string;
  open_page_id: number;
  manage_page_id: number;
  open_page_url?: string;
  manage_page_url?: string;
  pages?: PageChoice[];
};

type SupportSettingsFieldsProps = {
  settings: Record<string, string>;
  pages: PageChoice[];
  onChange: (key: string, value: string) => void;
  onPagesGenerated?: (pages: PageChoice[]) => void;
};

function pageLabel(pages: PageChoice[], id: string) {
  const match = pages.find((page) => page.id === id);
  return match?.title || 'Not selected';
}

export function SupportSettingsFields({
  settings,
  pages,
  onChange,
  onPagesGenerated,
}: SupportSettingsFieldsProps) {
  const [generating, setGenerating] = useState(false);
  const yesNo = (key: string) => settings[key] === 'yes';
  const enabled = yesNo('lship_support_enabled');
  const myAccount = yesNo('lship_support_my_account');
  const openId = settings.lship_support_open_page_id || '0';
  const manageId = settings.lship_support_manage_page_id || '0';

  const generatePages = async () => {
    setGenerating(true);
    try {
      const result = await apiRequest<GeneratePagesResponse>('support/generate-pages', {
        method: 'POST',
        body: JSON.stringify({}),
      });

      onChange('lship_support_open_page_id', String(result.open_page_id));
      onChange('lship_support_manage_page_id', String(result.manage_page_id));

      if (result.pages) {
        onPagesGenerated?.(result.pages);
      }

      toast.success(result.message || 'Support pages generated successfully.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Could not generate support pages.');
    } finally {
      setGenerating(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-slate-100 p-5">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div className="max-w-2xl space-y-2">
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="secondary">Customer portal</Badge>
              <Badge variant="outline">SaaS agent desk</Badge>
              {enabled ? (
                <Badge className="bg-emerald-600 hover:bg-emerald-600">Enabled</Badge>
              ) : (
                <Badge variant="destructive">Disabled</Badge>
              )}
            </div>
            <h3 className="text-base font-semibold text-foreground">Storefront support tickets</h3>
            <p className="text-sm text-muted-foreground">
              Customers open and manage tickets on your store. Agents reply in LicenseSender with order/license context, email piping, and AI drafts.
            </p>
          </div>
          <div className="grid grid-cols-3 gap-2 text-center text-xs text-slate-600">
            <div className="rounded-xl border border-slate-200 bg-white px-3 py-2">
              <LifeBuoy className="mx-auto mb-1 h-4 w-4 text-slate-500" />
              Open
            </div>
            <div className="rounded-xl border border-slate-200 bg-white px-3 py-2">
              <Ticket className="mx-auto mb-1 h-4 w-4 text-slate-500" />
              Manage
            </div>
            <div className="rounded-xl border border-slate-200 bg-white px-3 py-2">
              <Headphones className="mx-auto mb-1 h-4 w-4 text-slate-500" />
              Agent desk
            </div>
          </div>
        </div>
      </div>

      <div className="grid gap-6 xl:grid-cols-5">
        <div className="space-y-5 xl:col-span-3">
          <div className="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5">
            <div className="space-y-1 pr-4">
              <Label htmlFor="lship_support_enabled">Enable support</Label>
              <p className="text-xs text-muted-foreground">
                When disabled, support shortcodes show a notice instead of ticket forms.
              </p>
            </div>
            <Switch
              id="lship_support_enabled"
              checked={enabled}
              onCheckedChange={(v) => onChange('lship_support_enabled', v ? 'yes' : 'no')}
            />
          </div>

          <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
            <div>
              <h4 className="text-sm font-semibold text-foreground">Storefront pages</h4>
              <p className="mt-1 text-xs text-muted-foreground">
                Create or link the pages customers use to open and manage support requests.
              </p>
            </div>

            <div className="flex flex-col gap-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
              <div className="space-y-1">
                <p className="text-sm font-medium text-foreground">Auto-generate support pages</p>
                <p className="text-xs text-muted-foreground">
                  Creates Open Ticket and My Tickets pages with the correct shortcodes, then selects them below.
                </p>
              </div>
              <Button type="button" variant="outline" onClick={generatePages} disabled={generating}>
                <FilePlus2 className="mr-2 h-4 w-4" />
                {generating ? 'Generating…' : 'Generate pages'}
              </Button>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>Open ticket page</Label>
                <Select
                  value={openId || '0'}
                  onValueChange={(v) => onChange('lship_support_open_page_id', v === '0' ? '' : v)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select page" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="0">— Select —</SelectItem>
                    {pages.map((page) => (
                      <SelectItem key={page.id} value={page.id}>
                        {page.title}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">Shortcode: [ls_support_open]</p>
              </div>

              <div className="space-y-2">
                <Label>Manage tickets page</Label>
                <Select
                  value={manageId || '0'}
                  onValueChange={(v) => onChange('lship_support_manage_page_id', v === '0' ? '' : v)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select page" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="0">— Select —</SelectItem>
                    {pages.map((page) => (
                      <SelectItem key={page.id} value={page.id}>
                        {page.title}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">Shortcode: [ls_support_manage]</p>
              </div>
            </div>
          </div>

          <div className="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5">
            <div className="space-y-1 pr-4">
              <Label htmlFor="lship_support_auth_my_account">Login / register via My Account</Label>
              <p className="text-xs text-muted-foreground">
                Recommended when the store uses Cloudflare Turnstile, Google reCAPTCHA, or other captcha plugins. Customers sign in on WooCommerce My Account, then return to support.
              </p>
            </div>
            <Switch
              id="lship_support_auth_my_account"
              checked={settings.lship_support_auth_my_account !== 'no'}
              onCheckedChange={(v) => onChange('lship_support_auth_my_account', v ? 'yes' : 'no')}
            />
          </div>

          <div className="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5">
            <div className="space-y-1 pr-4">
              <Label htmlFor="lship_support_my_account">Add to WooCommerce My Account</Label>
              <p className="text-xs text-muted-foreground">
                Shows a Support Tickets item in the customer My Account menu and opens the manage portal.
              </p>
            </div>
            <Switch
              id="lship_support_my_account"
              checked={myAccount}
              onCheckedChange={(v) => onChange('lship_support_my_account', v ? 'yes' : 'no')}
            />
          </div>
        </div>

        <div className="xl:col-span-2">
          <div className="overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 shadow-sm xl:sticky xl:top-4">
            <div className="border-b border-slate-200/80 bg-white/90 px-4 py-3">
              <p className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Customer flow</p>
              <p className="mt-1 text-sm text-slate-600">How shoppers use support on your store</p>
            </div>
            <div className="space-y-3 p-4">
              <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div className="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-900">
                  <LifeBuoy className="h-4 w-4 text-slate-500" />
                  Open a ticket
                </div>
                <p className="text-xs text-slate-500">{pageLabel(pages, openId)}</p>
                <p className="mt-2 text-sm text-slate-600">
                  Customer describes the issue, attaches files, and links an order or license key.
                </p>
              </div>
              <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div className="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-900">
                  <Ticket className="h-4 w-4 text-slate-500" />
                  Manage tickets
                </div>
                <p className="text-xs text-slate-500">{pageLabel(pages, manageId)}</p>
                <p className="mt-2 text-sm text-slate-600">
                  Track status, read replies, download attachments, and continue the conversation.
                </p>
              </div>
              <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div className="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-900">
                  <Headphones className="h-4 w-4 text-slate-500" />
                  Agent desk
                </div>
                <p className="mt-2 text-sm text-slate-600">
                  You reply in LicenseSender with departments, SLA, CSAT, and email piping.
                </p>
              </div>
              {myAccount ? (
                <div className="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                  <ExternalLink className="h-3.5 w-3.5" />
                  My Account menu will include Support Tickets
                </div>
              ) : null}
              {settings.lship_support_auth_my_account !== 'no' ? (
                <div className="flex items-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-800">
                  <ExternalLink className="h-3.5 w-3.5" />
                  Login/register uses My Account (captcha-safe)
                </div>
              ) : null}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
