import { useEffect, useMemo, useState } from 'react';
import { Inbox, KeyRound, Layers3, Mail, RotateCcw, Send } from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

interface EmailSettingsFieldsProps {
  settings: Record<string, string>;
  onChange: (key: string, value: string) => void;
}

type EmailScene = 'delivery' | 'content' | 'test';
type PreviewMode = 'single' | 'bulk';

const DEFAULTS = {
  lship_email_subject: 'Your License Key',
  lship_email_subject_single: 'Your License Key',
  lship_email_subject_bulk: 'Your License Keys',
  lship_email_preheader: 'Your license keys and download links are inside.',
  lship_email_intro_single:
    'Thanks for your purchase. Below is your license key with download and activation links.',
  lship_email_intro_bulk:
    'Thanks for your purchase. Below you will find your keys, downloads, and activation guides.',
};

function Field({
  id,
  label,
  hint,
  value,
  placeholder,
  onChange,
  type = 'text',
  multiline = false,
}: {
  id: string;
  label: string;
  hint?: string;
  value: string;
  placeholder?: string;
  onChange: (value: string) => void;
  type?: string;
  multiline?: boolean;
}) {
  return (
    <div className="flex h-full flex-col space-y-2">
      <Label htmlFor={id}>{label}</Label>
      <p className="min-h-8 text-xs leading-4 text-muted-foreground">{hint || '\u00a0'}</p>
      {multiline ? (
        <textarea
          id={id}
          rows={4}
          placeholder={placeholder}
          value={value}
          className="flex min-h-[96px] w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          onChange={(e) => onChange(e.target.value)}
        />
      ) : (
        <Input
          id={id}
          type={type}
          placeholder={placeholder}
          value={value}
          className="mt-auto"
          onChange={(e) => onChange(e.target.value)}
        />
      )}
    </div>
  );
}

function SectionHeader({
  title,
  description,
  onReset,
}: {
  title: string;
  description: string;
  onReset?: () => void;
}) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h4 className="text-sm font-semibold text-foreground">{title}</h4>
        <p className="mt-1 text-xs text-muted-foreground">{description}</p>
      </div>
      {onReset ? (
        <Button type="button" variant="outline" size="sm" className="shrink-0 gap-1.5" onClick={onReset}>
          <RotateCcw className="h-3.5 w-3.5" />
          Reset defaults
        </Button>
      ) : null}
    </div>
  );
}

function resolveSubject(settings: Record<string, string>, mode: PreviewMode) {
  const fallback = settings.lship_email_subject || DEFAULTS.lship_email_subject;
  if (mode === 'single') {
    return settings.lship_email_subject_single || fallback || DEFAULTS.lship_email_subject_single;
  }
  return settings.lship_email_subject_bulk || fallback || DEFAULTS.lship_email_subject_bulk;
}

function resolveIntro(settings: Record<string, string>, mode: PreviewMode) {
  if (mode === 'single') {
    return settings.lship_email_intro_single || DEFAULTS.lship_email_intro_single;
  }
  return settings.lship_email_intro_bulk || DEFAULTS.lship_email_intro_bulk;
}

