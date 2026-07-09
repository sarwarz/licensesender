import { ColorField } from '@/components/settings/ColorField';
import { SettingsSection } from '@/components/settings/SettingsSection';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface DesignSettingsFieldsProps {
  settings: Record<string, string>;
  onChange: (key: string, value: string) => void;
}

export function DesignSettingsFields({ settings, onChange }: DesignSettingsFieldsProps) {
  return (
    <div className="space-y-8">
      <SettingsSection
        title="Brand colors"
        description="Primary colors used on Get Key buttons, pagination, and brand gradients."
      >
        <ColorField
          id="ls_brand"
          label="Primary color"
          description="Gradient start for primary action buttons."
          value={settings.ls_brand || ''}
          fallback="#4f46e5"
          onChange={(v) => onChange('ls_brand', v)}
        />
        <ColorField
          id="ls_brand_2"
          label="Primary color (secondary)"
          description="Gradient end for primary action buttons."
          value={settings.ls_brand_2 || ''}
          fallback="#6366f1"
          onChange={(v) => onChange('ls_brand_2', v)}
        />
        <ColorField
          id="ls_ring"
          label="Focus ring color"
          description="Keyboard focus outlines. Transparency is applied automatically."
          value={settings.ls_ring || ''}
          fallback="#6366f1"
          onChange={(v) => onChange('ls_ring', v)}
        />
      </SettingsSection>

      <SettingsSection
        title="Action button colors"
        description="Colors for View Key, Download, and Activation Guide buttons on My Keys and order pages."
      >
        <ColorField
          id="ls_success"
          label="View Key color (start)"
          value={settings.ls_success || ''}
          fallback="#059669"
          onChange={(v) => onChange('ls_success', v)}
        />
        <ColorField
          id="ls_success_2"
          label="View Key color (end)"
          value={settings.ls_success_2 || ''}
          fallback="#10b981"
          onChange={(v) => onChange('ls_success_2', v)}
        />
        <ColorField
          id="ls_blue_600"
          label="Download color (start)"
          value={settings.ls_blue_600 || ''}
          fallback="#2563eb"
          onChange={(v) => onChange('ls_blue_600', v)}
        />
        <ColorField
          id="ls_blue_500"
          label="Download color (end)"
          value={settings.ls_blue_500 || ''}
          fallback="#3b82f6"
          onChange={(v) => onChange('ls_blue_500', v)}
        />
        <ColorField
          id="ls_amber_500"
          label="Guide color (start)"
          value={settings.ls_amber_500 || ''}
          fallback="#f59e0b"
          onChange={(v) => onChange('ls_amber_500', v)}
        />
        <ColorField
          id="ls_amber_400"
          label="Guide color (end)"
          value={settings.ls_amber_400 || ''}
          fallback="#fbbf24"
          onChange={(v) => onChange('ls_amber_400', v)}
        />
      </SettingsSection>

      <SettingsSection
        title="License key display"
        description="Colors for license key blocks shown in popups and modals."
      >
        <ColorField
          id="ls_code_bg"
          label="Code background"
          value={settings.ls_code_bg || ''}
          fallback="#1e1e2e"
          onChange={(v) => onChange('ls_code_bg', v)}
        />
        <ColorField
          id="ls_code_fg"
          label="Code text"
          value={settings.ls_code_fg || ''}
          fallback="#cdd6f4"
          onChange={(v) => onChange('ls_code_fg', v)}
        />
        <ColorField
          id="ls_code_border"
          label="Code border"
          value={settings.ls_code_border || ''}
          fallback="#313244"
          onChange={(v) => onChange('ls_code_border', v)}
        />
        <ColorField
          id="ls_code_accent"
          label="Code accent"
          description="Selection highlight inside key blocks."
          value={settings.ls_code_accent || ''}
          fallback="#89b4fa"
          onChange={(v) => onChange('ls_code_accent', v)}
        />
      </SettingsSection>

      <SettingsSection
        title="Email branding"
        description="Colors and logo used in customer license emails."
      >
        <ColorField
          id="lship_brand_color"
          label="Email brand color"
          description="Badge and primary accents in license emails."
          value={settings.lship_brand_color || ''}
          fallback="#4F46E5"
          onChange={(v) => onChange('lship_brand_color', v)}
        />
        <ColorField
          id="lship_accent_color"
          label="Email accent color"
          description="Guide button color in license emails."
          value={settings.lship_accent_color || ''}
          fallback="#0EA5E9"
          onChange={(v) => onChange('lship_accent_color', v)}
        />
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
    </div>
  );
}
