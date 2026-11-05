import { AlertTriangle, Check, Copy, KeyRound, Monitor, Moon, Sun } from 'lucide-react';
import { ColorField } from '@/components/settings/ColorField';
import { SettingsSection } from '@/components/settings/SettingsSection';
import { cn } from '@/lib/utils';

/**
 * Settings → Design. Three choices style every customer screen (My Keys, order and
 * thank-you keys, support, wholesale); the storefront apps derive hover shades, focus
 * rings and readable button text from the brand colour themselves.
 */

export type BrandSwatch = { name: string; hex: string };

interface Props {
  settings: Record<string, string>;
  swatches?: BrandSwatch[];
  onChange: (key: string, value: string) => void;
}

const DEFAULT_BRAND = '#4f46e5';
const HEX = /^#[0-9a-f]{6}$/i;

const RADIUS: { id: string; label: string; card: string; ctl: string }[] = [
  { id: 'sm', label: 'Subtle', card: '8px', ctl: '6px' },
  { id: 'md', label: 'Rounded', card: '14px', ctl: '10px' },
  { id: 'lg', label: 'Soft', card: '20px', ctl: '14px' },
];

const MODES = [
  { id: 'auto', label: 'Match my theme', text: "Light or dark, following your theme's background.", icon: Monitor },
  { id: 'light', label: 'Always light', text: 'Best for themes with a light background.', icon: Sun },
  { id: 'dark', label: 'Always dark', text: 'Best for themes with a dark background.', icon: Moon },
] as const;

/* Same palette the storefront apps use (resources/public/support/styles.css). */
const SURFACES = {
  light: { page: '#f1f5f9', card: '#ffffff', subtle: '#f8fafc', line: '#e2e8f0', fg: '#0f172a', muted: '#475569' },
  dark: { page: '#020617', card: '#0f172a', subtle: '#162034', line: '#27344a', fg: '#f1f5f9', muted: '#a0aec4' },
};

