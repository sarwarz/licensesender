import { useMemo, useState } from 'react';
import { Check, Copy, KeyRound, Layers3, MessageSquareText, RotateCcw } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';

interface PopupSettingsFieldsProps {
  settings: Record<string, string>;
  onChange: (key: string, value: string) => void;
}

type PopupScene = 'confirm' | 'bulk' | 'view';

const SAMPLE_PRODUCT = 'Windows 11 Pro';

const DEFAULTS: Record<string, string> = {
  ls_sw_confirm_title: 'Get license keys?',
  ls_sw_confirm_text: 'We will fetch your license keys for {product}. Continue?',
  ls_sw_confirm_btn: 'Yes, get keys',
  ls_sw_cancel_btn: 'Cancel',
  ls_sw_confirm_color: '#4f46e5',
  ls_sw_cancel_color: '#6b7280',
  ls_sw_bulk_title: 'Fetch All Keys?',
  ls_sw_bulk_text: 'This will retrieve all license keys for this order.',
  ls_sw_bulk_confirm_btn: 'Yes, fetch all',
  ls_sw_bulk_cancel_btn: 'Cancel',
  ls_sw_bulk_done_title: 'Done!',
  ls_sw_bulk_done_text: 'All license keys have been processed.',
  ls_sw_view_title: 'Your License Key',
  ls_sw_view_title_many: 'Your License Keys',
  ls_sw_view_copy_all: 'Copy All',
  ls_sw_view_close: 'Close',
};

const COLOR_PRESETS = ['#0f172a', '#1d4ed8', '#0f766e', '#b45309', '#be123c', '#4f46e5', '#6b7280'];

function replaceProduct(text: string) {
  return (text || '').replace(/\{product\}/gi, SAMPLE_PRODUCT);
}

function Field({
  id,
  label,
  hint,
  value,
  placeholder,
  onChange,
  multiline = false,
  token,
  onInsertToken,
}: {
  id: string;
  label: string;
  hint?: string;
  value: string;
  placeholder?: string;
  onChange: (value: string) => void;
  multiline?: boolean;
  token?: string;
  onInsertToken?: () => void;
}) {
  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between gap-3">
        <Label htmlFor={id}>{label}</Label>
        {token && onInsertToken ? (
          <button
            type="button"
            onClick={onInsertToken}
            className="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 font-mono text-[11px] text-slate-600 transition hover:border-slate-300 hover:bg-white"
          >
            Insert {token}
          </button>
        ) : null}
      </div>
      {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
      {multiline ? (
        <textarea
          id={id}
          rows={3}
          placeholder={placeholder}
          value={value}
          className="flex min-h-[88px] w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          onChange={(e) => onChange(e.target.value)}
        />
      ) : (
        <Input id={id} placeholder={placeholder} value={value} onChange={(e) => onChange(e.target.value)} />
      )}
    </div>
  );
}

function ColorWithPresets({
  id,
  label,
  value,
  fallback,
  brandColor,
  onChange,
}: {
  id: string;
  label: string;
  value: string;
  fallback: string;
  brandColor?: string;
  onChange: (value: string) => void;
}) {
  const current = value || fallback;
  const presets = useMemo(() => {
    const list = [...COLOR_PRESETS];
    if (brandColor) {
      const normalized = brandColor.toLowerCase();
      if (!list.some((swatch) => swatch.toLowerCase() === normalized)) {
        list.unshift(brandColor);
      }
    }
    return list;
  }, [brandColor]);

  return (
    <div className="space-y-3 rounded-lg border border-slate-200 bg-white p-4">
      <Label htmlFor={id}>{label}</Label>
      <div className="flex gap-2">
        <Input
          id={id}
          type="color"
          className="h-10 w-14 shrink-0 cursor-pointer p-1"
          value={current}
          onChange={(e) => onChange(e.target.value)}
        />
        <Input value={value} placeholder={fallback} onChange={(e) => onChange(e.target.value)} />
      </div>
      <div className="flex flex-wrap items-center gap-2">
        {presets.map((swatch) => {
          const active = current.toLowerCase() === swatch.toLowerCase();
          return (
            <button
              key={`${id}-${swatch}`}
              type="button"
              title={swatch}
              onClick={() => onChange(swatch)}
              className={cn(
                'h-7 w-7 rounded-full border border-black/10 shadow-sm transition',
                active ? 'ring-2 ring-offset-2 ring-slate-400' : 'hover:scale-105',
              )}
              style={{ backgroundColor: swatch }}
            />
          );
        })}
        {brandColor ? (
          <Button type="button" variant="outline" size="sm" className="h-7 text-xs" onClick={() => onChange(brandColor)}>
            Match brand
          </Button>
        ) : null}
      </div>
    </div>
  );
}

