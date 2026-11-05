import { useEffect, useState } from 'react';
import type { LucideIcon } from 'lucide-react';
import {
  Building2,
  Check,
  Copy,
  ExternalLink,
  Globe,
  Hash,
  Mail,
  MessageCircle,
  Phone,
  ShieldOff,
  Trash2,
  UserRound,
  Users,
  X,
} from 'lucide-react';
import { toast } from 'sonner';
import { getBootstrap } from '@/lib/bootstrap';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
  avatarTone,
  displayName,
  formatDate,
  initials,
  statusDot,
  statusTone,
  timeAgo,
  toHref,
  type ApplicationStatus,
  type BuyerGroup,
  type WholesaleApplication,
} from './types';

interface Props {
  application: WholesaleApplication | null;
  onOpenChange: (open: boolean) => void;
  onReview: (id: number, status: Exclude<ApplicationStatus, 'pending'>, note: string, group?: string) => Promise<boolean>;
  onGroupChange: (id: number, group: string) => Promise<boolean>;
  groups: BuyerGroup[];
  /** Group preselected when approving (settings: group for new buyers). */
  defaultGroup: string;
  onDelete: (app: WholesaleApplication) => void;
  reviewing: boolean;
}

export function StatusBadge({ status }: { status: ApplicationStatus }) {
  const { i18n } = getBootstrap();
  const labels: Record<ApplicationStatus, string> = {
    pending: i18n.pending ?? 'Pending',
    approved: i18n.approved ?? 'Approved',
    rejected: i18n.rejected ?? 'Rejected',
  };

  return (
    <Badge className={cn('gap-1.5 whitespace-nowrap border-0 font-medium ring-1 ring-inset', statusTone[status])}>
      <span className={cn('h-1.5 w-1.5 rounded-full', statusDot[status])} />
      {labels[status]}
    </Badge>
  );
}

function ContactRow({ icon: Icon, label, value, href }: { icon: LucideIcon; label: string; value: string; href?: string }) {
  const { i18n } = getBootstrap();
  if (!value) return null;

  const copy = async () => {
    await navigator.clipboard.writeText(value);
    toast.success(i18n.copied ?? 'Copied!');
  };

  return (
    <div className="group flex items-center gap-3 py-2.5">
      <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
        <Icon className="h-4 w-4" />
      </span>
      <div className="min-w-0 flex-1">
        <p className="text-xs text-slate-500">{label}</p>
        {href ? (
          <a
            href={href}
            target={href.startsWith('mailto:') || href.startsWith('tel:') ? undefined : '_blank'}
            rel="noopener noreferrer"
            className="block truncate text-sm font-medium text-indigo-600 no-underline hover:underline"
            title={value}
          >
            {value}
          </a>
        ) : (
          <p className="truncate text-sm font-medium text-slate-900" title={value}>{value}</p>
        )}
      </div>
      <Button
        variant="ghost"
        size="icon"
        className="h-7 w-7 text-slate-400 opacity-0 transition-opacity hover:text-slate-700 focus-visible:opacity-100 group-hover:opacity-100"
        onClick={copy}
        aria-label={i18n.copy ?? 'Copy'}
      >
        <Copy className="h-3.5 w-3.5" />
      </Button>
    </div>
  );
}

