import { useRef, useState } from 'react';
import { Copy, FileDown, KeyRound, RotateCcw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SettingsSection } from '@/components/settings/SettingsSection';
import { cn } from '@/lib/utils';

/**
 * Settings → Key texts: the wording customers see when they get and view license keys
 * (My Account → My Keys, the order page and the thank-you page). Empty = built-in text,
 * which translation plugins can translate. Option keys are the old pop-up ones (ls_sw_*).
 */

interface Props {
  settings: Record<string, string>;
  onChange: (key: string, value: string) => void;
}

const BUILT_IN: Record<string, string> = {
  ls_sw_confirm_title: 'Get license keys?',
  ls_sw_confirm_text: 'We will fetch your license keys for {product}. Continue?',
  ls_sw_confirm_btn: 'Yes, get keys',
  ls_sw_cancel_btn: 'Cancel',
  ls_sw_view_title: 'Your license key',
  ls_sw_view_title_many: 'Your license keys',
  ls_sw_view_copy_all: 'Copy all',
};

const CONFIRM_KEYS = ['ls_sw_confirm_title', 'ls_sw_confirm_text', 'ls_sw_confirm_btn', 'ls_sw_cancel_btn'];
const PANEL_KEYS = ['ls_sw_view_title', 'ls_sw_view_title_many', 'ls_sw_view_copy_all'];
const SAMPLE_PRODUCT = 'Windows 11 Pro';
const TEXT_MAX = 80;
const MESSAGE_MAX = 300;

