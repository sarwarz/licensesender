export interface LicenseItem {
  id: number;
  license_key: string;
  order_id: number;
  order_number: string;
  order_link: string;
  order_status: string;
  product_id: number;
  product_name: string;
  product_link: string;
  sku: string;
  email: string;
  email_sent: boolean;
  source: string;
  sold_at: string;
}

export interface LicenseDetail extends LicenseItem {
  download_link: string;
  activation_guide: string;
  remote_license_id: number;
  last_synced_at: string;
}

export interface LicenseCounts {
  all: number;
  sent: number;
  pending: number;
}

export interface LicenseListResponse {
  items: LicenseItem[];
  total: number;
  page: number;
  per_page: number;
  counts: LicenseCounts;
}

export interface LicenseProduct {
  id: number;
  name: string;
  count: number;
}

export interface BulkResult {
  done: number;
  failed: number;
  errors: string[];
  message: string;
}

export type StatusFilter = 'all' | 'sent' | 'pending';
export type SortKey = 'id' | 'key_value' | 'order_id' | 'product_id' | 'email' | 'email_sent' | 'created_at';

export function maskKey(key: string): string {
  if (key.length <= 8) {
    return '•'.repeat(Math.max(4, key.length));
  }
  return `${key.slice(0, 5)}${'•'.repeat(Math.min(12, key.length - 10))}${key.slice(-5)}`;
}

export function productLabel(item: { product_id: number; product_name: string }): string {
  return item.product_name || (item.product_id ? `Product #${item.product_id} (deleted)` : '—');
}

export const orderStatusTone: Record<string, string> = {
  completed: 'bg-emerald-50 text-emerald-700',
  processing: 'bg-sky-50 text-sky-700',
  'on-hold': 'bg-amber-50 text-amber-700',
  pending: 'bg-slate-100 text-slate-600',
  refunded: 'bg-rose-50 text-rose-700',
  cancelled: 'bg-rose-50 text-rose-700',
  failed: 'bg-rose-50 text-rose-700',
};

export function formatDate(value: string): string {
  const d = new Date(value.replace(' ', 'T'));
  return Number.isNaN(d.getTime()) ? value : d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

export function toCsv(items: LicenseItem[]): string {
  const header = ['License Key', 'Order', 'Order Status', 'Product', 'Product ID', 'SKU', 'Email', 'Emailed', 'Source', 'Sold At'];
  const rows = items.map((i) => [i.license_key, i.order_number, i.order_status, productLabel(i), i.product_id, i.sku, i.email, i.email_sent ? 'yes' : 'no', i.source, i.sold_at]);
  // BOM so Excel opens UTF-8 correctly.
  return '﻿' + [header, ...rows].map((row) => row.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\r\n');
}

export function downloadFile(name: string, content: string, type = 'text/csv;charset=utf-8') {
  const url = URL.createObjectURL(new Blob([content], { type }));
  const a = document.createElement('a');
  a.href = url;
  a.download = name;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}
