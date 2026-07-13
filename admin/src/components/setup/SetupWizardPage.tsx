import { useMemo, useState } from 'react';
import {
  CheckCircle2,
  ExternalLink,
  KeyRound,
  Package,
  PlugZap,
  Settings2,
  ArrowRight,
  ArrowLeft,
} from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { getBootstrap, type SetupStatus } from '@/lib/bootstrap';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

type StepId = 'welcome' | 'connect' | 'essentials' | 'done';

const STEPS: { id: StepId; labelKey: string }[] = [
  { id: 'welcome', labelKey: 'stepWelcome' },
  { id: 'connect', labelKey: 'stepConnect' },
  { id: 'essentials', labelKey: 'stepEssentials' },
  { id: 'done', labelKey: 'stepDone' },
];

export function SetupWizardPage() {
  const { i18n, pluginVersion, logoUrl, setup: initialSetup } = getBootstrap();
  const [step, setStep] = useState<StepId>('welcome');
  const [status, setStatus] = useState<SetupStatus>(
    initialSetup || {
      complete: false,
      api_key: '',
      autocomplete: true,
      send_email_redeem: false,
      manage_downloads: false,
      manage_guides: false,
      site_name: '',
      admin_email: '',
      dashboard_url: '',
      settings_url: '',
      products_url: '',
      account_url: 'https://licensesender.com/client',
      docs_url: 'https://licensesender.com/docs',
      plugin_version: pluginVersion || '',
    }
  );
  const [apiKey, setApiKey] = useState(status.api_key || '');
  const [testing, setTesting] = useState(false);
  const [connected, setConnected] = useState(Boolean(status.api_key));
  const [saving, setSaving] = useState(false);

  const stepIndex = STEPS.findIndex((s) => s.id === step);

  const essentials = useMemo(
    () => [
      {
        key: 'autocomplete' as const,
        title: i18n.autocomplete || 'Auto-complete orders',
        hint: i18n.autocompleteHint || 'Mark orders completed after keys are delivered.',
        value: status.autocomplete,
      },
      {
        key: 'send_email_redeem' as const,
        title: i18n.emailRedeem || 'Email after redemption',
        hint: i18n.emailRedeemHint || 'Send license emails when customers redeem keys.',
        value: status.send_email_redeem,
      },
      {
        key: 'manage_downloads' as const,
        title: i18n.manageDownloads || 'Manage download links',
        hint: i18n.manageDownloadsHint || 'Enable the Download Links admin menu.',
        value: status.manage_downloads,
      },
      {
        key: 'manage_guides' as const,
        title: i18n.manageGuides || 'Manage activation guides',
        hint: i18n.manageGuidesHint || 'Enable the Activation Guides admin menu.',
        value: status.manage_guides,
      },
    ],
    [i18n, status]
  );

  const savePartial = async (payload: Record<string, unknown>) => {
    const next = await apiRequest<SetupStatus>('setup', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    setStatus(next);
    return next;
  };

  const testConnection = async () => {
    const key = apiKey.trim();
    if (!key) {
      toast.error(i18n.needApiKey || 'Enter your API key to continue.');
      return false;
    }

    setTesting(true);
    try {
      await savePartial({ api_key: key });
      await apiRequest('settings/ping', { method: 'POST' });
      setConnected(true);
      toast.success(i18n.connectionOk || 'Connected successfully');
      return true;
    } catch (err) {
      setConnected(false);
      toast.error(err instanceof Error ? err.message : i18n.error);
      return false;
    } finally {
      setTesting(false);
    }
  };

  const finish = async (skip = false) => {
    setSaving(true);
    try {
      const payload = skip
        ? {}
        : {
            api_key: apiKey.trim(),
            autocomplete: status.autocomplete,
            send_email_redeem: status.send_email_redeem,
            manage_downloads: status.manage_downloads,
            manage_guides: status.manage_guides,
          };

      const result = await apiRequest<SetupStatus & { message?: string }>('setup/complete', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      setStatus(result);

      if (skip) {
        window.location.href = result.dashboard_url || status.dashboard_url;
        return;
      }

      setStep('done');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setSaving(false);
    }
  };

  const goNext = async () => {
    if (step === 'welcome') {
      setStep('connect');
      return;
    }

    if (step === 'connect') {
      if (!connected) {
        const ok = await testConnection();
        if (!ok) return;
      } else if (apiKey.trim() && apiKey.trim() !== status.api_key) {
        const ok = await testConnection();
        if (!ok) return;
      } else if (!apiKey.trim()) {
        toast.error(i18n.needApiKey || 'Enter your API key to continue.');
        return;
      }
      setStep('essentials');
      return;
    }

    if (step === 'essentials') {
      setSaving(true);
      try {
        await savePartial({
          autocomplete: status.autocomplete,
          send_email_redeem: status.send_email_redeem,
          manage_downloads: status.manage_downloads,
          manage_guides: status.manage_guides,
        });
        await finish(false);
      } catch (err) {
        toast.error(err instanceof Error ? err.message : i18n.error);
      } finally {
        setSaving(false);
      }
    }
  };

  const goBack = () => {
    if (step === 'connect') setStep('welcome');
    if (step === 'essentials') setStep('connect');
  };

  return (
    <div className="relative min-h-screen bg-slate-50 text-slate-900">
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(255,255,255,0.95),transparent_55%)]" />

      <div className="relative mx-auto flex min-h-screen w-full max-w-2xl flex-col px-4 py-6 sm:px-6">
        <header className="flex shrink-0 items-center justify-between gap-3">
          <div className="flex min-w-0 items-center gap-3">
            {logoUrl ? (
              <img
                src={logoUrl}
                alt="LicenseSender"
                width={40}
                height={40}
                className="h-10 w-10 shrink-0 rounded-xl object-cover shadow-sm ring-1 ring-slate-200/80"
              />
            ) : (
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white shadow-sm">
                <Settings2 className="h-5 w-5 text-slate-800" />
              </div>
            )}
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold tracking-tight">LicenseSender</p>
              <p className="text-xs text-slate-500">
                Setup wizard{pluginVersion ? ` · v${pluginVersion}` : ''}
              </p>
            </div>
          </div>
          {step !== 'done' ? (
            <Button variant="ghost" size="sm" className="shrink-0" disabled={saving} onClick={() => finish(true)}>
              {i18n.skipSetup || 'Skip for now'}
            </Button>
          ) : null}
        </header>

        <div className="flex flex-1 flex-col justify-center py-8 sm:py-10">
          <nav aria-label="Setup progress" className="mb-6 grid grid-cols-4 gap-2 sm:gap-3">
            {STEPS.map((s, index) => {
              const active = index === stepIndex;
              const done = index < stepIndex;
              return (
                <div key={s.id} className="min-w-0 text-center">
                  <div
                    className={cn(
                      'mx-auto h-1.5 w-full rounded-full transition-colors',
                      done || active ? 'bg-slate-900' : 'bg-slate-200'
                    )}
                  />
                  <p
                    className={cn(
                      'mt-2 truncate text-[11px] font-medium',
                      active ? 'text-slate-900' : 'text-slate-400'
                    )}
                  >
                    {i18n[s.labelKey] || s.id}
                  </p>
                </div>
              );
            })}
          </nav>

          <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="px-5 py-6 sm:px-8 sm:py-8">
              {step === 'welcome' ? (
                <div className="space-y-6">
                  <Badge variant="outline" className="border-slate-200 bg-slate-50 text-slate-600">
                    {status.site_name || 'WooCommerce'}
                  </Badge>
                  <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                      {i18n.welcomeTitle || 'Welcome to LicenseSender'}
                    </h1>
                    <p className="mt-2 max-w-xl text-sm leading-relaxed text-slate-500">
                      {i18n.welcomeSubtitle ||
                        'A short setup gets license delivery working on your WooCommerce store.'}
                    </p>
                  </div>
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    {[
                      { icon: KeyRound, label: 'Connect API' },
                      { icon: Package, label: 'Map products' },
                      { icon: PlugZap, label: 'Deliver keys' },
                    ].map((item) => (
                      <div
                        key={item.label}
                        className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-left"
                      >
                        <item.icon className="mb-3 h-4 w-4 text-slate-700" />
                        <p className="text-sm font-medium text-slate-800">{item.label}</p>
                      </div>
                    ))}
                  </div>
                </div>
              ) : null}

              {step === 'connect' ? (
                <div className="space-y-6">
                  <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                      {i18n.connectTitle || 'Connect your API key'}
                    </h1>
                    <p className="mt-2 text-sm leading-relaxed text-slate-500">
                      {i18n.connectSubtitle ||
                        'Paste the API key from your LicenseSender account. You can find it under API Keys.'}
                    </p>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="ls-setup-api-key">{i18n.apiKey || 'API Key'}</Label>
                    <Input
                      id="ls-setup-api-key"
                      value={apiKey}
                      onChange={(e) => {
                        setApiKey(e.target.value);
                        setConnected(false);
                      }}
                      placeholder={i18n.apiKeyPlaceholder || 'Paste your API key here'}
                      className="font-mono text-sm"
                      autoComplete="off"
                    />
                  </div>
                  <div className="flex flex-wrap items-center gap-3">
                    <Button type="button" variant="outline" disabled={testing} onClick={() => testConnection()}>
                      <PlugZap className="h-4 w-4" />
                      {testing ? i18n.testing || 'Testing…' : i18n.testConnection || 'Test connection'}
                    </Button>
                    {connected ? (
                      <span className="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-700">
                        <CheckCircle2 className="h-4 w-4" />
                        {i18n.connectionOk || 'Connected successfully'}
                      </span>
                    ) : null}
                  </div>
                  <a
                    href={status.account_url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-slate-900"
                  >
                    {i18n.openAccount || 'Open LicenseSender account'}
                    <ExternalLink className="h-3.5 w-3.5" />
                  </a>
                </div>
              ) : null}

              {step === 'essentials' ? (
                <div className="space-y-6">
                  <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                      {i18n.essentialsTitle || 'Choose essentials'}
                    </h1>
                    <p className="mt-2 text-sm leading-relaxed text-slate-500">
                      {i18n.essentialsSubtitle || 'You can change these anytime in Settings.'}
                    </p>
                  </div>
                  <div className="space-y-3">
                    {essentials.map((item) => (
                      <div
                        key={item.key}
                        className="flex items-start justify-between gap-4 rounded-xl border border-slate-200 p-4"
                      >
                        <div className="min-w-0">
                          <p className="text-sm font-semibold text-slate-900">{item.title}</p>
                          <p className="mt-1 text-xs leading-relaxed text-slate-500">{item.hint}</p>
                        </div>
                        <Switch
                          checked={item.value}
                          className="mt-0.5 shrink-0"
                          onCheckedChange={(v) =>
                            setStatus((prev) => ({
                              ...prev,
                              [item.key]: v,
                            }))
                          }
                        />
                      </div>
                    ))}
                  </div>
                </div>
              ) : null}

              {step === 'done' ? (
                <div className="space-y-6 text-center">
                  <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                    <CheckCircle2 className="h-7 w-7" />
                  </div>
                  <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                      {i18n.finishTitle || 'You are ready'}
                    </h1>
                    <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500">
                      {i18n.finishSubtitle ||
                        'Map LicenseSender products in WooCommerce, then start delivering keys.'}
                    </p>
                  </div>
                  <div className="flex flex-col justify-center gap-2 sm:flex-row">
                    <Button asChild>
                      <a href={status.dashboard_url}>
                        {i18n.goDashboard || 'Go to Dashboard'}
                        <ArrowRight className="h-4 w-4" />
                      </a>
                    </Button>
                    <Button variant="outline" asChild>
                      <a href={status.products_url}>{i18n.mapProducts || 'Open products'}</a>
                    </Button>
                  </div>
                </div>
              ) : null}
            </div>

            {step !== 'done' ? (
              <div className="flex items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/80 px-5 py-4 sm:px-8">
                <Button
                  variant="outline"
                  disabled={step === 'welcome' || testing || saving}
                  onClick={goBack}
                  className={cn('bg-white', step === 'welcome' && 'invisible')}
                >
                  <ArrowLeft className="h-4 w-4" />
                  {i18n.back || 'Back'}
                </Button>
                <Button disabled={testing || saving} onClick={() => goNext()}>
                  {step === 'essentials'
                    ? saving
                      ? i18n.loading || 'Loading…'
                      : i18n.finishSetup || 'Finish setup'
                    : i18n.continue || 'Continue'}
                  <ArrowRight className="h-4 w-4" />
                </Button>
              </div>
            ) : null}
          </div>
        </div>
      </div>
    </div>
  );
}