function ModalPreview({
  scene,
  settings,
}: {
  scene: PopupScene;
  settings: Record<string, string>;
}) {
  const confirmColor = settings.ls_sw_confirm_color || DEFAULTS.ls_sw_confirm_color;
  const cancelColor = settings.ls_sw_cancel_color || DEFAULTS.ls_sw_cancel_color;

  if (scene === 'confirm') {
    return (
      <PreviewShell eyebrow="Get Key confirmation" label="Shown before fetching a single product">
        <PreviewDialog
          icon={<KeyRound className="h-5 w-5" />}
          title={replaceProduct(settings.ls_sw_confirm_title || DEFAULTS.ls_sw_confirm_title)}
          body={replaceProduct(settings.ls_sw_confirm_text || DEFAULTS.ls_sw_confirm_text)}
          primary={settings.ls_sw_confirm_btn || DEFAULTS.ls_sw_confirm_btn}
          secondary={settings.ls_sw_cancel_btn || DEFAULTS.ls_sw_cancel_btn}
          primaryColor={confirmColor}
          secondaryColor={cancelColor}
        />
      </PreviewShell>
    );
  }

  if (scene === 'bulk') {
    return (
      <PreviewShell eyebrow="Bulk fetch flow" label="Confirm, then success after all keys load">
        <div className="space-y-4">
          <PreviewDialog
            icon={<Layers3 className="h-5 w-5" />}
            title={settings.ls_sw_bulk_title || DEFAULTS.ls_sw_bulk_title}
            body={settings.ls_sw_bulk_text || DEFAULTS.ls_sw_bulk_text}
            primary={settings.ls_sw_bulk_confirm_btn || DEFAULTS.ls_sw_bulk_confirm_btn}
            secondary={settings.ls_sw_bulk_cancel_btn || DEFAULTS.ls_sw_bulk_cancel_btn}
            primaryColor={confirmColor}
            secondaryColor={cancelColor}
          />
          <PreviewDialog
            tone="success"
            icon={<Check className="h-5 w-5" />}
            title={settings.ls_sw_bulk_done_title || DEFAULTS.ls_sw_bulk_done_title}
            body={settings.ls_sw_bulk_done_text || DEFAULTS.ls_sw_bulk_done_text}
            primary={settings.ls_sw_view_close || DEFAULTS.ls_sw_view_close}
            primaryColor={confirmColor}
          />
        </div>
      </PreviewShell>
    );
  }

  return (
    <PreviewShell eyebrow="License keys modal" label="Shown after keys are fetched successfully">
      <PreviewDialog
        icon={<KeyRound className="h-5 w-5" />}
        title={settings.ls_sw_view_title_many || DEFAULTS.ls_sw_view_title_many}
        body={
          <div className="space-y-2 text-left">
            {['XXXX-XXXX-XXXX-1111', 'XXXX-XXXX-XXXX-2222'].map((key) => (
              <div
                key={key}
                className="rounded-md border border-slate-200 bg-slate-950 px-3 py-2 font-mono text-xs text-emerald-300"
              >
                {key}
              </div>
            ))}
          </div>
        }
        primary={settings.ls_sw_view_copy_all || DEFAULTS.ls_sw_view_copy_all}
        secondary={settings.ls_sw_view_close || DEFAULTS.ls_sw_view_close}
        primaryColor={confirmColor}
        secondaryColor={cancelColor}
        primaryIcon={<Copy className="h-3.5 w-3.5" />}
      />
      <p className="mt-3 text-center text-[11px] text-slate-500">
        Single-key title: <span className="font-medium text-slate-700">{settings.ls_sw_view_title || DEFAULTS.ls_sw_view_title}</span>
      </p>
    </PreviewShell>
  );
}

