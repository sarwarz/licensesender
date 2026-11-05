import { useEffect, useState, type FormEvent, type MouseEvent } from 'react';
import { ArrowLeft, CheckCircle2, Send } from 'lucide-react';
import { useApp } from '../App';
import { DropArea, Dropzone, FileChips, useFilePicker } from '../Attachments';
import { ajax, cn, getConfig, messageTooLong, t, textToHtml } from '../lib';
import { Alert, Button, Card, Field, Input, Select, Textarea } from '../ui';

interface KeyOption {
  key: string;
  sku: string;
}

function maskKey(key: string): string {
  return key.length > 12 ? `${key.slice(0, 5)}…${key.slice(-5)}` : key;
}

export function NewTicketView() {
  const cfg = getConfig();
  const { navigate, hrefFor, wide, isManagePage } = useApp();
  const [subject, setSubject] = useState('');
  const [category, setCategory] = useState(cfg.categories[0]?.value ?? 'general');
  const [priority, setPriority] = useState('normal');
  const [orderId, setOrderId] = useState('');
  const [keys, setKeys] = useState<KeyOption[]>([]);
  const [keysLoading, setKeysLoading] = useState(false);
  const [licenseKey, setLicenseKey] = useState('');
  const [message, setMessage] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [done, setDone] = useState(false);
  const picker = useFilePicker();

  const order = cfg.orders.find((o) => String(o.id) === orderId);

  useEffect(() => {
    setLicenseKey('');
    setKeys([]);
    if (!orderId) return;
    let cancelled = false;
    setKeysLoading(true);
    ajax<{ keys: KeyOption[] }>('ls_support_order_keys', { order_id: orderId })
      .then((res) => !cancelled && setKeys(res.keys || []))
      .catch(() => !cancelled && setKeys([]))
      .finally(() => !cancelled && setKeysLoading(false));
    return () => {
      cancelled = true;
    };
  }, [orderId]);

  const validate = () => {
    const next: Record<string, string> = {};
    if (!subject.trim()) next.subject = t('subjectRequired', 'Add a short subject.');
    if (!message.trim()) next.message = t('descriptionRequired', 'Describe the problem so we can help.');
    else if (messageTooLong(message)) next.message = t('messageTooLong', 'Your message is too long. Please shorten it or split it into two replies.');
    setErrors(next);
    return Object.keys(next).length === 0;
  };

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    // Enter in a field submits the form even while the button is disabled.
    if (submitting || done) return;
    if (!validate()) return;
    setSubmitting(true);
    setFormError('');
    try {
      const result = await ajax<{ ticket_number: string; redirect_url: string }>('ls_support_create_ticket', {
        subject: subject.trim(),
        category,
        priority,
        order_id: order ? order.number : '',
        license_key: licenseKey,
        message: textToHtml(message),
        attachments: picker.files,
      });
      setDone(true);
      if (isManagePage && result.ticket_number) {
        navigate({ name: 'ticket', number: result.ticket_number, created: true });
      } else {
        window.location.href = result.redirect_url || hrefFor({ name: 'list' });
      }
    } catch (err) {
      setFormError((err as Error).message);
      setSubmitting(false);
    }
  };

  const toList = (event: MouseEvent<HTMLAnchorElement>) => {
    if (!isManagePage || event.metaKey || event.ctrlKey) return;
    event.preventDefault();
    navigate({ name: 'list' });
  };

  const orderOptions = [
    { value: '', label: t('noOrder', 'Not about a specific order') },
    ...cfg.orders.map((o) => ({
      value: String(o.id),
      label: t('orderShort', 'Order %s', `#${o.number}`),
      description: [o.date, o.total].filter(Boolean).join(' · '),
    })),
  ];

  const keyPlaceholder = !orderId
    ? t('selectOrderFirst', 'Choose an order first')
    : keysLoading
      ? t('loadingKeys', 'Loading license keys…')
      : keys.length === 0
        ? t('noKeys', 'No license keys on this order')
        : t('selectKey', 'Choose a license key');

  const keyOptions = keys.length
    ? [{ value: '', label: t('noKey', 'No specific key') }, ...keys.map((k) => ({ value: k.key, label: maskKey(k.key), description: k.sku }))]
    : [];

  return (
    <div className="space-y-5">
      <header className="min-w-0">
        {cfg.manageUrl ? (
          <a href={hrefFor({ name: 'list' })} onClick={toList} className="-ml-2 mb-2 inline-flex h-8 items-center gap-1.5 rounded-ctl px-2 text-[13px] font-medium text-muted no-underline hover:bg-fg/5 hover:text-fg">
            <ArrowLeft className="h-4 w-4" />
            {t('myTicketsLink', 'My tickets')}
          </a>
        ) : null}
        <h2 className="text-xl font-semibold tracking-tight text-fg">{t('newTicketTitle', 'Open a support ticket')}</h2>
        <p className="mt-1 text-sm text-muted">{t('newTicketLead', 'Tell us what went wrong and our team will get back to you as soon as possible.')}</p>
      </header>

      <form onSubmit={submit} noValidate>
          <DropArea onFiles={picker.add} className="rounded-card">
            <Card>
              <div className="space-y-5 p-5 sm:p-6">
                <Field label={t('subject', 'Subject')} htmlFor="ls-subject" error={errors.subject} aside={<span className="text-[11px] tabular-nums text-faint">{subject.length}/{cfg.limits.maxSubject}</span>}>
                  <Input
                    id="ls-subject"
                    value={subject}
                    maxLength={cfg.limits.maxSubject}
                    onChange={(e) => setSubject(e.target.value)}
                    placeholder={t('subjectPlaceholder', 'e.g. My license key says it is already in use')}
                    aria-invalid={!!errors.subject}
                  />
                </Field>

                <div className={cn('grid gap-5', wide && 'grid-cols-2')}>
                  <Field label={t('category', 'Category')} htmlFor="ls-category">
                    <Select id="ls-category" value={category} onChange={setCategory} options={cfg.categories} />
                  </Field>
                  <Field label={t('priority', 'Priority')}>
                    <div role="radiogroup" aria-label={t('priority', 'Priority')} className="grid h-10 gap-1 rounded-ctl border border-line bg-subtle p-1" style={{ gridTemplateColumns: `repeat(${cfg.priorities.length}, minmax(0, 1fr))` }}>
                      {cfg.priorities.map((o) => (
                        <button
                          key={o.value}
                          type="button"
                          role="radio"
                          aria-checked={priority === o.value}
                          onClick={() => setPriority(o.value)}
                          className={cn(
                            'rounded-[calc(var(--ls-radius-ctl)-4px)] text-[13px] font-medium transition-colors',
                            priority === o.value
                              ? o.value === 'urgent'
                                ? 'bg-red-600 text-white shadow-sm'
                                : 'bg-surface text-fg shadow-sm ring-1 ring-line'
                              : 'text-muted hover:text-fg',
                          )}
                        >
                          {o.label}
                        </button>
                      ))}
                    </div>
                  </Field>
                </div>

                {cfg.orders.length ? (
                  <div className={cn('grid gap-5', wide && 'grid-cols-2')}>
                    <Field label={t('order', 'Order')} htmlFor="ls-order" optional>
                      <Select id="ls-order" value={orderId} onChange={setOrderId} options={orderOptions} />
                    </Field>
                    <Field label={t('licenseKey', 'License key')} htmlFor="ls-key" optional>
                      <Select
                        id="ls-key"
                        value={licenseKey}
                        onChange={setLicenseKey}
                        options={keyOptions}
                        placeholder={keyPlaceholder}
                        loading={keysLoading}
                        disabled={!orderId || keys.length === 0}
                      />
                    </Field>
                  </div>
                ) : null}

                <Field
                  label={t('description', 'Description')}
                  htmlFor="ls-message"
                  error={errors.message}
                  aside={<span className="text-[11px] tabular-nums text-faint">{message.length}/{cfg.limits.maxMessage}</span>}
                >
                  <Textarea
                    id="ls-message"
                    value={message}
                    rows={7}
                    maxLength={cfg.limits.maxMessage}
                    onChange={(e) => setMessage(e.target.value)}
                    placeholder={t('descriptionPlaceholder', 'What happened? Include any error messages and the steps to reproduce it.')}
                    aria-invalid={!!errors.message}
                  />
                </Field>

                <Field label={t('attachments', 'Attachments')} optional error={picker.error}>
                  <div className="space-y-3">
                    <Dropzone onFiles={picker.add} />
                    <FileChips files={picker.files} onRemove={picker.remove} />
                  </div>
                </Field>

                {formError ? <Alert tone="error" title={t('submitFailed', 'Your ticket was not sent')} onClose={() => setFormError('')}>{formError}</Alert> : null}
              </div>

              <div className={cn('flex gap-3 border-t border-line bg-subtle px-5 py-4 sm:px-6', wide ? 'items-center justify-between' : 'flex-col-reverse')}>
                <p className="text-[13px] text-muted">
                  {t('submittingAs', 'Submitting as %s', cfg.user.name || cfg.user.email)}
                  {cfg.user.email && cfg.user.name ? <span className="text-faint"> · {cfg.user.email}</span> : null}
                </p>
                <Button type="submit" variant="primary" icon={done ? CheckCircle2 : Send} loading={submitting && !done} disabled={done} className={cn(!wide && 'w-full')}>
                  {done ? t('submitted', 'Submitted') : t('submitTicket', 'Submit ticket')}
                </Button>
              </div>
            </Card>
          </DropArea>
      </form>
    </div>
  );
}
