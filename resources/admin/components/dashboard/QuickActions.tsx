import type { LucideIcon } from 'lucide-react';
import { BookOpenText, Download, KeyRound, Package, Settings, ShoppingCart, Users } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { DashboardData } from './types';

export function QuickActions({ data, i18n }: { data: DashboardData; i18n: Record<string, string> }) {
  const { links, setup } = data;
  const actions: { label: string; href: string; icon: LucideIcon }[] = [
    { label: i18n.licenseKeys || 'License keys', href: links.licenses, icon: KeyRound },
    { label: i18n.ordersLink || 'Orders', href: links.orders, icon: ShoppingCart },
    { label: i18n.productsLink || 'Products', href: links.products, icon: Package },
    { label: i18n.wholesaleLink || 'Wholesale', href: links.wholesale, icon: Users },
    ...(setup.downloads_enabled ? [{ label: i18n.downloadLinks || 'Download links', href: links.download_links, icon: Download }] : []),
    ...(setup.guides_enabled ? [{ label: i18n.activationGuides || 'Activation guides', href: links.guides, icon: BookOpenText }] : []),
    { label: i18n.settingsLink || 'Settings', href: links.settings, icon: Settings },
  ];

  return (
    <Card className="border-slate-200 shadow-sm">
      <CardHeader className="pb-3">
        <CardTitle className="text-base font-semibold">{i18n.quickActions || 'Quick links'}</CardTitle>
      </CardHeader>
      <CardContent className="grid grid-cols-2 gap-2">
        {actions.map((a) => (
          <a
            key={a.label}
            href={a.href}
            className="group flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 no-underline transition-colors hover:border-indigo-200 hover:bg-indigo-50/60 hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
          >
            <a.icon className="h-4 w-4 shrink-0 text-slate-400 transition-colors group-hover:text-indigo-500" />
            <span className="truncate">{a.label}</span>
          </a>
        ))}
      </CardContent>
    </Card>
  );
}
