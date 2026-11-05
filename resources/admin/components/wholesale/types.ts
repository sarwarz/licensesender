import { formatDistanceToNow } from 'date-fns';

export type ApplicationStatus = 'pending' | 'approved' | 'rejected';

export interface WholesaleApplication {
  id: number;
  user_id: number;
  applicant_name: string;
  user_edit_url: string;
  company_name: string;
  business_email: string;
  phone: string;
  messenger_link: string;
  website: string;
  tax_id: string;
  message: string;
  status: ApplicationStatus;
  admin_note: string;
  created_at: string;
  reviewed_at: string;
  /** Buyer group slug ('' = account default group). */
  group: string;
}

export interface BuyerGroup {
  slug: string;
  name: string;
  discount_percent: number;
  min_order_qty: number | null;
  min_order_value: number | null;
  is_default: boolean;
}

/** "Gold · 8% off" for a slug; '' means the account default. */
export function groupLabel(slug: string, groups: BuyerGroup[], defaultLabel: string): string {
  const group = groups.find((g) => g.slug === slug);
  if (!group) return slug || defaultLabel;
  return group.discount_percent > 0 ? `${group.name} · ${group.discount_percent}% off` : group.name;
}

export const statusTone: Record<ApplicationStatus, string> = {
  pending: 'bg-amber-50 text-amber-700 ring-amber-200',
  approved: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  rejected: 'bg-red-50 text-red-700 ring-red-200',
};

export const statusDot: Record<ApplicationStatus, string> = {
  pending: 'bg-amber-500',
  approved: 'bg-emerald-500',
  rejected: 'bg-red-500',
};

const avatarTones = [
  'bg-indigo-100 text-indigo-700',
  'bg-sky-100 text-sky-700',
  'bg-emerald-100 text-emerald-700',
  'bg-amber-100 text-amber-700',
  'bg-rose-100 text-rose-700',
  'bg-violet-100 text-violet-700',
];

export function displayName(app: WholesaleApplication): string {
  return app.applicant_name || app.company_name || app.business_email || `#${app.user_id}`;
}

export function initials(app: WholesaleApplication): string {
  const source = app.company_name || app.applicant_name || app.business_email || '?';
  const parts = source.replace(/[^\p{L}\p{N}\s]/gu, ' ').trim().split(/\s+/);
  return ((parts[0]?.[0] ?? '?') + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
}

export function avatarTone(app: WholesaleApplication): string {
  let hash = 0;
  for (const ch of app.company_name || app.business_email || String(app.id)) {
    hash = (hash * 31 + ch.charCodeAt(0)) | 0;
  }
  return avatarTones[Math.abs(hash) % avatarTones.length];
}

function parseDate(value: string): Date | null {
  if (!value || value.startsWith('0000')) return null;
  const d = new Date(value.replace(' ', 'T'));
  return Number.isNaN(d.getTime()) ? null : d;
}

export function formatDate(value: string): string {
  const d = parseDate(value);
  return d ? d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : value || '—';
}

export function timeAgo(value: string): string {
  const d = parseDate(value);
  return d ? formatDistanceToNow(d, { addSuffix: true }) : '';
}

/** Messenger and website values are free text; only link them when they look like a URL. */
export function toHref(value: string): string | undefined {
  const v = value.trim();
  if (/^https?:\/\//i.test(v)) return v;
  if (/^[\w-]+(\.[\w-]+)+(\/\S*)?$/.test(v)) return `https://${v}`;
  return undefined;
}
