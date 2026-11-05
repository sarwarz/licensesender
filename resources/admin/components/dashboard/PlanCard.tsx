import { ArrowRight, Crown, RefreshCw } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { ApiSubscriptionDetails } from '@/components/settings/ApiSubscriptionCard';
import { cn } from '@/lib/utils';
import { numberFormat, parseDate } from './types';

function statusClass(status?: string) {
  const v = (status || '').toLowerCase();
  if (['active', 'valid', 'enabled'].includes(v)) return 'bg-emerald-50 text-emerald-700';
  if (['trial', 'trialing', 'grace', 'pending'].includes(v)) return 'bg-amber-50 text-amber-700';
  if (['expired', 'inactive', 'cancelled', 'canceled', 'suspended'].includes(v)) return 'bg-rose-50 text-rose-700';
  return 'bg-slate-100 text-slate-700';
}

function Usage({ label, used, limit, unlimited }: { label: string; used?: number | null; limit?: number | null; unlimited?: boolean }) {
  const hasLimit = !unlimited && !!limit;
  const ratio = hasLimit ? Math.min(1, (used ?? 0) / (limit as number)) : 0;
  return (
    <div className="space-y-1.5">
      <div className="flex items-center justify-between text-xs">
        <span className="text-slate-500">{label}</span>
        <span className="font-medium text-slate-700">
          {numberFormat.format(used ?? 0)} / {hasLimit ? numberFormat.format(limit as number) : '∞'}
        </span>
      </div>
      <div className="h-1.5 overflow-hidden rounded-full bg-slate-100">
        <div
          className={cn('h-full rounded-full', ratio >= 1 ? 'bg-rose-500' : ratio >= 0.8 ? 'bg-amber-500' : 'bg-indigo-500')}
          style={{ width: hasLimit ? `${Math.max(2, ratio * 100)}%` : '100%', opacity: hasLimit ? 1 : 0.25 }}
        />
      </div>
    </div>
  );
}

export function PlanCard({
  details,
  loading,
  onRefresh,
  manageUrl,
  i18n,
}: {
  details: ApiSubscriptionDetails | null;
  loading: boolean;
  onRefresh: () => void;
  manageUrl: string;
  i18n: Record<string, string>;
}) {
  const sub = details?.subscription;
  const renews = sub?.expires_at ? parseDate(sub.expires_at) : null;

  return (
    <Card className="overflow-hidden border-slate-200 shadow-sm">
      <div className="bg-gradient-to-br from-indigo-500 to-violet-600 px-5 py-4 text-white">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0">
            <p className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-indigo-100">
              <Crown className="h-3.5 w-3.5" /> {i18n.yourPlan || 'Your plan'}
            </p>
            {loading && !details ? (
              <Skeleton className="mt-2 h-6 w-32 bg-white/20" />
            ) : (
              <p className="mt-1 truncate text-lg font-semibold">{sub?.plan || (details?.connected ? '—' : i18n.notConnected || 'Not connected')}</p>
            )}
          </div>
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8 shrink-0 text-white hover:bg-white/15 hover:text-white"
            onClick={onRefresh}
            disabled={loading}
            aria-label={i18n.refresh || 'Refresh'}
          >
            <RefreshCw className={cn('h-4 w-4', loading && 'animate-spin')} />
          </Button>
        </div>
      </div>
      <CardHeader className="sr-only">
        <CardTitle>{i18n.subscriptionOverview || 'Subscription'}</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4 p-5">
        {loading && !details ? (
          <div className="space-y-3">
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-4 w-2/3" />
          </div>
        ) : !details?.connected ? (
          <p className="text-sm text-slate-500">{details?.message || i18n.planUnavailable || 'Connect your API key to see plan details.'}</p>
        ) : (
          <>
            <div className="flex flex-wrap items-center gap-2 text-xs">
              {sub?.status ? <Badge className={cn('border-0 font-medium capitalize', statusClass(sub.status))}>{sub.status}</Badge> : null}
              {renews ? (
                <span className="text-slate-500">
                  {i18n.renews || 'Renews'} {renews.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })}
                </span>
              ) : null}
            </div>
            <Usage label={i18n.apiRequests || 'API requests this month'} used={sub?.monthly_used} limit={sub?.monthly_quota} unlimited={sub?.monthly_quota_unlimited} />
            <Usage label={i18n.apiProducts || 'Products'} used={details.product_count} limit={details.product_limit ?? sub?.product_limit} unlimited={!(details.product_limit ?? sub?.product_limit)} />
          </>
        )}
        <Button asChild variant="outline" size="sm" className="w-full bg-white">
          <a href={manageUrl}>
            {i18n.manageSubscription || 'Subscription details'} <ArrowRight className="h-3.5 w-3.5" />
          </a>
        </Button>
      </CardContent>
    </Card>
  );
}
