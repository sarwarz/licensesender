import { useCallback, useEffect, useState } from 'react';
import {
  CalendarDays,
  Key,
  Lightbulb,
  Mail,
  Package,
  PlugZap,
  RefreshCw,
  ShoppingCart,
  Users,
} from 'lucide-react';
import { toast } from 'sonner';
import { apiRequest } from '@/api/client';
import { getBootstrap } from '@/lib/bootstrap';
import { PageHeader } from '@/components/shared/PageHeader';
import { StatCard } from '@/components/licenses/StatCard';
import { ApiSubscriptionCard, type ApiSubscriptionDetails } from '@/components/settings/ApiSubscriptionCard';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface DashboardStats {
  total: number;
  orders: number;
  products: number;
  emails: number;
  today: number;
  week: number;
  emails_sent: number;
  emails_pending: number;
  wholesale_pending: number;
  api_connected: boolean;
}

interface DashboardData {
  stats: DashboardStats;
}

export function DashboardPage() {
  const { i18n } = getBootstrap();
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [subscription, setSubscription] = useState<ApiSubscriptionDetails | null>(null);
  const [subscriptionLoading, setSubscriptionLoading] = useState(false);
  const [featureTitle, setFeatureTitle] = useState('');
  const [featureMessage, setFeatureMessage] = useState('');
  const [featureSubmitting, setFeatureSubmitting] = useState(false);

  const loadStats = useCallback(async () => {
    setLoading(true);
    try {
      const payload = await apiRequest<DashboardData>('dashboard');
      setData(payload);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setLoading(false);
    }
  }, [i18n.error]);

  const loadSubscription = useCallback(async (refresh = false) => {
    setSubscriptionLoading(true);
    try {
      const details = await apiRequest<ApiSubscriptionDetails>(
        `settings/subscription${refresh ? '?refresh=1' : ''}`,
      );
      setSubscription(details);
    } catch {
      setSubscription(null);
    } finally {
      setSubscriptionLoading(false);
    }
  }, []);

  const refreshAll = useCallback(async () => {
    await Promise.all([loadStats(), loadSubscription(true)]);
  }, [loadStats, loadSubscription]);

  const submitFeatureRequest = useCallback(async () => {
    const title = featureTitle.trim();
    const message = featureMessage.trim();

    if (!title || !message) {
      toast.error(i18n.featureRequestRequired || 'Please enter a title and description.');
      return;
    }

    setFeatureSubmitting(true);
    try {
      const result = await apiRequest<{ message?: string }>('feature-requests', {
        method: 'POST',
        body: JSON.stringify({ title, message }),
      });
      toast.success(result.message || i18n.featureRequestSuccess || 'Feature request submitted. Thank you!');
      setFeatureTitle('');
      setFeatureMessage('');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : i18n.error);
    } finally {
      setFeatureSubmitting(false);
    }
  }, [featureTitle, featureMessage, i18n.error, i18n.featureRequestRequired, i18n.featureRequestSuccess]);

  useEffect(() => {
    loadStats().catch(() => undefined);
    loadSubscription().catch(() => undefined);
  }, [loadStats, loadSubscription]);

  const stats = data?.stats;

  return (
    <div className="mx-auto max-w-[1400px] space-y-6 pb-8">
      <PageHeader
        title={i18n.title || 'Dashboard'}
        subtitle={i18n.subtitle}
        actions={
          <Button variant="outline" size="sm" className="bg-white" onClick={() => refreshAll()}>
            <RefreshCw className="h-4 w-4" />
            {i18n.refresh || 'Refresh'}
          </Button>
        }
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {loading && !stats ? (
          Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-[88px] rounded-xl" />)
        ) : (
          <>
            <StatCard label={i18n.totalKeys || 'Total Keys'} value={stats?.total ?? 0} icon={Key} tone="indigo" />
            <StatCard label={i18n.orders || 'Orders'} value={stats?.orders ?? 0} icon={ShoppingCart} tone="sky" />
            <StatCard label={i18n.products || 'Products'} value={stats?.products ?? 0} icon={Package} tone="amber" />
            <StatCard label={i18n.emails || 'Unique Emails'} value={stats?.emails ?? 0} icon={Mail} tone="emerald" />
          </>
        )}
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {loading && !stats ? (
          Array.from({ length: 4 }).map((_, i) => <Skeleton key={`s2-${i}`} className="h-[88px] rounded-xl" />)
        ) : (
          <>
            <StatCard label={i18n.today || 'Delivered Today'} value={stats?.today ?? 0} icon={CalendarDays} tone="emerald" />
            <StatCard label={i18n.week || 'Last 7 Days'} value={stats?.week ?? 0} icon={CalendarDays} tone="sky" />
            <StatCard
              label={i18n.wholesalePending || 'Pending Wholesale'}
              value={stats?.wholesale_pending ?? 0}
              icon={Users}
              tone="amber"
            />
            <Card className="border-slate-200 shadow-sm">
              <CardContent className="flex items-center gap-4 p-5">
                <div
                  className={`flex h-11 w-11 items-center justify-center rounded-xl ${
                    stats?.api_connected ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'
                  }`}
                >
                  <PlugZap className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">{i18n.apiStatus || 'API Connection'}</p>
                  <div className="mt-1">
                    <Badge
                      variant="outline"
                      className={
                        stats?.api_connected
                          ? 'border-0 bg-emerald-50 font-semibold text-emerald-800'
                          : 'border-0 bg-amber-50 font-semibold text-amber-800'
                      }
                    >
                      {stats?.api_connected ? i18n.connected || 'Connected' : i18n.notConnected || 'Not connected'}
                    </Badge>
                  </div>
                </div>
              </CardContent>
            </Card>
          </>
        )}
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <ApiSubscriptionCard
            details={subscription}
            loading={subscriptionLoading}
            onRefresh={() => loadSubscription(true)}
            title={i18n.subscriptionOverview || 'Subscription Overview'}
            description={
              i18n.subscriptionOverviewDesc || 'Plan, quota, and account details from your LicenseSender API key.'
            }
          />
        </div>

        <div className="space-y-6">
          <Card className="border-slate-200 shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base font-semibold">{i18n.emailsSent || 'Email Delivery'}</CardTitle>
            </CardHeader>
            <CardContent className="grid grid-cols-2 gap-3">
              <div className="rounded-lg border bg-slate-50 p-3">
                <p className="text-xs font-medium text-muted-foreground">{i18n.emailsSent || 'Emails Sent'}</p>
                <p className="mt-1 text-2xl font-semibold">{stats?.emails_sent ?? 0}</p>
              </div>
              <div className="rounded-lg border bg-slate-50 p-3">
                <p className="text-xs font-medium text-muted-foreground">{i18n.emailsPending || 'Emails Pending'}</p>
                <p className="mt-1 text-2xl font-semibold">{stats?.emails_pending ?? 0}</p>
              </div>
            </CardContent>
          </Card>

          <Card className="border-slate-200 shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="flex items-center gap-2 text-base font-semibold">
                <Lightbulb className="h-4 w-4 text-amber-500" />
                {i18n.featureRequest || 'Feature Request'}
              </CardTitle>
              <CardDescription>
                {i18n.featureRequestDesc ||
                  'Suggest a plugin improvement. Requests appear in the LicenseSender admin panel.'}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="space-y-1.5">
                <Label htmlFor="ls-feature-title">{i18n.featureRequestTitle || 'Title'}</Label>
                <Input
                  id="ls-feature-title"
                  value={featureTitle}
                  onChange={(e) => setFeatureTitle(e.target.value)}
                  placeholder={i18n.featureRequestTitlePlaceholder || 'Short summary of your idea'}
                  maxLength={200}
                  disabled={featureSubmitting}
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="ls-feature-message">{i18n.featureRequestMessage || 'Details'}</Label>
                <textarea
                  id="ls-feature-message"
                  value={featureMessage}
                  onChange={(e) => setFeatureMessage(e.target.value)}
                  placeholder={
                    i18n.featureRequestMessagePlaceholder ||
                    'Describe the problem or what you would like added…'
                  }
                  maxLength={5000}
                  disabled={featureSubmitting}
                  rows={4}
                  className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                />
              </div>
              <Button className="w-full" onClick={() => submitFeatureRequest()} disabled={featureSubmitting}>
                {featureSubmitting
                  ? i18n.featureRequestSubmitting || 'Sending…'
                  : i18n.featureRequestSubmit || 'Send request'}
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
