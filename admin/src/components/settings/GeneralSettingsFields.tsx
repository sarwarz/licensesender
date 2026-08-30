import {
  BookOpen,
  CalendarClock,
  CheckCircle2,
  Download,
  Layers,
  Mail,
  PackageCheck,
  RotateCcw,
  type LucideIcon,
} from 'lucide-react';
import { SettingsSection } from '@/components/settings/SettingsSection';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

type GeneralSettingsFieldsProps = {
  settings: Record<string, string>;
  onChange: (key: string, value: string) => void;
};

type ToggleItem = {
  key: string;
  title: string;
  description: string;
  icon: LucideIcon;
};

const ORDER_TOGGLES: ToggleItem[] = [
  {
    key: 'lship_autocomplete_order',
    title: 'Auto-complete orders',
    description: 'Mark WooCommerce orders as completed once LicenseSender finishes delivering keys.',
    icon: PackageCheck,
  },
  {
    key: 'lship_send_email_after_redeem',
    title: 'Email after redemption',
    description: 'Send the license email only after the customer redeems keys, instead of at purchase.',
    icon: Mail,
  },
];

const CATALOG_TOGGLES: ToggleItem[] = [
  {
    key: 'lship_enable_variation_support',
    title: 'Variation support',
    description: 'Map LicenseSender products to WooCommerce variations independently.',
    icon: Layers,
  },
  {
    key: 'lship_enable_manage_downloads',
    title: 'Manage download links',
    description: 'Show the Download Links menu so you can edit product download URLs from LicenseSender.',
    icon: Download,
  },
  {
    key: 'lship_enable_manage_activation_guides',
    title: 'Manage activation guides',
    description: 'Show the Activation Guides menu for editing install and activation instructions.',
    icon: BookOpen,
  },
];

function ToggleRow({
  item,
  checked,
  onCheckedChange,
}: {
  item: ToggleItem;
  checked: boolean;
  onCheckedChange: (value: boolean) => void;
}) {
  const Icon = item.icon;

  return (
    <div
      className={cn(
        'group flex items-start gap-4 rounded-xl border bg-white p-4 transition-colors',
        checked ? 'border-slate-300 shadow-sm' : 'border-slate-200 hover:border-slate-300'
      )}
    >
      <div
        className={cn(
          'mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border',
          checked ? 'border-slate-900/10 bg-slate-900 text-white' : 'border-slate-200 bg-slate-50 text-slate-600'
        )}
      >
        <Icon className="h-4 w-4" aria-hidden />
      </div>

      <div className="min-w-0 flex-1 space-y-1">
        <div className="flex flex-wrap items-center gap-2">
          <Label htmlFor={item.key} className="cursor-pointer text-sm font-semibold text-foreground">
            {item.title}
          </Label>
          <Badge
            variant="outline"
            className={cn(
              'border-0 text-[10px] font-semibold uppercase tracking-wide',
              checked ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'
            )}
          >
            {checked ? 'On' : 'Off'}
          </Badge>
        </div>
        <p className="max-w-2xl text-xs leading-relaxed text-muted-foreground">{item.description}</p>
      </div>

      <Switch
        id={item.key}
        checked={checked}
        onCheckedChange={onCheckedChange}
        className="mt-1 shrink-0"
        aria-label={item.title}
      />
    </div>
  );
}

