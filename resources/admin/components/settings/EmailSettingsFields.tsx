import { useCallback, useEffect, useState } from 'react';
import { AlertTriangle, CheckCircle2, Cloud, ExternalLink, Loader2, Mail, RefreshCw, Send, Server } from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { SettingsSection } from '@/components/settings/SettingsSection';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

/**
 * Settings → Email. One license email per order, sent either by WordPress (a normal
 * WooCommerce email) or by the LicenseSender app. If the app can't send, WordPress does,
 * so customers always get their keys once.
 */

interface Props {
  settings: Record<string, string>;
  onChange: (key: string, value: string) => void;
}

type AppStatus = { reachable: boolean; ready: boolean; message: string; shop: string; from: string };

const SENDERS = [
  {
    id: 'wordpress',
    icon: Server,
    title: 'WordPress',
    badge: 'Recommended',
    text: 'A WooCommerce email in your store’s email style, sent through your site’s mail (or SMTP plugin). Translatable.',
  },
  {
    id: 'app',
    icon: Cloud,
    title: 'LicenseSender app',
    badge: '',
    text: 'Sent from your shop’s mail settings in LicenseSender, with its template editor and email log. WordPress sends if the app can’t.',
  },
] as const;

export function EmailSettingsFields({ settings, onChange }: Props) {
  const enabled = settings.lship_send_email_after_redeem === 'yes';
  const sender = settings.lship_email_sender === 'app' ? 'app' : 'wordpress';
  const [testEmail, setTestEmail] = useState(settings.admin_email || '');
  const [sending, setSending] = useState(false);
  const [status, setStatus] = useState<AppStatus | null>(null);
  const [checking, setChecking] = useState(false);

  useEffect(() => {
    if (!testEmail && settings.admin_email) setTestEmail(settings.admin_email);
  }, [settings.admin_email, testEmail]);

  const checkApp = useCallback(async () => {
    setChecking(true);
    try {
      setStatus(await apiRequest<AppStatus>('settings/email-app-status'));
    } catch (err) {
      setStatus({ reachable: false, ready: false, message: err instanceof Error ? err.message : 'Could not reach LicenseSender.', shop: '', from: '' });
    } finally {
      setChecking(false);
    }
  }, []);

  useEffect(() => {
    if (sender === 'app' && !status && !checking) void checkApp();
  }, [sender, status, checking, checkApp]);

  const sendTest = async () => {
    if (!testEmail.trim()) {
      toast.error('Enter an email address.');
      return;
    }
    setSending(true);
    try {
      const res = await apiRequest<{ message?: string }>('settings/test-email', {
        method: 'POST',
        body: JSON.stringify({ email: testEmail.trim() }),
      });
      toast.success(res.message || 'Test email sent.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Could not send the test email.');
    } finally {
      setSending(false);
    }
  };

  return (
    <div className="space-y-8">
      <SettingsSection title="License emails" description="One email per order with every key, sent once all of the order’s keys are delivered." singleColumn>
        <div className="flex items-center justify-between rounded-lg border p-4">
          <div className="space-y-1 pr-4">
            <Label htmlFor="lship_send_email_after_redeem">Email customers their keys</Label>
            <p className="text-xs text-muted-foreground">
              Off: customers still see their keys on the order page, the thank-you page and My Account → My Keys. You can resend from an order any time.
            </p>
          </div>
          <Switch
            id="lship_send_email_after_redeem"
            checked={enabled}
            onCheckedChange={(v) => onChange('lship_send_email_after_redeem', v ? 'yes' : 'no')}
          />
        </div>
      </SettingsSection>

      <SettingsSection title="Who sends it" description="Pick one. Customers never get two emails for the same order." singleColumn>
        <div className={cn('grid gap-3 sm:grid-cols-2', !enabled && 'opacity-60')} role="radiogroup" aria-label="Who sends license emails">
          {SENDERS.map((option) => {
            const active = sender === option.id;
            return (
              <button
                key={option.id}
                type="button"
                role="radio"
                aria-checked={active}
                onClick={() => onChange('lship_email_sender', option.id)}
                className={cn(
                  'rounded-xl border p-4 text-left transition-all',
                  active ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-slate-200 bg-white hover:border-slate-300',
                )}
              >
                <span className="flex items-center justify-between gap-2">
                  <span className="flex items-center gap-2">
                    <option.icon className={cn('h-4 w-4', active ? 'text-primary' : 'text-slate-500')} />
                    <span className="text-sm font-semibold text-foreground">{option.title}</span>
                  </span>
                  {option.badge ? <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">{option.badge}</span> : null}
                </span>
                <span className="mt-2 block text-xs leading-relaxed text-muted-foreground">{option.text}</span>
              </button>
            );
          })}
        </div>

        {sender === 'wordpress' ? (
          <div className="space-y-3 rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div className="space-y-1">
                <p className="text-sm font-medium text-foreground">Subject, heading and text</p>
                <p className="text-xs text-muted-foreground">
                  Edited in WooCommerce like your other store emails (“License keys”). Colours, logo and footer come from WooCommerce’s email settings.
                </p>
              </div>
              <Button asChild variant="outline" size="sm" className="gap-1.5">
                <a href={settings.wc_email_url}>
                  Edit email <ExternalLink className="h-3.5 w-3.5" />
                </a>
              </Button>
            </div>
            <p className="text-xs text-muted-foreground">
              Sent from <span className="font-medium text-foreground">{settings.wc_from_name || 'your store'}</span>
              {settings.wc_from_address ? <> &lt;{settings.wc_from_address}&gt;</> : null} ·{' '}
              <a className="text-primary hover:underline" href={settings.wc_sender_url}>change sender</a>
            </p>
          </div>
        ) : (
          <AppStatusCard status={status} checking={checking} onRefresh={checkApp} settings={settings} />
        )}
      </SettingsSection>

      <SettingsSection
        title="Send a test"
        description={
          sender === 'app'
            ? 'Sends the WordPress version (used if the app can’t send). Test the app’s template from its editor in LicenseSender.'
            : 'Sends the license keys email with sample keys.'
        }
        singleColumn
      >
        <div className="flex flex-col gap-2 sm:flex-row">
          <Input
            type="email"
            value={testEmail}
            placeholder="you@example.com"
            onChange={(e) => setTestEmail(e.target.value)}
            aria-label="Send the test to"
          />
          <Button type="button" className="shrink-0 gap-2" onClick={sendTest} disabled={sending}>
            {sending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
            Send test
          </Button>
        </div>
        <p className="text-xs text-muted-foreground">
          <Mail className="mr-1 inline h-3.5 w-3.5 align-[-2px]" />
          Not arriving? WordPress mail often lands in spam without an SMTP plugin.
        </p>
      </SettingsSection>
    </div>
  );
}

function AppStatusCard({
  status,
  checking,
  onRefresh,
  settings,
}: {
  status: AppStatus | null;
  checking: boolean;
  onRefresh: () => void;
  settings: Record<string, string>;
}) {
  const ready = !!status?.ready;

  return (
    <div className={cn('space-y-3 rounded-lg border p-4', status && !ready && 'border-amber-200 bg-amber-50/60')}>
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-start gap-2.5">
          {checking || !status ? (
            <Loader2 className="mt-0.5 h-4 w-4 animate-spin text-slate-400" />
          ) : ready ? (
            <CheckCircle2 className="mt-0.5 h-4 w-4 text-emerald-600" />
          ) : (
            <AlertTriangle className="mt-0.5 h-4 w-4 text-amber-600" />
          )}
          <div className="space-y-1">
            <p className="text-sm font-medium text-foreground">
              {checking || !status ? 'Checking LicenseSender…' : ready ? `Ready: ${status.shop} sends from ${status.from}` : 'LicenseSender can’t send yet'}
            </p>
            {status && !ready && !checking ? (
              <p className="text-xs text-amber-800">
                {status.message} Until this is fixed, WordPress sends the emails.
              </p>
            ) : null}
          </div>
        </div>
        <Button type="button" variant="ghost" size="sm" className="h-7 gap-1.5 text-xs" onClick={onRefresh} disabled={checking}>
          <RefreshCw className={cn('h-3.5 w-3.5', checking && 'animate-spin')} /> Check
        </Button>
      </div>
      <div className="flex flex-wrap gap-2">
        <Button asChild variant="outline" size="sm" className="gap-1.5">
          <a href={settings.app_template_url} target="_blank" rel="noopener noreferrer">
            Edit template <ExternalLink className="h-3.5 w-3.5" />
          </a>
        </Button>
        <Button asChild variant="outline" size="sm" className="gap-1.5">
          <a href={settings.app_shops_url} target="_blank" rel="noopener noreferrer">
            Shop mail settings <ExternalLink className="h-3.5 w-3.5" />
          </a>
        </Button>
        <Button asChild variant="ghost" size="sm" className="gap-1.5">
          <a href={settings.app_log_url} target="_blank" rel="noopener noreferrer">
            Email log <ExternalLink className="h-3.5 w-3.5" />
          </a>
        </Button>
      </div>
    </div>
  );
}
