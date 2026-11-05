import { useCallback, useEffect, useState } from 'react';
import { AlertTriangle, CheckCircle2, ExternalLink, Loader2, MessageCircle, MinusCircle, RefreshCw } from 'lucide-react';
import { apiRequest } from '@/api/client';
import { ColorField } from '@/components/settings/ColorField';
import { SettingsSection } from '@/components/settings/SettingsSection';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

/**
 * Settings → Live Chat. The chat is hosted by LicenseSender; this store only turns it on
 * and may override its colour. Title, welcome, AI and email gate live in the LicenseSender
 * panel (Live Chat → Settings).
 */

type ChatStatus = { state: 'ready' | 'off' | 'turned_off' | 'not_in_plan' | 'error'; message: string; settings_url: string };

type Props = {
  settings: Record<string, string>;
  onChange: (key: string, value: string) => void;
};

const COLOR_CHOICES = [
  { id: 'app', label: 'Same as LicenseSender', text: 'The colour set in Live Chat → Settings.' },
  { id: 'brand', label: 'My brand colour', text: 'Settings → Design.' },
  { id: 'custom', label: 'Custom', text: 'Any colour for this store.' },
] as const;

export function ChatSettingsFields({ settings, onChange }: Props) {
  const enabled = settings.lship_chat_enabled === 'yes';
  const saved = settings.lship_chat_color || '';
  const choice = saved === '' ? 'app' : saved === 'brand' ? 'brand' : 'custom';
  const brand = settings.design_brand || '#4f46e5';
  const swatch = choice === 'brand' ? brand : choice === 'custom' ? saved : null;
  const [status, setStatus] = useState<ChatStatus | null>(null);
  const [checking, setChecking] = useState(false);

  const check = useCallback(async () => {
    setChecking(true);
    try {
      setStatus(await apiRequest<ChatStatus>('settings/chat-status'));
    } catch (err) {
      setStatus({ state: 'error', message: err instanceof Error ? err.message : 'Could not check the chat.', settings_url: '' });
    } finally {
      setChecking(false);
    }
  }, []);

  // Status of the saved settings; "Check" re-asks LicenseSender (e.g. after saving or changing the panel).
  useEffect(() => {
    void check();
  }, [check]);

  const pickColor = (id: (typeof COLOR_CHOICES)[number]['id']) => {
    if (id === 'app') onChange('lship_chat_color', '');
    else if (id === 'brand') onChange('lship_chat_color', 'brand');
    else onChange('lship_chat_color', /^#[0-9a-f]{6}$/i.test(saved) ? saved : brand);
  };

  return (
    <div className="space-y-8">
      <SettingsSection
        title="Live chat"
        description="An AI chat on every store page, hosted by LicenseSender in a secure frame so your theme can't break it."
        singleColumn
      >
        <div className="flex items-center justify-between rounded-lg border p-4">
          <div className="flex items-start gap-3 pr-4">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-white" style={{ backgroundColor: swatch || '#64748b' }}>
              <MessageCircle className="h-[18px] w-[18px]" />
            </span>
            <div className="space-y-1">
              <Label htmlFor="lship_chat_enabled">Show the chat on this store</Label>
              <p className="text-xs text-muted-foreground">Logged-in customers are recognised automatically, so they don&apos;t have to type their email.</p>
            </div>
          </div>
          <Switch id="lship_chat_enabled" checked={enabled} onCheckedChange={(v) => onChange('lship_chat_enabled', v ? 'yes' : 'no')} />
        </div>

        <StatusLine status={status} checking={checking} onCheck={check} />
      </SettingsSection>

      <SettingsSection title="Colour" description="Header, launcher button and message bubbles." singleColumn>
        <div className="grid gap-3 sm:grid-cols-3" role="radiogroup" aria-label="Chat colour">
          {COLOR_CHOICES.map((option) => {
            const active = choice === option.id;
            const dot = option.id === 'brand' ? brand : option.id === 'custom' && choice === 'custom' ? saved : null;
            return (
              <button
                key={option.id}
                type="button"
                role="radio"
                aria-checked={active}
                onClick={() => pickColor(option.id)}
                className={cn(
                  'rounded-xl border p-4 text-left transition-all',
                  active ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-slate-200 bg-white hover:border-slate-300',
                )}
              >
                <span className="flex items-center gap-2">
                  {dot ? <span className="h-3.5 w-3.5 rounded-full ring-1 ring-black/10" style={{ backgroundColor: dot }} /> : null}
                  <span className="text-sm font-medium text-foreground">{option.label}</span>
                </span>
                <span className="mt-1 block text-xs text-muted-foreground">{option.text}</span>
              </button>
            );
          })}
        </div>
        {choice === 'custom' ? (
          <ColorField
            id="lship_chat_color"
            label="Custom colour"
            value={saved}
            defaultValue={brand}
            onChange={(v) => onChange('lship_chat_color', v)}
          />
        ) : null}
      </SettingsSection>

      <SettingsSection title="Title, welcome message and AI" description="Set in your LicenseSender panel, shared by all your stores." singleColumn>
        <div>
          <Button asChild variant="outline" size="sm" className="gap-1.5">
            <a href={status?.settings_url || '#'} target="_blank" rel="noopener noreferrer">
              Open Live Chat settings <ExternalLink className="h-3.5 w-3.5" />
            </a>
          </Button>
        </div>
      </SettingsSection>
    </div>
  );
}

function StatusLine({ status, checking, onCheck }: { status: ChatStatus | null; checking: boolean; onCheck: () => void }) {
  const tone =
    !status || checking ? 'neutral' : status.state === 'ready' ? 'ok' : status.state === 'off' ? 'neutral' : 'warn';
  const Icon = !status || checking ? Loader2 : status.state === 'ready' ? CheckCircle2 : status.state === 'off' ? MinusCircle : AlertTriangle;

  return (
    <div
      className={cn(
        'flex items-start justify-between gap-3 rounded-lg border px-4 py-3',
        tone === 'ok' && 'border-emerald-200 bg-emerald-50/70',
        tone === 'warn' && 'border-amber-200 bg-amber-50/70',
      )}
    >
      <p className={cn('flex items-start gap-2 text-sm', tone === 'ok' ? 'text-emerald-800' : tone === 'warn' ? 'text-amber-800' : 'text-muted-foreground')}>
        <Icon className={cn('mt-0.5 h-4 w-4 shrink-0', (!status || checking) && 'animate-spin')} />
        {!status || checking ? 'Checking LicenseSender…' : status.message}
      </p>
      <Button type="button" variant="ghost" size="sm" className="h-7 shrink-0 gap-1.5 text-xs" onClick={onCheck} disabled={checking}>
        <RefreshCw className={cn('h-3.5 w-3.5', checking && 'animate-spin')} /> Check
      </Button>
    </div>
  );
}
