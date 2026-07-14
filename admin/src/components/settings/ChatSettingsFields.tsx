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

  return (
    <div className="space-y-6">
      <div className="rounded-xl border border-slate-200 bg-white p-5">
        <div className="mb-4 flex items-start gap-3">
          <div className="rounded-lg bg-teal-50 p-2 text-teal-700">
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
