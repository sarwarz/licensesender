import { useState, type FormEvent, type ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';
import { ArrowRight, BadgePercent, Building2, Check, Clock, LogIn, Mail, PackageCheck, Send, ShieldCheck, Store, UserPlus, Zap } from 'lucide-react';
import { ajax, cn, fullDate, t } from '../support/lib';
import { Alert, Button, Card, Field, Input, Textarea } from '../support/ui';
import { config } from './lib';
import type { WsApplication } from './types';

type FieldName = 'company_name' | 'business_email' | 'phone' | 'messenger_link' | 'website' | 'tax_id' | 'message';

const MESSAGE_MAX = 1000;

/* ---------- Layout ---------- */

/** Account → Business details → Review. `current` is 0-based; 3 = all done. */
function Steps({ current }: { current: number }) {
  const steps = [t('stepAccount', 'Your account'), t('stepDetails', 'Business details'), t('stepReviewShort', 'Review')];
  return (
    <ol className="space-y-0">
      {steps.map((label, i) => {
        const state = i < current ? 'done' : i === current ? 'current' : 'todo';
        return (
          <li key={label} className="relative flex items-center gap-3 pb-4 last:pb-0">
            {i < steps.length - 1 ? <span className={cn('absolute left-[13px] top-7 h-[calc(100%-20px)] w-px', i < current ? 'bg-emerald-500/50' : 'bg-line')} aria-hidden="true" /> : null}
            <span
              className={cn(
                'relative flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[12px] font-semibold',
                state === 'done' && 'bg-emerald-500 text-white',
                state === 'current' && 'bg-brand text-brand-fg ring-4 ring-brand/15',
                state === 'todo' && 'bg-fg/[0.07] text-faint',
              )}
            >
              {state === 'done' ? <Check className="h-4 w-4" /> : i + 1}
            </span>
            <span className={cn('text-sm', state === 'current' ? 'font-semibold text-fg' : state === 'done' ? 'text-fg' : 'text-muted')}>{label}</span>
            {state === 'current' ? <span className="sr-only">{t('currentStep', '(current step)')}</span> : null}
          </li>
        );
      })}
    </ol>
  );
}

function Aside({ step, compact }: { step: number; compact: boolean }) {
  if (compact) {
    // Phones: keep the form near the top; one line says where the visitor is.
    const labels = [t('stepAccount', 'Your account'), t('stepDetails', 'Business details'), t('stepReviewShort', 'Review')];
    return (
      <div className="overflow-hidden rounded-card border border-line bg-gradient-to-br from-brand/[0.1] via-brand/[0.04] to-surface px-5 py-5 shadow-card">
        <span className="text-[12px] font-semibold uppercase tracking-wider text-brand">{t('eyebrow', 'Wholesale')}</span>
        <h2 className="mt-1 text-lg font-semibold leading-tight tracking-tight text-fg">{t('applyTitle', 'Become a wholesale partner')}</h2>
        {step < 3 ? (
          <div className="mt-3 flex items-center gap-2">
            <div className="flex gap-1" aria-hidden="true">
              {labels.map((label, i) => (
                <span key={label} className={cn('h-1.5 w-6 rounded-full', i < step ? 'bg-emerald-500' : i === step ? 'bg-brand' : 'bg-fg/10')} />
              ))}
            </div>
            <span className="text-[12px] text-muted">{t('stepOf', 'Step %d of %d · %s', step + 1, labels.length, labels[step])}</span>
          </div>
        ) : null}
      </div>
    );
  }

  const perks: Array<{ icon: LucideIcon; title: string; text: string }> = [
    { icon: BadgePercent, title: t('perkPricing', 'Volume pricing'), text: t('perkPricingText', 'Lower prices per key the more you order.') },
    { icon: Zap, title: t('perkDelivery', 'Instant delivery'), text: t('perkDeliveryText', 'Keys arrive with your order as soon as it is paid.') },
    { icon: PackageCheck, title: t('perkBulk', 'Bulk ordering'), text: t('perkBulkText', 'Order many products at once or paste a list.') },
  ];
  return (
    <aside className="overflow-hidden rounded-card border border-line bg-surface shadow-card">
      <div className="bg-gradient-to-br from-brand/[0.1] via-brand/[0.04] to-transparent px-6 pb-6 pt-7">
        <span className="text-[12px] font-semibold uppercase tracking-wider text-brand">{t('eyebrow', 'Wholesale')}</span>
        <h2 className="mt-1.5 text-[22px] font-semibold leading-tight tracking-tight text-fg">{t('applyTitle', 'Become a wholesale partner')}</h2>
        <p className="mt-2 text-sm leading-relaxed text-muted">{t('applyLead', 'Tell us about your business. Once approved, you can order at wholesale prices from your account.')}</p>
      </div>
      <ul className="space-y-4 border-t border-line px-6 py-5">
        {perks.map((perk) => (
          <li key={perk.title} className="flex gap-3">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-brand">
              <perk.icon className="h-[18px] w-[18px]" />
            </span>
            <div>
              <p className="text-sm font-semibold text-fg">{perk.title}</p>
              <p className="text-[13px] leading-snug text-muted">{perk.text}</p>
            </div>
          </li>
        ))}
      </ul>
      <div className="border-t border-line bg-subtle px-6 py-5">
        <p className="mb-3 text-[11px] font-semibold uppercase tracking-wider text-faint">{t('howItWorks', 'How it works')}</p>
        <Steps current={step} />
      </div>
    </aside>
  );
}

function Layout({ wide, step, children }: { wide: boolean; step: number; children: ReactNode }) {
  return (
    <div className={cn('gap-4', wide ? 'grid grid-cols-[minmax(0,320px)_minmax(0,1fr)] items-start' : 'flex flex-col')}>
      <Aside step={step} compact={!wide} />
      <div className="min-w-0 space-y-4">{children}</div>
    </div>
  );
}

/* ---------- Steps' content ---------- */

/** Not logged in: sign in (or sign up) on the store's account page, then come back. */
function GuestCard() {
  const auth = config().auth;
  return (
    <Card className="overflow-hidden">
      <div className="px-6 pb-6 pt-7 sm:px-8">
        <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand/10 text-brand ring-8 ring-brand/[0.04]">
          <Store className="h-5 w-5" />
        </span>
        <h3 className="mt-5 text-lg font-semibold tracking-tight text-fg">{t('guestTitle', 'Sign in to apply')}</h3>
        <p className="mt-1.5 max-w-md text-sm leading-relaxed text-muted">
          {t('guestLead', 'Wholesale access is linked to your customer account. Log in, or create a free account, and we will bring you right back to the application.')}
        </p>
        <div className="mt-6 grid gap-2.5 sm:grid-cols-2">
          <a href={auth?.login} className="flex h-11 items-center justify-center gap-2 rounded-ctl bg-brand text-sm font-semibold text-brand-fg no-underline shadow-sm transition-colors hover:bg-brand/90">
            <LogIn className="h-4 w-4" /> {t('logIn', 'Log in')}
          </a>
          <a href={auth?.register} className="flex h-11 items-center justify-center gap-2 rounded-ctl border border-line bg-surface text-sm font-semibold text-fg no-underline shadow-sm transition-colors hover:bg-subtle">
            <UserPlus className="h-4 w-4" /> {t('createAccount', 'Create an account')}
          </a>
        </div>
      </div>
      <div className="flex items-start gap-2.5 border-t border-line bg-subtle px-6 py-3.5 text-xs leading-relaxed text-muted sm:px-8">
        <ShieldCheck className="mt-px h-4 w-4 shrink-0 text-emerald-500" />
        <span>{t('guestNote', 'You sign in on our secure account page. Existing customers keep their order history.')}</span>
      </div>
    </Card>
  );
}

function PendingCard({ application }: { application: WsApplication }) {
  const timeline = [
    { label: t('stepSubmitted', 'Application submitted'), detail: fullDate(application.submitted), state: 'done' },
    { label: t('stepReview', 'In review'), detail: t('stepReviewText', 'We usually reply within 1–2 business days.'), state: 'current' },
    { label: t('stepApproved', 'Approved'), detail: t('stepApprovedText', 'You will get an email and can start ordering.'), state: 'todo' },
  ];
  return (
    <Card className="p-6 sm:p-8">
      <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-500/15 text-amber-600 dark:text-amber-300">
        <Clock className="h-5 w-5" />
      </span>
      <h3 className="mt-5 text-lg font-semibold tracking-tight text-fg">{t('pendingTitle', 'Your application is in review')}</h3>
      <p className="mt-1 text-sm text-muted">{t('pendingLead', 'Thanks for applying. Nothing else is needed from you right now.')}</p>
      <ol className="mt-6">
        {timeline.map((step, i) => (
          <li key={step.label} className="relative flex gap-3 pb-5 last:pb-0">
            {i < timeline.length - 1 ? <span className="absolute left-[11px] top-6 h-[calc(100%-16px)] w-px bg-line" aria-hidden="true" /> : null}
            <span
              className={cn(
                'relative flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold',
                step.state === 'done' && 'bg-emerald-500 text-white',
                step.state === 'current' && 'bg-brand text-brand-fg ring-4 ring-brand/15',
                step.state === 'todo' && 'bg-fg/[0.07] text-faint',
              )}
            >
              {step.state === 'done' ? <Check className="h-3.5 w-3.5" /> : i + 1}
            </span>
            <div>
              <p className={cn('text-sm font-medium', step.state === 'todo' ? 'text-muted' : 'text-fg')}>{step.label}</p>
              <p className="text-[13px] text-muted">{step.detail}</p>
            </div>
          </li>
        ))}
      </ol>
      <p className="mt-6 flex items-center gap-2 border-t border-line pt-4 text-[13px] text-muted">
        <Mail className="h-4 w-4 shrink-0 text-faint" /> {t('pendingEmail', 'We will email %s when your account is approved.', config().user.email)}
      </p>
    </Card>
  );
}

function ApprovedCard() {
  const cfg = config();
  return (
    <Card className="p-6 sm:p-8">
      <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-300">
        <ShieldCheck className="h-6 w-6" />
      </span>
      <h3 className="mt-5 text-lg font-semibold tracking-tight text-fg">{t('approvedTitle', "You're a wholesale partner")}</h3>
      <p className="mt-1 text-sm text-muted">{t('approvedLead', 'Your account has wholesale prices. Start an order from the catalog.')}</p>
      <a href={cfg.links.catalog} className="mt-6 inline-flex h-11 items-center gap-2 rounded-ctl bg-brand px-5 text-sm font-semibold text-brand-fg no-underline shadow-sm hover:bg-brand/90">
        {t('openCatalog', 'Open wholesale catalog')} <ArrowRight className="h-4 w-4" />
      </a>
    </Card>
  );
}

function Section({ icon: Icon, title, children }: { icon: LucideIcon; title: string; children: ReactNode }) {
  return (
    <section className="space-y-4">
      <h4 className="flex items-center gap-2 text-[13px] font-semibold text-fg">
        <Icon className="h-4 w-4 text-faint" /> {title}
      </h4>
      {children}
    </section>
  );
}

function ApplyForm({ wide, onSubmitted }: { wide: boolean; onSubmitted: (app: WsApplication | null, approved: boolean) => void }) {
  const cfg = config();
  const [values, setValues] = useState<Record<FieldName, string>>(() => ({
    company_name: '',
    business_email: cfg.user.email,
    phone: '',
    messenger_link: '',
    website: '',
    tax_id: '',
    message: '',
  }));
  const [errors, setErrors] = useState<Partial<Record<FieldName, string>>>({});
  const [error, setError] = useState('');
  const [sending, setSending] = useState(false);

  const set = (name: FieldName) => (e: { target: { value: string } }) => {
    setValues((v) => ({ ...v, [name]: e.target.value }));
    setErrors((v) => ({ ...v, [name]: undefined }));
  };

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    const next: Partial<Record<FieldName, string>> = {};
    if (!values.company_name.trim()) next.company_name = t('reqCompany', 'Enter your company name.');
    if (!/^\S+@\S+\.\S+$/.test(values.business_email.trim())) next.business_email = t('reqEmail', 'Enter a valid email address.');
    if (values.website.trim() && !/^(https?:\/\/)?[\w-]+(\.[\w-]+)+(\/\S*)?$/i.test(values.website.trim())) next.website = t('reqWebsite', 'Enter a web address like example.com.');
    if (!values.message.trim()) next.message = t('reqMessage', 'Tell us a little about your business.');
    setErrors(next);
    if (Object.keys(next).length) return;

    setSending(true);
    setError('');
    try {
      const website = values.website.trim() && !/^https?:\/\//i.test(values.website.trim()) ? `https://${values.website.trim()}` : values.website.trim();
      const res = await ajax<{ wholesale: boolean; application: WsApplication | null }>('ls_wholesale_portal_apply', { nonce: cfg.applyNonce, ...values, website });
      onSubmitted(res.application, res.wholesale);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setSending(false);
    }
  };

  const input = (name: FieldName, label: string, props: Record<string, unknown> = {}, optional = true, hint?: string) => (
    <Field label={label} htmlFor={`ls-ws-${name}`} optional={optional} error={errors[name]} hint={hint}>
      <Input id={`ls-ws-${name}`} value={values[name]} onChange={set(name)} className="h-10 px-3" aria-invalid={!!errors[name]} {...props} />
    </Field>
  );
  const grid = wide ? 'grid grid-cols-2 gap-4' : 'grid gap-4';

  return (
    <Card className="overflow-hidden">
      <div className="border-b border-line px-6 py-5 sm:px-8">
        <h3 className="text-lg font-semibold tracking-tight text-fg">{t('formTitle', 'Business details')}</h3>
        <p className="mt-0.5 text-sm text-muted">{t('formLead', 'Applying as %s.', cfg.user.name)}</p>
      </div>
      <form onSubmit={submit} noValidate>
        <div className="space-y-7 px-6 py-6 sm:px-8">
          <Section icon={Building2} title={t('sectionCompany', 'Company')}>
            <div className={grid}>
              {input('company_name', t('company', 'Company name'), { autoComplete: 'organization' }, false)}
              {input('tax_id', t('taxId', 'Tax / VAT ID'))}
              <div className={wide ? 'col-span-2' : undefined}>{input('website', t('website', 'Website or store'), { inputMode: 'url', placeholder: 'example.com', autoComplete: 'url' })}</div>
            </div>
          </Section>

          <Section icon={Mail} title={t('sectionContact', 'Contact')}>
            <div className={grid}>
              {input('business_email', t('businessEmail', 'Business email'), { type: 'email', autoComplete: 'email' }, false)}
              {input('phone', t('phone', 'Phone'), { type: 'tel', autoComplete: 'tel' })}
              <div className={wide ? 'col-span-2' : undefined}>
                {input('messenger_link', t('messenger', 'Telegram or WhatsApp'), { placeholder: '@username or +1 555 0100' }, true, t('messengerHint', 'The fastest way for us to reach you about orders.'))}
              </div>
            </div>
          </Section>

          <Section icon={Store} title={t('about', 'About your business')}>
            <Field
              label={t('aboutLabel', 'Where and how do you sell?')}
              htmlFor="ls-ws-message"
              error={errors.message}
              hint={t('aboutHint', 'Your store or channel, region, and how many keys you expect to order per month.')}
              aside={<span className={cn('text-[12px] tabular-nums', values.message.length > MESSAGE_MAX * 0.9 ? 'text-amber-600' : 'text-faint')}>{values.message.length}/{MESSAGE_MAX}</span>}
            >
              <Textarea id="ls-ws-message" value={values.message} onChange={set('message')} rows={5} maxLength={MESSAGE_MAX} className="px-3 py-2.5" aria-invalid={!!errors.message} />
            </Field>
          </Section>

          {error ? (
            <Alert tone="error" onClose={() => setError('')}>
              {error}
            </Alert>
          ) : null}
        </div>
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line bg-subtle px-6 py-4 sm:px-8">
          <p className="flex items-center gap-1.5 text-[13px] text-muted">
            <ShieldCheck className="h-4 w-4 text-emerald-500" /> {t('formFootnote', 'We review every application and reply by email.')}
          </p>
          <Button type="submit" variant="primary" icon={Send} loading={sending} className="h-11 px-5">
            {t('submit', 'Submit application')}
          </Button>
        </div>
      </form>
    </Card>
  );
}

/* ---------- View ---------- */

export function ApplyView({
  wide,
  application,
  onSubmitted,
}: {
  wide: boolean;
  application: WsApplication | null;
  onSubmitted: (app: WsApplication | null, approved: boolean) => void;
}) {
  const cfg = config();

  if (cfg.view === 'guest') {
    return (
      <Layout wide={wide} step={0}>
        <GuestCard />
      </Layout>
    );
  }

  if (cfg.wholesale) {
    return (
      <Layout wide={wide} step={3}>
        <ApprovedCard />
      </Layout>
    );
  }

  if (application?.status === 'pending') {
    return (
      <Layout wide={wide} step={2}>
        <PendingCard application={application} />
      </Layout>
    );
  }

  return (
    <Layout wide={wide} step={1}>
      {application?.status === 'rejected' ? (
        <Alert tone="warning" title={t('rejectedTitle', 'Your last application was not approved')}>
          {application.note || t('rejectedText', 'You are welcome to apply again with more details about your business.')}
        </Alert>
      ) : null}
      <ApplyForm wide={wide} onSubmitted={onSubmitted} />
    </Layout>
  );
}
