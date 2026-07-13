import type { ReactNode } from 'react';
import { CalendarDays, Gauge, KeyRound, Package, RefreshCw, Server, ShieldCheck, User } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export interface ApiSubscriptionInfo {
  plan?: string;
  plan_code?: string;
  plan_price_month?: number;
  plan_price_year?: number;
  status?: string;
  expires_at?: string;
  payment_method?: string;
  monthly_quota?: number;
  monthly_quota_unlimited?: boolean;
  monthly_used?: number;
  monthly_remaining?: number;
  product_limit?: number;
  api_key_limit?: number;
  rpm_limit?: number;
  email?: string;
  account_name?: string;
  api_key_name?: string;
  api_key_project?: string;
  api_key_last_used?: string;
}

export interface ApiSubscriptionDetails {
  success: boolean;
  connected: boolean;
  message: string;
  server_time?: string;
  product_count?: number | null;
  product_limit?: number | null;
  subscription?: ApiSubscriptionInfo;
}

interface ApiSubscriptionCardProps {
  details: ApiSubscriptionDetails | null;
  loading?: boolean;
  onRefresh?: () => void;
  title?: string;
  description?: string;
}

function statusTone(status?: string) {
  const value = (status || '').toLowerCase();
  if (['active', 'valid', 'enabled'].includes(value)) {
    return 'bg-emerald-100 text-emerald-800 hover:bg-emerald-100';
  }
  if (['trial', 'grace', 'pending'].includes(value)) {
    return 'bg-amber-100 text-amber-800 hover:bg-amber-100';
  }
  if (['expired', 'inactive', 'cancelled', 'canceled', 'missing'].includes(value)) {
    return 'bg-red-100 text-red-800 hover:bg-red-100';
  }
  return 'bg-slate-100 text-slate-700 hover:bg-slate-100';
}

function formatDate(value?: string) {
  if (!value) {
    return '—';
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }
  return date.toLocaleString();
}

function DetailRow({
  icon: Icon,
  label,
  value,
}: {
  icon: typeof Server;
  label: string;
  value: ReactNode;
}) {
  return (
    <div className="flex items-start gap-3 rounded-lg border border-slate-100 bg-slate-50/80 px-4 py-3">
      <Icon className="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
      <div className="min-w-0">
        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
        <div className="mt-1 text-sm font-medium text-foreground">{value}</div>
      </div>
    </div>
  );
}

function isUnlimitedLimit(value?: number | null) {
  return value === 0;
}

function formatLimitValue(value?: number | null, suffix = '') {
  if (value == null) {
    return null;
  }
  if (isUnlimitedLimit(value)) {
    return 'Unlimited';
  }
  return `${value.toLocaleString()}${suffix}`;
}

