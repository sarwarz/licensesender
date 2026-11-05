export interface DashboardStats {
  total: number;
  orders: number;
  products: number;
  emails: number;
  today: number;
  week: number;
  month: number;
  previous_month: number;
  month_orders: number;
  emails_sent: number;
  emails_pending: number;
  wholesale_pending: number;
  api_connected: boolean;
}

export interface TrendPoint {
  date: string;
  count: number;
}

export interface RecentDelivery {
  id: number;
  order_id: number;
  order_url: string;
  product: string;
  email: string;
  key_masked: string;
  email_sent: boolean;
  created_at: string;
  license_url: string;
}

export interface TopProduct {
  product_id: number;
  name: string;
  count: number;
  edit_url: string;
}

export interface DashboardLinks {
  licenses: string;
  wholesale: string;
  settings: string;
  api_settings: string;
  setup: string;
  download_links: string;
  guides: string;
  products: string;
  orders: string;
}

export interface DashboardData {
  stats: DashboardStats;
  trend: TrendPoint[];
  recent: RecentDelivery[];
  top_products: TopProduct[];
  setup: {
    complete: boolean;
    downloads_enabled: boolean;
    guides_enabled: boolean;
    autocomplete: boolean;
  };
  links: DashboardLinks;
}

/** Parses a MySQL "Y-m-d H:i:s" value as local time. */
export function parseDate(value: string): Date | null {
  const date = new Date(value.replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? null : date;
}

export function timeAgo(value: string): string {
  const date = parseDate(value);
  if (!date) {
    return value;
  }
  const seconds = Math.round((date.getTime() - Date.now()) / 1000);
  const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
  ];
  const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
  for (const [unit, size] of units) {
    if (Math.abs(seconds) >= size) {
      return rtf.format(Math.round(seconds / size), unit);
    }
  }
  return rtf.format(0, 'minute');
}

export const numberFormat = new Intl.NumberFormat();