function PreviewShell({
  eyebrow,
  label,
  children,
}: {
  eyebrow: string;
  label: string;
  children: React.ReactNode;
}) {
  return (
    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 shadow-sm">
      <div className="border-b border-slate-200/80 bg-white/80 px-4 py-3 backdrop-blur">
        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{eyebrow}</p>
        <p className="mt-1 text-sm text-slate-600">{label}</p>
      </div>
      <div className="relative min-h-[320px] bg-[radial-gradient(circle_at_top,_#ffffff_0%,_#e2e8f0_55%,_#cbd5e1_100%)] p-5">
        <div className="absolute inset-0 bg-slate-900/20" />
        <div className="relative z-10">{children}</div>
      </div>
    </div>
  );
}

function PreviewDialog({
  icon,
  title,
  body,
  primary,
  secondary,
  primaryColor,
  secondaryColor,
  tone = 'default',
  primaryIcon,
}: {
  icon: React.ReactNode;
  title: string;
  body: React.ReactNode;
  primary: string;
  secondary?: string;
  primaryColor: string;
  secondaryColor?: string;
  tone?: 'default' | 'success';
  primaryIcon?: React.ReactNode;
}) {
  return (
    <div className="mx-auto w-full max-w-[320px] rounded-2xl border border-white/70 bg-white p-5 text-center shadow-xl shadow-slate-900/10">
      <div
        className={cn(
          'mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-full',
          tone === 'success' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-700',
        )}
      >
        {icon}
      </div>
      <h4 className="text-base font-semibold text-slate-900">{title}</h4>
      <div className="mt-2 text-sm leading-relaxed text-slate-600">{body}</div>
      <div className={cn('mt-5 flex flex-col gap-2', secondary ? 'sm:flex-row sm:justify-center' : '')}>
        {secondary ? (
          <button
            type="button"
            className="inline-flex items-center justify-center rounded-lg px-3 py-2 text-sm font-medium text-white"
            style={{ backgroundColor: secondaryColor || '#6b7280' }}
          >
            {secondary}
          </button>
        ) : null}
        <button
          type="button"
          className="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-white"
          style={{ backgroundColor: primaryColor }}
        >
          {primaryIcon}
          {primary}
        </button>
      </div>
    </div>
  );
}

