import { useCallback, useEffect, useLayoutEffect, useRef, useState, type MouseEvent, type ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';
import {
  ArrowLeft,
  CalendarDays,
  Check,
  Clock,
  Copy,
  Flag,
  Lock,
  MessagesSquare,
  RefreshCw,
  Send,
  ShoppingBag,
  Tag,
  TicketX,
} from 'lucide-react';
import { useApp } from '../App';
import { AttachButton, DropArea, FileChips, Lightbox, MessageAttachments, useFilePicker } from '../Attachments';
import { ajax, cn, fullDate, getConfig, htmlToText, isCustomerMessage, messageTime, messageTooLong, parseDate, relativeTime, t, textToHtml } from '../lib';
import type { Attachment, Conversation, Message, TicketRow } from '../types';
import { Alert, Avatar, Button, Card, EmptyState, PriorityBadge, Skeleton, StatusBadge } from '../ui';

const COLLAPSE_AT = 900;
const locale = document.documentElement.lang || undefined;

function present(value?: string): string {
  return value && value !== '—' ? value : '';
}

type SortOrder = 'newest' | 'oldest';
const SORT_KEY = 'ls-support-conversation-order';

function storedSort(): SortOrder {
  try {
    return window.localStorage.getItem(SORT_KEY) === 'oldest' ? 'oldest' : 'newest';
  } catch {
    return 'newest';
  }
}

function useCopy(text: string) {
  const [copied, setCopied] = useState(false);
  const copy = async () => {
    try {
      await navigator.clipboard.writeText(text);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1500);
    } catch {
      // Clipboard blocked; nothing else to do.
    }
  };
  return { copied, copy };
}

/* ---------- Conversation list ---------- */

function MessageRow({ message, customerName, onPreview }: { message: Message; customerName: string; onPreview: (a: Attachment) => void }) {
  const mine = isCustomerMessage(message);
  const html = message.body_html || '';
  const long = html ? htmlToText(html).length > COLLAPSE_AT : false;
  const [expanded, setExpanded] = useState(!long);
  const name = mine ? customerName || t('you', 'You') : message.author_name || t('supportName', 'Support team');
  const when = messageTime(message);

  return (
    <li className="flex animate-fade-in gap-3 px-5 py-4 sm:px-6">
      <Avatar name={name} tone={mine ? 'brand' : 'neutral'} />
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
          <div className="flex min-w-0 items-center gap-2">
            <span className="truncate text-sm font-semibold text-fg">{name}</span>
            <span
              className={cn(
                'shrink-0 rounded-full px-2 py-px text-[11px] font-medium',
                mine ? 'bg-sky-500/10 text-sky-700 dark:text-sky-300' : 'bg-brand/10 text-brand dark:bg-brand/25 dark:text-fg',
              )}
            >
              {mine ? t('you', 'You') : t('staff', 'Staff')}
            </span>
          </div>
          <time className="shrink-0 text-xs text-faint" dateTime={when} title={fullDate(when)}>
            {relativeTime(when)}
          </time>
        </div>
        {html ? (
          <div className="mt-1.5 text-fg">
            <div
              className={cn('ls-prose', !expanded && 'max-h-[200px] overflow-hidden [mask-image:linear-gradient(to_bottom,black_60%,transparent)]')}
              // Sanitised with wp_kses_post on the server.
              dangerouslySetInnerHTML={{ __html: html }}
            />
            {long ? (
              <button type="button" onClick={() => setExpanded((v) => !v)} className="mt-1.5 text-xs font-semibold text-brand underline-offset-2 hover:underline">
                {expanded ? t('showLess', 'Show less') : t('showMore', 'Show full message')}
              </button>
            ) : null}
          </div>
        ) : null}
        <MessageAttachments items={message.attachments ?? []} mine={false} onPreview={onPreview} />
      </div>
    </li>
  );
}

