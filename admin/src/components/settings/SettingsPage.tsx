import { useCallback, useEffect, useState } from 'react';
import {
  BookOpen,
  ExternalLink,
  Headphones,
  KeyRound,
  Mail,
  MessageSquare,
  Palette,
  Save,
  Settings2,
  Shield,
  Store,
  type LucideIcon,
} from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { getBootstrap } from '@/lib/bootstrap';
import { DesignSettingsFields, type DesignPreset } from '@/components/settings/DesignSettingsFields';
import { EmailSettingsFields } from '@/components/settings/EmailSettingsFields';
import { GeneralSettingsFields } from '@/components/settings/GeneralSettingsFields';
import { PopupSettingsFields } from '@/components/settings/PopupSettingsFields';
import { WholesaleSettingsFields, type PageChoice, type PaymentGatewayChoice } from '@/components/settings/WholesaleSettingsFields';
import { SupportSettingsFields } from '@/components/settings/SupportSettingsFields';
import { AdvancedSettingsFields } from '@/components/settings/AdvancedSettingsFields';
import { ApiSubscriptionCard, type ApiSubscriptionDetails } from '@/components/settings/ApiSubscriptionCard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';

const TABS: {
  id: string;
  label: string;
  description: string;
  hint: string;
  icon: LucideIcon;
  group: 'configuration' | 'storefront' | 'system';
}[] = [
  {
    id: 'general',
    label: 'General',
    description: 'Orders, email timing, and catalog tools.',
    hint: 'Core behavior',
    icon: Settings2,
    group: 'configuration',
  },
  {
    id: 'api',
    label: 'API',
    description: 'licensesender API connection and subscription details.',
    hint: 'Connection',
    icon: KeyRound,
    group: 'configuration',
  },
  {
    id: 'email',
    label: 'Email',
    description: 'Sender details, license email templates, and test delivery.',
    hint: 'Delivery',
    icon: Mail,
    group: 'configuration',
  },
  {
    id: 'design',
    label: 'Design',
    description: 'Theme, brand colors, and storefront appearance.',
    hint: 'Appearance',
    icon: Palette,
    group: 'storefront',
  },
  {
    id: 'popup',
    label: 'Pop-ups',
    description: 'Customize confirm, bulk-fetch, and license key dialogs with a live preview.',
    hint: 'Dialogs',
    icon: MessageSquare,
    group: 'storefront',
  },
  {
    id: 'wholesale',
    label: 'Wholesale',
    description: 'B2B catalog, checkout rules, and storefront pages.',
    hint: 'B2B',
    icon: Store,
    group: 'storefront',
  },
  {
    id: 'support',
    label: 'Support',
    description: 'Customer support tickets and storefront pages.',
    hint: 'Tickets',
    icon: Headphones,
    group: 'storefront',
  },
  {
    id: 'advance',
    label: 'Advanced',
    description: 'SSO, webhooks, and advanced configuration.',
    hint: 'Integrations',
    icon: Shield,
    group: 'system',
  },
];

const NAV_GROUPS: { id: (typeof TABS)[number]['group']; label: string }[] = [
  { id: 'configuration', label: 'Configuration' },
  { id: 'storefront', label: 'Storefront' },
  { id: 'system', label: 'System' },
];

type TabId = (typeof TABS)[number]['id'];