function luminance(hex: string) {
  const [r, g, b] = [1, 3, 5].map((i) => {
    const c = parseInt(hex.slice(i, i + 2), 16) / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrast(a: string, b: string) {
  const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
  return (hi + 0.05) / (lo + 0.05);
}

/** Text colour the apps put on a brand-coloured button. */
function readableOn(hex: string) {
  return contrast(hex, '#ffffff') >= contrast(hex, '#0f172a') ? '#ffffff' : '#0f172a';
}

export function DesignSettingsFields({ settings, swatches = [], onChange }: Props) {
  const brand = HEX.test(settings.ls_brand || '') ? settings.ls_brand.toLowerCase() : DEFAULT_BRAND;
  const radius = RADIUS.find((r) => r.id === settings.ls_radius) ?? RADIUS[1];
  const mode = settings.ls_color_mode || 'auto';

  const onLight = contrast(brand, SURFACES.light.card);
  const onDark = contrast(brand, SURFACES.dark.card);
  const warnings = [
    mode !== 'dark' && onLight < 3 ? 'This colour is very light: links and outlines will be hard to see on light backgrounds.' : '',
    mode !== 'light' && onDark < 3 ? 'This colour is very dark: links and outlines will be hard to see in dark mode.' : '',
  ].filter(Boolean);

  return (
    <div className="grid gap-8 xl:grid-cols-[minmax(0,1fr)_400px]">
      <div className="space-y-8">
        <SettingsSection
          title="Brand colour"
          description="Buttons, links, focus rings and highlights on every customer screen. Hover shades and button text colour are worked out for you."
          singleColumn
        >
          <ColorField
            id="ls_brand"
            label="Brand colour"
            value={settings.ls_brand || ''}
            defaultValue={DEFAULT_BRAND}
            presets={swatches.map((s) => ({ value: s.hex, name: s.name }))}
            onChange={(v) => onChange('ls_brand', v)}
          />
          {warnings.map((text) => (
            <p key={text} className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
              <AlertTriangle className="mt-px h-3.5 w-3.5 shrink-0" />
              {text}
            </p>
          ))}
        </SettingsSection>

        <SettingsSection title="Corners" description="How rounded cards, buttons and fields are." singleColumn>
          <div className="grid grid-cols-3 gap-3" role="radiogroup" aria-label="Corners">
            {RADIUS.map((option) => {
              const active = option.id === radius.id;
              return (
                <button
                  key={option.id}
                  type="button"
                  role="radio"
                  aria-checked={active}
                  onClick={() => onChange('ls_radius', option.id)}
                  className={cn(
                    'flex flex-col items-center gap-3 rounded-xl border p-4 transition-all',
                    active ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-slate-200 bg-white hover:border-slate-300',
                  )}
                >
                  <span className="flex h-12 w-full items-end justify-center gap-1.5">
                    <span className="h-12 w-16 border-2 border-slate-300 bg-white" style={{ borderRadius: option.card }} />
                    <span className="h-6 w-10 self-center" style={{ borderRadius: option.ctl, background: brand }} />
                  </span>
                  <span className="text-sm font-medium text-foreground">{option.label}</span>
                </button>
              );
            })}
          </div>
        </SettingsSection>

        <SettingsSection title="Colour mode" description="Light or dark for My Keys, order keys, support and wholesale." singleColumn>
          <div className="grid gap-3 sm:grid-cols-3" role="radiogroup" aria-label="Colour mode">
            {MODES.map((option) => {
              const active = option.id === mode;
              return (
                <button
                  key={option.id}
                  type="button"
                  role="radio"
                  aria-checked={active}
                  onClick={() => onChange('ls_color_mode', option.id)}
                  className={cn(
                    'rounded-xl border p-4 text-left transition-all',
                    active ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-slate-200 bg-white hover:border-slate-300',
                  )}
                >
                  <span className="flex items-center justify-between">
                    <option.icon className={cn('h-4 w-4', active ? 'text-primary' : 'text-slate-500')} />
                    {active ? <Check className="h-4 w-4 text-primary" /> : null}
                  </span>
                  <span className="mt-2 block text-sm font-medium text-foreground">{option.label}</span>
                  <span className="mt-0.5 block text-xs text-muted-foreground">{option.text}</span>
                </button>
              );
            })}
          </div>
          <p className="text-xs text-muted-foreground">
            A support shortcode can still override this with <code className="rounded bg-slate-100 px-1 text-[11px]">theme="light"</code> or{' '}
            <code className="rounded bg-slate-100 px-1 text-[11px]">theme="dark"</code>.
          </p>
        </SettingsSection>

        <p className="text-xs text-muted-foreground">
          Customer emails follow WooCommerce’s email style (WooCommerce → Settings → Emails), like your other store emails.
        </p>
      </div>

      <div className="xl:sticky xl:top-4 xl:self-start">
        <Preview brand={brand} radius={radius} mode={mode} />
      </div>
    </div>
  );
}

function Preview({ brand, radius, mode }: { brand: string; radius: (typeof RADIUS)[number]; mode: string }) {
  const schemes: ('light' | 'dark')[] = mode === 'light' ? ['light'] : mode === 'dark' ? ['dark'] : ['light', 'dark'];

  return (
    <div className="space-y-3">
      <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Preview</p>
      {schemes.map((scheme) => (
        <SampleCard key={scheme} scheme={scheme} brand={brand} radius={radius} label={schemes.length > 1 ? (scheme === 'light' ? 'Light theme' : 'Dark theme') : ''} />
      ))}
      <p className="text-[11px] leading-relaxed text-muted-foreground">
        A sample of the My Keys card. Support and wholesale use the same colour, corners and mode.
      </p>
    </div>
  );
}

function SampleCard({
  scheme,
  brand,
  radius,
  label,
}: {
  scheme: 'light' | 'dark';
  brand: string;
  radius: (typeof RADIUS)[number];
  label: string;
}) {
  const s = SURFACES[scheme];
  const tint = (alpha: number) => `color-mix(in srgb, ${brand} ${alpha}%, transparent)`;

  return (
    <div className="rounded-xl p-3" style={{ background: s.page, border: `1px solid ${s.line}` }}>
      {label ? (
        <p className="mb-2 text-[10px] font-semibold uppercase tracking-wider" style={{ color: s.muted }}>
          {label}
        </p>
      ) : null}
      <div className="overflow-hidden" style={{ background: s.card, border: `1px solid ${s.line}`, borderRadius: radius.card }}>
        <div className="flex items-center gap-3 p-3.5">
          <span className="flex h-9 w-9 shrink-0 items-center justify-center" style={{ borderRadius: radius.ctl, background: tint(scheme === 'dark' ? 20 : 10), color: brand }}>
            <KeyRound className="h-4 w-4" />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block truncate text-[13px] font-semibold" style={{ color: s.fg }}>
              Windows 11 Pro
            </span>
            <span className="text-[11px]" style={{ color: s.muted }}>
              Order #1042 ·{' '}
              <span className="font-medium" style={{ color: brand }}>
                View order
              </span>
            </span>
          </span>
          <span
            className="inline-flex h-8 shrink-0 items-center px-3 text-[12px] font-medium shadow-sm"
            style={{ borderRadius: radius.ctl, background: brand, color: readableOn(brand) }}
          >
            Get my key
          </span>
        </div>
        <div className="px-3.5 pb-3.5">
          <div className="flex items-center justify-between gap-2 px-3 py-2" style={{ borderRadius: radius.ctl, background: s.subtle, border: `1px solid ${s.line}` }}>
            <code className="truncate font-mono text-[11px]" style={{ color: s.fg }}>
              7XK2M-QP9RT-4HVWB-3CJ8D
            </code>
            <Copy className="h-3.5 w-3.5 shrink-0" style={{ color: s.muted }} />
          </div>
          <div className="mt-2.5 flex items-center gap-2">
            <span
              className="h-8 flex-1 px-3 text-[12px] leading-8"
              style={{ borderRadius: radius.ctl, border: `1px solid ${brand}`, boxShadow: `0 0 0 3px ${tint(18)}`, background: s.card, color: s.muted }}
            >
              Search keys…
            </span>
            <span className="rounded-full px-2 py-0.5 text-[10px] font-semibold" style={{ background: tint(scheme === 'dark' ? 22 : 12), color: brand }}>
              Delivered
            </span>
          </div>
        </div>
      </div>
    </div>
  );
}
