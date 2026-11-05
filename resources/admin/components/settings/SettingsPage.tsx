import { useCallback, useEffect, useRef, useState } from 'react';
import {
  BookOpen,
  ExternalLink,
  Eye,
  EyeOff,
  Headphones,
  KeyRound,
  Mail,
  MessageCircle,
  MessageSquare,
  Palette,
  PlugZap,
  RotateCcw,
  Save,
  Settings2,
  Shield,
  Store,
  type LucideIcon,
} from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { getBootstrap } from '@/lib/bootstrap';
import { DesignSettingsFields, type BrandSwatch } from '@/components/settings/DesignSettingsFields';
import { EmailSettingsFields } from '@/components/settings/EmailSettingsFields';
import { GeneralSettingsFields } from '@/components/settings/GeneralSettingsFields';
import { PopupSettingsFields } from '@/components/settings/PopupSettingsFields';
import { WholesaleSettingsFields, type GroupChoice, type PageChoice, type PaymentGatewayChoice } from '@/components/settings/WholesaleSettingsFields';
import { SupportSettingsFields } from '@/components/settings/SupportSettingsFields';
import { ChatSettingsFields } from '@/components/settings/ChatSettingsFields';
import { AdvancedSettingsFields, type OrderBackfillStatus } from '@/components/settings/AdvancedSettingsFields';
import { ApiSubscriptionCard, type ApiSubscriptionDetails } from '@/components/settings/ApiSubscriptionCard';
import { PageHeader } from '@/components/shared/PageHeader';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
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
  /** Tabs with nothing to save hide the save bar. */
  readOnly?: boolean;
}[] = [
  { id: 'general', label: 'General', description: 'Orders, email timing, and catalog tools.', hint: 'Core behavior', icon: Settings2, group: 'configuration' },
  { id: 'api', label: 'API', description: 'LicenseSender API connection and subscription details.', hint: 'Connection', icon: KeyRound, group: 'configuration' },
  { id: 'email', label: 'Email', description: 'The license keys email: on or off, who sends it, and a test.', hint: 'Delivery', icon: Mail, group: 'configuration' },
  { id: 'design', label: 'Design', description: 'Brand colour, corners and light or dark mode for every customer screen.', hint: 'Appearance', icon: Palette, group: 'storefront' },
  { id: 'popup', label: 'Key texts', description: 'Wording customers see when they get and view their license keys.', hint: 'Wording', icon: MessageSquare, group: 'storefront' },
  { id: 'wholesale', label: 'Wholesale', description: 'B2B catalog, checkout rules, and storefront pages.', hint: 'B2B', icon: Store, group: 'storefront' },
  { id: 'support', label: 'Support', description: 'Customer support tickets and storefront pages.', hint: 'Tickets', icon: Headphones, group: 'storefront' },
  { id: 'chat', label: 'Live Chat', description: 'AI floating chat widget for your storefront.', hint: 'Widget', icon: MessageCircle, group: 'storefront' },
  { id: 'advance', label: 'Advanced', description: 'Webhooks, order sync, and advanced configuration.', hint: 'Integrations', icon: Shield, group: 'system', readOnly: true },
];

const NAV_GROUPS: { id: (typeof TABS)[number]['group']; label: string }[] = [
  { id: 'configuration', label: 'Configuration' },
  { id: 'storefront', label: 'Storefront' },
  { id: 'system', label: 'System' },
];

type TabId = (typeof TABS)[number]['id'];

type SettingsResponse = Record<string, unknown> & {
  pages?: PageChoice[];
  payment_gateway_choices?: PaymentGatewayChoice[];
  group_choices?: GroupChoice[];
  swatches?: BrandSwatch[];
  preview?: Record<string, string>;
  order_backfill?: OrderBackfillStatus;
};

/** Field values as strings; lists and objects (pages, presets…) are handled separately. */
function toValues(data: Record<string, unknown>): Record<string, string> {
  const values: Record<string, string> = {};
  Object.entries(data).forEach(([key, value]) => {
    if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
      values[key] = String(value);
    }
  });
  return values;
}

function sameValues(a: Record<string, string>, b: Record<string, string>) {
  const keys = new Set([...Object.keys(a), ...Object.keys(b)]);
  for (const k of keys) {
    if ((a[k] ?? '') !== (b[k] ?? '')) return false;
  }
  return true;
}