export function ApiSubscriptionCard({
  details,
  loading = false,
  onRefresh,
  title = 'Subscription & Connection',
  description = 'Live account details from your Licensesender API key.',
}: ApiSubscriptionCardProps) {
  const subscription = details?.subscription ?? {};
  const quota = subscription.monthly_quota ?? null;
  const quotaUnlimited = subscription.monthly_quota_unlimited === true || isUnlimitedLimit(quota);
  const used = subscription.monthly_used ?? 0;
  const remaining = quotaUnlimited
    ? null
    : subscription.monthly_remaining ?? (quota !== null && quota > 0 ? Math.max(0, quota - used) : null);
  const usagePercent = !quotaUnlimited && quota && quota > 0 ? Math.min(100, Math.round((used / quota) * 100)) : null;
  const hasSubscriptionFields = Boolean(
    subscription.plan ||
      subscription.status ||
      subscription.expires_at ||
      subscription.email ||
      subscription.account_name ||
      subscription.api_key_name ||
      quota !== null,
  );

  const productCount = details?.product_count ?? null;
  const productLimit = subscription.product_limit ?? details?.product_limit ?? null;
  const productLimitUnlimited = isUnlimitedLimit(productLimit);
  const productLabel =
    productCount != null && productLimit != null
      ? productLimitUnlimited
        ? `${productCount.toLocaleString()} / Unlimited`
        : `${productCount.toLocaleString()} / ${productLimit.toLocaleString()}`
      : productCount != null
        ? productCount.toLocaleString()
        : productLimit != null
          ? productLimitUnlimited
            ? 'Unlimited'
            : `Limit ${productLimit.toLocaleString()}`
          : '—';

  const planLabel = subscription.plan
    ? subscription.plan_code && subscription.plan_code !== subscription.plan
      ? `${subscription.plan} (${subscription.plan_code})`
      : subscription.plan
    : hasSubscriptionFields
      ? '—'
      : 'Not returned by API';

  return (
    <Card className="border-slate-200 bg-gradient-to-br from-white to-slate-50/80 shadow-sm">
      <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
        <div>
          <CardTitle className="text-lg">{title}</CardTitle>
          <CardDescription>{description}</CardDescription>
        </div>
        {onRefresh ? (
          <Button variant="outline" size="sm" className="bg-white" onClick={onRefresh} disabled={loading}>
            <RefreshCw className={cn('h-4 w-4', loading && 'animate-spin')} />
            Refresh
          </Button>
        ) : null}
      </CardHeader>
      <CardContent className="space-y-4">
        {loading && !details ? (
          <p className="text-sm text-muted-foreground">Loading subscription details…</p>
        ) : null}

        {!loading && !details ? (
          <p className="text-sm text-muted-foreground">
            Save your API key, then test the connection to load subscription details.
          </p>
        ) : null}

        {details ? (
          <>
            <div className="flex flex-wrap items-center gap-2">
              <Badge className={details.connected ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-100' : 'bg-red-100 text-red-800 hover:bg-red-100'}>
                {details.connected ? 'Connected' : 'Not connected'}
              </Badge>
              {subscription.status ? (
                <Badge className={statusTone(subscription.status)}>{subscription.status}</Badge>
              ) : null}
              {subscription.plan ? (
                <Badge variant="outline" className="bg-white">{subscription.plan}</Badge>
              ) : null}
            </div>

            {details.message ? (
              <p className="text-sm text-muted-foreground">{details.message}</p>
            ) : null}

            <div className="grid gap-3 md:grid-cols-2">
              <DetailRow icon={ShieldCheck} label="Plan" value={planLabel} />
              <DetailRow icon={CalendarDays} label="Renews" value={formatDate(subscription.expires_at)} />
              <DetailRow icon={Server} label="Server Time" value={details.server_time || '—'} />
              <DetailRow icon={Package} label="API Products" value={productLabel} />
              {subscription.account_name ? (
                <DetailRow icon={User} label="Account" value={subscription.account_name} />
              ) : null}
              {subscription.email ? <DetailRow icon={User} label="Account Email" value={subscription.email} /> : null}
              {subscription.api_key_name ? (
                <DetailRow
                  icon={KeyRound}
                  label="API Key"
                  value={
                    subscription.api_key_project
                      ? `${subscription.api_key_name} · ${subscription.api_key_project}`
                      : subscription.api_key_name
                  }
                />
              ) : null}
            </div>

            {quota !== null ? (
              <div className="rounded-xl border border-slate-200 bg-white p-4">
                <div className="mb-3 flex items-center justify-between gap-3">
                  <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                    <Gauge className="h-4 w-4 text-primary" />
                    Monthly API requests
                  </div>
                  <span className="text-sm text-muted-foreground">
                    {used.toLocaleString()} / {quotaUnlimited ? 'Unlimited' : quota.toLocaleString()}
                  </span>
                </div>
                {usagePercent !== null ? (
                  <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                    <div
                      className={cn(
                        'h-full rounded-full transition-all',
                        usagePercent >= 90 ? 'bg-red-500' : usagePercent >= 75 ? 'bg-amber-500' : 'bg-primary',
                      )}
                      style={{ width: `${usagePercent}%` }}
                    />
                  </div>
                ) : null}
                {quotaUnlimited ? (
                  <p className="mt-2 text-xs text-muted-foreground">Unlimited API requests this month.</p>
                ) : remaining !== null ? (
                  <p className="mt-2 text-xs text-muted-foreground">
                    {remaining.toLocaleString()} API requests remaining this month.
                  </p>
                ) : null}
              </div>
            ) : null}

            {(subscription.rpm_limit != null || subscription.api_key_limit != null) ? (
              <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                {subscription.rpm_limit != null ? (
                  <span className="rounded-full border bg-white px-3 py-1">
                    Rate limit: {formatLimitValue(subscription.rpm_limit, ' req/min')}
                  </span>
                ) : null}
                {subscription.api_key_limit != null ? (
                  <span className="rounded-full border bg-white px-3 py-1">
                    API keys: {formatLimitValue(subscription.api_key_limit)}
                  </span>
                ) : null}
                {subscription.payment_method ? (
                  <span className="rounded-full border bg-white px-3 py-1">Billing: {subscription.payment_method}</span>
                ) : null}
              </div>
            ) : null}

            {!hasSubscriptionFields && details.connected ? (
              <p className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                API connection works, but plan/quota fields were not returned. Manage your subscription in the{' '}
                <a
                  href="https://app.licensesender.com"
                  target="_blank"
                  rel="noreferrer"
                  className="font-medium underline"
                >
                  Licensesender dashboard
                </a>
                .
              </p>
            ) : null}
          </>
        ) : null}
      </CardContent>
    </Card>
  );
}