export function PopupSettingsFields({ settings, onChange }: PopupSettingsFieldsProps) {
  const [scene, setScene] = useState<PopupScene>('confirm');
  const brandColor = settings.ls_brand || '';

  const resetScene = (keys: string[]) => {
    keys.forEach((key) => {
      if (DEFAULTS[key] !== undefined) {
        onChange(key, DEFAULTS[key]);
      }
    });
  };

  const insertProductToken = (key: string) => {
    const current = settings[key] || '';
    if (current.includes('{product}')) {
      return;
    }
    const next = current.trim() ? `${current.trim()} {product}` : 'We will fetch your license keys for {product}. Continue?';
    onChange(key, next);
  };

  return (
    <div className="space-y-6">
      <div className="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-slate-100 p-5">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div className="max-w-2xl space-y-2">
            <div className="flex items-center gap-2">
              <Badge variant="secondary">Customer dialogs</Badge>
              <Badge variant="outline">Live preview</Badge>
            </div>
            <h3 className="text-base font-semibold text-foreground">Pop-up copy & styling</h3>
            <p className="text-sm text-muted-foreground">
              Edit the SweetAlert dialogs customers see when fetching license keys. Changes preview instantly on the right before you save.
            </p>
          </div>
          <div className="grid grid-cols-3 gap-2 text-center text-xs text-slate-600">
            <div className="rounded-xl border border-slate-200 bg-white px-3 py-2">
              <MessageSquareText className="mx-auto mb-1 h-4 w-4 text-slate-500" />
              Confirm
            </div>
            <div className="rounded-xl border border-slate-200 bg-white px-3 py-2">
              <Layers3 className="mx-auto mb-1 h-4 w-4 text-slate-500" />
              Bulk
            </div>
            <div className="rounded-xl border border-slate-200 bg-white px-3 py-2">
              <KeyRound className="mx-auto mb-1 h-4 w-4 text-slate-500" />
              Keys view
            </div>
          </div>
        </div>
      </div>

      <Tabs value={scene} onValueChange={(value) => setScene(value as PopupScene)} className="space-y-5">
        <TabsList className="grid h-auto w-full grid-cols-3 gap-1 rounded-xl bg-slate-100 p-1">
          <TabsTrigger value="confirm" className="rounded-lg py-2.5 text-xs sm:text-sm">
            Get Key confirm
          </TabsTrigger>
          <TabsTrigger value="bulk" className="rounded-lg py-2.5 text-xs sm:text-sm">
            Bulk fetch
          </TabsTrigger>
          <TabsTrigger value="view" className="rounded-lg py-2.5 text-xs sm:text-sm">
            Keys modal
          </TabsTrigger>
        </TabsList>

        <div className="grid gap-6 xl:grid-cols-5">
          <div className="space-y-5 xl:col-span-3">
            <TabsContent value="confirm" className="mt-0 space-y-5">
              <SectionHeader
                title="Confirm before fetching keys"
                description="Shown when a customer clicks Get Key on an order or My Keys page."
                onReset={() =>
                  resetScene([
                    'ls_sw_confirm_title',
                    'ls_sw_confirm_text',
                    'ls_sw_confirm_btn',
                    'ls_sw_cancel_btn',
                    'ls_sw_confirm_color',
                    'ls_sw_cancel_color',
                  ])
                }
              />
              <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
                <Field
                  id="ls_sw_confirm_title"
                  label="Title"
                  value={settings.ls_sw_confirm_title || ''}
                  placeholder={DEFAULTS.ls_sw_confirm_title}
                  onChange={(v) => onChange('ls_sw_confirm_title', v)}
                />
                <Field
                  id="ls_sw_confirm_text"
                  label="Message"
                  hint="Use {product} to insert the product name automatically."
                  value={settings.ls_sw_confirm_text || ''}
                  placeholder={DEFAULTS.ls_sw_confirm_text}
                  onChange={(v) => onChange('ls_sw_confirm_text', v)}
                  multiline
                  token="{product}"
                  onInsertToken={() => insertProductToken('ls_sw_confirm_text')}
                />
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field
                    id="ls_sw_confirm_btn"
                    label="Confirm button"
                    value={settings.ls_sw_confirm_btn || ''}
                    placeholder={DEFAULTS.ls_sw_confirm_btn}
                    onChange={(v) => onChange('ls_sw_confirm_btn', v)}
                  />
                  <Field
                    id="ls_sw_cancel_btn"
                    label="Cancel button"
                    value={settings.ls_sw_cancel_btn || ''}
                    placeholder={DEFAULTS.ls_sw_cancel_btn}
                    onChange={(v) => onChange('ls_sw_cancel_btn', v)}
                  />
                </div>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <ColorWithPresets
                  id="ls_sw_confirm_color"
                  label="Confirm button color"
                  value={settings.ls_sw_confirm_color || ''}
                  fallback={DEFAULTS.ls_sw_confirm_color}
                  brandColor={brandColor}
                  onChange={(v) => onChange('ls_sw_confirm_color', v)}
                />
                <ColorWithPresets
                  id="ls_sw_cancel_color"
                  label="Cancel button color"
                  value={settings.ls_sw_cancel_color || ''}
                  fallback={DEFAULTS.ls_sw_cancel_color}
                  onChange={(v) => onChange('ls_sw_cancel_color', v)}
                />
              </div>
              <p className="text-xs text-muted-foreground">
                These button colors also apply to bulk confirm and keys modal actions.
              </p>
            </TabsContent>

            <TabsContent value="bulk" className="mt-0 space-y-5">
              <SectionHeader
                title="Bulk fetch (Get All Keys)"
                description="Shown on the order page when fetching every remaining key at once."
                onReset={() =>
                  resetScene([
                    'ls_sw_bulk_title',
                    'ls_sw_bulk_text',
                    'ls_sw_bulk_confirm_btn',
                    'ls_sw_bulk_cancel_btn',
                    'ls_sw_bulk_done_title',
                    'ls_sw_bulk_done_text',
                  ])
                }
              />
              <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
                <p className="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Confirmation</p>
                <Field
                  id="ls_sw_bulk_title"
                  label="Title"
                  value={settings.ls_sw_bulk_title || ''}
                  placeholder={DEFAULTS.ls_sw_bulk_title}
                  onChange={(v) => onChange('ls_sw_bulk_title', v)}
                />
                <Field
                  id="ls_sw_bulk_text"
                  label="Message"
                  value={settings.ls_sw_bulk_text || ''}
                  placeholder={DEFAULTS.ls_sw_bulk_text}
                  onChange={(v) => onChange('ls_sw_bulk_text', v)}
                  multiline
                />
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field
                    id="ls_sw_bulk_confirm_btn"
                    label="Confirm button"
                    value={settings.ls_sw_bulk_confirm_btn || ''}
                    placeholder={DEFAULTS.ls_sw_bulk_confirm_btn}
                    onChange={(v) => onChange('ls_sw_bulk_confirm_btn', v)}
                  />
                  <Field
                    id="ls_sw_bulk_cancel_btn"
                    label="Cancel button"
                    value={settings.ls_sw_bulk_cancel_btn || ''}
                    placeholder={DEFAULTS.ls_sw_bulk_cancel_btn}
                    onChange={(v) => onChange('ls_sw_bulk_cancel_btn', v)}
                  />
                </div>
              </div>
              <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
                <p className="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Completion</p>
                <Field
                  id="ls_sw_bulk_done_title"
                  label="Success title"
                  value={settings.ls_sw_bulk_done_title || ''}
                  placeholder={DEFAULTS.ls_sw_bulk_done_title}
                  onChange={(v) => onChange('ls_sw_bulk_done_title', v)}
                />
                <Field
                  id="ls_sw_bulk_done_text"
                  label="Success message"
                  value={settings.ls_sw_bulk_done_text || ''}
                  placeholder={DEFAULTS.ls_sw_bulk_done_text}
                  onChange={(v) => onChange('ls_sw_bulk_done_text', v)}
                  multiline
                />
              </div>
            </TabsContent>

            <TabsContent value="view" className="mt-0 space-y-5">
              <SectionHeader
                title="License keys modal"
                description="Shown after keys are successfully fetched so customers can copy them."
                onReset={() =>
                  resetScene(['ls_sw_view_title', 'ls_sw_view_title_many', 'ls_sw_view_copy_all', 'ls_sw_view_close'])
                }
              />
              <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field
                    id="ls_sw_view_title"
                    label="Single key title"
                    value={settings.ls_sw_view_title || ''}
                    placeholder={DEFAULTS.ls_sw_view_title}
                    onChange={(v) => onChange('ls_sw_view_title', v)}
                  />
                  <Field
                    id="ls_sw_view_title_many"
                    label="Multiple keys title"
                    value={settings.ls_sw_view_title_many || ''}
                    placeholder={DEFAULTS.ls_sw_view_title_many}
                    onChange={(v) => onChange('ls_sw_view_title_many', v)}
                  />
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field
                    id="ls_sw_view_copy_all"
                    label="Copy all button"
                    value={settings.ls_sw_view_copy_all || ''}
                    placeholder={DEFAULTS.ls_sw_view_copy_all}
                    onChange={(v) => onChange('ls_sw_view_copy_all', v)}
                  />
                  <Field
                    id="ls_sw_view_close"
                    label="Close button"
                    value={settings.ls_sw_view_close || ''}
                    placeholder={DEFAULTS.ls_sw_view_close}
                    onChange={(v) => onChange('ls_sw_view_close', v)}
                  />
                </div>
              </div>
            </TabsContent>
          </div>

          <div className="xl:col-span-2">
            <div className="xl:sticky xl:top-4">
              <ModalPreview scene={scene} settings={settings} />
            </div>
          </div>
        </div>
      </Tabs>
    </div>
  );
}

function SectionHeader({
  title,
  description,
  onReset,
}: {
  title: string;
  description: string;
  onReset: () => void;
}) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h4 className="text-sm font-semibold text-foreground">{title}</h4>
        <p className="mt-1 text-xs text-muted-foreground">{description}</p>
      </div>
      <Button type="button" variant="outline" size="sm" className="shrink-0 gap-1.5" onClick={onReset}>
        <RotateCcw className="h-3.5 w-3.5" />
        Reset defaults
      </Button>
    </div>
  );
}
