import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { getBootstrap } from '@/lib/bootstrap';
import { PageHeader } from '@/components/shared/PageHeader';
import { DesignSettingsFields } from '@/components/settings/DesignSettingsFields';
import { PopupSettingsFields } from '@/components/settings/PopupSettingsFields';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

const TABS = [
  { id: 'general', label: 'General' },
  { id: 'api', label: 'API' },
  { id: 'email', label: 'Email' },
  { id: 'design', label: 'Design' },
  { id: 'popup', label: 'PopUp' },
  { id: 'advance', label: 'Advance' },
] as const;

type TabId = (typeof TABS)[number]['id'];

export function SettingsPage() {
  const { tab: initialTab, i18n } = getBootstrap();
  const [activeTab, setActiveTab] = useState<TabId>((initialTab as TabId) || 'general');
  const [settings, setSettings] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [testEmail, setTestEmail] = useState('');
  const [testEmailMode, setTestEmailMode] = useState<'single' | 'bulk'>('bulk');

  const loadSettings = useCallback(async (tab: TabId) => {
    setLoading(true);
    try {
      const data = await apiRequest<Record<string, string>>(`settings?tab=${tab}`);
      setSettings(data);
      if (tab === 'email' && data.admin_email) {
        setTestEmail(data.admin_email);
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

  const updateField = (key: string, value: string) => {
    setSettings((prev) => ({ ...prev, [key]: value }));
  };

  const yesNo = (key: string) => settings[key] === 'yes';

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
      await apiRequest('settings/ping', { method: 'POST' });
      toast.success('API connection successful.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    }
  };

  const sendTestEmail = async () => {
    try {
      await apiRequest('settings/test-email', {
        method: 'POST',
        body: JSON.stringify({ email: testEmail, mode: testEmailMode }),
      });
      toast.success('Test email sent.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    }
  };

  return (
    <div className="mx-auto max-w-[1400px] space-y-6 pb-8">
      <PageHeader title="Settings" subtitle="Configure License Shipper plugin options." />

      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as TabId)} className="flex flex-col gap-6 lg:flex-row lg:items-start">
        <aside className="w-full shrink-0 lg:w-[220px]">
          <TabsList className="flex h-auto w-full flex-col items-stretch gap-0.5 rounded-xl border border-slate-200 bg-white p-1.5 shadow-sm">
            {TABS.map((t) => (
              <TabsTrigger
                key={t.id}
                value={t.id}
                className="w-full justify-start rounded-lg px-3 py-2.5 text-left data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-none"
              >
                {t.label}
              </TabsTrigger>
            ))}
          </TabsList>
        </aside>

        <div className="min-w-0 flex-1">
        {TABS.map((t) => (
          <TabsContent key={t.id} value={t.id} className="mt-0">
            <Card className="border-slate-200 shadow-sm">
              <CardHeader>
                <CardTitle>{t.label} Settings</CardTitle>
                <CardDescription>Manage {t.label.toLowerCase()} configuration.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                {loading ? (
                  <p className="text-muted-foreground">{i18n.loading}</p>
                ) : (
                  <>
                    {t.id === 'general' && (
                      <div className="space-y-4">
                        {[
                          ['lship_autocomplete_order', 'Auto-complete orders'],
                          ['lship_send_email_after_redeem', 'Send email after redemption'],
                          ['lship_enable_variation_support', 'Enable variation support'],
                          ['lship_enable_manage_downloads', 'Enable manage download links'],
                          ['lship_enable_manage_activation_guides', 'Enable manage activation guides'],
                        ].map(([key, label]) => (
                          <div key={key} className="flex items-center justify-between rounded-lg border p-4">
                            <Label htmlFor={key}>{label}</Label>
                            <Switch
                              id={key}
                              checked={yesNo(key)}
                              onCheckedChange={(v) => updateField(key, v ? 'yes' : 'no')}
                            />
                          </div>
                        ))}
                      </div>
                    )}

                    {t.id === 'api' && (
                      <div className="space-y-4">
                        <div className="space-y-2">
                          <Label>API Key</Label>
                          <Input value={settings.lship_api_key || ''} onChange={(e) => updateField('lship_api_key', e.target.value)} />
                        </div>
                        <div className="space-y-2">
                          <Label>API Base URL</Label>
                          <Input value={settings.lship_api_base_url || ''} onChange={(e) => updateField('lship_api_base_url', e.target.value)} />
                        </div>
                        <Button variant="outline" onClick={pingApi}>Test Connection</Button>
                      </div>
                    )}

                    {t.id === 'email' && (
                      <div className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                          One email is sent after all license keys for the order are fetched.
                        </p>
                        {[
                          ['lship_email_sender_name', 'Sender Name'],
                          ['lship_email_sender_email', 'Sender Email'],
                          ['lship_email_subject', 'Default Email Subject'],
                          ['lship_email_subject_single', 'Subject (single key)'],
                          ['lship_email_subject_bulk', 'Subject (multiple keys)'],
                          ['lship_email_preheader', 'Inbox Preheader'],
                          ['lship_support_email', 'Support Email'],
                        ].map(([key, label]) => (
                          <div key={key} className="space-y-2">
                            <Label>{label}</Label>
                            <Input value={settings[key] || ''} onChange={(e) => updateField(key, e.target.value)} />
                          </div>
                        ))}
                        {[
                          ['lship_email_intro_single', 'Intro (single key)'],
                          ['lship_email_intro_bulk', 'Intro (multiple keys)'],
                        ].map(([key, label]) => (
                          <div key={key} className="space-y-2">
                            <Label>{label}</Label>
                            <textarea
                              className="flex min-h-[80px] w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                              value={settings[key] || ''}
                              onChange={(e) => updateField(key, e.target.value)}
                            />
                          </div>
                        ))}
                        <div className="flex flex-wrap gap-2">
                          <Input type="email" value={testEmail} onChange={(e) => setTestEmail(e.target.value)} placeholder="Test email address" className="min-w-[200px] flex-1" />
                          <Select value={testEmailMode} onValueChange={(v) => setTestEmailMode(v as 'single' | 'bulk')}>
                            <SelectTrigger className="w-[140px]">
                              <SelectValue placeholder="Preview" />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="single">Single key</SelectItem>
                              <SelectItem value="bulk">Multiple keys</SelectItem>
                            </SelectContent>
                          </Select>
                          <Button variant="outline" onClick={sendTestEmail}>Send Test</Button>
                        </div>
                      </div>
                    )}

                    {t.id === 'design' && (
                      <DesignSettingsFields settings={settings} onChange={updateField} />
                    )}

                    {t.id === 'popup' && (
                      <PopupSettingsFields settings={settings} onChange={updateField} />
                    )}

                    {t.id === 'advance' && (
                      <div className="space-y-4">
                        <div className="flex items-center justify-between rounded-lg border p-4">
                          <Label>Enable SSO Login</Label>
                          <Switch checked={yesNo('lship_sso_enabled')} onCheckedChange={(v) => updateField('lship_sso_enabled', v ? 'yes' : 'no')} />
                        </div>
                        <div className="space-y-2">
                          <Label>SSO Access Token</Label>
                          <Input value={settings.lship_sso_token || ''} onChange={(e) => updateField('lship_sso_token', e.target.value)} />
                        </div>
                        <div className="space-y-2">
                          <Label>SSO User Email</Label>
                          <Input type="email" value={settings.lship_sso_user_email || ''} onChange={(e) => updateField('lship_sso_user_email', e.target.value)} />
                        </div>
                      </div>
                    )}
                  </>
                )}

                <div className="sticky bottom-0 flex justify-end border-t bg-white pt-4">
                  <Button onClick={save} disabled={saving || loading}>{saving ? 'Saving…' : i18n.save}</Button>
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        ))}
        </div>
      </Tabs>
    </div>
  );
}
