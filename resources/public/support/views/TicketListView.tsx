import { useEffect, useRef, useState, type MouseEvent } from 'react';
import { ArrowDownWideNarrow, ChevronLeft, ChevronRight, Inbox, LifeBuoy, MessageSquareText, Plus, RefreshCw, Search, SearchX, X } from 'lucide-react';
import { useApp } from '../App';
import { ajax, cn, fullDate, getConfig, relativeTime, t } from '../lib';
import type { TicketList, TicketRow } from '../types';
import { Alert, Button, Card, EmptyState, PriorityBadge, Select, Skeleton, StatusBadge } from '../ui';

function useDebounced<T>(value: T, delay = 350): T {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const id = window.setTimeout(() => setDebounced(value), delay);
    return () => window.clearTimeout(id);
  }, [value, delay]);
  return debounced;
}

function TicketRowItem({ ticket }: { ticket: TicketRow }) {
  const { navigate, hrefFor, wide } = useApp();
  const route = { name: 'ticket' as const, number: ticket.ticket_number };
  const needsReply = ticket.status_class === 'awaiting-customer';

  const onClick = (event: MouseEvent<HTMLAnchorElement>) => {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;
    event.preventDefault();
    navigate(route);
  };

  return (
    <li>
      <a
        href={hrefFor(route)}
        onClick={onClick}
        className={cn(
          // cn() doesn't merge conflicting utilities, so each branch sets its own alignment and gap.
          'group relative flex px-5 py-4 no-underline transition-colors hover:bg-subtle focus-visible:bg-subtle',
          wide ? 'items-center gap-4' : 'flex-col gap-2.5',
        )}
      >
        {needsReply ? <span className="absolute inset-y-3 left-0 w-[3px] rounded-r-full bg-amber-500" aria-hidden="true" /> : null}
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2">
            <p className="truncate text-[15px] font-semibold text-fg group-hover:text-brand">{ticket.subject || t('untitled', 'Untitled ticket')}</p>
            <PriorityBadge priorityClass={ticket.priority_class} priority={ticket.priority} label={ticket.priority_label} />
          </div>
          <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[13px] text-muted">
            <span className="font-mono text-[12px] text-faint">#{ticket.ticket_number}</span>
            {ticket.category_label && ticket.category_label !== '—' ? (
              <>
                <span className="text-faint">·</span>
                <span>{ticket.category_label}</span>
              </>
            ) : null}
            {ticket.order_label && ticket.order_label !== '—' ? (
              <>
                <span className="text-faint">·</span>
                <span>{t('orderShort', 'Order %s', ticket.order_label.replace(/^#?/, '#'))}</span>
              </>
            ) : null}
          </p>
        </div>
        {/* Fixed width on wide screens so badges and dates line up from row to row. */}
        <div className={cn('flex shrink-0', wide ? 'w-44 flex-col items-end gap-1.5' : 'items-center justify-between gap-3')}>
          <StatusBadge statusClass={ticket.status_class} label={ticket.status_label} />
          <time className="text-xs text-muted" dateTime={ticket.updated_at} title={fullDate(ticket.updated_at)}>
            {t('updatedAgo', 'Updated %s', relativeTime(ticket.updated_at))}
          </time>
        </div>
        {wide ? <ChevronRight className="h-4 w-4 shrink-0 text-faint transition-transform group-hover:translate-x-0.5 group-hover:text-muted" /> : null}
      </a>
    </li>
  );
}

function ListSkeleton() {
  return (
    <ul className="divide-y divide-line">
      {Array.from({ length: 5 }).map((_, i) => (
        <li key={i} className="flex items-center gap-4 px-5 py-4">
          <div className="flex-1 space-y-2">
            <Skeleton className="h-4 w-2/3 max-w-[340px]" />
            <Skeleton className="h-3 w-1/3 max-w-[200px]" />
          </div>
          <div className="flex flex-col items-end gap-2">
            <Skeleton className="h-5 w-24 rounded-full" />
            <Skeleton className="h-3 w-20" />
          </div>
        </li>
      ))}
    </ul>
  );
}

export function TicketListView() {
  const cfg = getConfig();
  const { navigate, hrefFor, wide } = useApp();
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [sortBy, setSortBy] = useState('updated_at');
  const [page, setPage] = useState(1);
  const [data, setData] = useState<TicketList | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [reloadKey, setReloadKey] = useState(0);
  const debouncedSearch = useDebounced(search.trim());
  const requestId = useRef(0);

  const filtersKey = `${debouncedSearch}|${status}|${sortBy}`;
  const lastFilters = useRef(filtersKey);

  useEffect(() => {
    // New filters start from page 1. If we're on a later page, reset it and let that
    // change trigger the single request (otherwise we'd fetch twice).
    if (lastFilters.current !== filtersKey) {
      lastFilters.current = filtersKey;
      if (page !== 1) {
        setPage(1);
        return;
      }
    }

    const id = ++requestId.current;
    setLoading(true);
    setError('');
    ajax<TicketList>('ls_support_list_tickets', {
      search: debouncedSearch,
      status,
      sort_by: sortBy,
      sort_order: sortBy === 'subject' ? 'asc' : 'desc',
      page,
      per_page: cfg.perPage,
    })
      .then((result) => id === requestId.current && setData(result))
      .catch((err: Error) => id === requestId.current && setError(err.message))
      .finally(() => id === requestId.current && setLoading(false));
  }, [debouncedSearch, status, sortBy, page, reloadKey, cfg.perPage]);

  const tickets = data?.tickets ?? [];
  const filtered = debouncedSearch !== '' || status !== '';
  const start = data && data.total ? (data.page - 1) * data.per_page + 1 : 0;
  const end = data ? Math.min(data.page * data.per_page, data.total) : 0;

  const openNew = (event: MouseEvent<HTMLAnchorElement>) => {
    if (event.metaKey || event.ctrlKey) return;
    event.preventDefault();
    navigate({ name: 'new' });
  };

  const newButton = (
    <a
      href={hrefFor({ name: 'new' })}
      onClick={openNew}
      className="inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-ctl bg-brand px-4 text-sm font-medium text-brand-fg no-underline shadow-sm transition-colors hover:bg-brand/90"
    >
      <Plus className="h-4 w-4" />
      {t('newTicket', 'New ticket')}
    </a>
  );

  return (
    <div className="space-y-5">
      <header className={cn('flex gap-4', wide ? 'items-end justify-between' : 'flex-col')}>
        <div className="min-w-0">
          <div className="flex items-center gap-2.5">
            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand/10 text-brand">
              <LifeBuoy className="h-5 w-5" />
            </span>
            <h2 className="text-xl font-semibold tracking-tight text-fg">{t('myTickets', 'Support tickets')}</h2>
          </div>
          <p className="mt-1.5 text-sm text-muted">{t('myTicketsLead', 'Track your requests and continue conversations with our team.')}</p>
        </div>
        {newButton}
      </header>

      <Card className="overflow-hidden">
        <div className="space-y-3 border-b border-line px-4 py-3.5 sm:px-5">
          <div className={cn('flex gap-2.5', wide ? 'items-center' : 'flex-col')}>
            <div className="relative min-w-0 flex-1">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
              <input
                type="search"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder={t('searchTickets', 'Search by subject, ticket or order number…')}
                className="h-10 w-full rounded-ctl border border-line bg-subtle pl-9 pr-9 text-sm text-fg placeholder:text-faint focus:border-brand focus:bg-surface focus:outline-none focus:ring-4 focus:ring-brand/15 [&::-webkit-search-cancel-button]:hidden"
                aria-label={t('search', 'Search')}
              />
              {search ? (
                <button type="button" onClick={() => setSearch('')} className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-faint hover:text-fg" aria-label={t('clearSearch', 'Clear search')}>
                  <X className="h-3.5 w-3.5" />
                </button>
              ) : null}
            </div>
            <div className="flex items-center gap-2">
              <Select
                value={sortBy}
                onChange={setSortBy}
                options={cfg.sortOptions}
                icon={ArrowDownWideNarrow}
                aria-label={t('sortBy', 'Sort by')}
                className={wide ? 'w-52' : 'flex-1'}
              />
              <Button variant="secondary" size="icon" onClick={() => setReloadKey((k) => k + 1)} aria-label={t('refresh', 'Refresh')} title={t('refresh', 'Refresh')}>
                <RefreshCw className={cn('h-4 w-4', loading && 'animate-spin')} />
              </Button>
            </div>
          </div>
          <div className="ls-scrollbar-none -mx-1 flex gap-1 overflow-x-auto px-1" role="tablist" aria-label={t('status', 'Status')}>
            {cfg.statusFilters.map((o) => (
              <button
                key={o.value || 'all'}
                type="button"
                role="tab"
                aria-selected={status === o.value}
                onClick={() => setStatus(o.value)}
                className={cn(
                  'h-8 whitespace-nowrap rounded-full px-3.5 text-[13px] font-medium transition-colors',
                  status === o.value ? 'bg-fg text-surface' : 'text-muted hover:bg-fg/5 hover:text-fg',
                )}
              >
                {o.label}
              </button>
            ))}
          </div>
        </div>

        {error ? (
          <div className="p-5">
            <Alert tone="error" title={t('loadFailed', 'Could not load your tickets')} action={<Button size="sm" icon={RefreshCw} onClick={() => setReloadKey((k) => k + 1)}>{t('tryAgain', 'Try again')}</Button>}>
              {error}
            </Alert>
          </div>
        ) : loading && !data ? (
          <ListSkeleton />
        ) : tickets.length === 0 ? (
          filtered ? (
            <EmptyState
              icon={SearchX}
              title={t('noMatches', 'No tickets match your filters')}
              action={
                <Button size="sm" onClick={() => { setSearch(''); setStatus(''); }}>
                  {t('clearFilters', 'Clear filters')}
                </Button>
              }
            >
              {t('noMatchesHint', 'Try a different search term or status.')}
            </EmptyState>
          ) : (
            <EmptyState icon={Inbox} title={t('noTicketsTitle', 'No tickets yet')} action={newButton}>
              {t('noTicketsHint', 'When you need help with an order or license, open a ticket and we will follow up here.')}
            </EmptyState>
          )
        ) : (
          <ul className={cn('divide-y divide-line transition-opacity', loading && 'opacity-60')}>
            {tickets.map((ticket) => (
              <TicketRowItem key={ticket.ticket_number} ticket={ticket} />
            ))}
          </ul>
        )}

        {data && data.total > 0 && !error ? (
          <div className="flex items-center justify-between gap-3 border-t border-line bg-subtle px-5 py-3 text-[13px] text-muted">
            <span className="flex items-center gap-1.5">
              <MessageSquareText className="h-3.5 w-3.5" />
              {t('ticketRange', '%d–%d of %d tickets', start, end, data.total)}
            </span>
            {data.total_pages > 1 ? (
              <div className="flex items-center gap-1">
                <Button size="icon-sm" variant="ghost" disabled={page <= 1 || loading} onClick={() => setPage((p) => p - 1)} aria-label={t('prevPage', 'Previous page')}>
                  <ChevronLeft className="h-4 w-4" />
                </Button>
                <span className="min-w-[56px] text-center tabular-nums">
                  {data.page} / {data.total_pages}
                </span>
                <Button size="icon-sm" variant="ghost" disabled={page >= data.total_pages || loading} onClick={() => setPage((p) => p + 1)} aria-label={t('nextPage', 'Next page')}>
                  <ChevronRight className="h-4 w-4" />
                </Button>
              </div>
            ) : null}
          </div>
        ) : null}
      </Card>
    </div>
  );
}