function formatDisplayDate(value: string): string {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
  const date = new Date(`${value}T12:00:00`);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export function GeneralSettingsFields({ settings, onChange }: GeneralSettingsFieldsProps) {
  const yesNo = (key: string) => settings[key] === 'yes';
  const enabledCount = [...ORDER_TOGGLES, ...CATALOG_TOGGLES].filter((item) => yesNo(item.key)).length;
  const activationDate = settings.lship_activated_at || '';
  const deliveryStartDate = settings.lship_delivery_start_date || activationDate;
  const isActivationDefault = Boolean(activationDate) && deliveryStartDate === activationDate;

  return (
    <div className="space-y-8">
      <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div className="border-b border-slate-200 bg-[linear-gradient(135deg,#f8fafc_0%,#ffffff_55%,#f1f5f9_100%)] px-5 py-5 sm:px-6">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-start gap-3">
              <div className="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white shadow-sm">
                <CheckCircle2 className="h-5 w-5 text-slate-700" aria-hidden />
              </div>
              <div>
                <p className="text-sm font-semibold text-foreground">Store behavior</p>
                <p className="mt-1 max-w-xl text-xs leading-relaxed text-muted-foreground">
                  Control how LicenseSender handles completed orders, email timing, and optional catalog
                  tools in WordPress admin.
                </p>
              </div>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-center shadow-sm sm:min-w-[120px]">
              <p className="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Enabled</p>
              <p className="mt-0.5 text-2xl font-semibold tabular-nums text-foreground">
                {enabledCount}
                <span className="text-sm font-medium text-muted-foreground">
                  /{[...ORDER_TOGGLES, ...CATALOG_TOGGLES].length}
                </span>
              </p>
            </div>
          </div>
        </div>
      </div>

      <SettingsSection
        title="Order fulfillment"
        description="Decide what happens after LicenseSender delivers keys to a customer order."
        singleColumn
      >
        {ORDER_TOGGLES.map((item) => (
          <ToggleRow
            key={item.key}
            item={item}
            checked={yesNo(item.key)}
            onCheckedChange={(v) => onChange(item.key, v ? 'yes' : 'no')}
          />
        ))}

        <div className="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="flex items-start gap-4 border-b border-slate-100 bg-[linear-gradient(135deg,#f8fafc_0%,#ffffff_70%)] px-4 py-4 sm:px-5">
            <div className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 shadow-sm">
              <CalendarClock className="h-4 w-4" aria-hidden />
            </div>
            <div className="min-w-0 flex-1 space-y-1">
              <div className="flex flex-wrap items-center gap-2">
                <Label htmlFor="lship_delivery_start_date" className="text-sm font-semibold text-foreground">
                  Delivery start date
                </Label>
                <Badge
                  variant="outline"
                  className={cn(
                    'border-0 text-[10px] font-semibold uppercase tracking-wide',
                    isActivationDefault ? 'bg-sky-50 text-sky-700' : 'bg-amber-50 text-amber-700'
                  )}
                >
                  {isActivationDefault ? 'Activation default' : 'Custom'}
                </Badge>
              </div>
              <p className="max-w-2xl text-xs leading-relaxed text-muted-foreground">
                Orders completed before this date cannot fetch new LicenseSender keys. Defaults to the
                plugin activation date so older other-system orders are not redelivered.
              </p>
            </div>
          </div>

          <div className="space-y-3 px-4 py-4 sm:px-5">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div className="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-start sm:gap-3">
                <DatePicker
                  id="lship_delivery_start_date"
                  value={deliveryStartDate}
                  onChange={(next) => onChange('lship_delivery_start_date', next)}
                  placeholder="Select delivery start date"
                />
                {activationDate && !isActivationDefault ? (
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-9 shrink-0 gap-1.5 px-2.5 text-slate-600"
                    onClick={() => onChange('lship_delivery_start_date', activationDate)}
                  >
                    <RotateCcw className="h-3.5 w-3.5" aria-hidden />
                    Use activation date
                  </Button>
                ) : null}
              </div>
              {activationDate ? (
                <p className="shrink-0 pt-2 text-xs text-muted-foreground sm:pt-2.5">
                  Activated{' '}
                  <span className="font-medium text-slate-700">{formatDisplayDate(activationDate)}</span>
                </p>
              ) : null}
            </div>
          </div>
        </div>
      </SettingsSection>

      <SettingsSection
        title="Catalog tools"
        description="Optional LicenseSender menus and mapping features for your product catalog."
        singleColumn
      >
        {CATALOG_TOGGLES.map((item) => (
          <ToggleRow
            key={item.key}
            item={item}
            checked={yesNo(item.key)}
            onCheckedChange={(v) => onChange(item.key, v ? 'yes' : 'no')}
          />
        ))}
      </SettingsSection>
    </div>
  );
}
