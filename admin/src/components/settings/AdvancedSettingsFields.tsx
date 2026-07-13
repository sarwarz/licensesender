import { useState } from 'react';
import { Check, Copy, Eye, EyeOff, KeyRound, RefreshCw, Webhook } from 'lucide-react';
import { toast } from 'sonner';
import { SettingsSection } from '@/components/settings/SettingsSection';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

type AdvancedSettingsFieldsProps = {
  settings: Record<string, string>;
  regeneratingSecret?: boolean;
  onChange: (key: string, value: string) => void;
  onRegenerateSecret: () => void;
};

async function copyText(value: string, label: string) {
  const text = value.trim();
  if (!text) {
    toast.error(`${label} is empty.`);
    return;
  }

  try {
    await navigator.clipboard.writeText(text);
    toast.success(`${label} copied.`);
  } catch {
    toast.error(`Could not copy ${label.toLowerCase()}.`);
  }
}

function CopyableField({
  id,
  label,
  value,
  mono,
  secret,
  help,
}: {
  id: string;
  label: string;
  value: string;
  mono?: boolean;
  secret?: boolean;
  help?: string;
}) {
  const [revealed, setRevealed] = useState(false);
  const [copied, setCopied] = useState(false);

  const onCopy = async () => {
    await copyText(value, label);
    setCopied(true);
    window.setTimeout(() => setCopied(false), 1500);
  };

  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      <div className="flex gap-2">
        <Input
          id={id}
          type={secret && !revealed ? 'password' : 'text'}
          value={value}
          readOnly
          onFocus={(e) => e.target.select()}
          className={cn(mono && 'font-mono text-xs')}
        />
        {secret ? (
          <Button
            type="button"
            variant="outline"
            size="icon"
            aria-label={revealed ? 'Hide secret' : 'Show secret'}
            onClick={() => setRevealed((v) => !v)}
          >
            {revealed ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
          </Button>
        ) : null}
        <Button type="button" variant="outline" size="icon" aria-label={`Copy ${label}`} onClick={onCopy}>
          {copied ? <Check className="h-4 w-4 text-emerald-600" /> : <Copy className="h-4 w-4" />}
        </Button>
      </div>
      {help ? <p className="text-xs text-muted-foreground">{help}</p> : null}
    </div>
  );
}