export function ApplicationSheet({ application, onOpenChange, onReview, onGroupChange, onDelete, reviewing, groups, defaultGroup }: Props) {
  const { i18n } = getBootstrap();
  const [note, setNote] = useState('');
  const [group, setGroup] = useState('');

  useEffect(() => {
    setNote('');
    setGroup(application?.status === 'approved' ? application.group : defaultGroup);
  }, [application?.id, application?.status, application?.group, defaultGroup]);

  const app = application;
  const submit = async (status: Exclude<ApplicationStatus, 'pending'>) => {
    if (app && (await onReview(app.id, status, note, status === 'approved' ? group : undefined))) {
      setNote('');
    }
  };

  return (
    <Sheet open={!!app} onOpenChange={onOpenChange}>
      <SheetContent className="flex w-full flex-col gap-0 p-0 sm:max-w-lg">
        {app ? (
          <>
            <SheetHeader className="space-y-0 border-b border-slate-200 px-6 pb-5 pt-6 text-left">
              <div className="flex items-start gap-4 pr-6">
                <span className={cn('flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-base font-semibold', avatarTone(app))}>
                  {initials(app)}
                </span>
                <div className="min-w-0 flex-1">
                  <SheetTitle className="truncate text-lg">{app.company_name || displayName(app)}</SheetTitle>
                  <SheetDescription className="truncate">
                    {app.company_name ? displayName(app) : app.business_email}
                  </SheetDescription>
                  <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <StatusBadge status={app.status} />
                    <span title={formatDate(app.created_at)}>
                      {(i18n.submittedAgo ?? 'Submitted %s').replace('%s', timeAgo(app.created_at) || formatDate(app.created_at))}
                    </span>
                  </div>
                </div>
              </div>
            </SheetHeader>

            <div className="flex-1 space-y-6 overflow-y-auto px-6 py-5">
              <section>
                <h3 className="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{i18n.contact ?? 'Contact'}</h3>
                <div className="divide-y divide-slate-100">
                  <ContactRow icon={Mail} label={i18n.email ?? 'Email'} value={app.business_email} href={`mailto:${app.business_email}`} />
                  <ContactRow icon={Phone} label={i18n.phone ?? 'Phone'} value={app.phone} href={app.phone ? `tel:${app.phone.replace(/[^\d+]/g, '')}` : undefined} />
                  <ContactRow icon={MessageCircle} label={i18n.messenger ?? 'Telegram / WhatsApp'} value={app.messenger_link} href={toHref(app.messenger_link)} />
                  <ContactRow icon={Globe} label={i18n.website ?? 'Website'} value={app.website} href={toHref(app.website)} />
                  <ContactRow icon={Building2} label={i18n.company ?? 'Company'} value={app.company_name} />
                  <ContactRow icon={Hash} label={i18n.taxId ?? 'Tax / VAT ID'} value={app.tax_id} />
                </div>
              </section>

              {app.message ? (
                <section>
                  <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{i18n.message ?? 'Message'}</h3>
                  <p className="m-0 whitespace-pre-wrap rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-relaxed text-slate-700">
                    {app.message}
                  </p>
                </section>
              ) : null}

              {app.status !== 'pending' ? (
                <section className={cn('rounded-xl p-4 ring-1 ring-inset', statusTone[app.status])}>
                  <p className="m-0 text-sm font-medium">
                    {app.status === 'approved' ? i18n.approvedOn ?? 'Approved' : i18n.rejectedOn ?? 'Rejected'}
                    {app.reviewed_at ? ` · ${formatDate(app.reviewed_at)}` : ''}
                  </p>
                  {app.admin_note ? <p className="m-0 mt-1.5 whitespace-pre-wrap text-sm opacity-90">{app.admin_note}</p> : null}
                </section>
              ) : null}

              {app.status !== 'rejected' && groups.length > 0 ? (
                <section>
                  <h3 className="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <Users className="h-3.5 w-3.5" /> {i18n.buyerGroup ?? 'Buyer group'}
                  </h3>
                  <Select
                    value={group || '__default'}
                    onValueChange={async (value) => {
                      const next = value === '__default' ? '' : value;
                      setGroup(next);
                      if (app.status === 'approved') {
                        await onGroupChange(app.id, next);
                      }
                    }}
                    disabled={reviewing}
                  >
                    <SelectTrigger className="bg-white">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="__default">{i18n.defaultGroup ?? 'Default group'}</SelectItem>
                      {groups.map((g) => (
                        <SelectItem key={g.slug} value={g.slug}>
                          {g.name}
                          {g.discount_percent > 0 ? ` · ${g.discount_percent}% off` : ''}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <p className="m-0 mt-1.5 text-xs text-slate-500">
                    {app.status === 'approved'
                      ? i18n.groupHintApproved ?? "Changes apply to this buyer's prices right away."
                      : i18n.groupHintPending ?? 'The buyer joins this group when you approve.'}
                  </p>
                </section>
              ) : null}

              {app.user_edit_url ? (
                <Button asChild variant="outline" size="sm" className="w-full bg-white">
                  <a href={app.user_edit_url}>
                    <UserRound className="h-4 w-4" /> {i18n.openUser ?? 'Open WordPress user'} <ExternalLink className="ml-auto h-3.5 w-3.5 text-slate-400" />
                  </a>
                </Button>
              ) : (
                <p className="m-0 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-500">{i18n.deletedUser ?? '(Deleted user)'}</p>
              )}
            </div>

            <div className="space-y-3 border-t border-slate-200 bg-slate-50/80 px-6 py-4">
              <div className="space-y-1.5">
                <Label htmlFor="ls-review-note" className="text-xs text-slate-600">
                  {i18n.noteToApplicant ?? 'Note to applicant (optional)'}
                </Label>
                <textarea
                  id="ls-review-note"
                  value={note}
                  onChange={(event) => setNote(event.target.value)}
                  rows={2}
                  className="flex w-full rounded-md border border-input bg-white px-3 py-2 text-sm placeholder:text-slate-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  placeholder={i18n.noteHint ?? 'Saved with the decision and shown in the email.'}
                />
              </div>
              <div className="flex items-center gap-2">
                <Button
                  variant="ghost"
                  size="sm"
                  className="text-slate-500 hover:bg-red-50 hover:text-red-700"
                  onClick={() => onDelete(app)}
                  disabled={reviewing}
                >
                  <Trash2 className="h-4 w-4" /> {i18n.delete ?? 'Delete'}
                </Button>
                <div className="ml-auto flex gap-2">
                  {app.status === 'pending' ? (
                    <>
                      <Button variant="outline" className="bg-white text-red-700 hover:bg-red-50 hover:text-red-800" onClick={() => submit('rejected')} disabled={reviewing}>
                        <X className="h-4 w-4" /> {i18n.reject ?? 'Reject'}
                      </Button>
                      <Button className="bg-emerald-600 text-white hover:bg-emerald-700" onClick={() => submit('approved')} disabled={reviewing}>
                        <Check className="h-4 w-4" /> {i18n.approve ?? 'Approve'}
                      </Button>
                    </>
                  ) : app.status === 'approved' ? (
                    <Button variant="outline" className="bg-white text-red-700 hover:bg-red-50 hover:text-red-800" onClick={() => submit('rejected')} disabled={reviewing}>
                      <ShieldOff className="h-4 w-4" /> {i18n.revoke ?? 'Revoke access'}
                    </Button>
                  ) : (
                    <Button className="bg-emerald-600 text-white hover:bg-emerald-700" onClick={() => submit('approved')} disabled={reviewing}>
                      <Check className="h-4 w-4" /> {i18n.approveInstead ?? 'Approve instead'}
                    </Button>
                  )}
                </div>
              </div>
            </div>
          </>
        ) : null}
      </SheetContent>
    </Sheet>
  );
}
