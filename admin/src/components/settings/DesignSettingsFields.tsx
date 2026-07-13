import { ColorField } from '@/components/settings/ColorField';
import { SettingsSection } from '@/components/settings/SettingsSection';
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
import { cn } from '@/lib/utils';

export type DesignPreset = {
  id: string;
  label: string;
  description: string;
  brand: string;
  accent: string;
  swatches: string[];
};

interface DesignSettingsFieldsProps {
  settings: Record<string, string>;
  presets?: DesignPreset[];
  onChange: (key: string, value: string) => void;
}

export function DesignSettingsFields({
  settings,
  presets = [],
  onChange,
}: DesignSettingsFieldsProps) {
  const selectedPreset = settings.ls_theme_preset || 'indigo';
  const emailSync = settings.ls_email_sync_brand !== 'no';

  const applyPreset = (preset: DesignPreset) => {
    onChange('ls_theme_preset', preset.id);
    onChange('ls_brand', preset.brand);
    onChange('ls_accent', preset.accent);
  };

  return (
    <div className="space-y-8">
      <SettingsSection
        title="Theme"
        description="Pick a look for My Keys, order licenses, support, wholesale, and email. Individual button colors are generated for you."
        singleColumn
      >
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 md:col-span-2">
          {presets.map((preset) => {
            const active = selectedPreset === preset.id;
            return (
              <button
                key={preset.id}
                type="button"
                onClick={() => applyPreset(preset)}
                className={cn(
                  'rounded-xl border p-4 text-left transition-all',
                  active
                    ? 'border-primary ring-2 ring-primary/20 bg-primary/5'
                    : 'border-slate-200 hover:border-slate-300 bg-white',
                )}
              >
                <div className="mb-3 flex gap-1.5">
                  {preset.swatches.slice(0, 4).map((swatch) => (
                    <span
                      key={`${preset.id}-${swatch}`}
                      className="h-6 w-6 rounded-full border border-black/5 shadow-sm"
                      style={{ backgroundColor: swatch }}
                    />
                  ))}
                </div>
                <p className="text-sm font-semibold text-foreground">{preset.label}</p>
                <p className="mt-1 text-xs text-muted-foreground">{preset.description}</p>
              </button>
            );
          })}
        </div>
      </SettingsSection>

      <SettingsSection
        title="Brand"
        description="Your brand colors control primary buttons, focus rings, downloads, and accents across the storefront."
      >
        <ColorField
          id="ls_brand"
          label="Primary brand"
          description="Get Key buttons, pagination, and admin accent."
          value={settings.ls_brand || ''}
          fallback="#4f46e5"
          onChange={(v) => onChange('ls_brand', v)}
        />
        <ColorField
          id="ls_accent"
          label="Accent"
          description="Download / secondary actions and email highlight."
          value={settings.ls_accent || ''}
          fallback="#2563eb"
          onChange={(v) => onChange('ls_accent', v)}
        />
      </SettingsSection>

      <SettingsSection
        title="Shape & density"
        description="Control how rounded and spacious the plugin UI feels."
      >
        <div className="space-y-2 rounded-lg border border-slate-200 bg-white p-4">
          <Label>Corner radius</Label>
          <Select
            value={settings.ls_radius || 'md'}
            onValueChange={(v) => onChange('ls_radius', v)}
          >
            <SelectTrigger>
              <SelectValue placeholder="Radius" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="sm">Soft sharp</SelectItem>
              <SelectItem value="md">Rounded (default)</SelectItem>
              <SelectItem value="lg">Extra rounded</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-2 rounded-lg border border-slate-200 bg-white p-4">
          <Label>Density</Label>
          <Select
            value={settings.ls_density || 'comfortable'}
            onValueChange={(v) => onChange('ls_density', v)}
          >
            <SelectTrigger>
              <SelectValue placeholder="Density" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="comfortable">Comfortable</SelectItem>
              <SelectItem value="compact">Compact</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-2 rounded-lg border border-slate-200 bg-white p-4">
          <Label>License key block</Label>
          <Select
            value={settings.ls_code_style || 'dark'}
            onValueChange={(v) => onChange('ls_code_style', v)}
          >
            <SelectTrigger>
              <SelectValue placeholder="Code style" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="dark">Dark key block</SelectItem>
              <SelectItem value="light">Light key block</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </SettingsSection>

      <SettingsSection
        title="Email branding"
        description="Customer license emails follow the same system as the storefront."
        singleColumn
      >
        <div className="flex items-center justify-between rounded-lg border p-4 md:col-span-2">
          <div className="space-y-1 pr-4">
            <Label htmlFor="ls_email_sync_brand">Match storefront branding</Label>
            <p className="text-xs text-muted-foreground">
              Uses your primary brand and accent colors in license emails automatically.
            </p>
          </div>
          <Switch
            id="ls_email_sync_brand"
            checked={emailSync}
            onCheckedChange={(v) => onChange('ls_email_sync_brand', v ? 'yes' : 'no')}
          />
        </div>
        <div className="space-y-2 rounded-lg border border-slate-200 bg-white p-4 md:col-span-2">
          <Label htmlFor="lship_email_logo">Email logo URL</Label>
          <p className="text-xs text-muted-foreground">Leave empty to use your site icon.</p>
          <Input
            id="lship_email_logo"
            type="url"
            placeholder="https://example.com/logo.png"
            value={settings.lship_email_logo || ''}
            onChange={(e) => onChange('lship_email_logo', e.target.value)}
          />
        </div>
      </SettingsSection>

      <div className="rounded-xl border bg-muted/30 p-4">
        <p className="text-sm font-medium text-foreground">Live preview tokens</p>
        <p className="mt-1 text-xs text-muted-foreground">
          Success / warning roles come from the theme pack. Changing brand or accent updates primary CTAs site-wide after save.
        </p>
        <div className="mt-3 flex flex-wrap gap-2">
          {[
            settings.ls_brand || '#4f46e5',
            settings.ls_accent || '#2563eb',
          ].map((color) => (
            <span
              key={color}
              className="inline-flex items-center gap-2 rounded-full border bg-white px-3 py-1 text-xs font-mono"
            >
              <span className="h-3 w-3 rounded-full" style={{ backgroundColor: color }} />
              {color}
            </span>
          ))}
        </div>
      </div>
    </div>
  );
}