export function AdvancedSettingsFields({
  settings,
  regeneratingSecret = false,
  onChange,
  onRegenerateSecret,
}: AdvancedSettingsFieldsProps) {
  const ssoEnabled = settings.lship_sso_enabled === 'yes';
  const webhookUrl = settings.lship_webhook_url || '';
  const webhookSecret = settings.lship_webhook_secret || '';
  const webhookReady = Boolean(webhookUrl && webhookSecret);
  const ssoToken = settings.lship_sso_token || '';
  const ssoEmail = settings.lship_sso_user_email || '';
  const ssoReady = ssoEnabled && Boolean(ssoToken.trim() && ssoEmail.trim());

  return (
    <div className="space-y-8">
      <SettingsSection
        title="Incoming webhook"
        description="Share these credentials with LicenseSender (Shops → WordPress plugin webhook). When a license is replaced in LicenseSender, WordPress updates the local key cache automatically."
        singleColumn
      >
        <div className="rounded-xl border bg-card p-5 shadow-sm">
          <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div className="flex items-start gap-3">
              <div className="mt-0.5 flex h-9 w-9 items-center justify-center rounded-lg bg-muted">
                <Webhook className="h-4 w-4 text-foreground" />
              </div>
              <div>
                <p className="text-sm font-semibold text-foreground">license.replaced endpoint</p>
                <p className="mt-0.5 text-xs text-muted-foreground">
                  Signed with{' '}
                  <code className="rounded bg-muted px-1 py-0.5">X-LS-Signature: sha256=…</code>
                </p>
              </div>
            </div>
            <Badge
              variant="secondary"
              className={cn(
                webhookReady
                  ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-100'
                  : 'bg-amber-100 text-amber-800 hover:bg-amber-100',
              )}
            >
              {webhookReady ? 'Ready' : 'Setup required'}
            </Badge>
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <CopyableField
              id="lship_webhook_url"
              label="Webhook URL"
              value={webhookUrl}
              help="Paste into LicenseSender shop webhook settings."
            />
            <div className="space-y-2">
              <CopyableField
                id="lship_webhook_secret"
                label="Webhook secret"
                value={webhookSecret}
                mono
                secret
                help="Keep private. Rotate if the secret may have leaked."
              />
              <div className="flex justify-end pt-1">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  disabled={regeneratingSecret}
                  onClick={onRegenerateSecret}
                >
                  <RefreshCw className={cn('mr-2 h-3.5 w-3.5', regeneratingSecret && 'animate-spin')} />
                  {regeneratingSecret ? 'Regenerating…' : 'Regenerate secret'}
                </Button>
              </div>
            </div>
          </div>
        </div>
      </SettingsSection>

      <SettingsSection
        title="Single sign-on"
        description="Open your LicenseSender dashboard from the WordPress admin bar using a shared token from LicenseSender → Settings → Security."
        singleColumn
      >
        <div className="space-y-4 rounded-xl border bg-card p-5 shadow-sm">
          <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4">
            <div className="space-y-1 pr-4">
              <div className="flex flex-wrap items-center gap-2">
                <Label htmlFor="lship_sso_enabled">Enable SSO Login</Label>
                {ssoEnabled ? (
                  <Badge
                    variant="secondary"
                    className={cn(
                      ssoReady
                        ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-100'
                        : 'bg-amber-100 text-amber-800 hover:bg-amber-100',
                    )}
                  >
                    {ssoReady ? 'Ready' : 'Needs token & email'}
                  </Badge>
                ) : (
                  <Badge variant="secondary" className="bg-slate-100 text-slate-600 hover:bg-slate-100">
                    Off
                  </Badge>
                )}
              </div>
              <p className="text-xs text-muted-foreground">
                When enabled, admins see a LicenseSender shortcut in the admin bar that opens a signed login URL.
              </p>
            </div>
            <Switch
              id="lship_sso_enabled"
              checked={ssoEnabled}
              onCheckedChange={(v) => onChange('lship_sso_enabled', v ? 'yes' : 'no')}
            />
          </div>

          {ssoEnabled ? (
            <div className="space-y-4 border-t pt-4">
              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="lship_sso_token">SSO Access Token</Label>
                  <Input
                    id="lship_sso_token"
                    type="password"
                    value={ssoToken}
                    onChange={(e) => onChange('lship_sso_token', e.target.value)}
                    placeholder="Paste token from LicenseSender Settings → Security"
                    autoComplete="new-password"
                    className="font-mono text-xs"
                  />
                  <p className="text-xs text-muted-foreground">
                    Must match the SSO token generated in LicenseSender.
                  </p>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="lship_sso_user_email">SSO User Email</Label>
                  <Input
                    id="lship_sso_user_email"
                    type="email"
                    value={ssoEmail}
                    onChange={(e) => onChange('lship_sso_user_email', e.target.value)}
                    placeholder="your@email.com"
                    autoComplete="off"
                  />
                  <p className="text-xs text-muted-foreground">
                    LicenseSender account email that will be signed in.
                  </p>
                </div>
              </div>

              <div className="rounded-lg border bg-muted/40 p-4">
                <div className="mb-3 flex items-center gap-2">
                  <KeyRound className="h-4 w-4 text-muted-foreground" />
                  <p className="text-sm font-medium text-foreground">Setup checklist</p>
                </div>
                <ol className="list-decimal space-y-1.5 ps-4 text-xs text-muted-foreground">
                  <li>In LicenseSender go to Settings → Security and enable SSO.</li>
                  <li>Generate an SSO access token and copy it here.</li>
                  <li>Enter the same LicenseSender account email you use to sign in.</li>
                  <li>Save changes, then use the LicenseSender item in the WP admin bar.</li>
                </ol>
              </div>
            </div>
          ) : null}
        </div>
      </SettingsSection>
    </div>
  );
}
