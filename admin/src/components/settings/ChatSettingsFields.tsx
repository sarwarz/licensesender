import { Bot, MessageCircle } from 'lucide-react';
import type { ChangeEvent } from 'react';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

type ChatSettingsFieldsProps = {
  settings: Record<string, string>;
  onChange: (key: string, value: string) => void;
};

export function ChatSettingsFields({ settings, onChange }: ChatSettingsFieldsProps) {
  const yesNo = (key: string) => settings[key] === 'yes';
  const enabled = yesNo('lship_chat_enabled');
  const requireEmail = yesNo('lship_chat_require_email');
  const brandColor = settings.lship_chat_color || '#0f766e';

  return (
    <div className="space-y-6">
      <div className="rounded-xl border border-slate-200 bg-white p-5">
        <div className="mb-4 flex items-start gap-3">
          <div
            className="rounded-lg p-2 text-white"
            style={{ backgroundColor: brandColor }}
          >
            <MessageCircle className="h-5 w-5" />
          </div>
          <div>
            <h3 className="text-base font-semibold text-foreground">AI live chat widget</h3>
            <p className="text-sm text-muted-foreground">
              Floating storefront chat proxies to LicenseSender. Visitors never see your API key.
            </p>
          </div>
        </div>

        <div className="space-y-5">
          <div className="flex items-center justify-between gap-4">
            <div>
              <Label htmlFor="lship_chat_enabled">Enable live chat</Label>
              <p className="text-xs text-muted-foreground">
                Also enable chat in the LicenseSender SaaS dashboard (Support → Live Chat).
              </p>
            </div>
            <Switch
              id="lship_chat_enabled"
              checked={enabled}
              onCheckedChange={(v) => onChange('lship_chat_enabled', v ? 'yes' : 'no')}
            />
          </div>

          <div className="flex items-center justify-between gap-4">
            <div>
              <Label htmlFor="lship_chat_require_email">Require email before chat</Label>
              <p className="text-xs text-muted-foreground">Ask guests for an email before starting a session.</p>
            </div>
            <Switch
              id="lship_chat_require_email"
              checked={requireEmail}
              onCheckedChange={(v) => onChange('lship_chat_require_email', v ? 'yes' : 'no')}
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="lship_chat_color">Widget brand color</Label>
            <div className="flex items-center gap-3">
              <input
                id="lship_chat_color"
                type="color"
                value={settings.lship_chat_color || '#0f766e'}
                onChange={(e: ChangeEvent<HTMLInputElement>) => onChange('lship_chat_color', e.target.value)}
                className="h-10 w-14 cursor-pointer rounded border border-input bg-transparent p-1"
              />
              <input
                type="text"
                value={settings.lship_chat_color || ''}
                placeholder="#0f766e"
                onChange={(e: ChangeEvent<HTMLInputElement>) => onChange('lship_chat_color', e.target.value)}
                className={cn(
                  'flex h-10 w-full max-w-[10rem] rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-sm',
                  'placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring',
                )}
              />
            </div>
            <p className="text-xs text-muted-foreground">
              Used for header, launcher, and assistant bubbles. Empty falls back to Design → brand color.
            </p>
          </div>

          <div className="space-y-3">
            <div>
              <Label>Launcher style</Label>
              <p className="text-xs text-muted-foreground">
                Choose how the floating chat button appears on your storefront.
              </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              {(
                [
                  {
                    value: 'icon',
                    title: 'Icon only',
                    hint: 'Circular chat button',
                  },
                  {
                    value: 'label',
                    title: 'Chat with us',
                    hint: 'Icon with message label',
                  },
                ] as const
              ).map((option) => {
                const selected = (settings.lship_chat_launcher_style || 'icon') === option.value;
                return (
                  <button
                    key={option.value}
                    type="button"
                    onClick={() => onChange('lship_chat_launcher_style', option.value)}
                    className={cn(
                      'rounded-xl border p-4 text-left transition-colors',
                      selected ? '' : 'border-slate-200 bg-white hover:border-slate-300',
                    )}
                    style={
                      selected
                        ? {
                            borderColor: brandColor,
                            backgroundColor: `color-mix(in srgb, ${brandColor} 10%, white)`,
                            boxShadow: `0 0 0 1px ${brandColor}`,
                          }
                        : undefined
                    }
                  >
                    <div className="mb-3 flex items-center gap-2">
                      <span
                        className={cn(
                          'inline-flex items-center justify-center rounded-full text-white',
                          option.value === 'icon' ? 'h-10 w-10' : 'h-9 gap-2 px-3',
                        )}
                        style={{ backgroundColor: brandColor }}
                      >
                        <MessageCircle className="h-4 w-4 shrink-0" />
                        {option.value === 'label' ? (
                          <span className="text-xs font-semibold whitespace-nowrap">Chat with us</span>
                        ) : null}
                      </span>
                    </div>
                    <div className="text-sm font-semibold text-foreground">{option.title}</div>
                    <div className="text-xs text-muted-foreground">{option.hint}</div>
                  </button>
                );
              })}
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="lship_chat_welcome">Welcome message</Label>
            <textarea
              id="lship_chat_welcome"
              value={settings.lship_chat_welcome || ''}
              placeholder="Hi! How can we help you today?"
              onChange={(e: ChangeEvent<HTMLTextAreaElement>) => onChange('lship_chat_welcome', e.target.value)}
              rows={3}
              className={cn(
                'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm',
                'placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring',
              )}
            />
            <p className="flex items-center gap-1 text-xs text-muted-foreground">
              <Bot className="h-3.5 w-3.5" />
              Optional storefront override. Empty uses the SaaS welcome after session start.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