function ConversationList({
  messages,
  customerName,
  onPreview,
  order,
  onOrder,
}: {
  messages: Message[];
  customerName: string;
  onPreview: (a: Attachment) => void;
  order: SortOrder;
  onOrder: (order: SortOrder) => void;
}) {
  const sorted = order === 'newest' ? [...messages].reverse() : messages;
  const count = messages.length;

  return (
    <section aria-label={t('conversation', 'Conversation')}>
      <div className="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5 sm:px-6">
        <div className="flex items-center gap-3">
          <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand/10 text-brand">
            <MessagesSquare className="h-4 w-4" />
          </span>
          <div>
            <p className="text-sm font-semibold text-fg">{t('conversation', 'Conversation')}</p>
            <p className="text-xs text-muted">{count === 1 ? t('oneMessage', '1 message') : t('messagesCount', '%d messages', count)}</p>
          </div>
        </div>
        {count > 1 ? (
          <div role="radiogroup" aria-label={t('sortBy', 'Sort by')} className="flex rounded-ctl bg-subtle p-1 ring-1 ring-inset ring-line">
            {(
              [
                ['newest', t('newestFirst', 'Newest first')],
                ['oldest', t('oldestFirst', 'Oldest first')],
              ] as const
            ).map(([value, label]) => (
              <button
                key={value}
                type="button"
                role="radio"
                aria-checked={order === value}
                onClick={() => onOrder(value)}
                className={cn(
                  'whitespace-nowrap rounded-[calc(var(--ls-radius-ctl)-4px)] px-2.5 py-1 text-xs font-medium transition-colors',
                  order === value ? 'bg-surface text-fg shadow-sm ring-1 ring-line' : 'text-muted hover:text-fg',
                )}
              >
                {label}
              </button>
            ))}
          </div>
        ) : null}
      </div>
      {count ? (
        <ol className="divide-y divide-line">
          {sorted.map((message, index) => (
            <MessageRow key={message.id ?? `${messageTime(message)}-${index}`} message={message} customerName={customerName} onPreview={onPreview} />
          ))}
        </ol>
      ) : (
        <p className="py-10 text-center text-sm text-muted">{t('noMessages', 'No messages yet.')}</p>
      )}
    </section>
  );
}
/* ---------- Header & details ---------- */

function MetaItem({ icon: Icon, children }: { icon: LucideIcon; children: ReactNode }) {
  return (
    <span className="inline-flex items-center gap-1.5">
      <Icon className="h-3.5 w-3.5 text-faint" />
      {children}
    </span>
  );
}

