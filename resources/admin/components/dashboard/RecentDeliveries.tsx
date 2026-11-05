import { ArrowRight, KeyRound, MailCheck, MailWarning } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { parseDate, timeAgo, type RecentDelivery } from './types';

export function RecentDeliveries({ items, allUrl, i18n }: { items: RecentDelivery[]; allUrl: string; i18n: Record<string, string> }) {
  return (
    <Card className="border-slate-200 shadow-sm">
      <CardHeader className="flex flex-row items-start justify-between gap-3 space-y-0 pb-3">
        <div className="space-y-1">
          <CardTitle className="text-base font-semibold">{i18n.recentDeliveries || 'Recent deliveries'}</CardTitle>
          <CardDescription>{i18n.recentDeliveriesDesc || 'The latest keys sent to customers.'}</CardDescription>
        </div>
        <Button asChild variant="ghost" size="sm" className="text-indigo-600 hover:text-indigo-700">
          <a href={allUrl}>
            {i18n.viewAll || 'View all'} <ArrowRight className="h-3.5 w-3.5" />
          </a>
        </Button>
      </CardHeader>
      <CardContent className="px-0 pb-2">
        {items.length === 0 ? (
          <div className="mx-6 mb-4 flex flex-col items-center gap-2 rounded-lg border border-dashed border-slate-200 py-10 text-center">
            <KeyRound className="h-6 w-6 text-slate-300" />
            <p className="text-sm text-slate-500">{i18n.noRecent || 'No keys delivered yet. They appear here after the first order.'}</p>
          </div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="pl-6">{i18n.product || 'Product'}</TableHead>
                <TableHead>{i18n.key || 'Key'}</TableHead>
                <TableHead className="hidden md:table-cell">{i18n.customer || 'Customer'}</TableHead>
                <TableHead>{i18n.order || 'Order'}</TableHead>
                <TableHead className="pr-6 text-right">{i18n.when || 'When'}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {items.map((item) => (
                <TableRow key={item.id}>
                  <TableCell className="max-w-[220px] pl-6">
                    <a href={item.license_url} className="block truncate font-medium text-slate-900 no-underline hover:text-indigo-600">
                      {item.product}
                    </a>
                  </TableCell>
                  <TableCell>
                    <code className="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-700">{item.key_masked}</code>
                  </TableCell>
                  <TableCell className="hidden max-w-[220px] md:table-cell">
                    <span className="flex items-center gap-1.5 truncate text-slate-600">
                      {item.email_sent ? (
                        <MailCheck className="h-3.5 w-3.5 shrink-0 text-emerald-500" aria-label={i18n.emailed || 'Emailed'} />
                      ) : (
                        <MailWarning className="h-3.5 w-3.5 shrink-0 text-amber-500" aria-label={i18n.notEmailed || 'Not emailed'} />
                      )}
                      <span className="truncate">{item.email || '—'}</span>
                    </span>
                  </TableCell>
                  <TableCell>
                    {item.order_url ? (
                      <a href={item.order_url} className="text-indigo-600 no-underline hover:underline">
                        #{item.order_id}
                      </a>
                    ) : (
                      <span className="text-slate-500">#{item.order_id}</span>
                    )}
                  </TableCell>
                  <TableCell className="whitespace-nowrap pr-6 text-right text-slate-500" title={parseDate(item.created_at)?.toLocaleString()}>
                    {timeAgo(item.created_at)}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </CardContent>
    </Card>
  );
}
