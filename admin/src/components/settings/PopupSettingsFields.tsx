import { ColorField } from '@/components/settings/ColorField';
import { SettingsSection } from '@/components/settings/SettingsSection';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface PopupSettingsFieldsProps {
  settings: Record<string, string>;
  onChange: (key: string, value: string) => void;
}

function TextField({
  id,
  label,
  description,
  value,
  placeholder,
  onChange,
  multiline = false,
}: {
  id: string;
  label: string;
  description?: string;
  value: string;
  placeholder?: string;
  onChange: (value: string) => void;
  multiline?: boolean;
}) {
  return (
    <div className="space-y-2 rounded-lg border border-slate-200 bg-white p-4 md:col-span-2">
      <Label htmlFor={id}>{label}</Label>
      {description ? <p className="text-xs text-muted-foreground">{description}</p> : null}
      {multiline ? (
        <textarea
          id={id}
          rows={3}
          placeholder={placeholder}
          value={value}
          className="flex min-h-[80px] w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          onChange={(e) => onChange(e.target.value)}
        />
      ) : (
        <Input id={id} placeholder={placeholder} value={value} onChange={(e) => onChange(e.target.value)} />
      )}
    </div>
  );
}

export function PopupSettingsFields({ settings, onChange }: PopupSettingsFieldsProps) {
  return (
    <div className="space-y-8">
      <SettingsSection
        title="Confirm before fetching keys"
        description="Shown when a customer clicks Get Key on an order or My Keys page. Use {product} for the product name."
      >
        <TextField
          id="ls_sw_confirm_title"
          label="Confirm popup title"
          placeholder="Get license keys?"
          value={settings.ls_sw_confirm_title || ''}
          onChange={(v) => onChange('ls_sw_confirm_title', v)}
        />
        <TextField
          id="ls_sw_confirm_text"
          label="Confirm popup text"
          placeholder="We will fetch your license keys for {product}. Continue?"
          value={settings.ls_sw_confirm_text || ''}
          onChange={(v) => onChange('ls_sw_confirm_text', v)}
          multiline
        />
        <TextField
          id="ls_sw_confirm_btn"
          label="Confirm button label"
          placeholder="Yes, get keys"
          value={settings.ls_sw_confirm_btn || ''}
          onChange={(v) => onChange('ls_sw_confirm_btn', v)}
        />
        <TextField
          id="ls_sw_cancel_btn"
          label="Cancel button label"
          placeholder="Cancel"
          value={settings.ls_sw_cancel_btn || ''}
          onChange={(v) => onChange('ls_sw_cancel_btn', v)}
        />
        <ColorField
          id="ls_sw_confirm_color"
          label="Confirm button color"
          value={settings.ls_sw_confirm_color || ''}
          fallback="#4f46e5"
          onChange={(v) => onChange('ls_sw_confirm_color', v)}
        />
        <ColorField
          id="ls_sw_cancel_color"
          label="Cancel button color"
          value={settings.ls_sw_cancel_color || ''}
          fallback="#6b7280"
          onChange={(v) => onChange('ls_sw_cancel_color', v)}
        />
      </SettingsSection>

      <SettingsSection
        title="Bulk fetch (Get All Keys)"
        description="Shown on the order page when fetching all remaining license keys at once."
      >
        <TextField
          id="ls_sw_bulk_title"
          label="Bulk confirm title"
          placeholder="Fetch All Keys?"
          value={settings.ls_sw_bulk_title || ''}
          onChange={(v) => onChange('ls_sw_bulk_title', v)}
        />
        <TextField
          id="ls_sw_bulk_text"
          label="Bulk confirm text"
          placeholder="This will retrieve all license keys for this order."
          value={settings.ls_sw_bulk_text || ''}
          onChange={(v) => onChange('ls_sw_bulk_text', v)}
          multiline
        />
        <TextField
          id="ls_sw_bulk_confirm_btn"
          label="Bulk confirm button"
          placeholder="Yes, fetch all"
          value={settings.ls_sw_bulk_confirm_btn || ''}
          onChange={(v) => onChange('ls_sw_bulk_confirm_btn', v)}
        />
        <TextField
          id="ls_sw_bulk_cancel_btn"
          label="Bulk cancel button"
          placeholder="Cancel"
          value={settings.ls_sw_bulk_cancel_btn || ''}
          onChange={(v) => onChange('ls_sw_bulk_cancel_btn', v)}
        />
        <TextField
          id="ls_sw_bulk_done_title"
          label="Bulk complete title"
          placeholder="Done!"
          value={settings.ls_sw_bulk_done_title || ''}
          onChange={(v) => onChange('ls_sw_bulk_done_title', v)}
        />
        <TextField
          id="ls_sw_bulk_done_text"
          label="Bulk complete text"
          placeholder="All license keys have been processed."
          value={settings.ls_sw_bulk_done_text || ''}
          onChange={(v) => onChange('ls_sw_bulk_done_text', v)}
        />
      </SettingsSection>

      <SettingsSection
        title="License keys modal"
        description="Shown after keys are successfully fetched."
      >
        <TextField
          id="ls_sw_view_title"
          label="Single key modal title"
          placeholder="Your License Key"
          value={settings.ls_sw_view_title || ''}
          onChange={(v) => onChange('ls_sw_view_title', v)}
        />
        <TextField
          id="ls_sw_view_title_many"
          label="Multiple keys modal title"
          placeholder="Your License Keys"
          value={settings.ls_sw_view_title_many || ''}
          onChange={(v) => onChange('ls_sw_view_title_many', v)}
        />
        <TextField
          id="ls_sw_view_copy_all"
          label="Copy all button label"
          placeholder="Copy All"
          value={settings.ls_sw_view_copy_all || ''}
          onChange={(v) => onChange('ls_sw_view_copy_all', v)}
        />
        <TextField
          id="ls_sw_view_close"
          label="Close button label"
          placeholder="Close"
          value={settings.ls_sw_view_close || ''}
          onChange={(v) => onChange('ls_sw_view_close', v)}
        />
      </SettingsSection>
    </div>
  );
}