function TicketHeader({ ticket, subject, number }: { ticket: Partial<TicketRow>; subject: string; number: string }) {
  const { copied, copy } = useCopy(number);
  const category = present(ticket.category_label);
  const order = present(ticket.order_label);

  return (
    <div className="border-b border-line px-5 pb-5 pt-5 sm:px-7 sm:pt-6">
      <div className="flex flex-wrap items-center gap-2">
        <StatusBadge statusClass={ticket.status_class} label={ticket.status_label} />
        <PriorityBadge priorityClass={ticket.priority_class} priority={ticket.priority} label={ticket.priority_label} />
        <button
          type="button"
          onClick={copy}
          title={t('copyNumber', 'Copy ticket number')}
          className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-mono text-xs text-muted transition-colors hover:bg-fg/5 hover:text-fg"
        >
          #{number}
          {copied ? <Check className="h-3 w-3 text-emerald-500" /> : <Copy className="h-3 w-3 text-faint" />}
        </button>
      </div>
      <h2 className="mt-3 text-[22px] font-semibold leading-tight tracking-tight text-fg">{subject || t('untitled', 'Untitled ticket')}</h2>
      <p className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[13px] text-muted">
        {category ? <MetaItem icon={Tag}>{category}</MetaItem> : null}
        {order ? <MetaItem icon={ShoppingBag}>{t('orderShort', 'Order %s', order.replace(/^#?/, '#'))}</MetaItem> : null}
        {ticket.created_at ? (
          <MetaItem icon={CalendarDays}>
            <span title={fullDate(ticket.created_at)}>{t('openedOn', 'Opened %s', parseDate(ticket.created_at)?.toLocaleDateString(locale, { dateStyle: 'medium' }) ?? '')}</span>
          </MetaItem>
        ) : null}
      </p>
    </div>
  );
}

function DetailRow({ icon: Icon, label, children }: { icon: LucideIcon; label: string; children: ReactNode }) {
  return (
    <div className="flex items-center gap-3 py-3">
      <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-subtle text-muted ring-1 ring-inset ring-line">
        <Icon className="h-4 w-4" />
      </span>
      <div className="min-w-0 flex-1">
        <dt className="text-[11px] font-medium uppercase tracking-wide text-faint">{label}</dt>
        <dd className="m-0 mt-0.5 truncate text-[13px] font-medium text-fg">{children}</dd>
      </div>
    </div>
  );
}

function DetailsPanel({ ticket }: { ticket: Partial<TicketRow> }) {
  const priority = present(ticket.priority_label);
  const category = present(ticket.category_label);
  const order = present(ticket.order_label);

  return (
    <Card className="px-5 py-4">
      <p className="text-sm font-semibold text-fg">{t('ticketDetails', 'Ticket details')}</p>
      <dl className="mt-1 divide-y divide-line">
        <DetailRow icon={Flag} label={t('priority', 'Priority')}>
          {priority || '—'}
        </DetailRow>
        <DetailRow icon={Tag} label={t('category', 'Category')}>
          {category || '—'}
        </DetailRow>
        {order ? (
          <DetailRow icon={ShoppingBag} label={t('order', 'Order')}>
            {order.replace(/^#?/, '#')}
          </DetailRow>
        ) : null}
        <DetailRow icon={CalendarDays} label={t('created', 'Created')}>
          <span title={fullDate(ticket.created_at)}>{ticket.created_at ? fullDate(ticket.created_at) : '—'}</span>
        </DetailRow>
        <DetailRow icon={Clock} label={t('lastUpdate', 'Last update')}>
          <span title={fullDate(ticket.updated_at)}>{relativeTime(ticket.updated_at)}</span>
        </DetailRow>
      </dl>
    </Card>
  );
}

/* ---------- Composer ---------- */

function Composer({ number, onSent }: { number: string; onSent: (conv: Conversation) => void }) {
  const cfg = getConfig();
  const [text, setText] = useState('');
  const [sending, setSending] = useState(false);
  const [error, setError] = useState('');
  const picker = useFilePicker();
  const area = useRef<HTMLTextAreaElement>(null);

  // Grow with the text up to a sensible height. Empty: fall back to the CSS min-height (measuring
  // before the layout settles can report a huge scrollHeight for the wrapped placeholder).
  useLayoutEffect(() => {
    const el = area.current;
    if (!el) return;
    el.style.height = 'auto';
    if (text) el.style.height = `${Math.min(el.scrollHeight, 320)}px`;
  }, [text]);

  const send = async () => {
    // Ctrl+Enter can fire while a send is still in flight.
    if (sending) return;
    if (!text.trim()) {
      setError(t('replyRequired', 'Write a message before sending.'));
      area.current?.focus();
      return;
    }
    if (messageTooLong(text)) {
      setError(t('messageTooLong', 'Your message is too long. Please shorten it or split it into two replies.'));
      return;
    }
    setSending(true);
    setError('');
    try {
      const result = await ajax<Conversation>('ls_support_reply_ticket', {
        ticket_number: number,
        message: textToHtml(text),
        attachments: picker.files,
      });
      setText('');
      picker.reset();
      onSent(result);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setSending(false);
    }
  };

  return (
    <DropArea onFiles={picker.add} className="rounded-ctl">
      <div className="rounded-ctl border border-line bg-surface shadow-sm transition-shadow focus-within:border-brand/60 focus-within:ring-4 focus-within:ring-brand/10">
        <label htmlFor="ls-reply" className="sr-only">
          {t('replyLabel', 'Your reply')}
        </label>
        <textarea
          id="ls-reply"
          ref={area}
          value={text}
          maxLength={cfg.limits.maxMessage}
          onChange={(e) => setText(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
              e.preventDefault();
              send();
            }
          }}
          rows={3}
          placeholder={t('replyPlaceholder', 'Write your reply…')}
          className="block min-h-[88px] w-full resize-none rounded-ctl border-0 bg-transparent px-4 pb-1 pt-3.5 text-sm leading-relaxed text-fg placeholder:text-faint focus:outline-none"
        />
        {picker.files.length ? (
          <div className="px-3 pb-2 pt-1">
            <FileChips files={picker.files} onRemove={picker.remove} />
          </div>
        ) : null}
        <div className="flex items-center justify-between gap-2 px-2 pb-2">
          <AttachButton onFiles={picker.add} />
          <div className="flex items-center gap-3">
            <span className="hidden text-[11px] text-faint sm:inline">{t('sendShortcut', 'Ctrl + Enter to send')}</span>
            <Button variant="primary" size="sm" icon={Send} loading={sending} onClick={send}>
              {t('sendReply', 'Send reply')}
            </Button>
          </div>
        </div>
      </div>
      {error || picker.error ? (
        <div className="mt-2">
          <Alert tone="error" onClose={() => setError('')}>
            {error || picker.error}
          </Alert>
        </div>
      ) : null}
    </DropArea>
  );
}

/* ---------- Loading ---------- */

/** Mirrors the loaded layout so nothing jumps when data arrives. */
function TicketSkeleton({ wide }: { wide: boolean }) {
  const rows = [
    ['w-24', 'w-[70%]'],
    ['w-28', 'w-[92%]', 'w-[55%]'],
  ];

  return (
    <div className={cn('grid gap-5', wide && 'grid-cols-[minmax(0,1fr)_288px] items-start')} role="status" aria-busy="true" aria-label={t('loadingTicket', 'Loading ticket…')}>
      <div className="min-w-0 space-y-5">
        <Card className="overflow-hidden">
          <div className="space-y-3 border-b border-line px-5 py-6 sm:px-7">
            <div className="flex items-center gap-2">
              <Skeleton className="h-[22px] w-24 rounded-full" />
              <Skeleton className="h-4 w-12" />
            </div>
            <Skeleton className="h-6 w-[min(420px,75%)] rounded-lg" />
            <div className="flex gap-4">
              <Skeleton className="h-3 w-24" />
              <Skeleton className="h-3 w-20" />
              <Skeleton className="h-3 w-28" />
            </div>
          </div>
          <div className="px-5 py-4 sm:px-7 sm:py-5">
            <div className="rounded-ctl border border-line p-3">
              <Skeleton className="h-3 w-40" />
              <div className="mt-8 flex items-center justify-between">
                <Skeleton className="h-6 w-20" />
                <Skeleton className="h-8 w-28 rounded-ctl" />
              </div>
            </div>
          </div>
        </Card>

        <Card className="overflow-hidden">
          <div className="flex items-center justify-between border-b border-line px-5 py-3.5 sm:px-6">
            <div className="flex items-center gap-3">
              <Skeleton className="h-9 w-9 rounded-lg" />
              <div className="space-y-1.5">
                <Skeleton className="h-3 w-24" />
                <Skeleton className="h-2.5 w-16" />
              </div>
            </div>
            <Skeleton className="h-8 w-44 rounded-ctl" />
          </div>
          <div className="divide-y divide-line">
            {rows.map(([name, ...lines], i) => (
              <div key={i} className="flex gap-3 px-5 py-4 sm:px-6">
                <Skeleton className="h-8 w-8 shrink-0 rounded-full" />
                <div className="min-w-0 flex-1 space-y-2.5">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <Skeleton className={cn('h-3', name)} />
                      <Skeleton className="h-4 w-10 rounded-full" />
                    </div>
                    <Skeleton className="h-2.5 w-16" />
                  </div>
                  {lines.map((line, j) => (
                    <Skeleton key={j} className={cn('h-3', line)} />
                  ))}
                </div>
              </div>
            ))}
          </div>
        </Card>
      </div>

      <Card className="px-5 py-4">
        <Skeleton className="h-4 w-28" />
        <div className="mt-2 divide-y divide-line">
          {['w-16', 'w-24', 'w-20', 'w-32', 'w-20'].map((value, i) => (
            <div key={i} className="flex items-center gap-3 py-3">
              <Skeleton className="h-8 w-8 rounded-lg" />
              <div className="space-y-1.5">
                <Skeleton className="h-2.5 w-14" />
                <Skeleton className={cn('h-3', value)} />
              </div>
            </div>
          ))}
        </div>
      </Card>
    </div>
  );
}

/* ---------- Page ---------- */

export function TicketView({ number, created }: { number: string; created: boolean }) {
  const { navigate, hrefFor, wide, isManagePage } = useApp();
  const [conv, setConv] = useState<Conversation | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState(created ? t('createdNotice', 'Your ticket was created. We will reply here and by email.') : '');
  const [preview, setPreview] = useState<Attachment | null>(null);
  const [order, setOrder] = useState<SortOrder>(storedSort);
  const conversationRef = useRef<HTMLDivElement>(null);
  const scrollOnUpdate = useRef(false);

  const changeOrder = (next: SortOrder) => {
    setOrder(next);
    try {
      window.localStorage.setItem(SORT_KEY, next);
    } catch {
      // Storage blocked: the choice just won't be remembered.
    }
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      setConv(await ajax<Conversation>('ls_support_get_conversation', { ticket_number: number }));
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setLoading(false);
    }
  }, [number]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    // After sending, bring the conversation (with the new message) into view.
    if (scrollOnUpdate.current) {
      scrollOnUpdate.current = false;
      conversationRef.current?.scrollIntoView({ behavior: 'smooth', block: order === 'newest' ? 'start' : 'end' });
    }
  }, [conv]);

  const onSent = (result: Conversation) => {
    setNotice('');
    scrollOnUpdate.current = true;
    if (result.reload_ticket || !result.messages) {
      load();
    } else {
      setConv((prev) => ({ ...(prev as Conversation), ...result, subject: result.ticket?.subject || prev?.subject || '' }));
    }
  };

  const back = (event: MouseEvent<HTMLAnchorElement>) => {
    if (!isManagePage || event.metaKey || event.ctrlKey) return;
    event.preventDefault();
    navigate({ name: 'list' });
  };

  const ticket = conv?.ticket ?? {};
  const messages = [...(conv?.messages ?? [])].sort((a, b) => (parseDate(messageTime(a))?.getTime() ?? 0) - (parseDate(messageTime(b))?.getTime() ?? 0));
  const closed = ticket.status_class === 'closed' || ticket.status_class === 'resolved';
  const customerName = ticket.customer_name || getConfig().user.name;

  return (
    <div className="space-y-4">
      <nav className="flex items-center justify-between gap-3">
        <a
          href={hrefFor({ name: 'list' })}
          onClick={back}
          className="-ml-2 inline-flex h-9 items-center gap-1.5 rounded-ctl px-2 text-sm font-medium text-muted no-underline transition-colors hover:bg-fg/5 hover:text-fg"
        >
          <ArrowLeft className="h-4 w-4" />
          {t('backToTickets', 'All tickets')}
        </a>
        <Button variant="ghost" size="sm" icon={RefreshCw} onClick={load} disabled={loading} className={cn(loading && '[&_svg]:animate-spin')}>
          <span className="hidden sm:inline">{t('refresh', 'Refresh')}</span>
        </Button>
      </nav>

      {notice ? (
        <Alert tone="success" title={t('createdTitle', 'Ticket submitted')} onClose={() => setNotice('')}>
          {notice}
        </Alert>
      ) : null}

      {error && !conv ? (
        <Card>
          <EmptyState
            icon={TicketX}
            title={t('ticketLoadFailed', 'This ticket could not be opened')}
            action={
              <div className="flex flex-wrap justify-center gap-2">
                <a
                  href={hrefFor({ name: 'list' })}
                  onClick={back}
                  className="inline-flex h-10 items-center gap-2 rounded-ctl bg-brand px-4 text-sm font-medium text-brand-fg no-underline shadow-sm hover:bg-brand/90"
                >
                  <ArrowLeft className="h-4 w-4" />
                  {t('backToTickets', 'All tickets')}
                </a>
                <Button icon={RefreshCw} onClick={load} loading={loading}>
                  {t('tryAgain', 'Try again')}
                </Button>
              </div>
            }
          >
            {error}
          </EmptyState>
        </Card>
      ) : !conv ? (
        <TicketSkeleton wide={wide} />
      ) : (
        <div className={cn('grid gap-5', wide && 'grid-cols-[minmax(0,1fr)_288px] items-start')}>
          <div className="min-w-0 space-y-5">
            <Card className="overflow-hidden">
              <TicketHeader ticket={ticket} subject={conv.subject || ticket.subject || ''} number={number} />

              {ticket.status_class === 'awaiting-customer' && conv.can_reply ? (
                <div className="flex items-center gap-2.5 border-b border-amber-500/20 bg-amber-500/10 px-5 py-2.5 text-[13px] text-amber-900 dark:text-amber-200 sm:px-7">
                  <span className="h-2 w-2 shrink-0 rounded-full bg-amber-500" />
                  {t('awaitingYouHint', 'Our team replied and is waiting for your answer.')}
                </div>
              ) : null}

              {error ? (
                <div className="px-5 pt-4 sm:px-7">
                  <Alert tone="error" onClose={() => setError('')}>
                    {error}
                  </Alert>
                </div>
              ) : null}

              <div className="px-4 py-4 sm:px-7 sm:py-5">
                {conv.can_reply ? (
                  <Composer number={number} onSent={onSent} />
                ) : (
                  <div className="flex items-start gap-3 text-sm text-muted">
                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-subtle ring-1 ring-inset ring-line">
                      <Lock className="h-4 w-4" />
                    </span>
                    <div>
                      <p className="font-medium text-fg">{closed ? t('closedTitle', 'This ticket is closed') : t('viewOnlyTitle', 'Replies are not available')}</p>
                      <p className="mt-0.5">
                        {closed
                          ? t('ticketClosed', 'This conversation can no longer receive replies. Open a new ticket if you still need help.')
                          : t('viewOnly', 'You can read this ticket here. To reply, use the link in your ticket confirmation email.')}
                      </p>
                      {closed ? (
                        <a href={hrefFor({ name: 'new' })} className="mt-2 inline-block font-medium text-brand no-underline hover:underline">
                          {t('openNewTicket', 'Open a new ticket')} →
                        </a>
                      ) : null}
                    </div>
                  </div>
                )}
              </div>
            </Card>

            <div ref={conversationRef} className="scroll-mt-6">
              <Card className="overflow-hidden">
                <ConversationList messages={messages} customerName={customerName} onPreview={setPreview} order={order} onOrder={changeOrder} />
              </Card>
            </div>
          </div>

          <aside className={cn(wide && 'sticky top-6')}>
            <DetailsPanel ticket={ticket} />
          </aside>
        </div>
      )}

      <Lightbox item={preview} onClose={() => setPreview(null)} />
    </div>
  );
}
