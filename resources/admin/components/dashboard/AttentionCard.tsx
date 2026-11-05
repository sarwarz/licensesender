import type { LucideIcon } from 'lucide-react';
import { ArrowRight, CircleCheck, Gauge, MailWarning, PlugZap, Rocket, TriangleAlert, Users } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { ApiSubscriptionDetails } from '@/components/settings/ApiSubscriptionCard';
import { cn } from '@/lib/utils';
import { numberFormat, parseDate, type DashboardData } from './types';

interface Item {
  id: string;
  level: 'critical' | 'warning' | 'info';
  icon: LucideIcon;
  title: string;
  description: string;
  href: string;
  action: string;
}

const levels = {
  critical: 'bg-rose-50 text-rose-600 ring-rose-100',
  warning: 'bg-amber-50 text-amber-600 ring-amber-100',
  info: 'bg-sky-50 text-sky-600 ring-sky-100',
};

/** Builds the to-do list from store stats and the subscription. Most urgent first. */
function buildItems(data: DashboardData, subscription: ApiSubscriptionDetails | null, i18n: Record<string, string>): Item[] {
  const { stats, setup, links } = data;
  const items: Item[] = [];

  if (!stats.api_connected) {
    items.push({
      id: 'api',
      level: 'critical',
      icon: PlugZap,
      title: i18n.attnApiTitle || 'Connect your API key',
      description: i18n.attnApiDesc || 'Keys cannot be delivered until the store is connected to LicenseSender.',
      href: links.api_settings,
      action: i18n.connect || 'Connect',
    });
  }

  const sub = subscription?.subscription;
  const status = (sub?.status || '').toLowerCase();
  if (subscription?.connected && ['expired', 'inactive', 'cancelled', 'canceled', 'suspended'].includes(status)) {
    items.push({
      id: 'plan',
      level: 'critical',
      icon: TriangleAlert,
      title: i18n.attnPlanTitle || 'Your plan is not active',
      description: (i18n.attnPlanDesc || 'Status: %s. Renew to keep delivering keys.').replace('%s', sub?.status || ''),
      href: links.api_settings,
      action: i18n.review || 'Review',
    });
  } else if (sub?.expires_at) {
    const expires = parseDate(sub.expires_at);
    const days = expires ? Math.ceil((expires.getTime() - Date.now()) / 86400000) : null;
    if (days !== null && days >= 0 && days <= 7) {
      items.push({
        id: 'renew',
        level: 'warning',
        icon: TriangleAlert,
        title: (i18n.attnRenewTitle || 'Plan renews in %d days').replace('%d', String(days)),
        description: i18n.attnRenewDesc || 'Check your payment method so delivery is not interrupted.',
        href: links.api_settings,
        action: i18n.review || 'Review',
      });
    }
  }

  if (sub && !sub.monthly_quota_unlimited && sub.monthly_quota && sub.monthly_used !== undefined) {
    const used = sub.monthly_used / sub.monthly_quota;
    if (used >= 0.8) {
      items.push({
        id: 'quota',
        level: used >= 1 ? 'critical' : 'warning',
        icon: Gauge,
        title: (i18n.attnQuotaTitle || '%d% of monthly API requests used').replace('%d', String(Math.round(used * 100))),
        description: `${numberFormat.format(sub.monthly_used)} / ${numberFormat.format(sub.monthly_quota)}`,
        href: links.api_settings,
        action: i18n.review || 'Review',
      });
    }
  }

  if (!setup.complete) {
    items.push({
      id: 'setup',
      level: 'warning',
      icon: Rocket,
      title: i18n.attnSetupTitle || 'Finish the setup wizard',
      description: i18n.attnSetupDesc || 'A few steps are left before delivery is fully configured.',
      href: links.setup,
      action: i18n.continue || 'Continue',
    });
  }

  if (stats.wholesale_pending > 0) {
    items.push({
      id: 'wholesale',
      level: 'info',
      icon: Users,
      title: (i18n.attnWholesaleTitle || '%d wholesale application(s) waiting').replace('%d', numberFormat.format(stats.wholesale_pending)),
      description: i18n.attnWholesaleDesc || 'Approve or reject new reseller requests.',
      href: links.wholesale,
      action: i18n.review || 'Review',
    });
  }

  if (stats.emails_pending > 0) {
    items.push({
      id: 'emails',
      level: 'info',
      icon: MailWarning,
      title: (i18n.attnEmailsTitle || '%d key(s) not emailed yet').replace('%d', numberFormat.format(stats.emails_pending)),
      description: i18n.attnEmailsDesc || 'Customers can still see them in My Account. Resend from the License Keys page.',
      href: links.licenses,
      action: i18n.view || 'View',
    });
  }

  return items;
}

export function AttentionCard({
  data,
  subscription,
  i18n,
}: {
  data: DashboardData;
  subscription: ApiSubscriptionDetails | null;
  i18n: Record<string, string>;
}) {
  const items = buildItems(data, subscription, i18n);

  return (
    <Card className="flex flex-col border-slate-200 shadow-sm">
      <CardHeader className="pb-3">
        <CardTitle className="flex items-center justify-between text-base font-semibold">
          {i18n.needsAttention || 'Needs attention'}
          {items.length ? (
            <span className="rounded-full bg-slate-900 px-2 py-0.5 text-xs font-semibold text-white">{items.length}</span>
          ) : null}
        </CardTitle>
        <CardDescription>{i18n.needsAttentionDesc || 'Things to check in your store.'}</CardDescription>
      </CardHeader>
      <CardContent className="flex-1">
        {items.length === 0 ? (
          <div className="flex h-full min-h-[180px] flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-slate-200 text-center">
            <span className="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
              <CircleCheck className="h-5 w-5" />
            </span>
            <p className="text-sm font-medium text-slate-800">{i18n.allGood || 'All good'}</p>
            <p className="max-w-[220px] text-xs text-slate-500">{i18n.allGoodDesc || 'Delivery is running and nothing needs your attention.'}</p>
          </div>
        ) : (
          <ul className="m-0 list-none space-y-2 p-0">
            {items.map((item) => (
              <li key={item.id} className="m-0">
                <a
                  href={item.href}
                  className="group flex items-start gap-3 rounded-lg border border-slate-200 p-3 text-inherit no-underline transition-colors hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                  <span className={cn('flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ring-1', levels[item.level])}>
                    <item.icon className="h-4 w-4" />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block text-sm font-medium text-slate-900">{item.title}</span>
                    <span className="mt-0.5 block text-xs text-slate-500">{item.description}</span>
                  </span>
                  <span className="mt-1 inline-flex shrink-0 items-center gap-1 text-xs font-medium text-indigo-600">
                    {item.action}
                    <ArrowRight className="h-3 w-3 transition-transform group-hover:translate-x-0.5" />
                  </span>
                </a>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}