export function SettingsPage() {
  const { tab: initialTab, i18n, pluginVersion } = getBootstrap();
  const [activeTab, setActiveTab] = useState<TabId>((initialTab as TabId) || 'general');
  const [settings, setSettings] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [subscriptionDetails, setSubscriptionDetails] = useState<ApiSubscriptionDetails | null>(null);
  const [subscriptionLoading, setSubscriptionLoading] = useState(false);
  const [wholesalePages, setWholesalePages] = useState<PageChoice[]>([]);
  const [supportPages, setSupportPages] = useState<PageChoice[]>([]);
  const [paymentGatewayChoices, setPaymentGatewayChoices] = useState<PaymentGatewayChoice[]>([]);
  const [regeneratingSecret, setRegeneratingSecret] = useState(false);
  const [designPresets, setDesignPresets] = useState<DesignPreset[]>([]);

  const regenerateWebhookSecret = useCallback(async () => {
    if (!window.confirm('Regenerate the webhook secret? Update the secret in LicenseSender after this.')) {
      return;
    }
    setRegeneratingSecret(true);
    try {
      const data = await apiRequest<{
        lship_webhook_secret?: string;
        lship_webhook_url?: string;
        message?: string;
      }>('settings/webhook-secret/regenerate', { method: 'POST' });
      setSettings((prev) => ({
        ...prev,
        lship_webhook_secret: data.lship_webhook_secret || '',
        lship_webhook_url: data.lship_webhook_url || prev.lship_webhook_url || '',
      }));
      toast.success(data.message || 'Webhook secret regenerated.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setRegeneratingSecret(false);
    }
  }, [i18n.error]);

  const loadSubscriptionDetails = useCallback(async (refresh = false) => {
    if (!settings.lship_api_key) {
      setSubscriptionDetails(null);
      return;
    }

    setSubscriptionLoading(true);
    try {
      const data = await apiRequest<ApiSubscriptionDetails>(
        `settings/subscription${refresh ? '?refresh=1' : ''}`,
      );
      setSubscriptionDetails(data);
    } catch (err) {
      setSubscriptionDetails(null);
      if (refresh) {
        toast.error(err instanceof Error ? err.message : i18n.error);
      }
    } finally {
      setSubscriptionLoading(false);
    }
  }, [settings.lship_api_key, i18n.error]);

  const loadSettings = useCallback(async (tab: TabId) => {
    setLoading(true);
    try {
      const data = await apiRequest<Record<string, unknown> & {
        pages?: PageChoice[];
        payment_gateway_choices?: PaymentGatewayChoice[];
        presets?: DesignPreset[];
        preview?: Record<string, string>;
      }>(`settings?tab=${tab}`);
      const { pages, payment_gateway_choices, presets, preview: _preview, ...rest } = data;
      const settingValues: Record<string, string> = {};
      Object.entries(rest).forEach(([key, value]) => {
        if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
          settingValues[key] = String(value);
        }
      });
      setSettings(settingValues);
      if (tab === 'wholesale' && Array.isArray(pages)) {
        setWholesalePages(pages);
      }
      if (tab === 'support' && Array.isArray(pages)) {
        setSupportPages(pages);
      }
      if (tab === 'wholesale' && Array.isArray(payment_gateway_choices)) {
        setPaymentGatewayChoices(payment_gateway_choices);
      }
      if (tab === 'design' && Array.isArray(presets)) {
        setDesignPresets(presets);
      }
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setLoading(false);
    }
  }, [i18n.error]);

  useEffect(() => {
    loadSettings(activeTab);
    const url = new URL(window.location.href);
    url.searchParams.set('tab', activeTab);
    window.history.replaceState({}, '', url.toString());
  }, [activeTab, loadSettings]);

  useEffect(() => {
    if (activeTab === 'api' && settings.lship_api_key) {
      loadSubscriptionDetails();
    }
  }, [activeTab, settings.lship_api_key, loadSubscriptionDetails]);

  const updateField = (key: string, value: string) => {
    setSettings((prev) => ({ ...prev, [key]: value }));
  };

  const save = async () => {
    setSaving(true);
    try {
      const payload: Record<string, string> = { ...settings };
      await apiRequest(`settings?tab=${activeTab}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
      });
      toast.success(i18n.success);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setSaving(false);
    }
  };

  const pingApi = async () => {
    try {
      const result = await apiRequest<ApiSubscriptionDetails & { subscription_details?: ApiSubscriptionDetails }>(
        'settings/ping',
        { method: 'POST' },
      );
      if (result.subscription_details) {
        setSubscriptionDetails(result.subscription_details);
      } else {
        await loadSubscriptionDetails(true);
      }
      toast.success(result.message || 'API connection successful.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    }
  };

  return (
    <div className="mx-auto max-w-[1400px] space-y-6 pb-8">
      <header className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2.5">
            <h1 className="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">Settings</h1>
            {pluginVersion ? (
              <Badge
                variant="outline"
                className="border-slate-200 bg-slate-50 font-mono text-[10px] font-medium text-slate-500"
              >
                v{pluginVersion}
              </Badge>
            ) : null}
          </div>
          <p className="mt-1 text-sm text-slate-500">
            Delivery, storefront, and integration options for LicenseSender.
          </p>
        </div>

        <Button onClick={save} disabled={saving || loading} className="h-10 shrink-0 gap-2 px-4">
          <Save className="h-4 w-4" aria-hidden />
          {saving ? 'Saving…' : i18n.save || 'Save Changes'}
        </Button>
      </header>

      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as TabId)} className="flex flex-col gap-6 lg:flex-row lg:items-start">
        <aside className="w-full shrink-0 lg:sticky lg:top-6 lg:w-[260px]">
          <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 px-4 py-3">
              <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Sections</p>
              <p className="mt-0.5 text-sm font-semibold text-slate-800">Plugin settings</p>
            </div>

            <TabsList className="flex h-auto w-full flex-col items-stretch gap-4 rounded-none border-0 bg-transparent p-3 shadow-none">
              {NAV_GROUPS.map((group) => {
                const items = TABS.filter((tab) => tab.group === group.id);
                return (
                  <div key={group.id} className="space-y-1">
                    <p className="px-2 pb-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
                      {group.label}
                    </p>
                    {items.map((t) => {
                      const Icon = t.icon;
                      const isActive = activeTab === t.id;
                      return (
                        <TabsTrigger
                          key={t.id}
                          value={t.id}
                          className={cn(
                            'group relative h-auto w-full justify-start gap-3 rounded-xl border border-transparent px-3 py-2.5 text-left shadow-none',
                            'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                            'data-[state=active]:border-slate-200 data-[state=active]:bg-slate-900 data-[state=active]:text-white data-[state=active]:shadow-sm'
                          )}
                        >
                          <span
                            className={cn(
                              'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border transition-colors',
                              isActive
                                ? 'border-white/15 bg-white/10 text-white'
                                : 'border-slate-200 bg-slate-50 text-slate-600 group-hover:border-slate-300 group-hover:bg-white'
                            )}
                          >
                            <Icon className="h-4 w-4" aria-hidden />
                          </span>
                          <span className="min-w-0 flex-1">
                            <span className="block text-sm font-semibold leading-none">{t.label}</span>
                            <span
                              className={cn(
                                'mt-1 block truncate text-[11px] leading-none',
                                isActive ? 'text-white/65' : 'text-slate-400'
                              )}
                            >
                              {t.hint}
                            </span>
                          </span>
                        </TabsTrigger>
                      );
                    })}
                  </div>
                );
              })}
            </TabsList>

            <div className="border-t border-slate-100 bg-slate-50/80 px-4 py-3">
              <a
                href="https://licensesender.com/docs"
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-2 text-xs font-medium text-slate-600 transition-colors hover:text-slate-900"
              >
                <BookOpen className="h-3.5 w-3.5" aria-hidden />
                Documentation
                <ExternalLink className="h-3 w-3 opacity-60" aria-hidden />
              </a>
            </div>
          </div>
        </aside>

        <div className="min-w-0 flex-1">
          {TABS.map((t) => {
            const TabIcon = t.icon;
            return (
              <TabsContent key={t.id} value={t.id} className="mt-0">
                <Card className="overflow-hidden border-slate-200 shadow-sm">
                  <CardHeader className="space-y-0 border-b border-slate-100 bg-slate-50/60 px-6 py-5">
                    <div className="flex items-start gap-3">
                      <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white shadow-sm">
                        <TabIcon className="h-4 w-4 text-slate-700" aria-hidden />
                      </div>
                      <div className="min-w-0">
                        <CardTitle className="text-lg font-semibold tracking-tight">{t.label}</CardTitle>
                        <CardDescription className="mt-1 text-sm leading-relaxed">{t.description}</CardDescription>
                      </div>
                    </div>
                  </CardHeader>
                  <CardContent className="space-y-6 p-6">
                    {loading ? (
                      <div className="space-y-3">
                        <Skeleton className="h-24 w-full rounded-xl" />
                        <Skeleton className="h-20 w-full rounded-xl" />
                        <Skeleton className="h-20 w-full rounded-xl" />
                      </div>
                    ) : (
                      <>
                        {t.id === 'general' && (
                          <GeneralSettingsFields settings={settings} onChange={updateField} />
                        )}

                        {t.id === 'api' && (
                          <div className="space-y-6">
                            <ApiSubscriptionCard
                              details={subscriptionDetails}
                              loading={subscriptionLoading}
                              onRefresh={() => loadSubscriptionDetails(true)}
                            />

                            <div className="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
                              <div className="space-y-2">
                                <Label>API Key</Label>
                                <Input
                                  value={settings.lship_api_key || ''}
                                  onChange={(e) => updateField('lship_api_key', e.target.value)}
                                />
                              </div>
                              <Button variant="outline" onClick={pingApi}>
                                Test Connection
                              </Button>
                            </div>
                          </div>
                        )}

                        {t.id === 'email' && (
                          <EmailSettingsFields settings={settings} onChange={updateField} />
                        )}

                        {t.id === 'design' && (
                          <DesignSettingsFields
                            settings={settings}
                            presets={designPresets}
                            onChange={updateField}
                          />
                        )}

                        {t.id === 'popup' && (
                          <PopupSettingsFields settings={settings} onChange={updateField} />
                        )}

                        {t.id === 'wholesale' && (
                          <WholesaleSettingsFields
                            settings={settings}
                            pages={wholesalePages}
                            paymentGateways={paymentGatewayChoices}
                            onChange={updateField}
                            onPagesGenerated={setWholesalePages}
                          />
                        )}

                        {t.id === 'support' && (
                          <SupportSettingsFields
                            settings={settings}
                            pages={supportPages}
                            onChange={updateField}
                            onPagesGenerated={setSupportPages}
                          />
                        )}

                        {t.id === 'advance' && (
                          <AdvancedSettingsFields
                            settings={settings}
                            regeneratingSecret={regeneratingSecret}
                            onChange={updateField}
                            onRegenerateSecret={regenerateWebhookSecret}
                          />
                        )}
                      </>
                    )}

                    <div className="sticky bottom-0 -mx-6 -mb-6 flex items-center justify-between gap-3 border-t border-slate-200 bg-white/95 px-6 py-4 backdrop-blur">
                      <p className="hidden text-xs text-muted-foreground sm:block">
                        Changes apply after you save this tab.
                      </p>
                      <Button onClick={save} disabled={saving || loading} className="ml-auto min-w-[140px]">
                        {saving ? 'Saving…' : i18n.save}
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              </TabsContent>
            );
          })}
        </div>
      </Tabs>
    </div>
  );
}
