import { useCallback, useEffect, useState } from 'react';
import { Database, KeyRound, MailWarning, RefreshCw, ShoppingCart } from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { getBootstrap } from '@/lib/bootstrap';
import { cn } from '@/lib/utils';
import { PageHeader } from '@/components/shared/PageHeader';
import type { ApiSubscriptionDetails } from '@/components/settings/ApiSubscriptionCard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { AttentionCard } from './AttentionCard';
import { DeliveryChart } from './DeliveryChart';
import { FeatureRequestDialog } from './FeatureRequestDialog';
import { KpiCard } from './KpiCard';
import { PlanCard } from './PlanCard';
import { QuickActions } from './QuickActions';
import { RecentDeliveries } from './RecentDeliveries';
import { TopProducts } from './TopProducts';
import { numberFormat, type DashboardData } from './types';

export function DashboardPage() {
  const { i18n } = getBootstrap();
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [subscription, setSubscription] = useState<ApiSubscriptionDetails | null>(null);
  const [subscriptionLoading, setSubscriptionLoading] = useState(false);

  const loadStats = useCallback(async () => {
    setLoading(true);
    try {
      setData(await apiRequest<DashboardData>('dashboard'));
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setLoading(false);
    }
  }, [i18n.error]);

  const loadSubscription = useCallback(async (refresh = false) => {
    setSubscriptionLoading(true);
    try {
      setSubscription(await apiRequest<ApiSubscriptionDetails>(`settings/subscription${refresh ? '?refresh=1' : ''}`));
    } catch {
      setSubscription(null);
    } finally {
      setSubscriptionLoading(false);
    }
  }, []);

  useEffect(() => {
    loadStats().catch(() => undefined);
    loadSubscription().catch(() => undefined);
  }, [loadStats, loadSubscription]);

  const refreshing = loading || subscriptionLoading;
  const stats = data?.stats;

  return (
    <div className="mx-auto max-w-[1400px] space-y-6 pb-8">
      <PageHeader
        title={i18n.title || 'Dashboard'}
        subtitle={i18n.subtitle}
        badge={
          stats ? (
            <Badge
              variant="outline"
              className={cn(
                'gap-1.5 border-0 font-medium',
                stats.api_connected ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'
              )}
            >
              <span className={cn('h-1.5 w-1.5 rounded-full', stats.api_connected ? 'bg-emerald-500' : 'bg-amber-500')} />
              {stats.api_connected ? i18n.connected || 'Connected' : i18n.notConnected || 'Not connected'}
            </Badge>
          ) : null
        }
        actions={
          <>
            <FeatureRequestDialog i18n={i18n} />
            <Button
              variant="outline"
              size="sm"
              className="bg-white"
              disabled={refreshing}
              onClick={() => Promise.all([loadStats(), loadSubscription(true)])}
            >
              <RefreshCw className={cn('h-4 w-4', refreshing && 'animate-spin')} />
              {i18n.refresh || 'Refresh'}
            </Button>
          </>
        }
      />

      {!data || !stats ? (
        loading ? (
          <DashboardSkeleton />
        ) : (
          <div className="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
            {i18n.loadFailed || 'The dashboard could not be loaded.'}{' '}
            <button type="button" className="font-medium text-indigo-600 underline-offset-2 hover:underline" onClick={() => loadStats()}>
              {i18n.tryAgain || 'Try again'}
            </button>
          </div>
        )
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <KpiCard
              label={i18n.keysDelivered || 'Keys delivered · 30 days'}
              value={stats.month}
              previous={stats.previous_month}
              icon={KeyRound}
              tone="indigo"
              hint={`${numberFormat.format(stats.today)} ${i18n.todayShort || 'today'} · ${numberFormat.format(stats.week)} ${i18n.weekShort || 'this week'}`}
            />
            <KpiCard
              label={i18n.ordersFulfilled || 'Orders fulfilled · 30 days'}
              value={stats.month_orders}
              icon={ShoppingCart}
              tone="sky"
              hint={`${numberFormat.format(stats.orders)} ${i18n.allTime || 'all time'}`}
              href={data.links.orders}
            />
            <KpiCard
              label={i18n.totalKeys || 'Total keys'}
              value={stats.total}
              icon={Database}
              tone="emerald"
              hint={`${numberFormat.format(stats.products)} ${i18n.productsShort || 'products'} · ${numberFormat.format(stats.emails)} ${i18n.customersShort || 'customers'}`}
              href={data.links.licenses}
            />
            <KpiCard
              label={i18n.emailsPending || 'Emails pending'}
              value={stats.emails_pending}
              icon={MailWarning}
              tone={stats.emails_pending > 0 ? 'amber' : 'emerald'}
              progress={stats.total ? stats.emails_sent / stats.total : 0}
              hint={`${numberFormat.format(stats.emails_sent)} ${i18n.sentShort || 'sent'} · ${stats.total ? Math.round((stats.emails_sent / stats.total) * 100) : 0}% ${i18n.emailedShort || 'emailed'}`}
              href={data.links.licenses}
            />
          </div>

          <div className="grid gap-6 lg:grid-cols-3">
            <div className="min-w-0 lg:col-span-2">
              <DeliveryChart trend={data.trend} i18n={i18n} />
            </div>
            <AttentionCard data={data} subscription={subscription} i18n={i18n} />
          </div>

          <div className="grid gap-6 lg:grid-cols-3">
            <div className="min-w-0 space-y-6 lg:col-span-2">
              <RecentDeliveries items={data.recent} allUrl={data.links.licenses} i18n={i18n} />
              <TopProducts items={data.top_products} i18n={i18n} />
            </div>
            <div className="min-w-0 space-y-6">
              <PlanCard
                details={subscription}
                loading={subscriptionLoading}
                onRefresh={() => loadSubscription(true)}
                manageUrl={data.links.api_settings}
                i18n={i18n}
              />
              <QuickActions data={data} i18n={i18n} />
            </div>
          </div>
        </>
      )}
    </div>
  );
}

function DashboardSkeleton() {
  return (
    <div className="space-y-6">
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {Array.from({ length: 4 }).map((_, i) => (
          <Skeleton key={i} className="h-[132px] rounded-xl" />
        ))}
      </div>
      <div className="grid gap-6 lg:grid-cols-3">
        <Skeleton className="h-[340px] rounded-xl lg:col-span-2" />
        <Skeleton className="h-[340px] rounded-xl" />
      </div>
      <div className="grid gap-6 lg:grid-cols-3">
        <Skeleton className="h-[360px] rounded-xl lg:col-span-2" />
        <Skeleton className="h-[360px] rounded-xl" />
      </div>
    </div>
  );
}