export function PopupSettingsFields({ settings, onChange }: Props) {
  const [keyCount, setKeyCount] = useState<1 | 2>(2);
  const messageRef = useRef<HTMLTextAreaElement>(null);
  const text = (key: string) => (settings[key] || '').trim() || BUILT_IN[key];
  const customised = (keys: string[]) => keys.some((key) => (settings[key] || '').trim() !== '');
  const reset = (keys: string[]) => keys.forEach((key) => onChange(key, ''));

  const insertProduct = () => {
    const el = messageRef.current;
    const current = settings.ls_sw_confirm_text || '';
    const start = el?.selectionStart ?? current.length;
    const end = el?.selectionEnd ?? current.length;
    const next = (current.slice(0, start) + '{product}' + current.slice(end)).slice(0, MESSAGE_MAX);
    onChange('ls_sw_confirm_text', next);
    requestAnimationFrame(() => {
      el?.focus();
      el?.setSelectionRange(start + 9, start + 9);
    });
  };

  const field = (key: string, label: string, hint?: string) => (
    <div className="space-y-2">
      <Label htmlFor={key}>{label}</Label>
      <Input
        id={key}
        value={settings[key] || ''}
        placeholder={BUILT_IN[key]}
        maxLength={TEXT_MAX}
        onChange={(e) => onChange(key, e.target.value)}
      />
      {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
    </div>
  );

  const message = settings.ls_sw_confirm_text || '';

  return (
    <div className="grid gap-8 xl:grid-cols-[minmax(0,1fr)_380px]">
      <div className="space-y-8">
        <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600">
          These texts appear in the license keys section on <span className="font-medium text-slate-800">My Account → My Keys</span>,
          the <span className="font-medium text-slate-800">order page</span> and the <span className="font-medium text-slate-800">thank-you page</span>.
          Leave a field empty to use the built-in text (shown in grey), which translation plugins can translate.
        </div>

        <SettingsSection
          title="Get keys confirmation"
          description="Customers confirm before keys are fetched and assigned to their order."
          singleColumn
        >
          <SectionReset show={customised(CONFIRM_KEYS)} onReset={() => reset(CONFIRM_KEYS)} />
          {field('ls_sw_confirm_title', 'Title')}
          <div className="space-y-2">
            <div className="flex items-center justify-between gap-3">
              <Label htmlFor="ls_sw_confirm_text">Message</Label>
              <button
                type="button"
                onClick={insertProduct}
                className="rounded-full border border-slate-200 bg-white px-2 py-0.5 font-mono text-[11px] text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
              >
                + {'{product}'}
              </button>
            </div>
            <textarea
              ref={messageRef}
              id="ls_sw_confirm_text"
              rows={3}
              value={message}
              placeholder={BUILT_IN.ls_sw_confirm_text}
              maxLength={MESSAGE_MAX}
              onChange={(e) => onChange('ls_sw_confirm_text', e.target.value)}
              className="flex min-h-[84px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            />
            <div className="flex items-start justify-between gap-3 text-xs text-muted-foreground">
              <p>
                <code className="rounded bg-slate-100 px-1 py-0.5 text-[11px] text-slate-700">{'{product}'}</code> becomes the product name
                (or names, when several are fetched at once).
              </p>
              <span className={cn('shrink-0 tabular-nums', message.length > MESSAGE_MAX - 30 && 'text-amber-600')}>
                {message.length}/{MESSAGE_MAX}
              </span>
            </div>
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            {field('ls_sw_confirm_btn', 'Confirm button')}
            {field('ls_sw_cancel_btn', 'Cancel button')}
          </div>
        </SettingsSection>

        <SettingsSection
          title="Keys panel"
          description="The panel that lists the keys once they are delivered."
          singleColumn
        >
          <SectionReset show={customised(PANEL_KEYS)} onReset={() => reset(PANEL_KEYS)} />
          <div className="grid gap-4 sm:grid-cols-2">
            {field('ls_sw_view_title', 'Heading for one key')}
            {field('ls_sw_view_title_many', 'Heading for several keys')}
          </div>
          {field('ls_sw_view_copy_all', 'Copy all button', 'Shown when an order line has more than one key.')}
        </SettingsSection>
      </div>

      <div className="xl:sticky xl:top-4 xl:self-start">
        <Preview settings={settings} text={text} keyCount={keyCount} onKeyCount={setKeyCount} />
      </div>
    </div>
  );
}

function SectionReset({ show, onReset }: { show: boolean; onReset: () => void }) {
  if (!show) return null;
  return (
    <div className="-mt-2 flex justify-end">
      <Button type="button" variant="ghost" size="sm" className="h-7 gap-1.5 text-xs text-muted-foreground" onClick={onReset}>
        <RotateCcw className="h-3.5 w-3.5" />
        Use built-in text
      </Button>
    </div>
  );
}

/** Mirrors the storefront keys card (resources/public/my-keys) in the store's brand colour and radii. */
function Preview({
  settings,
  text,
  keyCount,
  onKeyCount,
}: {
  settings: Record<string, string>;
  text: (key: string) => string;
  keyCount: 1 | 2;
  onKeyCount: (count: 1 | 2) => void;
}) {
  const brand = settings.preview_brand || '#4f46e5';
  const radiusCard = settings.preview_radius_card || '14px';
  const radiusCtl = settings.preview_radius_ctl || '10px';
  const keys = ['7XK2M-QP9RT-4HVWB-3CJ8D-6YFNG', 'B4N8R-KX2TM-9QWP7-HC3VJ-D6YG2'].slice(0, keyCount);
  const tint = (alpha: number) => `color-mix(in srgb, ${brand} ${alpha}%, transparent)`;

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Preview</p>
        <div className="inline-flex rounded-md border border-slate-200 bg-white p-0.5 text-xs" role="group" aria-label="Keys in the order line">
          {([1, 2] as const).map((count) => (
            <button
              key={count}
              type="button"
              onClick={() => onKeyCount(count)}
              aria-pressed={keyCount === count}
              className={cn('rounded px-2 py-1 font-medium transition', keyCount === count ? 'bg-slate-900 text-white' : 'text-slate-600 hover:text-slate-900')}
            >
              {count === 1 ? '1 key' : '2 keys'}
            </button>
          ))}
        </div>
      </div>

      <div className="rounded-xl border border-slate-200 bg-slate-100 p-3">
        <div className="overflow-hidden border border-slate-200 bg-white" style={{ borderRadius: radiusCard }}>
          {/* Product row */}
          <div className="flex items-center gap-3 p-4">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center" style={{ borderRadius: radiusCtl, background: tint(10), color: brand }}>
              <KeyRound className="h-5 w-5" />
            </div>
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-semibold text-slate-900">{SAMPLE_PRODUCT}</p>
              <p className="text-xs text-slate-500">Order #1042 · {keyCount === 1 ? '1 key' : `${keyCount} keys`}</p>
            </div>
          </div>

          {/* Confirmation */}
          <div className="px-4 pb-4">
            <div className="flex flex-col gap-3 border p-3.5" style={{ borderRadius: radiusCard, borderColor: tint(25), background: tint(6) }}>
              <div className="min-w-0">
                <p className="text-sm font-semibold text-slate-900">{text('ls_sw_confirm_title')}</p>
                <p className="mt-0.5 text-[13px] text-slate-500">{text('ls_sw_confirm_text').replace(/\{product\}/gi, SAMPLE_PRODUCT)}</p>
              </div>
              <div className="flex justify-end gap-2">
                <span className="inline-flex h-8 items-center px-3 text-[13px] font-medium text-slate-700" style={{ borderRadius: radiusCtl }}>
                  {text('ls_sw_cancel_btn')}
                </span>
                <span className="inline-flex h-8 items-center px-3 text-[13px] font-medium text-white shadow-sm" style={{ borderRadius: radiusCtl, background: brand }}>
                  {text('ls_sw_confirm_btn')}
                </span>
              </div>
            </div>
          </div>

          {/* Keys panel */}
          <div className="px-4 pb-4">
            <div className="overflow-hidden border border-slate-200 bg-slate-50" style={{ borderRadius: radiusCard }}>
              <div className="flex flex-wrap items-center justify-between gap-2 px-3.5 pt-3">
                <div className="flex items-center gap-2">
                  <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    {keyCount > 1 ? text('ls_sw_view_title_many') : text('ls_sw_view_title')}
                  </p>
                  <span className="rounded-full bg-slate-900/5 px-2 py-px text-[11px] font-semibold tabular-nums text-slate-500">{keyCount}</span>
                </div>
                <div className="flex items-center gap-2.5 text-xs font-medium text-slate-600">
                  {keyCount > 1 ? (
                    <span className="inline-flex items-center gap-1">
                      <Copy className="h-3.5 w-3.5" />
                      {text('ls_sw_view_copy_all')}
                    </span>
                  ) : null}
                  <span className="inline-flex items-center gap-1">
                    <FileDown className="h-3.5 w-3.5" />
                    Download .txt
                  </span>
                </div>
              </div>
              <div className="space-y-1.5 p-3.5 pt-2.5">
                {keys.map((key) => (
                  <div key={key} className="flex items-center justify-between gap-2 border border-slate-200 bg-white px-3 py-2" style={{ borderRadius: radiusCtl }}>
                    <code className="truncate font-mono text-xs text-slate-800">{key}</code>
                    <Copy className="h-3.5 w-3.5 shrink-0 text-slate-400" />
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
      <p className="text-[11px] leading-relaxed text-muted-foreground">
        Colours and corners follow your <span className="font-medium text-slate-700">Design</span> settings. Customers see the confirmation first, then the keys panel.
      </p>
    </div>
  );
}