export function SettingsPage() {
  const { tab: initialTab, i18n, pluginVersion } = getBootstrap();
  const [activeTab, setActiveTab] = useState<TabId>(TABS.some((t) => t.id === initialTab) ? (initialTab as TabId) : 'general');
  const [settings, setSettings] = useState<Record<string, string>>({});
  const [saved, setSaved] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [pendingTab, setPendingTab] = useState<TabId | null>(null);
  const [showKey, setShowKey] = useState(false);
  const [testing, setTesting] = useState(false);
  const [subscriptionDetails, setSubscriptionDetails] = useState<ApiSubscriptionDetails | null>(null);
  const [subscriptionLoading, setSubscriptionLoading] = useState(false);
  const [wholesalePages, setWholesalePages] = useState<PageChoice[]>([]);
  const [supportPages, setSupportPages] = useState<PageChoice[]>([]);
  const [paymentGatewayChoices, setPaymentGatewayChoices] = useState<PaymentGatewayChoice[]>([]);
  const [groupChoices, setGroupChoices] = useState<GroupChoice[]>([]);
  const [regeneratingSecret, setRegeneratingSecret] = useState(false);
  const [brandSwatches, setBrandSwatches] = useState<BrandSwatch[]>([]);
  const [orderBackfill, setOrderBackfill] = useState<OrderBackfillStatus | null>(null);

  const current = TABS.find((t) => t.id === activeTab) ?? TABS[0];
  const dirty = !loading && !current.readOnly && !sameValues(settings, saved);

  const regenerateWebhookSecret = useCallback(async () => {
    if (!window.confirm('Regenerate the webhook secret? Update the secret in LicenseSender after this.')) {
      return;
    }
    setRegeneratingSecret(true);
    try {
      const data = await apiRequest<{ lship_webhook_secret?: string; lship_webhook_url?: string; message?: string }>(
        'settings/webhook-secret/regenerate',
        { method: 'POST' }
      );
      const update = (prev: Record<string, string>) => ({
        ...prev,
        lship_webhook_secret: data.lship_webhook_secret || '',
        lship_webhook_url: data.lship_webhook_url || prev.lship_webhook_url || '',
      });
      setSettings(update);
      setSaved(update);
      toast.success(data.message || 'Webhook secret regenerated.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setRegeneratingSecret(false);
    }
  }, [i18n.error]);

  const loadSubscriptionDetails = useCallback(
    async (refresh = false) => {
      setSubscriptionLoading(true);
      try {
        setSubscriptionDetails(await apiRequest<ApiSubscriptionDetails>(`settings/subscription${refresh ? '?refresh=1' : ''}`));
      } catch (err) {
        setSubscriptionDetails(null);
        if (refresh) {
          toast.error(err instanceof Error ? err.message : i18n.error);
        }
      } finally {
        setSubscriptionLoading(false);
      }
    },
    [i18n.error]
  );

  const applyResponse = useCallback((tab: TabId, data: SettingsResponse) => {
    const { pages, payment_gateway_choices, group_choices, swatches, preview: _preview, order_backfill, ...rest } = data;
    const values = toValues(rest);
    setSettings(values);
    setSaved(values);
    if (tab === 'advance') setOrderBackfill(order_backfill && typeof order_backfill === 'object' ? order_backfill : null);
    if (tab === 'wholesale' && Array.isArray(pages)) setWholesalePages(pages);
    if (tab === 'support' && Array.isArray(pages)) setSupportPages(pages);
    if (tab === 'wholesale' && Array.isArray(payment_gateway_choices)) setPaymentGatewayChoices(payment_gateway_choices);
    if (tab === 'wholesale' && Array.isArray(group_choices)) setGroupChoices(group_choices);
    if (tab === 'design' && Array.isArray(swatches)) setBrandSwatches(swatches);
  }, []);

  const loadSettings = useCallback(
    async (tab: TabId) => {
      setLoading(true);
      try {
        applyResponse(tab, await apiRequest<SettingsResponse>(`settings?tab=${tab}`));
      } catch (err) {
        toast.error(err instanceof Error ? err.message : i18n.error);
      } finally {
        setLoading(false);
      }
    },
    [applyResponse, i18n.error]
  );

  useEffect(() => {
    loadSettings(activeTab);
    const url = new URL(window.location.href);
    url.searchParams.set('tab', activeTab);
    window.history.replaceState({}, '', url.toString());
  }, [activeTab, loadSettings]);

  // Load the plan card once the API tab knows there is a key.
  const hasKey = activeTab === 'api' && !!saved.lship_api_key;
  useEffect(() => {
    if (hasKey) {
      loadSubscriptionDetails();
    } else if (activeTab === 'api') {
      setSubscriptionDetails(null);
    }
  }, [hasKey, activeTab, loadSubscriptionDetails]);

  // Warn before leaving the page with unsaved edits.
  useEffect(() => {
    if (!dirty) return;
    const onBeforeUnload = (e: BeforeUnloadEvent) => {
      e.preventDefault();
      e.returnValue = '';
    };
    window.addEventListener('beforeunload', onBeforeUnload);
    return () => window.removeEventListener('beforeunload', onBeforeUnload);
  }, [dirty]);

  const updateField = (key: string, value: string) => {
    setSettings((prev) => ({ ...prev, [key]: value }));
  };

  const save = useCallback(async () => {
    if (current.readOnly) return true;
    setSaving(true);
    try {
      const result = await apiRequest<{ message?: string; settings?: SettingsResponse }>(`settings?tab=${activeTab}`, {
        method: 'PUT',
        body: JSON.stringify(settings),
      });
      // Show what was actually stored (emails cleaned, numbers clamped, …).
      if (result.settings) applyResponse(activeTab, result.settings);
      else setSaved(settings);
      toast.success(result.message || i18n.success || 'Settings saved.');
      if (activeTab === 'api') loadSubscriptionDetails(true);
      return true;
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
      return false;
    } finally {
      setSaving(false);
    }
  }, [activeTab, settings, current.readOnly, applyResponse, loadSubscriptionDetails, i18n.success, i18n.error]);

  // Ctrl/Cmd+S saves the open tab.
  const saveRef = useRef(save);
  saveRef.current = save;
  const canSaveRef = useRef(false);
  canSaveRef.current = dirty && !saving;
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        if (canSaveRef.current) saveRef.current();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  const requestTab = (tab: TabId) => {
    if (tab === activeTab) return;
    if (dirty) {
      setPendingTab(tab);
      return;
    }
    setActiveTab(tab);
  };

  const discard = () => setSettings(saved);

  const pingApi = async () => {
    setTesting(true);
    try {
      const result = await apiRequest<ApiSubscriptionDetails & { subscription_details?: ApiSubscriptionDetails }>('settings/ping', { method: 'POST' });
      if (result.subscription_details) {
        setSubscriptionDetails(result.subscription_details);
      } else {
        await loadSubscriptionDetails(true);
      }
      toast.success(result.message || 'API connection successful.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setTesting(false);
    }
  };

  const saveLabel = saving ? 'Saving…' : i18n.save || 'Save changes';

  return (
    <div className="mx-auto max-w-[1400px] space-y-6 pb-8">
      <PageHeader
        title="Settings"
        subtitle="Delivery, storefront, and integration options for LicenseSender."
        badge={
          pluginVersion ? (
            <Badge variant="outline" className="border-slate-200 bg-slate-50 font-mono text-[10px] font-medium text-slate-500">
              v{pluginVersion}
            </Badge>
          ) : null
        }
        actions={
          current.readOnly ? null : (
            <Button onClick={() => save()} disabled={saving || loading || !dirty} className="h-10 shrink-0 gap-2 px-4">
              <Save className="h-4 w-4" aria-hidden />
              {saveLabel}
            </Button>
          )
        }
      />

      <Tabs value={activeTab} onValueChange={(v) => requestTab(v as TabId)} className="flex flex-col gap-6 lg:flex-row lg:items-start">
        {/* Phones and tablets: a compact section picker. */}
        <div className="lg:hidden">
          <Label className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Section</Label>
          <Select value={activeTab} onValueChange={(v) => requestTab(v as TabId)}>
            <SelectTrigger className="h-11 bg-white">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {NAV_GROUPS.map((group) => (
                <SelectGroup key={group.id}>
                  <SelectLabel className="text-[11px] uppercase tracking-wide text-slate-400">{group.label}</SelectLabel>
                  {TABS.filter((t) => t.group === group.id).map((t) => (
                    <SelectItem key={t.id} value={t.id}>
                      {t.label}
                    </SelectItem>
                  ))}
                </SelectGroup>
              ))}
            </SelectContent>
          </Select>
        </div>

        <aside className="hidden w-full shrink-0 lg:sticky lg:top-12 lg:block lg:w-[260px]">
          <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 px-4 py-3">
              <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Sections</p>
              <p className="mt-0.5 text-sm font-semibold text-slate-800">Plugin settings</p>
            </div>

            <TabsList className="flex h-auto w-full flex-col items-stretch gap-4 rounded-none border-0 bg-transparent p-3 shadow-none">
              {NAV_GROUPS.map((group) => (
                <div key={group.id} className="space-y-1">
                  <p className="px-2 pb-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">{group.label}</p>
                  {TABS.filter((tab) => tab.group === group.id).map((t) => {
                    const Icon = t.icon;
                    const isActive = activeTab === t.id;
                    return (
                      <TabsTrigger
                        key={t.id}
                        value={t.id}
                        className={cn(
                          'group relative h-auto w-full justify-start gap-3 rounded-xl border border-transparent px-3 py-2.5 text-left shadow-none',
                          'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                          'data-[state=active]:border-indigo-100 data-[state=active]:bg-indigo-50 data-[state=active]:text-indigo-900 data-[state=active]:shadow-none'
                        )}
                      >
                        <span
                          className={cn(
                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border transition-colors',
                            isActive ? 'border-indigo-200 bg-white text-indigo-600' : 'border-slate-200 bg-slate-50 text-slate-600 group-hover:border-slate-300 group-hover:bg-white'
                          )}
                        >
                          <Icon className="h-4 w-4" aria-hidden />
                        </span>
                        <span className="min-w-0 flex-1">
                          <span className="block text-sm font-semibold leading-none">{t.label}</span>
                          <span className={cn('mt-1 block truncate text-[11px] leading-none', isActive ? 'text-indigo-600/70' : 'text-slate-400')}>{t.hint}</span>
                        </span>
                        {isActive && dirty ? <span className="h-2 w-2 shrink-0 rounded-full bg-amber-500" title="Unsaved changes" /> : null}
                      </TabsTrigger>
                    );
                  })}
                </div>
              ))}
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
                <Card className="overflow-visible border-slate-200 shadow-sm">
                  <CardHeader className="space-y-0 rounded-t-xl border-b border-slate-100 bg-slate-50/60 px-6 py-5">
                    <div className="flex items-start gap-3">
                      <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-indigo-100 bg-white text-indigo-600 shadow-sm">
                        <TabIcon className="h-4 w-4" aria-hidden />
                      </div>
                      <div className="min-w-0">
                        <CardTitle className="text-lg font-semibold tracking-tight">{t.label}</CardTitle>
                        <CardDescription className="mt-1 text-sm leading-relaxed">{t.description}</CardDescription>
                      </div>
                    </div>
                  </CardHeader>
                  <CardContent className="space-y-6 p-4 sm:p-6">
                    {loading ? (
                      <div className="space-y-3">
                        <Skeleton className="h-24 w-full rounded-xl" />
                        <Skeleton className="h-20 w-full rounded-xl" />
                        <Skeleton className="h-20 w-full rounded-xl" />
                      </div>
                    ) : (
                      <>
                        {t.id === 'general' && <GeneralSettingsFields settings={settings} onChange={updateField} />}

                        {t.id === 'api' && (
                          <div className="space-y-6">
                            <div className="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
                              <div className="space-y-2">
                                <Label htmlFor="lship_api_key">API key</Label>
                                <div className="flex gap-2">
                                  <div className="relative flex-1">
                                    <Input
                                      id="lship_api_key"
                                      type={showKey ? 'text' : 'password'}
                                      autoComplete="off"
                                      spellCheck={false}
                                      className="pr-10 font-mono"
                                      placeholder="Paste the key from your LicenseSender panel"
                                      value={settings.lship_api_key || ''}
                                      onChange={(e) => updateField('lship_api_key', e.target.value.trim())}
                                    />
                                    <button
                                      type="button"
                                      onClick={() => setShowKey((v) => !v)}
                                      className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-slate-400 hover:text-slate-700"
                                      aria-label={showKey ? 'Hide API key' : 'Show API key'}
                                    >
                                      {showKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                    </button>
                                  </div>
                                  <Button variant="outline" onClick={pingApi} disabled={testing || !saved.lship_api_key || dirty} title={dirty ? 'Save the new key first' : undefined}>
                                    <PlugZap className="h-4 w-4" />
                                    {testing ? 'Testing…' : 'Test connection'}
                                  </Button>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                  {dirty ? 'Save to use the new key, then test the connection.' : 'Find it in your LicenseSender panel under API keys.'}
                                </p>
                              </div>
                            </div>

                            {saved.lship_api_key ? (
                              <ApiSubscriptionCard details={subscriptionDetails} loading={subscriptionLoading} onRefresh={() => loadSubscriptionDetails(true)} />
                            ) : null}
                          </div>
                        )}

                        {t.id === 'email' && <EmailSettingsFields settings={settings} onChange={updateField} />}

                        {t.id === 'design' && <DesignSettingsFields settings={settings} swatches={brandSwatches} onChange={updateField} />}

                        {t.id === 'popup' && <PopupSettingsFields settings={settings} onChange={updateField} />}

                        {t.id === 'wholesale' && (
                          <WholesaleSettingsFields
                            settings={settings}
                            pages={wholesalePages}
                            paymentGateways={paymentGatewayChoices}
                            groups={groupChoices}
                            onChange={updateField}
                            onPagesGenerated={setWholesalePages}
                          />
                        )}

                        {t.id === 'support' && (
                          <SupportSettingsFields settings={settings} pages={supportPages} onChange={updateField} onPagesGenerated={setSupportPages} />
                        )}

                        {t.id === 'chat' && <ChatSettingsFields settings={settings} onChange={updateField} />}

                        {t.id === 'advance' && (
                          <AdvancedSettingsFields
                            settings={settings}
                            regeneratingSecret={regeneratingSecret}
                            orderBackfill={orderBackfill}
                            onRegenerateSecret={regenerateWebhookSecret}
                            onOrderBackfillChange={setOrderBackfill}
                          />
                        )}
                      </>
                    )}

                    {t.readOnly ? null : (
                      <div
                        className={cn(
                          'sticky bottom-0 z-10 -mx-4 -mb-4 flex flex-wrap items-center justify-between gap-3 rounded-b-xl border-t px-4 py-3 backdrop-blur transition-colors sm:-mx-6 sm:-mb-6 sm:px-6 sm:py-4',
                          dirty ? 'border-amber-200 bg-amber-50/95' : 'border-slate-200 bg-white/95'
                        )}
                      >
                        <p className={cn('flex items-center gap-2 text-xs', dirty ? 'font-medium text-amber-800' : 'text-muted-foreground')}>
                          {dirty ? (
                            <>
                              <span className="h-2 w-2 rounded-full bg-amber-500" /> You have unsaved changes
                            </>
                          ) : (
                            'All changes saved'
                          )}
                          <span className="hidden text-slate-400 sm:inline">· Ctrl/⌘ + S</span>
                        </p>
                        <div className="ml-auto flex gap-2">
                          {dirty ? (
                            <Button variant="ghost" onClick={discard} disabled={saving}>
                              <RotateCcw className="h-4 w-4" /> Discard
                            </Button>
                          ) : null}
                          <Button onClick={() => save()} disabled={saving || loading || !dirty} className="min-w-[140px]">
                            <Save className="h-4 w-4" />
                            {saveLabel}
                          </Button>
                        </div>
                      </div>
                    )}
                  </CardContent>
                </Card>
              </TabsContent>
            );
          })}
        </div>
      </Tabs>

      <AlertDialog open={pendingTab !== null} onOpenChange={(v) => !v && setPendingTab(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Unsaved changes</AlertDialogTitle>
            <AlertDialogDescription>
              You changed settings in {current.label}. Save them before switching to {TABS.find((t) => t.id === pendingTab)?.label}?
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Stay here</AlertDialogCancel>
            <Button
              variant="outline"
              onClick={() => {
                discard();
                if (pendingTab) setActiveTab(pendingTab);
                setPendingTab(null);
              }}
            >
              Discard changes
            </Button>
            <AlertDialogAction
              onClick={async (e) => {
                e.preventDefault();
                const target = pendingTab;
                if (await save()) {
                  setPendingTab(null);
                  if (target) setActiveTab(target);
                }
              }}
            >
              Save and continue
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