function EmailPreview({
  settings,
  mode,
  onModeChange,
}: {
  settings: Record<string, string>;
  mode: PreviewMode;
  onModeChange: (mode: PreviewMode) => void;
}) {
  const brand = settings.ls_brand || settings.lship_brand_color || '#4f46e5';
  const senderName = settings.lship_email_sender_name || 'licensesender';
  const senderEmail = settings.lship_email_sender_email || settings.admin_email || 'noreply@example.com';
  const supportEmail = settings.lship_support_email || settings.admin_email || senderEmail;
  const subject = resolveSubject(settings, mode);
  const preheader = settings.lship_email_preheader || DEFAULTS.lship_email_preheader;
  const intro = resolveIntro(settings, mode);
  const logo = settings.lship_email_logo || '';
  const headline = mode === 'single' ? 'Your license key is ready' : 'Your license keys are ready';
  const keys = mode === 'single' ? ['XXXX-XXXX-XXXX-1111'] : ['XXXX-XXXX-XXXX-1111', 'XXXX-XXXX-XXXX-2222'];

  return (
    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 shadow-sm">
      <div className="flex items-center justify-between gap-3 border-b border-slate-200/80 bg-white/90 px-4 py-3 backdrop-blur">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Inbox preview</p>
          <p className="mt-1 text-sm text-slate-600">Updates live as you edit copy</p>
        </div>
        <Tabs value={mode} onValueChange={(value) => onModeChange(value as PreviewMode)}>
          <TabsList className="grid h-8 grid-cols-2 rounded-lg bg-slate-100 p-0.5">
            <TabsTrigger value="single" className="rounded-md px-2.5 text-xs">
              Single
            </TabsTrigger>
            <TabsTrigger value="bulk" className="rounded-md px-2.5 text-xs">
              Multiple
            </TabsTrigger>
          </TabsList>
        </Tabs>
      </div>

      <div className="space-y-3 bg-[linear-gradient(180deg,#e2e8f0_0%,#f8fafc_40%,#e2e8f0_100%)] p-4">
        <div className="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
          <div className="flex items-start gap-3">
            <div
              className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white"
              style={{ backgroundColor: brand }}
            >
              {senderName.slice(0, 1).toUpperCase()}
            </div>
            <div className="min-w-0 flex-1">
              <div className="flex items-center justify-between gap-2">
                <p className="truncate text-sm font-semibold text-slate-900">{senderName}</p>
                <span className="text-[11px] text-slate-400">Just now</span>
              </div>
              <p className="truncate text-sm font-medium text-slate-800">{subject}</p>
              <p className="truncate text-xs text-slate-500">{preheader}</p>
            </div>
          </div>
        </div>

        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg shadow-slate-900/5">
          <div className="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
            <div className="min-w-0">
              {logo ? (
                <img src={logo} alt="" className="h-8 max-w-[140px] object-contain" />
              ) : (
                <p className="truncate text-sm font-bold text-slate-900">{senderName}</p>
              )}
            </div>
            <span
              className="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-medium text-white"
              style={{ backgroundColor: brand }}
            >
              Order #1042
            </span>
          </div>

          <div className="space-y-4 px-4 py-5">
            <div>
              <p className="text-xs text-slate-500">Hi Alex,</p>
              <h4 className="mt-2 text-lg font-semibold text-slate-900">{headline}</h4>
              <p className="mt-2 text-sm leading-relaxed text-slate-600">{intro}</p>
            </div>

            <div className="space-y-2">
              {keys.map((key) => (
                <div key={key} className="rounded-lg border border-slate-200 bg-slate-950 px-3 py-2.5">
                  <p className="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                    Windows 11 Pro
                  </p>
                  <p className="mt-1 font-mono text-xs text-emerald-300">{key}</p>
                </div>
              ))}
            </div>

            <div className="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500">
              Need help? Contact {supportEmail}
            </div>
          </div>

          <div className="border-t border-slate-100 px-4 py-3 text-[11px] text-slate-400">
            From {senderName} &lt;{senderEmail}&gt;
          </div>
        </div>
      </div>
    </div>
  );
}

export function EmailSettingsFields({ settings, onChange }: EmailSettingsFieldsProps) {
  const [scene, setScene] = useState<EmailScene>('delivery');
  const [previewMode, setPreviewMode] = useState<PreviewMode>('bulk');
  const [testEmail, setTestEmail] = useState(settings.admin_email || '');
  const [testMode, setTestMode] = useState<PreviewMode>('bulk');
  const [sending, setSending] = useState(false);

  useEffect(() => {
    if (!testEmail && settings.admin_email) {
      setTestEmail(settings.admin_email);
    }
  }, [settings.admin_email, testEmail]);

  const missingSender = useMemo(
    () => !settings.lship_email_sender_name || !settings.lship_email_sender_email,
    [settings.lship_email_sender_name, settings.lship_email_sender_email],
  );

  const resetContent = () => {
    Object.entries(DEFAULTS).forEach(([key, value]) => onChange(key, value));
  };

  const sendTest = async () => {
    if (!testEmail.trim()) {
      toast.error('Enter a test email address.');
      return;
    }
    setSending(true);
    try {
      await apiRequest('settings/test-email', {
        method: 'POST',
        body: JSON.stringify({ email: testEmail, mode: testMode }),
      });
      toast.success('Test email sent.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Could not send test email.');
    } finally {
      setSending(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-slate-100 p-5">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div className="max-w-2xl space-y-2">
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="secondary">License delivery</Badge>
              <Badge variant="outline">Live preview</Badge>
              {missingSender ? <Badge variant="destructive">Sender incomplete</Badge> : null}
            </div>
            <h3 className="text-base font-semibold text-foreground">Customer email templates</h3>
            <p className="text-sm text-muted-foreground">
              One email is sent after all license keys for an order are fetched. Configure the sender, subjects, intro copy, and send yourself a preview.
            </p>
          </div>
          <div className="grid grid-cols-3 gap-2 text-center text-xs text-slate-600">
            <div className="rounded-xl border border-slate-200 bg-white px-3 py-2">
              <Mail className="mx-auto mb-1 h-4 w-4 text-slate-500" />
              Sender
            </div>
            <div className="rounded-xl border border-slate-200 bg-white px-3 py-2">
              <Inbox className="mx-auto mb-1 h-4 w-4 text-slate-500" />
              Content
            </div>
            <div className="rounded-xl border border-slate-200 bg-white px-3 py-2">
              <Send className="mx-auto mb-1 h-4 w-4 text-slate-500" />
              Test
            </div>
          </div>
        </div>
      </div>

      <Tabs value={scene} onValueChange={(value) => setScene(value as EmailScene)} className="space-y-5">
        <TabsList className="grid h-auto w-full grid-cols-3 gap-1 rounded-xl bg-slate-100 p-1">
          <TabsTrigger value="delivery" className="rounded-lg py-2.5 text-xs sm:text-sm">
            Delivery
          </TabsTrigger>
          <TabsTrigger value="content" className="rounded-lg py-2.5 text-xs sm:text-sm">
            Templates
          </TabsTrigger>
          <TabsTrigger value="test" className="rounded-lg py-2.5 text-xs sm:text-sm">
            Send test
          </TabsTrigger>
        </TabsList>

        <div className="grid gap-6 xl:grid-cols-5">
          <div className="space-y-5 xl:col-span-3">
            <TabsContent value="delivery" className="mt-0 space-y-5">
              <SectionHeader
                title="From address"
                description="Controls the visible sender customers see in their inbox."
              />
              <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
                <div className="grid items-start gap-4 sm:grid-cols-2">
                  <Field
                    id="lship_email_sender_name"
                    label="Sender name"
                    value={settings.lship_email_sender_name || ''}
                    placeholder="licensesender"
                    onChange={(v) => onChange('lship_email_sender_name', v)}
                  />
                  <Field
                    id="lship_email_sender_email"
                    label="Sender email"
                    type="email"
                    hint="Leave blank to fall back to the site/admin email."
                    value={settings.lship_email_sender_email || ''}
                    placeholder={settings.admin_email || 'noreply@example.com'}
                    onChange={(v) => onChange('lship_email_sender_email', v)}
                  />
                </div>
                <Field
                  id="lship_support_email"
                  label="Support email"
                  type="email"
                  hint="Shown in the email footer for customer help requests."
                  value={settings.lship_support_email || ''}
                  placeholder={settings.admin_email || 'support@example.com'}
                  onChange={(v) => onChange('lship_support_email', v)}
                />
              </div>
              <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-xs text-slate-600">
                Logo and brand colors for this email come from <span className="font-medium text-slate-800">Design</span> settings.
                {settings.lship_email_logo ? ' A custom email logo is configured.' : ' Using the site icon when no email logo is set.'}
              </div>
            </TabsContent>

            <TabsContent value="content" className="mt-0 space-y-5">
              <SectionHeader
                title="Subjects & intro copy"
                description="Customize inbox text for single-key and multi-key deliveries."
                onReset={resetContent}
              />
              <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
                <Field
                  id="lship_email_subject"
                  label="Default subject"
                  hint="Used when a single/multiple-key subject is empty."
                  value={settings.lship_email_subject || ''}
                  placeholder={DEFAULTS.lship_email_subject}
                  onChange={(v) => onChange('lship_email_subject', v)}
                />
                <Field
                  id="lship_email_preheader"
                  label="Inbox preheader"
                  hint="Short preview line shown under the subject in most inbox apps."
                  value={settings.lship_email_preheader || ''}
                  placeholder={DEFAULTS.lship_email_preheader}
                  onChange={(v) => onChange('lship_email_preheader', v)}
                />
              </div>

              <div className="grid gap-4 lg:grid-cols-2">
                <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
                  <div className="flex items-center gap-2">
                    <KeyRound className="h-4 w-4 text-slate-500" />
                    <p className="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Single key</p>
                  </div>
                  <Field
                    id="lship_email_subject_single"
                    label="Subject"
                    value={settings.lship_email_subject_single || ''}
                    placeholder={DEFAULTS.lship_email_subject_single}
                    onChange={(v) => onChange('lship_email_subject_single', v)}
                  />
                  <Field
                    id="lship_email_intro_single"
                    label="Intro"
                    value={settings.lship_email_intro_single || ''}
                    placeholder={DEFAULTS.lship_email_intro_single}
                    onChange={(v) => onChange('lship_email_intro_single', v)}
                    multiline
                  />
                </div>
                <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
                  <div className="flex items-center gap-2">
                    <Layers3 className="h-4 w-4 text-slate-500" />
                    <p className="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Multiple keys</p>
                  </div>
                  <Field
                    id="lship_email_subject_bulk"
                    label="Subject"
                    value={settings.lship_email_subject_bulk || ''}
                    placeholder={DEFAULTS.lship_email_subject_bulk}
                    onChange={(v) => onChange('lship_email_subject_bulk', v)}
                  />
                  <Field
                    id="lship_email_intro_bulk"
                    label="Intro"
                    value={settings.lship_email_intro_bulk || ''}
                    placeholder={DEFAULTS.lship_email_intro_bulk}
                    onChange={(v) => onChange('lship_email_intro_bulk', v)}
                    multiline
                  />
                </div>
              </div>
            </TabsContent>

            <TabsContent value="test" className="mt-0 space-y-5">
              <SectionHeader
                title="Send a test email"
                description="Uses the live production template with sample license rows."
              />
              <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
                <Field
                  id="ls_test_email"
                  label="Send to"
                  type="email"
                  value={testEmail}
                  placeholder={settings.admin_email || 'you@example.com'}
                  onChange={setTestEmail}
                />
                <div className="space-y-2">
                  <Label>Preview mode</Label>
                  <Select value={testMode} onValueChange={(value) => setTestMode(value as PreviewMode)}>
                    <SelectTrigger>
                      <SelectValue placeholder="Mode" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="single">Single key</SelectItem>
                      <SelectItem value="bulk">Multiple keys</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <Button type="button" className="gap-2" onClick={sendTest} disabled={sending}>
                  <Send className="h-4 w-4" />
                  {sending ? 'Sending…' : 'Send test email'}
                </Button>
                <p className="text-xs text-muted-foreground">
                  Save your settings first if you want the emailed test to include the latest saved values.
                  The preview panel always reflects the form as you type.
                </p>
              </div>
            </TabsContent>
          </div>

          <div className="xl:col-span-2">
            <div className="xl:sticky xl:top-4">
              <EmailPreview settings={settings} mode={previewMode} onModeChange={setPreviewMode} />
            </div>
          </div>
        </div>
      </Tabs>
    </div>
  );
}
